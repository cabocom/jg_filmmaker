# 02. Pipeline Cinematográfico en 6 Etapas (`jg_filmmaker`)

[← 01. Arquitectura General](01_architecture.md) | [03. Gobernanza de Candados →](03_user_locks_governance.md)

---

## 1. Visión del Pipeline

El núcleo dramático y técnico de `jg_filmmaker` se fundamenta en un pipeline continuo de seis etapas que transforma una idea embrionaria en un montaje audiovisual pre-cortado.

```text
[1. Bruto] ──▶ [2. Tratamiento] ──▶ [3. Guión Literario] ──▶ [4. Planos] ──▶ [5. Rodaje] ──▶ [6. Montaje NLE]
```

---

## 2. Descripción Pormenorizada de las Etapas

### Etapa 1: Bruto (Raw)
- **Propósito:** Ingesta inmediata de notas del autor, pensamientos desestructurados o lluvia de ideas para la escena.
- **Modelo Eloquent:** `ScriptBlock`
- **Columna de Persistencia:** `raw_text` (TEXT / NULL)
- **Operatividad:** Edición libre en tiempo real desde la primera columna de [ScriptEditor](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php).

### Etapa 2: Tratamiento (Structured)
- **Propósito:** Normalización formal del texto en prosa dramática en tiempo presente de indicativo.
- **Acción:** Disparada mediante el botón *"⚡ Estructurar"*.
- **Servicio:** [ScriptPipelineService::transitionToStructured()](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php).
- **Herramienta Satélite:** `treatment_structuring` de `jg_plugin_filmmaker`.
- **Contexto Inyectado:** Personajes dados de alta en el proyecto (`scene.project.characters`) y localización asociada a la escena (`scene.location`).
- **Columna de Persistencia:** `structured_text`.

### Etapa 3: Guión Literario (Screenplay)
- **Propósito:** Formato cinematográfico estándar Fountain / Courier 12pt (estilo Final Draft).
- **Acción:** Disparada mediante el botón *"🎬 Convertir a Guion"*.
- **Servicio:** [ScriptPipelineService::transitionToScreenplay()](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php).
- **Herramienta Satélite:** `screenplay_formatter` de `jg_plugin_filmmaker`.
- **Estructura Formateada:**
  1. **Slugline / Encabezado de Escena:** Generado con los datos de localización y franja temporal (ej. `INT. BAR CENTRAL - NOCHE 1`).
  2. **Bloques de Acción:** Descripción de entorno y movimientos de personajes.
  3. **Diálogos:** Sangría de nombre en mayúsculas centradas (`PERSONAJE`), acotación opcional entre paréntesis `(tono)` y parlamento.
- **Columna de Persistencia:** `screenplay_content`.

### Etapa 4: Planos Técnicos (Shots)
- **Propósito:** Planificación de cobertura visual y desglose de cámara.
- **Acción:** Disparada mediante el botón *"📹 Desglosar Planos"*.
- **Herramienta Satélite:** `shot_breakdown_detector` de `jg_plugin_filmmaker`.
- **Sintaxis Canónica:**
  ```text
  --- shot 1 (PG) ---
  --- shot 2 (PM) ---
  --- shot 3 (PP) ---
  ```
- **Persistencia Relacional:** Cada plano detectado se sincroniza o inserta en la tabla `shots` vinculándose a `scene_id` y `script_block_id`.

### Etapa 5: Rodaje y Tomas (Takes)
- **Propósito:** Registro de material rodado en set para cada plano técnico.
- **Modelo Eloquent:** `Take`
- **Operaciones:**
  - Carga reactiva de archivos (`mp4`, `mov`, `webm`) con almacenamiento físico estructurado por UUID:
    `storage/app/public/media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.mp4`
  - Selección de toma buena mediante el indicador `is_good_take` (booleano).
  - Notas de dirección y etalonaje en `editorial_notes`.
  - Extracción y guardado de metadatos técnicos en JSONB (`technical_meta`: codec, resolución, duración, fps).

### Etapa 6: Montaje y Rough Cut (Timeline NLE)
- **Propósito:** Monitor de previsualización, revisión del corte preliminar y ensamblado de vídeo continuo.
- **Componente Livewire:** [RoughCutTimeline](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/RoughCutTimeline.php).
- **Funcionalidades:**
  - Filtrado estricto de tomas válidas (`is_good_take = true`) ordenadas secuencialmente por escena, plano y número de toma.
  - Reproductor con navegación secuencial entre clips.
  - Exportación de lista de corte en formato JSON estructurado.
  - Concatenación automatizada mediante FFmpeg (`RoughCutAssemblyService`) generando el máster `rough_cut_{timestamp}.mp4`.
