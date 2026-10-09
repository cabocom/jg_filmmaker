# CONTEXTO DE PROYECTO: jg_filmmaker

**Versión del documento:** 2.1.0  
**Fecha de consolidación:** 10 de octubre de 2026  
**Componente:** Plataforma Web de Pipeline Cinematográfico Asistido por IA  
**Stack Tecnológico:** Laravel 12 (v12.69.3), Livewire 3/4, Tailwind CSS v4, PHP 8.4, PostgreSQL / SQLite, FFmpeg, PHPUnit 12, Laravel Pint  
**Estado:** Fases 1 a 7 implementadas al 100% (Arquitectura UUIDs, UI Reactiva Livewire 3 con tema claro/oscuro, Conexión HMAC-SHA256 con jg_agente, Rodaje/Takes con subida física, NLE Rough Cut Timeline con ensamble FFmpeg, Inferencia Cognitiva Real con LLM y Aislamiento Mock Determinista en Tests).

---

## 1. Visión General y Propósito del Sistema

Dentro del ecosistema `javiergalvez-ia`, `jg_filmmaker` cumple el rol de **suite web de producción cinematográfica integral y asistida por agentes cognitivos**. Su propósito primordial es desacoplar el proceso de escritura y producción en etapas discretas, transformables y controlables por el cineasta:
1. **Desacoplamiento Estricto:** Es una aplicación web independiente. No se conecta a las bases de datos de otros servicios (`jg_agente`, `jg_dashboard`, `jg_hub`).
2. **Asistencia Cognitiva No Destructiva:** Consume las capacidades de análisis dramático de `jg_agente` mediante peticiones HTTP firmadas criptográficamente (`/api/v1/tools/{tool_name}`) e identificadas con `X-Agent-ID: jg_filmmaker`.
3. **Cero Pérdida de Control Humano:** El autor puede fijar términos o editar cualquier texto sin temor a que regeneraciones posteriores de la IA sobreescriban sus modificaciones manuales.

---

## 2. Descripción Detallada de las Etapas del Pipeline Cinematográfico

El pipeline narrativo y técnico se organiza en 6 etapas progresivas:

```
[1. Bruto (Raw)] ──▶ [2. Tratamiento] ──▶ [3. Guión Literario] ──▶ [4. Planos (Shots)] ──▶ [5. Rodaje (Takes)] ──▶ [6. Montaje (Rough Cut)]
```

### Etapa 1: Bruto (Raw)
- **Propósito:** Ingesta rápida y sin filtros de ideas narrativas, notas dispersas, lluvias de ideas o borradores iniciales aportados por el autor.
- **Persistencia:** Almacenado en la columna `script_blocks.raw_text`.
- **Comportamiento:** Permite edición libre en caliente. Es el punto de partida del flujo de reescritura asistida.

### Etapa 2: Tratamiento (Structured)
- **Propósito:** Conversión del bruto a prosa dramática estructurada en tiempo presente continuo, describiendo acciones, dinámica espacial y motivaciones inmediatas.
- **Mecanismo:** Invoca la herramienta `treatment_structuring` inyectando el contexto de la escena (personajes registrados y localizaciones de la Biblia del proyecto).
- **Persistencia:** Almacenado en `script_blocks.structured_text`.

### Etapa 3: Guión Literario (Screenplay)
- **Propósito:** Conversión del tratamiento a formato cinematográfico canónico estilo Courier / Fountain, respetando los estándares de la industria (Final Draft).
- **Mecanismo:** Invoca la herramienta `screenplay_formatter`, la cual:
  - Genera el encabezado de escena estándar (*slugline*: e.g. `INT. BAR CENTRAL - NOCHE 1`).
  - Identifica bloques de acción descriptiva y diálogos con sangrías y nombres de personajes en mayúsculas centradas (`PERSONAJE\n(acotación)\nDiálogo`).
  - Purga prefijos descriptivos redundantes (`En BAR CENTRAL:`).
- **Persistencia:** Almacenado en `script_blocks.screenplay_content`.

