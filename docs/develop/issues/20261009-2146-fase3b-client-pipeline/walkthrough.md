# Walkthrough — Fase 3B: Cliente HMAC-SHA256, UserLockResolver y Conexión Livewire con jg_agente

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker`  
**Estado:** Completado y Verificado al 100%

---

## 1. Prompt Recibido

```text
[PROJECT: jg_filmmaker] - Fase 3B: Cliente HMAC-SHA256, UserLockResolver y Conexión Livewire con jg_agente

Actúa como Ingeniero Senior Laravel/Full-Stack en el ecosistema jg_ia.

Hemos completado la Fase 3A: el plugin desacoplado `jg_plugin_filmmaker` ya está implementado, probado y enlazado en `jg_agente/plugins` con sus 3 herramientas canónicas (treatment_structuring, screenplay_formatter, shot_breakdown_detector).

Ahora implementaremos en `jg_filmmaker` el cliente HTTP canónico HMAC-SHA256, el servicio de resolución de bloqueos y la conexión reactiva con los botones del `ScriptEditor` de Livewire.

### CONTEXTO Y ESPECIFICACIONES:
- docs/specs/02_pipeline_stages_and_locks.md
- docs/specs/03_jg_plugin_filmmaker_contract.md
- docs/specs/04_shots_takes_media_management.md
- Ecosistema jg_ia: Comunicación autenticada punto a punto con HMAC-SHA256 (f"{METHOD}\n{PATH}\n{TIMESTAMP}\n{SHA256(RAW_BODY)}").

### DIRECTRICES DE IMPLEMENTACIÓN:
1. Configuración de Entorno (.env y config/services.php):
   - Añadir claves para el runtime del agente:
     `JG_AGENTE_URL=http://127.0.0.1:8001` (o puerto del agente)
     `JG_AGENTE_SECRET=desarrollo_local_secret`
     `JG_AGENTE_MOCK=false` (si es true o ante fallo de conexión en tests, fallback simulado determinista).

2. Cliente Criptográfico Canónico (`app/Services/JgAgente/AgentClientService.php`):
   - Firma HMAC-SHA256 idéntica a la especificación canónica:
     * Cabecera `X-Signature`: hash_hmac('sha256', "$method\n$path\n$timestamp\n$bodySha256", $secret).
     * Cabecera `X-Timestamp`: epoch actual (segundos).
     * Cabecera `X-Correlation-ID`: Str::uuid()->toString().
   - Métodos tipados para invocar las herramientas de filmmaker:
     * `structureTreatment(string $rawText, array $characters, ?array $location, array $userLocks): string`
     * `formatScreenplay(string $structuredText, array $characters, array $userLocks): string`
     * `breakdownShots(string $screenplayText): array`

3. Servicio de Dominio (`app/Services/ScriptPipelineService.php`):
   - `transitionToStructured(ScriptBlock $block)`: Extrae contexto de la biblia del proyecto, empaca los `user_locks`, invoca `structureTreatment` y actualiza `structured_text` y `stage = 'structured'`.
   - `transitionToScreenplay(ScriptBlock $block)`: Invoca `formatScreenplay` y actualiza `screenplay_content` y `stage = 'screenplay'`.
   - `generateShotsBreakdown(ScriptBlock $block)`: Invoca `breakdownShots`. Por cada plano detectado con la sintaxis canónica `--- shot N (tipo) ---`, crea o sincroniza registros en la tabla `shots` asociados a la escena y al bloque.

4. Integración Reactiva en `ScriptEditor` (Livewire):
   - Vincular botones de acción por bloque:
     * Botón "Estructurar" (dispara `structureBlock($blockId)`).
     * Botón "Convertir a Guion" (dispara `formatBlockToScreenplay($blockId)`).
     * Botón "Desglosar Planos" (dispara `breakdownBlockShots($blockId)`).
   - Añadir feedback visual con `wire:loading` y spinners de procesamiento en los botones.
   - Si el usuario edita manualmente y guarda un texto, registrar la directiva en `user_locks` del bloque para retener los cambios.

5. Suite de Pruebas Automatizadas:
   - Crear `tests/Feature/AgentClientHmacSignatureTest.php` comprobando que las cabeceras generadas coinciden con el cálculo canónico HMAC.
   - Crear `tests/Feature/ScriptPipelineIntegrationTest.php` probando las 3 transiciones del pipeline (con `Http::fake()` o el driver determinista de fallback) y verificando la creación automática de entidades `Shot` a partir de la salida `--- shot N (tipo) ---`.

### OBJETIVO DE ESTE PASO:
1. Implementa la configuración, `AgentClientService` y `ScriptPipelineService`.
2. Actualiza el componente Livewire `ScriptEditor` y su vista Blade con los disparadores interactivos.
3. Ejecuta `php artisan test` y `vendor/bin/pint` para validar que las nuevas pruebas y el estilo de código pasan al 100%.
```

---

## 2. Acciones Realizadas

### 2.1. Configuración de Entorno
- Modificado [config/services.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/config/services.php) con el bloque `'jg_agente'` (`url`, `secret`, `mock`, `timeout`).
- Actualizados [.env](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/.env) y [.env.example](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/.env.example) con variables:
  - `JG_AGENTE_URL=http://127.0.0.1:8001`
  - `JG_AGENTE_SECRET=filmmaker_dev_secret_key_12345`
  - `JG_AGENTE_MOCK=false`
  - `JG_AGENTE_TIMEOUT=30`

