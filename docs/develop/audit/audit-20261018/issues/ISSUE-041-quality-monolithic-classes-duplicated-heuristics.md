# [ISSUE-041] Clases monolíticas y lógica heurística duplicada entre `AgentClientService` (PHP) y `jg_plugin_filmmaker` (Python)

- **Severidad:** Baja
- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php` (1932 líneas), `app/Services/JgAgente/AgentClientService.php` (1287 líneas, `executeDeterministicFallback` 373-880), `jg_plugin_filmmaker/formatting.py`, `jg_plugin_filmmaker/structuring.py`, `jg_plugin_filmmaker/breakdown.py`
- **Categoría:** Calidad de Código

## 1. Descripción del Problema
- `ScriptEditor` concentra ~100 métodos y ~60 propiedades públicas (edición, drawer, modales, escenas, bloques, tomas, subida, auditoría IA, cuota, activity log): imposible de testear por unidades, con acoplamiento fuerte a Eloquent y al servicio, y causa de la mayoría de los hallazgos de seguridad (ISSUE-003, 018).
- `AgentClientService` mezcla: firma HMAC, transporte HTTP, cliente LLM directo, construcción de prompts (`buildSystemPrompt/UserPrompt`), parser de planos y un **motor heurístico de ~500 líneas** (`executeDeterministicFallback`) que **reimplementa en PHP la lógica del plugin Python** (regex de verbos, conectores, `ignoreWords`, refinamiento de diálogo) con ligeras divergencias (por ejemplo `parseShotsFromText` usa `[^)]+` mientras el plugin usa `[A-Za-z]+`). Dos implementaciones del mismo contrato deben mantenerse sincronizadas a mano; ya divergen.
- Ausencia de tipos de dominio: arrays asociativos anidados (`$payload`, `$userLocks`, `$telemetry`) por todas partes; sin DTOs ni enums para `stage`.
- Los comentarios mezclan referencias a casos concretos ("Estrella Casilda", "muñeca") dentro de heurísticas de producción (sobreajuste a un guion de ejemplo).
- `ScriptPipelineService::__construct` usa `app()` como fallback (service locator) y propiedades `protected ?AgentTelemetryService` redefinidas.

## 2. Evidencia de Código
```php
protected function executeDeterministicFallback(string $toolName, array $payload): array   // ~500 líneas
```

## 3. Impacto Operativo o de Seguridad
Coste de mantenimiento elevado, inconsistencias de comportamiento entre rutas (agente / LLM directo / fallback PHP) y mayor probabilidad de regresiones.

## 4. Solución Recomendada
- Extraer de `ScriptEditor` componentes Livewire pequeños (`BlockDrawer`, `SceneList`, `TakeManager`, `PlotHoleAudit`) y servicios de aplicación (`BlockService`, `TakeService`) con Policies.
- Dividir `AgentClientService` en `AgentHttpClient` (HMAC+transporte), `PromptBuilder` y `OfflineFallback` (mínimo, sólo para tests) o eliminar el fallback heurístico PHP y usar el servidor mock del plugin (`MockPluginRunner`) en tests.
- Introducir DTOs/enums (`BlockStage`, `UserLocks`, `AgentResult`).
- Test de contrato compartido (golden files) entre PHP y Python para evitar divergencias.