### Etapa 4: Planos Técnicos (Shots)
- **Propósito:** Desglose analítico de cámara para planificación visual y cobertura de rodaje.
- **Mecanismo:** Invoca `shot_breakdown_detector` sobre el guión literario, detectando la sintaxis canónica `--- shot N (tipo) ---` (e.g., `--- shot 1 (PG) ---`, `--- shot 2 (PM) ---`, `--- shot 3 (PP) ---`).
- **Persistencia:** Cada plano detectado se sincroniza o crea reactivamente en la tabla relacional `shots` (`shot_number`, `shot_type`, `description`, `script_block_id`, `scene_id`).

### Etapa 5: Rodaje y Tomas (Takes)
- **Propósito:** Registro y gestión física del material rodado en set para cada plano técnico.
- **Mecanismo:**
  - Carga reactiva de archivos multimedia mediante Livewire (`WithFileUploads`) con validación estricta de extensiones (`mp4`, `mov`, `webm`, `avi`, `mkv`) y límite de 500MB por clip.
  - Almacenamiento particionado por UUID: `media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.mp4` dentro del disco público de Laravel.
  - Marcaje ágil de toma válida (`is_good_take = true`) para selección editorial con un solo clic.
  - Registro de notas de dirección (`editorial_notes`).
  - Extracción y almacenamiento de metadatos técnicos en JSONB (`takes.technical_meta`: resolución, duración, tasa de fotogramas, codec, tamaño de archivo).

### Etapa 6: Montaje y Rough Cut (Timeline NLE)
- **Propósito:** Visualización secuencial y ensamblado del corte preliminar de la película.
- **Mecanismo:**
  - Componente reactivo `RoughCutTimeline`: filtra automáticamente las tomas marcadas como válidas (`is_good_take = true`) ordenadas por escena, plano y toma.
  - Monitor de reproducción de vídeo con controles de salto entre clips (anterior, siguiente, reproducción continua).
  - Pista horizontal de clips (estilo timeline NLE) con estado de carga y códigos de tiempo.
  - Exportación estructurada de lista de corte (EDL / JSON) para compatibilidad con suites de edición profesional.
  - Servicio de renderizado y ensamblado continuo con FFmpeg (`RoughCutAssemblyService`), que concatena los archivos físicos de vídeo de las tomas aprobadas generando un único máster de pre-corte en `media/projects/{project_id}/renders/rough_cut_{timestamp}.mp4`.

---

## 3. Mecanismo de Retención de Modificaciones Manuales (`user_locks`)

Para evitar que una regeneración cognitiva destruya las correcciones deliberadas introducidas por el cineasta, cada bloque de guión cuenta con una columna JSONB `user_locks`.

### 3.1. Estructura JSONB
```json
{
  "locked_fields": ["structured_text", "screenplay_content"],
  "manual_diffs": [
    {
      "field": "screenplay_content",
      "timestamp": "2026-10-09T21:40:00Z",
      "diff": "Corrección de acotación de tono en diálogo de Javier"
    }
  ],
  "pinned_terms": {
    "Javi": "Javier",
    "Bar Manolo": "BAR CENTRAL"
  }
}
```

### 3.2. Algoritmo de Preservación (`UserLockResolver`)
- **`isFieldLocked(ScriptBlock $block, string $field): bool`**: Comprueba si el campo específico está protegido contra regeneraciones globales.
- **`applyLocks(ScriptBlock $block, string $newContent, string $field): string`**: Sustituye términos variantes por sus `pinned_terms` canónicos definidos por el autor y rechaza la sobrescritura si el campo se encuentra en `locked_fields`.
- **`toggleFieldLock(ScriptBlock $block, string $field): ScriptBlock`**: Permite al usuario bloquear y desbloquear manualmente etapas desde la interfaz mediante el botón de candado.

---

## 4. Biblia Contextual Rápida (Drawer Lateral)

La coherencia narrativa del universo de la obra se gestiona mediante la Biblia del Proyecto:
- **Entidades de Dominio:**
  - `Character`: Nombre único por proyecto, bio, psicología, notas de vestuario y atributos personalizados JSON (`custom_attributes`).
  - `Location`: Nombre de localización, tipo (`INT`, `EXT`, `INT/EXT`), notas de ambientación, arte e iluminación.
