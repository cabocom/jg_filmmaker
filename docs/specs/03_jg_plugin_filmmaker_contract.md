# 03. Contratos Canónicos del Plugin `jg_plugin_filmmaker`

## 1. Identificación del Plugin
- **Repositorio:** `jg_plugin_filmmaker`
- **Ubicación de Desarrollo:** `../jg_plugin_filmmaker` vinculado a `jg_agente/plugins` vía `jg link`.
- **SDK Contratos:** `from jg_sdk.contracts import AgentTool, BasePlugin, PluginMetadata, RiskLevel, ToolType, ToolResult`

## 2. Herramientas Canónicas (7 Herramientas `AgentTool`)

### 1. `treatment_structuring`
- **Propósito:** Transforma ideas y lenguaje informal en tratamiento cinematográfico estructurado.
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `raw_text` (str): Texto original.
  - `characters_context` (list[dict]): Nombres y descripciones de personajes de la escena.
  - `location_context` (dict): Tipo y notas del escenario.
  - `user_locks` (dict, opcional): Directivas y términos fijados manualmente por el usuario (`pinned_terms`).
- **Output:** Texto en presente indicativo estructurado por acciones.

### 2. `screenplay_formatter`
- **Propósito:** Convierte tratamiento narrativo a formato de guion literario (Fountain / diálogos).
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `structured_text` (str): Texto procesado de la etapa anterior.
  - `characters_context` (list[dict])
  - `user_locks` (dict, opcional)
- **Output:** Bloques tipados de Encabezado, Acción, Personaje, Acotación y Diálogo.

### 3. `shot_breakdown_detector`
- **Propósito:** Detecta las necesidades visuales de la escena y propone la separación de planos siguiendo el estándar canónico:
  `--- shot (TIPO DE PLANO) ---` (con compatibilidad legacy `--- shot N (TIPO) ---`).
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `screenplay_text` (str)
- **Output:** Lista de planos con su tipo técnico (PG, PM, PP, PD, etc.) y la acción cubierta.

### 4. `plot_hole_detector`
- **Propósito:** Detecta inconsistencias de continuidad narrativa, saltos temporales ilógicos y agujeros de guion entre bloques y escenas.
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `blocks` (list[dict]): Lista de bloques con `id`, `text`, `stage`.
  - `characters_context` (list[dict])
  - `location_context` (dict)
- **Output:** Lista de incidencias de continuidad con severidad y sugerencia de corrección.

### 5. `generate_storyboard`
- **Propósito:** Genera viñetas visuales fotorrealistas con relación de aspecto 16:9 condicionadas por las referencias faciales del personaje y el atrezzo escenográfico.
- **RiskLevel:** `MEDIUM`
- **Input Schema:**
  - `prompt` (str): Descripción técnica y visual del plano.
  - `style` (str): Estilo artístico (ej: 'cinematic', 'noir', 'anime').
  - `character_name` (str, opcional)
  - `character_image_base64` (str, opcional): Imagen de referencia del personaje.
  - `location_name` (str, opcional)
  - `location_image_base64` (str, opcional): Imagen de referencia de la localización.
  - `prop_images_base64` (list[str], opcional): Imágenes de referencia de atrezzo.
- **Output:** Imagen renderizada en base64 / JPEG.

### 6. `extract_characters`
- **Propósito:** Analiza el texto del guion y extrae automáticamente las entidades de personajes con sus descripciones, psicología y vestuario sugerido.
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `text` (str): Texto completo o fragmento de guion.
- **Output:** Lista estructurada de personajes inferidos.

### 7. `audit_character_usage`
- **Propósito:** Audita deterministamente si un personaje tiene peso dramático real en una lista batch de fragmentos de guion.
- **RiskLevel:** `LOW`
- **Input Schema:**
  - `character_name` (str)
  - `scene_texts` (list[str]): Fragmentos de escenas a auditar en lote único.
- **Output:** Objeto con `in_use` (boolean), número de apariciones y resumen dramático.

## 3. Protocolo de Autenticación Inter-servicio (Laravel -> jg_agente)
Toda llamada HTTP generada desde `jg_filmmaker` hacia `jg_agente` debe enviarse con las cabeceras canónicas HMAC-SHA256:
- `X-Key-Id`: Identificador de la clave compartida configurada en el ecosistema.
- `X-Agent-ID`: Identificador del agente destino (`cinematography_agent`).
- `X-Signature`: Firma criptográfica `hmac_sha256(f"{METHOD}\n{PATH}\n{TIMESTAMP}\n{SHA256(RAW_BODY)}", SHARED_SECRET)`.
- `X-Timestamp`: Epoch UTC en segundos (tolerancia máxima de deriva de 300 segundos).
- `X-Correlation-ID`: UUIDv4 generado en Laravel para trazabilidad unificada de trazas y logs.