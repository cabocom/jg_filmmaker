---

### Fichero 3: `03_jg_plugin_filmmaker_contract.md`

```markdown
# 03. Contratos Canónicos del Plugin `jg_plugin_filmmaker`

## 1. Identificación del Plugin
- **Repositorio:** `jg_plugin_filmmaker`
- **Ubicación de Desarrollo:** `../jg_plugin_filmmaker` vinculado a `jg_agente/plugins` vía `jg link`.
- **SDK Contratos:** `from jg_sdk.contracts import AgentTool, BasePlugin, PluginMetadata, RiskLevel, ToolType, ToolResult`

## 2. Herramientas Canónicas (`AgentTool`)

### A. `treatment_structuring`
- **Propósito:** Transforma ideas y lenguaje informal en tratamiento cinematográfico estructurado.
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `raw_text` (str): Texto original.
  - `characters_context` (list[dict]): Nombres y descripciones de personajes de la escena.
  - `location_context` (dict): Tipo y notas del escenario.
  - `user_locks` (dict, opcional): Directivas y términos fijados manualmente por el usuario.
- **Output:** Texto en presente indicativo estructurado por acciones.

### B. `screenplay_formatter`
- **Propósito:** Convierte tratamiento narrativo a formato de guion literario (Fountain / JSON).
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `structured_text` (str): Texto procesado de la etapa anterior.
  - `characters_context` (list[dict])
  - `user_locks` (dict, opcional)
- **Output:** Bloques tipados de Encabezado, Acción, Personaje, Acotación y Diálogo.

### C. `shot_breakdown_detector`
- **Propósito:** Detecta las necesidades visuales de la escena y propone la separación de planos siguiendo el estándar:
  `--- shot N (tipo de plano) ---`
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `screenplay_text` (str)
- **Output:** Lista de planos numerados con su tipo técnico (PG, PM, PP, PD) y la acción cubierta.

## 3. Protocolo de Autenticación Inter-servicio (Laravel -> jg_agente)
Toda llamada HTTP generada desde `jg_filmmaker` hacia `jg_agente` debe enviarse con las cabeceras canónicas HMAC-SHA256:
- `X-Signature`: `hmac_sha256(f"{METHOD}\n{PATH}\n{TIMESTAMP}\n{SHA256(RAW_BODY)}", SHARED_SECRET)`
- `X-Timestamp`: Epoch UTC en segundos (tolerancia máxima de 300 segundos).
- `X-Correlation-ID`: UUIDv4 generado en Laravel para trazabilidad de logs.