- **Componente Livewire `ContextBibleDrawer`:**
  - Cajón lateral accesible globalmente desde el estudio mediante el botón "Biblia de Proyecto" o el atajo de teclado canónico (`Cmd+B` en macOS o `Ctrl+B` en Linux/Windows).
  - Permite consultar, dar de alta y editar personajes y localizaciones en caliente sin interrumpir la escritura ni perder el foco del editor.
  - Inyección reactiva: Los personajes y localizaciones dados de alta en la Biblia quedan inmediatamente disponibles como contexto semántico para las herramientas de estructuración y desglose.

---

## 5. Arquitectura Técnica, Seguridad y Comunicación Inter-servicio

### 5.1. Stack Tecnológico
- **Framework:** Laravel 12 (v12.69.3), PHP 8.4.
- **Capa Reactiva:** Livewire 3/4 con subidas en streaming (`WithFileUploads`).
- **Diseño y Estilos:** Tailwind CSS v4 con selector dual de tema Claro/Oscuro y soporte `@custom-variant dark`.
- **Motor Multimedia:** Integración directa con FFmpeg (`ffmpeg` binary en PATH) y demuxer concat.

### 5.2. Seguridad Estricta Anti-IDOR (UUIDv4)
- Todas las tablas de dominio (`users`, `projects`, `characters`, `locations`, `scenes`, `script_blocks`, `shots`, `takes`) utilizan identificadores primarios y foráneos UUIDv4 (`$table->uuid('id')->primary()`).
- Los modelos Eloquent implementan el trait canónico `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Ninguna ruta web o endpoint expone identificadores secuenciales enteros.

### 5.3. Criptografía Inter-servicio (Cliente HMAC-SHA256)
La comunicación con el motor cognitivo `jg_agente` se efectúa a través de `AgentClientService`:
- **Algoritmo de Firma:**
  ```text
  canonical_string = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + SHA256(RAW_BODY)
  signature = HMAC_SHA256(canonical_string, AGENT_SECRET)
  ```
- **Cabeceras HTTP Obligatorias:**
  - `X-Signature`: Firma hexadecimal calculada.
  - `X-Timestamp`: Marca de tiempo Unix en segundos (ventana de validez contra replay attacks: ±300s).
  - `X-Correlation-ID`: UUID de trazabilidad distribuida para logs unificados.
- **Fallback Determinista para Testing y Desconexión:** Si `jg_agente` no está accesible o se ejecuta en entorno local sin agente levantado, `AgentClientService` implementa una ejecución heurística determinista local (`executeDeterministicFallback`) que garantiza que la aplicación web funcione de forma autónoma sin bloquear al usuario.

---

## 6. Catálogo de Archivos, Rutas y Tests

### 6.1. Modelos de Dominio Eloquent (`app/Models/`)
- `User.php`: Autenticación, plan y slots (`paid_project_slots`).
- `Project.php`: Contenedor de producción cinematográfica.
- `Character.php`: Entidad de Biblia de personajes.
- `Location.php`: Entidad de Biblia de localizaciones.
- `Scene.php`: Secuencia dramática con orden y slugline.
- `ScriptBlock.php`: Bloque de guión con etapas y `user_locks`.
- `Shot.php`: Plano técnico de rodaje con desglose de cámara.
- `Take.php`: Toma audiovisual con archivo físico y notas editoriales.

### 6.2. Servicios de Negocio (`app/Services/`)
- `ScriptPipelineService.php`: Orquesta las transiciones entre etapas narrativas.
- `UserLockResolver.php`: Resuelve candados, diffs y sustituciones de términos fijados.
- `RoughCutAssemblyService.php`: Ensamblado y renderizado de pre-corte con FFmpeg.
- `JgAgente/AgentClientService.php`: Cliente HTTP firmado con HMAC-SHA256 y fallback local.

### 6.3. Componentes Livewire (`app/Livewire/` & `resources/views/livewire/`)
- `Projects/ProjectList.php` (`project-list.blade.php`): Panel de proyectos y cuotas de usuario.
- `Studio/ScriptEditor.php` (`script-editor.blade.php`): Editor de 3 columnas, candados y planos.
- `Studio/RoughCutTimeline.php` (`rough-cut-timeline.blade.php`): Timeline NLE, reproductor y ensamble.
- `Studio/ContextBibleDrawer.php` (`context-bible-drawer.blade.php`): Drawer lateral (`Cmd+B`) para personajes y localizaciones.

### 6.4. Rutas Web (`routes/web.php`)
- `GET /`: Redirección a `/projects`.
- `GET /projects`: Listado de proyectos cinematográficos (`ProjectList`).
- `GET /projects/{project}`: Estudio principal de rodaje y guión (`ScriptEditor`).
- `GET /projects/{project}/rough-cut`: Timeline NLE y pre-corte (`RoughCutTimeline`).

### 6.5. Pruebas Automatizadas (`tests/Feature/`)
- `DomainModelArchitectureTest.php`: Verifica UUIDv4, integridad referencial y cascadas en BD (6 tests).
- `ScriptEditorLivewireTest.php`: Verifica reactividad del editor y drawer de biblia (4 tests).
- `AgentClientHmacTest.php`: Verifica firma canónica HMAC y llamadas a `jg_agente` (5 tests).
- `TakeMediaManagementTest.php`: Verifica subida física de vídeo, validaciones y tomas válidas (5 tests).
- `RoughCutAssemblyTest.php`: Verifica ordenación de timeline, renderizado con FFmpeg y fallbacks (4 tests).
- `LivewirePipelineReactiveTest.php`: Verifica flujo end-to-end entre etapas y candados (4 tests).
- **Total:** 28 tests PHPUnit pasando al 100% (144 aserciones).

---

## 7. Estado Actual y Deudas Técnicas / Puntos de Mejora

### 7.1. Estado Operativo Real
- La aplicación es plenamente funcional en local (`http://127.0.0.1:8010`).
- Soporta el ciclo completo desde la creación del proyecto hasta el renderizado de vídeo concatenado con FFmpeg.
- Dispone de interfaz adaptativa en tema Claro (blanco puro estilo `jg_hub`/`jg_dashboard`) y tema Oscuro con selector interactivo y persistencia en `localStorage`.