### 2.2. Cliente Criptográfico Canónico (`AgentClientService`)
- Creado [app/Services/JgAgente/AgentClientService.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/JgAgente/AgentClientService.php):
  - `generateSignatureHeaders`: cálculo canónico `hash_hmac('sha256', "$method\n$path\n$timestamp\n$bodySha256", $secret)`.
  - Inyección de cabeceras `X-Signature`, `X-Timestamp`, `X-Correlation-ID`.
  - Métodos fuertemente tipados:
    - `structureTreatment(string $rawText, array $characters, ?array $location, array $userLocks): string`
    - `formatScreenplay(string $structuredText, array $characters, array $userLocks): string`
    - `breakdownShots(string $screenplayText): array`
  - `parseShotsFromText`: parser de expresiones regulares para `--- shot N (tipo) ---`.
  - `applyUserLocks`: aplicación de sustituciones `pinned_terms` y diffs manuales.
  - `executeDeterministicFallback`: emulación idéntica a `jg_plugin_filmmaker` si el microservicio está apagado o en modo mock.

### 2.3. Servicio de Dominio (`ScriptPipelineService`)
- Creado [app/Services/ScriptPipelineService.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php):
  - `transitionToStructured`: empaqueta contexto de personajes y localización de la biblia, aplica candados y actualiza `stage = 'structured'`.
  - `transitionToScreenplay`: formatea a guión literario respetando `user_locks` y actualiza `stage = 'screenplay'`.
  - `generateShotsBreakdown`: desglosa planos técnicos, sincroniza entidades `Shot` (`updateOrCreate`) y crea tomas iniciales (`Take`).
  - `recordManualOverride`: almacena diffs del usuario en `user_locks['manual_diffs']` y fija campos en `user_locks['locked_fields']`.

### 2.4. Conexión Reactiva en Livewire (`ScriptEditor`)
- Actualizado [app/Livewire/Studio/ScriptEditor.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php):
  - Añadidos métodos de acción:
    - `structureBlock(string $blockId, ScriptPipelineService $pipelineService)`
    - `formatBlockToScreenplay(string $blockId, ScriptPipelineService $pipelineService)`
    - `breakdownBlockShots(string $blockId, ScriptPipelineService $pipelineService)`
  - Modificado `saveBlock` para detectar ediciones del usuario y retenerlas mediante `recordManualOverride`.
- Actualizada vista [resources/views/livewire/studio/script-editor.blade.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/resources/views/livewire/studio/script-editor.blade.php):
  - Añadidos botones estilizados en Tailwind para cada acción con `wire:loading`, desactivación automática y spinners animados SVG.

### 2.5. Pruebas Automatizadas y Calidad
- Creado [tests/Feature/AgentClientHmacSignatureTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/AgentClientHmacSignatureTest.php):
  - Verificación estricta de headers HMAC (`X-Signature`, `X-Timestamp`, `X-Correlation-ID`).
  - Verificación de sensibilidad a alteración de un byte del cuerpo.
  - Verificación de envío real con `Http::fake()` y verificación del request.
- Creado [tests/Feature/ScriptPipelineIntegrationTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/tests/Feature/ScriptPipelineIntegrationTest.php):
  - Verificación de las 3 transiciones del pipeline con datos de la biblia.
  - Verificación de creación de entidades `Shot` y `Take`.
  - Verificación del esquema `user_locks`.
  - Verificación de los disparadores Livewire interactivos.

---

## 3. Resultados de Pruebas y Linters

### PHPUnit / Artisan Test
```text
$ php artisan test
   PASS  Tests\Feature\AgentClientHmacSignatureTest
  ✓ signature headers comply with canonical hmac sha256 specification
  ✓ signature changes when body is altered by one byte
  ✓ http call sends verified hmac headers to agent

   PASS  Tests\Feature\ScriptPipelineIntegrationTest
  ✓ pipeline transitions block to structured using project bible and locks
  ✓ pipeline transitions block to screenplay format
  ✓ pipeline generates shots breakdown and creates shot and take entities
  ✓ manual override is recorded in user locks schema
  ✓ livewire script editor triggers pipeline actions and updates state

   PASS  Tests\Feature\DomainModelArchitectureTest
   PASS  Tests\Feature\ExampleTest
   PASS  Tests\Feature\LivewireProjectDashboardTest
   PASS  Tests\Feature\LivewireScriptEditorTest

  Tests:    19 passed (103 assertions)
  Duration: 0.35s
```

### Laravel Pint
```text
$ ./vendor/bin/pint
  PASS  Pint fixed 1 file (unused import in test), 0 errors remaining.
```

---

## 4. Verificación en el Navegador

El servidor web de desarrollo se mantiene operativo en el puerto asignado:
```
http://127.0.0.1:8010
```
1. Al acceder a cualquier proyecto y escena (ej. `http://127.0.0.1:8010/studio/{projectId}`), cada bloque muestra en su cabecera los 3 botones de acción rápida:
   - **⚡ Estructurar**
   - **🎬 Convertir a Guion**
   - **📹 Desglosar Planos**
2. Al hacer clic en cualquiera de ellos, el botón muestra el spinner de procesamiento y actualiza el texto en vivo y los contadores de planos en la sección inferior de desglose.
3. Al editar manualmente y pulsar "✓ Guardar Bloque", el indicador de "Candados Activos" se actualiza y preserva los cambios manuales ante futuras ejecuciones de la IA.
