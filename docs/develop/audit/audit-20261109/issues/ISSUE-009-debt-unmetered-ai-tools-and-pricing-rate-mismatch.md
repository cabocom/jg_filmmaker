# [ISSUE-009] Operaciones IA sin Telemetría, Selección Arbitraria de Tarifas `TokenPricingRate` y Cobro de Cuota en Modo Fallback

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/StoryboardService.php:180`, `jg_filmmaker/app/Services/ScriptPipelineService.php:495`, `jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php:361`, `jg_filmmaker/app/Services/AgentTelemetryService.php:43-61`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:1677-1707`
- **Categoría:** Deuda Técnica

## 1. Descripción
El subsistema de telemetría, control de costes y cuotas diarias de tokens (`AgentTelemetryService` / `AgentRequestLog` / `TokenPricingRate`) presenta cuatro inconsistencias relevantes:
1. **3 de las 7 Herramientas IA No Registran Telemetría ni Descuentan Cuota:**
   - `generate_storyboard` (invocado en `StoryboardService::generateStoryboard()`)
   - `extract_characters` (invocado en `ScriptPipelineService::syncProjectCharacters()` e `inferCharactersFromText()`)
   - `audit_character_usage` (invocado en `ContextBibleDrawer::auditCharacterUsages()`)
   Ninguna de estas tres llamadas invoca `AgentTelemetryService::record()`. Aunque la UI muestra al usuario que generar una viñeta de storyboard cuesta `~350 tokens` (`confirmGenerateStoryboard`), tras la ejecución real el saldo diario del usuario permanece intacto (`0 tokens` descontados) y el coste en `/admin` figura como `$0.00`.
2. **Bypass de Cuota en Botón Rápido `generateShotStoryboard`:** Mientras `openStoryboardModal()` y `confirmGenerateStoryboard()` comprueban `$user->hasDailyTokensAvailable()`, el método público `ScriptEditor::generateShotStoryboard(string $shotId)` (línea 1677) ejecuta `StoryboardService::generateStoryboard()` directamente sin comprobar la cuota diaria del usuario.
3. **Selección Arbitraria de Tarifa en `AgentTelemetryService`:** `TokenPricingRate::where('is_active', true)->first()` toma la primera tarifa activa de la tabla sin filtrar por el proveedor (`$telemetry['provider']`) ni el modelo (`$telemetry['model']`) utilizados en la petición.
4. **Cobro de Cuota al Usuario Cuando Falla el LLM (`deterministic_engine`):** Cuando `jg_agente` o el proveedor LLM falla/no está disponible y el plugin responde usando el motor determinista local (`provider: "deterministic_engine"`, `model: "rule-based-nlp"`), `AgentClientService` calcula tokens estimados por longitud de texto y `AgentTelemetryService::record()` se los descuenta del límite diario del usuario y les aplica precio de LLM como si hubiera existido consumo real de IA.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/AgentTelemetryService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/AgentTelemetryService.php#L43-L61):
```php
        $rate = TokenPricingRate::where('is_active', true)->first();

        $inputPricePerMillion = $rate ? (float) $rate->input_price_per_million : 0.50;
        $outputPricePerMillion = $rate ? (float) $rate->output_price_per_million : 1.50;

        $costUsd = (($promptTokens / 1_000_000) * $inputPricePerMillion)
                 + (($completionTokens / 1_000_000) * $outputPricePerMillion);
```

En [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php#L1677-L1695):
```php
    public function generateShotStoryboard(string $shotId, ?string $style = null): void
    {
        $this->authorize('update', $this->project);
        // No comprueba $this->checkTokenQuotaOrNotify()
        $shot = Shot::with('scene')->find($shotId);
        // ...
        $storyboardService = app(StoryboardService::class);
        $path = $storyboardService->generateStoryboard($shot, $chosenStyle);
```

## 3. Impacto Operativo o Riesgo
- **Desviación Financiera en `/admin`:** Las métricas de coste en dólares y consumo de tokens del panel administrativo omiten por completo la generación de imágenes (`gemini-2.5-flash-image`), la extracción semántica de personajes y la síntesis TTS, que son precisamente las operaciones de mayor coste unitario.
- **Penalización Injusta en Modo Degradado:** Los usuarios pierden cuota diaria de tokens cuando el sistema opera en fallback determinista (`rule-based-nlp`).

## 4. Solución o Mejora Recomendada
1. Invocar `AgentTelemetryService::record()` en `StoryboardService::generateStoryboard()`, `ScriptPipelineService` (`extract_characters`) y `ContextBibleDrawer` (`audit_character_usage`), y añadir `$this->checkTokenQuotaOrNotify()` en `generateShotStoryboard()`.
2. En `AgentTelemetryService::record()`, buscar primero la tarifa que coincida con `provider` y `model` (`where('provider', $provider)->where('model', $model)` con fallback a la tarifa activa por defecto), y si `$provider === 'deterministic_engine'` (o `'mock'`), fijar `cost_usd = 0` y `total_tokens = 0` para no descontar cuota de IA al usuario.

## 5. Resolución Aplicada (2026-11-14)
- **Cobertura Completa de Telemetría en Herramientas IA:**
  - `StoryboardService::generateStoryboard()`: Invoca `AgentTelemetryService::record()` para la herramienta `generate_storyboard` registrando proveedor (`google_ai_studio`), modelo (`gemini-2.5-flash-image`) y tokens consumidos. Incorpora validación de saldo diario defensiva (`hasDailyTokensAvailable(350)`).
  - `BibleSyncService::requestCharacterExtraction()`: Invoca `AgentTelemetryService::record()` para la herramienta `extract_characters`.
  - `AuditBibleEntityUsageAction::auditCharacterUsages()`: Invoca `AgentTelemetryService::record()` para la herramienta `audit_character_usage`.
- **Selección Específica de Tarifas `TokenPricingRate`:**
  - En `AgentTelemetryService::record()`, se busca de forma jerárquica la tarifa activa que coincida con `provider` y `model`. En caso de no existir tarifa por modelo, busca por `provider`, y finalmente recurre a la tarifa activa por defecto.
- **Protección de Cuota en Modo Fallback Determinista:**
  - Si la operación se resuelve mediante el motor determinista local (`provider: "deterministic_engine"` o `model: "rule-based-nlp"`), se fijan `total_tokens = 0` y `estimated_cost_usd = 0.0`. Esto preserva el registro forense en `agent_request_logs` sin penalizar el saldo diario del usuario ni alterar las métricas financieras en `/admin`.
- **Validación:**
  - Creada suite `tests/Feature/AgentTelemetryAndPricingTest.php` con 6 tests verificando el registro de telemetría de las 3 herramientas, la validación de cuota previa, la selección jerárquica de tarifas y la no penalización de saldo en fallback determinista.
  - Suite de `jg_filmmaker` ampliada a 265 tests pasando al 100% y Pint limpio.