### 7.2. Deudas Técnicas Conocidas
1. **Autenticación y Multiusuario:**
   - Actualmente opera con el middleware `EnsureDemoUserAuthenticated` que inyecta automáticamente un usuario demo de prueba (`filmmaker@cabocom.net`).
   - *Mejora pendiente:* Conectar con el sistema de autenticación centralizado del ecosistema `jg_hub` o SSO OAuth2 / Laravel Fortify, y aplicar control estricto de cuotas (`paid_project_slots`) en pasarela de pago.
2. **Motor de Ensamblado FFmpeg:**
   - La concatenación actual utiliza el demuxer `concat` (`-f concat -safe 0 -c copy`), asumiendo que los clips de vídeo subidos comparten códec, contenedor y resolución.
   - *Mejora pendiente:* Diseñar un pipeline de transcodificación intermedia normalizada (e.g. H.264 1080p a 24fps unificado) para evitar inconsistencias cuando los rodajes mezclen cámaras con tasas de fotogramas o formatos heterogéneos.
3. **Formatos de Exportación NLE:**
   - Actualmente exporta la lista de corte secuencial en formato JSON estructurado.
   - *Mejora pendiente:* Desarrollar exportadores a formatos estándar de la industria como FCPXML (Final Cut Pro / DaVinci Resolve) y CMX 3600 EDL para interoperabilidad con salas de etalonaje y montaje profesional.
4. **Almacenamiento Distribuido en la Nube:**
   - Los vídeos se almacenan en el sistema de archivos local (`storage/app/public/media/...`).
   - *Mejora pendiente:* Desacoplar el driver de almacenamiento para soportar proveedores compatibles con S3 (AWS S3, MinIO, Cloudflare R2) para despliegues en clúster y alta concurrencia.
5. **Conexión Real con Modelos LLM:**
   - Aunque la arquitectura HTTP y el plugin satélite en Python están desplegados y validados, las transformaciones operan bajo fallbacks deterministas de heurística de texto.
   - *Mejora pendiente:* Activar el endpoint de inferencia en `jg_agente` enlazado con modelos LLM reales (OpenAI / Anthropic / Ollama) a través del grafo de LangGraph.
