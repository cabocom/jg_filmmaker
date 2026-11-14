# Análisis Técnico — Fase 3B: Cliente HMAC-SHA256, UserLockResolver y Conexión Livewire con jg_agente

**Fecha:** 2026-10-09  
**Proyecto:** `jg_filmmaker`  
**Autor:** Antigravity (Ingeniero Senior Laravel / Full-Stack)

---

## 1. Contexto y Objetivos

Con la culminación de la Fase 3A (donde se implementó el plugin desacoplado `jg_plugin_filmmaker` en Python con sus tres herramientas cognitivas: `treatment_structuring`, `screenplay_formatter`, y `shot_breakdown_detector`), el objetivo de la Fase 3B es materializar la capa cliente en Laravel 12 (`jg_filmmaker`) para comunicar la aplicación web con el runtime de `jg_agente` o su emulador determinista mediante el protocolo canónico de autenticación inter-servicio `HMAC-SHA256`.

### Objetivos Clave:
1. **Configuración Criptográfica y de Transporte:**
   - Establecer `JG_AGENTE_URL`, `JG_AGENTE_SECRET`, `JG_AGENTE_MOCK` y `JG_AGENTE_TIMEOUT` en `.env`, `.env.example` y `config/services.php`.
2. **Cliente HTTP Criptográfico Canónico (`AgentClientService`):**
   - Implementar la firma canónica:
     `METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)`
   - Producir las cabeceras `X-Signature`, `X-Timestamp` (segundos UTC) y `X-Correlation-ID` (UUIDv4).
   - Implementar métodos de invocación tipados: `structureTreatment`, `formatScreenplay` y `breakdownShots`.
   - Incorporar emulación y fallback determinista idéntico a las reglas de `jg_plugin_filmmaker` ante desconexión o configuración mock.
3. **Servicio de Dominio (`ScriptPipelineService`):**
   - Transicionar bloques entre etapas (`raw` -> `structured` -> `screenplay` -> `shots`).
   - Empaquetar el contexto dramático de la biblia (personajes y localización de la escena) y los candados `user_locks`.
   - Persistir y sincronizar entidades `Shot` a partir de la sintaxis estándar `--- shot N (tipo) ---`, inicializando sus tomas correspondientes (`Take`).
   - Proporcionar el mecanismo `recordManualOverride` para retener las modificaciones manuales del usuario en `user_locks['manual_diffs']`.
4. **Interactividad Reactiva en Livewire (`ScriptEditor`):**
   - Botones visuales "Estructurar", "Convertir a Guion" y "Desglosar Planos" con indicadores de carga `wire:loading` y spinners.
   - Preservación transparente de cambios del autor en `user_locks` al guardar bloques editados manualmente.
5. **Aseguramiento de Calidad:**
   - Pruebas automatizadas unitarias y de integración al 100% (HMAC exacto y transiciones de pipeline).
   - Formateo de código con Laravel Pint.

---

## 2. Decisiones de Diseño y Arquitectura

### 2.1. Firma Criptográfica HMAC-SHA256 Canónica
Para alinearse de forma estricta con el contrato de seguridad establecido en el ecosistema `jg_ia` (documentado en `ECOSYSTEM_FULL_SPEC.md` y verificado en `jg_agente/app/security/auth.py`), la firma HMAC sigue la fórmula:
```php
$timestamp = $timestamp ?? time();
$bodySha256 = hash('sha256', $rawBody);
$canonicalString = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $bodySha256;
$signature = hash_hmac('sha256', $canonicalString, $secret);
```
Cualquier variación en el cuerpo (incluso un solo byte) produce un hash SHA256 divergente y, por ende, una firma inválida, mitigando manipulaciones en tránsito (tampering) y repeticiones fuera de la ventana de 300 segundos.

### 2.2. Resiliencia y Fallback Determinista
Si el runtime Python de `jg_agente` no está en ejecución localmente durante las pruebas o desarrollo sin agente, el cliente recurre a una emulación local determinista idéntica al contrato de `jg_plugin_filmmaker`. Esto previene bloqueos de desarrollo y fallos espurios en suites de integración continuas.

### 2.3. Esquema de Retención de Modificaciones (`user_locks`)
Siguiendo `docs/specs/02_pipeline_stages_and_locks.md`:
- `pinned_terms`: Mapea términos informales a nombres canónicos fijados (ej. `{"Javi": "Javier"}`).
- `manual_diffs`: Registra pares `[original_ai, user_override, stage, applied_at]`.
- `locked_fields`: Lista de etapas o campos marcados como inmutables por la IA.
Cuando el usuario modifica el texto estructurado o guion en el modal/editor de Livewire y pulsa guardar, el sistema detecta la alteración respecto a la versión anterior y registra automáticamente el diff en `manual_diffs`.

---

## 3. Matriz de Componentes Creados y Modificados

| Componente | Rol / Ubicación | Propósito |
|---|---|---|
| `config/services.php` | Configuración | Parámetros de conexión con `jg_agente`. |
| `.env`, `.env.example` | Variables de Entorno | Valores predeterminados seguros para desarrollo. |
| `AgentClientService.php` | `app/Services/JgAgente/` | Cliente HTTP con firmado HMAC, headers canónicos y fallback determinista. |
| `ScriptPipelineService.php` | `app/Services/` | Orquestador de etapas, integración con biblia, `user_locks` y sincronización de `Shot`. |
| `ScriptEditor.php` | `app/Livewire/Studio/` | Métodos reactivos `structureBlock`, `formatBlockToScreenplay`, `breakdownBlockShots` y retención de overrides. |
| `script-editor.blade.php` | `resources/views/livewire/studio/` | Botones de acción por bloque con estados `wire:loading` y spinners. |
| `AgentClientHmacSignatureTest.php` | `tests/Feature/` | Verificación matemática de la firma HMAC y cabeceras canónicas. |
| `ScriptPipelineIntegrationTest.php` | `tests/Feature/` | Integración de pipeline, biblia, user locks, creación de planos y Livewire. |
