# CONTEXTO DE PROYECTO: jg_filmmaker

**Versión del documento:** 3.33.0  
**Fecha de consolidación:** 8 de noviembre de 2026  
**Componente:** Plataforma Web de Pipeline Cinematográfico Asistido por IA  
**Stack Tecnológico:** Laravel 12 (v12.69.3), Livewire 4 (v4.4.8), Tailwind CSS v4, PHP 8.4 (v8.4.27), PostgreSQL / SQLite, FFmpeg, DomPDF (barryvdh/laravel-dompdf), Speaches-AI (Piper TTS), Google AI Studio vía `jg_plugin_filmmaker` (`generate_storyboard`, `extract_characters`, `audit_character_usage`), PHPUnit 12, Laravel Pint  
**Estado:** ISSUE-051 completado: Demostración auditiva de voz (TTS) interactiva y carga de imágenes de referencia mediante Drag & Drop en la Biblia Contextual. 233 tests automatizados pasando al 100% y Pint superado.

## Resumen Ejecutivo de Capacidades Recientes

- **ISSUE-051: Demo de Voz Interactiva y Subida Drag & Drop en Biblia Contextual:**
  - **Demostración Auditiva de Voz TTS (`projects.tts.demo-voice`):**
    - Nuevo endpoint seguro `AudioStreamController::demoVoice` que sintetiza bajo demanda una muestra de voz personalizada con la frase `"Hola, soy la voz {Nombre}"` o texto parametrizado.
    - Se integra en `ContextBibleDrawer` un botón interactivo reactivo en Alpine.js con estados visuales claros (reproduciendo, detenido, error) tanto en la edición de personajes existentes como en el alta de nuevos personajes, permitiendo previsualizar al instante el timbre dramático antes de asignarlo.
  - **Carga de Imágenes de Referencia con Arrastrar y Soltar (Drag & Drop):**
    - Las áreas de carga de imagen para personajes y localizaciones incorporan soporte nativo de drag & drop con feedback visual inmediato (resaltado ámbar, estados de carga y previsualización de imagen temporal).
    - Permite tanto soltar archivos directamente desde el explorador como abrir el selector de archivos tradicional con un clic.
  - **Suite de Pruebas Ampliada a 233 Tests al 100%:** Nuevas pruebas en `AudioStreamControllerTest` y `ContextBibleAndTimelineIsolationTest` verificando el streaming de la demo de voz y la edición de personajes con carga de imagen.

- **ISSUE-050 (GitLab #4): Modales y Avisos Previos de Consumo de Tokens en Todas las Acciones de IA:**
  - **Pipeline Completo de Escena (`promptRunScenePipeline` / `showScenePipelineConfirmModal`):**
    - El icono `⚡` de escena ahora abre un modal previo de advertencia con el desglose exacto de bloques a procesar, coste estimado en tokens, saldo diario disponible y saldo resultante proyectado tras la ejecución.
  - **Procesamiento por Lote (`openBatchModal` / `recalculateBatchTokenEstimation`):**
    - Se integra en `showBatchModal` una tarjeta de cómputo reactiva que recalcula dinámicamente el coste según el ámbito (`scene` / `project`) y la etapa elegida (`all`, `structured`, `screenplay`, `shots`), previniendo la ejecución si el saldo es insuficiente.
  - **Acciones Individuales de Bloque en el Drawer (`promptBlockAction` / `showBlockActionConfirmModal`):**
    - Las acciones de estructurar tratamiento, convertir a guion y desglosar planos técnicos solicitan confirmación previa con el coste estimado del bloque antes de enviar la petición a la IA.
  - **Composición Visual de Storyboard (`showStoryboardModal`):**
    - Incorporación de la tarjeta de coste de viñeta (~350 tokens) antes de pulsar "Generar Viñeta con IA", bloqueando la acción si no hay cupo suficiente.
  - **Suite de Pruebas Ampliada a 230 Tests al 100%:** Nuevas pruebas en `LivewireScriptEditorTest` validando los flujos de confirmación de tokens, estimaciones dinámicas y bloqueos fail-safe.

- **Pipeline IA Automático por Escena (Bruto ➔ Tratamiento ➔ Guión ➔ Descomposición en Planos):**
  - **Acción Rápida en Cabecera de Escena (`runScenePipeline`):** Incorporado nuevo botón interactivo con icono de rayo (`⚡`) en la barra de acciones de cada escena (tanto en vista Pipeline de estudio como en vista Story Architect).
  - **Orquestación End-to-End Desatendida:** Al pulsar el icono, el sistema toma automáticamente todos los bloques que integran la sección/escena y ejecuta secuencialmente la cadena completa:
    1. Conversión de texto bruto a tratamiento cinematográfico (`transitionToStructured`).
    2. Formateo de tratamiento a guión literario canónico (`transitionToScreenplay`).
    3. Desglose técnico del guión en planos de cámara con tomas base sincronizadas (`generateShotsBreakdown`).
  - **Respeto de Bloqueos y Sincronización:** Respeta los candados individuales de etapa (`isStageLocked`) y sincroniza automáticamente las entidades dramáticas que emerjan en la Biblia del proyecto (`syncProjectCharacters` y `syncProjectLocations`).
  - **Conmutación Visual Inmediata:** Al finalizar el procesamiento, conmuta la pestaña de la escena directamente a la etapa de Planos/Tomas (`shots`) para que el cineasta visualice el resultado de forma inmediata.
  - **Suite de Pruebas Ampliada a 229 Tests al 100%:** Nueva prueba en `LivewireScriptEditorTest` validando el ciclo completo end-to-end por escena y la respuesta reactiva en interfaz.

- **ISSUE-049 (GitLab #3): Sistema Híbrido de Inteligencia de Personajes (IA Semántica + Grounding Determinista + Forzado HITL):**
  - **Inferencia Híbrida en `ScriptPipelineService`:** Integración con la tool canónica `extract_characters` de `jg_plugin_filmmaker`. Capacidad para reconocer personajes dramáticos reales (tanto personajes con diálogo como personajes mudos/silentes que intervienen en la acción física), complementada con grounding textual estricto en el texto original y catálogo de stopwords técnicas de cine (`INT`, `EXT`, `CORTE`, `SONIDO EN OFF`, `MIRA A SU ALREDEDOR`).
  - **Auditoría Semántica de Eliminación en `ContextBibleDrawer`:** Conexión con `audit_character_usage` para discernir si la aparición de un nombre corresponde a un personaje dramático real en uso o a una coincidencia casual en una acotación en prosa. Elimina falsos positivos que bloqueaban el borrado de entidades como *"Mira A Su Alrededor"* o *"Sonido En Off"*.
  - **Soberanía y Forzado del Director (HITL):** En el modal de advertencia por uso en el guion se incorpora el botón **"Eliminar de todos modos"** (`forceDelete`), permitiendo al usuario purgar cualquier elemento de la Biblia de forma inmediata y definitiva si así lo decide, sin callejones sin salida.
  - **Suite de Pruebas Ampliada a 228 Tests al 100%:** Nuevas pruebas en `LivewireScriptEditorTest` validando el descarte automático de falsos positivos en la auditoría y la funcionalidad de borrado forzado soberano.

- **Desacoplamiento Total de Generación de Storyboards hacia `jg_plugin_filmmaker`:**
  - `StoryboardService` en Laravel cesa cualquier conexión HTTP directa o acoplamiento con endpoints de proveedores de IA; delega íntegramente en `AgentClientService::callTool('generate_storyboard', $payload)`.
  - La herramienta canónica `generate_storyboard` en `jg_plugin_filmmaker` asume la orquestación multimodal con Google AI Studio (`gemini-2.5-flash-image`), gestión de API keys y modo mock determinista fail-closed para CI/CD.
  - Almacenamiento físico de viñetas estructurado canónicamente a nivel de escena (`projects/{project_id}/storyboards/scene_{scene_id}/shot_{shot_id}_{time}.ext`), blindando los enlaces contra renumeraciones y facilitando el montaje con rclone en `/media`.

- **Sintaxis de Referencia Visual con Arroba (`@`) en Prompts (Estilo Flow / Midjourney):**
  - El cineasta puede referenciar explícitamente entidades visuales dentro del prompt editable:
    - `@NombrePersonaje` o `@personaje` (ej. `@EDURNE`, `@MARCUS`).
    - `@NombreLocalizacion` o `@localizacion` (ej. `@CASA_ENTRADA`, `@BAÑO`).
    - `@Atrezzo1`, `@Atrezzo2`, `@Atrezzo3` para asociar objetos y accesorios específicos del plano a sus imágenes subidas.
  - El generador de prompts sugiere automáticamente la redacción integrando las etiquetas `@` correspondientes a la escena.
  - En el modal de composición se incluye una botonera interactiva con las etiquetas `@` activas; al hacer clic sobre una etiqueta, se inserta inmediatamente en la posición exacta del cursor en el área de texto del prompt.

- **Modal de Composición Visual y Generación de Storyboard con IA (`ScriptEditor` & `StoryboardService`):**
  - **Prompt Editable Previo a la Generación:** El cineasta puede inspeccionar, reescribir y refinar libremente el prompt descriptivo generado automáticamente antes de remitirlo a la API de Google AI Studio, con un botón reactivo de *Restablecer prompt automático* para sincronizarlo al vuelo con las referencias seleccionadas.
  - **Acondicionamiento Multimodal Integral de la Escena:**
    - **Personaje:** Selección o detección automática del personaje en escena; inyección obligatoria de su imagen de modelo (*turn-around*) almacenada en la Biblia (`Character.reference_image_path`) como `inline_data` multimodal.
    - **Localización / Escenografía:** Selección o detección automática de la localización de la escena; incorporación de su fotografía de referencia (`Location.reference_image_path`) junto con las notas de dirección de arte e iluminación como `inline_data` multimodal.
    - **Atrezzo y Objetos Específicos del Plano:** Soporte para adjuntar hasta 3 imágenes de referencia de objetos, accesorios o elementos clave (ej. ambientador, teléfono móvil, cartas, atrezzo dramático), enviadas como partes visuales directas a `gemini-2.5-flash-image` para garantizar que la IA dibuje exactamente los elementos deseados con coherencia formal.
  - **Selector de Estilos Gráficos:** Píldoras de selección inmediata entre *Escala de Grises (Sketch B&W)*, *Animación 2D Infantil* y *Enki Bilal*, con retroalimentación visual instantánea.
  - **Gestión Visual de Localizaciones en la Biblia de Producción (`ContextBibleDrawer`):**
    - Nueva columna `reference_image_path` en la tabla `locations`.
    - Formulario reactivo en el cajón de la Biblia para subir, previsualizar y suprimir la fotografía o referencia visual de cada localización (interior/exterior).
  - **Suite de Pruebas Ampliada a 226 Tests al 100% y Compatibilidad CI/CD sin Extensión GD:** Nuevas pruebas de integración en `StoryboardGenerationTest` cubriendo el ciclo de vida de imágenes de localización, apertura del modal de composición, edición de prompts, límite estricto de 3 objetos de atrezzo y carga multimodal a los endpoints de Google AI. Se implementó el helper `fakeImage` con bytes binarios reales PNG (`createWithContent`) para evitar el uso de `UploadedFile::fake()->image()`, garantizando la ejecución de la suite sin requerir la extensión PHP `gd` en el runner de GitLab CI (`composer:2`).

- **Condicionamiento Escenográfico de Localización en Storyboard (`StoryboardService`):**
  - Resolución inteligente de la localización vinculada a la escena o mediante coincidencia heurística con el *slugline*.
  - Enriquecimiento del prompt visual con las notas de dirección de arte (materiales, texturas, escenografía, paleta) y notas de iluminación (fuentes de luz, ambiente diurno/nocturno, neones, atmósfera) definidas en la Biblia de Producción (`Location.art_department_notes` y `Location.lighting_notes`).
  - Coexistencia sinérgica con el condicionamiento de personajes (*character turn-around*), garantizando coherencia visual tanto de sujetos como de entornos entre viñetas.
- **Perfeccionamiento del Desglose Técnico de Planos y Erradicación de Tomas/Descartes en Storyboard (`ScriptEditor` & `AgentClientService`):**
  - **Delimitadores de Planos sin Numeración Manual:** El editor de texto del cajón de detalles y los prompts de IA adoptan estrictamente la sintaxis limpia sin números: `--- shot (TIPO) ---` (ej. `--- shot (PG) ---`, `--- shot (PP) ---`). El cineasta no tiene que mantener ni calcular números al añadir, cortar o reordenar planos; la numeración secuencial (`1, 2, 3...`) se calcula y asigna de manera totalmente automática en la base de datos al guardar o regenerar.
  - **Regeneración Limpia en `ScriptPipelineService`:** Corrección de la lógica de actualización en `generateShotsBreakdown`. Se distingue entre tomas con material real de rodaje (vídeo subido, buena toma o notas manuales) y tomas dummy iniciales del pipeline; los planos sin material grabado se actualizan con la nueva cobertura y se eliminan los planos obsoletos o sluglines preexistentes en la base de datos.
  - **Exclusión Estricta de Sluglines y Agrupación Cinemática Coherente:** El motor determinista y los prompts de IA (`shot_breakdown_detector`) omiten categóricamente los encabezados de escena (`INT.`, `EXT.`, `INT/EXT.`, `EST.`), impidiendo que un encabezado se catalogue como plano de cámara. Además, cesa la división indiscriminada de un plano por cada línea, agrupando unidades dramáticas (hasta 2 párrafos continuos de acción o diálogo de personaje) y detectando indicios de cámara explícitos (`Detalle de...`, `Plano general de...`) como cortes técnicos naturales (`PD`, `PP`, etc.).
  - **Sincronización Bidireccional de Delimitadores de Planos:** El botón "✏️ Editar Planos" en el panel lateral de detalles de la pestaña de Planos permite al cineasta escribir y modificar delimitadores directos en el guión literario (soporta tanto `--- shot 1 (PG) ---` como `--- shot (PG) ---`). Al guardar, se parsean y sincronizan automáticamente los modelos `Shot` en base de datos, suprimiendo los sobrantes y protegiendo la etapa contra sobreescrituras accidentales.
  - **Botonera de Inserción Rápida de Cortes de Cámara:** En el editor de planos del cajón de detalles se incorpora una barra de herramientas reactiva en Alpine.js con atajos (`+ PG`, `+ PM`, `+ PP`, `+ PD`, `+ PA`) que insertan instantáneamente el delimitador sin número en la posición exacta del cursor en el texto.
  - **Edición y Eliminación Individual de Planos:** Cada tarjeta de plano dispone de botones para editar individualmente su escala y encuadre descriptivo en un formulario embebido, y eliminar el plano con reindexación correlativa inmediata de los planos restantes en el bloque.
  - **Añadir Plano Manual:** Botón visible al pie del listado de planos para dar de alta planos técnicos manuales rápidamente sin necesidad de reescribir todo el guion.
  - **Purga de Tomas y "Descartes":** Se retiran por completo los contadores de tomas, botones de descarte y sellos de "TOMA BUENA" de la pestaña de planos y vistas continuas, dejando la interfaz enfocada con exclusividad en la planificación visual, el tiro de cámara y las viñetas de storyboard.
  - Suite de pruebas de integración ampliada a **221 tests pasando al 100%** con Pint superado.
- **ISSUE-048 (GitLab #2): Generación de Storyboard por Plano con Google AI Studio, Consistencia de Personaje y Selector de Estilos (`StoryboardService` & `ScriptEditor`):**
  - Implementación del servicio `StoryboardService` para renderizado de viñetas gráficas 16:9 conectando con Google AI Studio (Imagen 3 / Gemini API: `POST /v1beta/models/imagen-3.0-generate-002:predict`).
  - Condicionamiento visual de identidad (*Character Conditioning / Turn-around*): integración de la imagen de referencia subida en la Biblia de Producción (`Character.reference_image_path`) para inyectar fisonomía, vestuario y rasgos faciales en el prompt, asegurando coherencia visual entre planos.
  - Catálogo de 3 estilos visuales canónicos de Storyboard:
    - `grayscale_sketch`: Boceto cinemático en blanco y negro (carboncillo y grafito, contrastes noir).
    - `kids_2d_animation`: Animación 2D tradicional infantil con colores vivos, cel-shading y líneas limpias.
    - `enki_bilal`: Novela gráfica de autor europea distópica/noir (texturas densas al pastel/óleo, tonos desaturados, melancolía retrofuturista).
  - Interfaz interactiva en `ScriptEditor` (etapa 4. Planos):
    - Selector persistente de estilo de Storyboard en cabecera del cajón de planos.
    - Tarjeta de plano enriquecida con miniatura de viñeta 16:9, distintivo de estilo, botón de generación/regeneración con estado de carga y visor modal lightbox a pantalla completa.
    - Previsualización compacta de viñeta integrada en la lectura continua de escenas.
  - Subida y administración de imágenes de referencia en la Biblia de Producción (`ContextBibleDrawer`) con soporte en `config/livewire.php` para extensiones gráficas (`png,jpg,jpeg,webp`).
  - Suite de 6 pruebas de integración (`tests/Feature/StoryboardGenerationTest.php`) con cobertura de catálogos, ensamblaje de prompts con condicionamiento de personaje, mocks de API Google AI con `Http::fake()`, cambio de estilos en Livewire y ciclo de vida de imágenes en la biblia. Total suite: **219 tests pasando al 100%**.
- **Precarga y Buffering Anticipado en Segundo Plano para Lectura TTS Continua (`ttsPlayer` en `ScriptEditor`):**
  - Implementación de un motor de prefetch secuencial anticipado (*look-ahead buffer*) de 2 segmentos en Alpine.js.
  - Mientras el segmento en curso se reproduce en el navegador, el reproductor descarga y pre-sintetiza en segundo plano los siguientes segmentos en blobs binarios en memoria (`URL.createObjectURL`).
  - Al terminar cada segmento, la transición al siguiente es instantánea (cero latencia y cero interrupciones de carga), garantizando una experiencia de audiolibro/lectura dramática fluida e ininterrumpida.
  - Gestión optimizada del ciclo de vida con `AbortController` ante detenciones, invalidación reactiva de caché al alterar la velocidad (1.0x-1.5x) y recolección de memoria mediante revocación de URLs de objeto (`URL.revokeObjectURL`) para segmentos antiguos.
- **Blindaje UX contra Cierres Accidentales en Edición de Bloques (`ScriptEditor`):**
  - Desactivación de eventos `@keydown.escape.window` tanto en el panel lateral de edición de bloques (`detailBlock`) como en el modal de creación de bloques (`showNewBlockModal`), impidiendo pérdidas accidentales de texto al pulsar la tecla Escape.
  - Eliminación del cierre por clic en el backdrop exterior (`wire:click="closeBlockDetails"`), garantizando que el modal únicamente se cierre mediante acción deliberada del usuario pulsando el botón explícito de cierre (`✕`) ubicado en la esquina superior derecha.
- **Prevención Universal de Doble Clic en Formularios y Botones de Acción:**
  - Deshabilitación automática de botones tras el primer clic en la plantilla base (`layouts/app.blade.php`), en el formulario de acceso OTP (`login.blade.php`) y en la creación/listado de proyectos (`project-list.blade.php`), evitando inserciones o peticiones concurrentes accidentales.
- **ISSUE-047 (GitLab #1): Lectura Dramática Automatizada con Síntesis de Voz (TTS) Multivoz vía Speaches-AI (`TextToSpeechService`, `AudioStreamController` & `ScriptEditor`):**
  - Integración nativa con el motor local `speaches-ai` (puerto interno 8000 en la red Docker `jg_network`, API compatible OpenAI `/v1/audio/speech`).
  - Diagrama de arquitectura formalizado en ASCII Art tanto en la documentación viva (`analysis.md`) como en el issue de GitLab.com (`#1`) evitando fallos de compatibilidad con parsers Mermaid.
  - Catálogo de voces en castellano de España (`es_ES`): `sharvard#M` (masculino), `sharvard#F` (femenino), `davefx`, `carlfm`, `mls_9972` y `mls_10246`.
  - Asignación configurable de voz por personaje en la Biblia del proyecto (`ContextBibleDrawer` y `Character.custom_attributes['voice_id']`) con inferencia heurística automática por género y nombre.
  - Parser dramático de guiones literarios (`TextToSpeechService::parseBlockToSegments`): bifurcación inteligente de acotaciones/sluglines hacia la voz del Narrador y parlamentos hacia la voz del personaje emisor (omitiendo acotaciones de interpretación en la locución del diálogo).
  - Reproductor flotante integrado en Alpine.js con controles de reproducción (Play/Pausa, anterior, siguiente, selector de velocidad 1.0x-1.5x), manifiesto de streaming O(1) con caché determinista SHA256 en disco privado (`storage/app/private/tts_cache` con permisos `0775` y propietario `www-data:www-data`), y resaltado visual en vivo con autoscroll hacia el bloque actualmente locutado.
  - Tres ámbitos de reproducción interactiva: por bloque individual, por escena completa y lectura continua de la obra completa.
- **Importación Inteligente de Guiones PDF y TXT (`ScriptImportService`):** Ingesta y parseo automático de guiones literarios en formato PDF (vía `smalot/pdfparser` nativo y fallback a `pdftotext`) y TXT UTF-8. Motor heurístico de análisis dramático que detecta sluglines canónicos (`INT./EXT.`), tiempos de día, numeración, personajes en mayúsculas, acotaciones y bloques de acción/diálogo. Ventana interactiva de previsualización con resumen métrico y confirmación obligatoria con aviso de destrucción irreversible de datos antes de sobreescribir un proyecto o crear uno nuevo desde el catálogo.
- **Edición Ágil del Nombre y Metadatos de la Obra:** Capacidad de modificar el título y sinopsis del proyecto tanto desde el listado general (`ProjectList`) como directamente en tiempo real desde el encabezado del estudio de rodaje (`ScriptEditor`).
- **Renderizado y Procesamiento en Lote Multietapa (Batch Processing):** Nuevo servicio `ScriptPipelineService::batch*` y modal interactivo en el estudio para procesar en lote a nivel de Escena Activa o Proyecto Completo: Brutos ➔ Tratamientos, Tratamientos ➔ Guiones, Guiones ➔ Planos Técnicos o Pipeline Completo en cadena. Protección absoluta de candados de usuario (`user_locks['locked_fields']`) para no sobreescribir bloques protegidos por el cineasta.
- **ISSUE-045: Exportación de Guión Completo a PDF y Corrección de Overflows en Barra Superior (`ScriptExportService`, `ScriptExportController` & `ScriptEditor`):**
  - Implementación del botón `📄 Exportar PDF` en la barra superior del estudio para descargar el guion completo del proyecto con maquetación tipográfica estándar de la industria (fuente Courier, portada canónica con título, autor, sinopsis y fecha, numeración de escenas, sluglines, personajes centrados, acotaciones y diálogos).
  - Integración de `barryvdh/laravel-dompdf` para renderizado en PHP puro sin dependencias de C externas, con ruta HTTP protegida `projects.export-pdf` y acción Livewire `exportPdf()`.
  - Corrección del corte de desbordamiento CSS en la barra superior sustituyendo `overflow-x-auto` por `flex-wrap py-1`, permitiendo que todos los menús desplegables (`stageMenuOpen`, `holeMenuOpen`) se abran y floten hacia abajo sin ser recortados por el contenedor.
  - Enriquecimiento del desplegable de Agujeros de Guión con todas las opciones disponibles con icono y descripción (Analizar con IA, Ver/Ocultar avisos, Borrar marcas).
- **ISSUE-046: Compatibilidad Estricta con PostgreSQL en Concurrencia Pesimista (`ScriptEditor`):**
  - Corrección del error `SQLSTATE[0A000]: Feature not supported: 7 ERROR: FOR UPDATE is not allowed with aggregate functions` producido al crear escenas, bloques, planos o tomas en PostgreSQL.
  - La concurrencia pesimista en creación de entidades jerárquicas bloquea ahora la entidad padre con `lockForUpdate()->first()` (`Project`, `Scene` o `Shot`), calculando el orden correlativo `max(...)` escalarmente sin añadir `FOR UPDATE` a la consulta de agregación.
  - Inicialización reactiva de `expandedSceneIds` y `scenePipelineStages` al crear nuevas escenas para garantizar su despliegue y visibilidad inmediata en el estudio.
- Suite ampliada a **213 tests automatizados pasando al 100%** con Pint superado.

- ISSUE-001: saneamiento de subida de tomas contra RCE (derivación estricta de extensiones desde MIME type verificado por el servidor, lista blanca `ALLOWED_EXTENSIONS` en `Take`, bloqueo de ejecución PHP en `/storage/` en Nginx).
- ISSUE-002: mitigación de fuga de OTP y toma de cuentas (bloqueo con `#[Locked]` de `$step` y `$devOtpCode`, eliminación de `$isLocalEnv`, comprobación estricta de `APP_ENV=local && APP_DEBUG=true` en servidor, erradicación de retención en sesión).
- ISSUE-003: erradicación de IDOR y autorización por propietario (creación de `ProjectPolicy` con privacidad estricta donde ni los administradores acceden al editor de guión de terceros, middleware `can:view,project` en `routes/web.php`, `Gate::authorize` en `ScriptEditor::mount()`, propiedad `$project` bloqueada con `#[Locked]` y métodos auxiliares con ámbito de proyecto `projectBlock`, `projectScene`, `projectShot`, `projectTake`).
- ISSUE-004: mitigación de IDOR y fuga de datos en `ContextBibleDrawer` y `RoughCutTimeline` (acotamiento de edición de personajes y localizaciones a `$this->project`, validación de pertenencia en `selectTakeForPlayback`, supresión de fallback global no acotado en reproductor, y bloqueo de `$project` con `#[Locked]`).
- ISSUE-005: blindaje contra robo de bloques y desincronización en `reorderBlock` (resolución estricta con `$this->projectScene()` y `$this->projectBlock()`, actualización atómica de `scene_id` en planos dependientes de la tabla `shots` y reindexación secuencial sin huecos en la escena de origen y destino).
- ISSUE-006: confinamiento de medios audiovisuales inéditos en disco privado `local` (`storage/app/private/media`), creación de `MediaStreamController` para streaming protegido con verificación estricta de propiedad de proyecto contra fugas de propiedad intelectual, y migración de los 13 modelos Eloquent a `HasVersion4Uuids` para garantizar la generación estricta de identificadores UUIDv4 RFC 4122 sin predictibilidad temporal.
- ISSUE-007: erradicación de secretos por defecto y fortalecimiento de Compose/arranque (variables obligatorias con `${VAR:?error}` en `docker-compose.yml`, aborto en `entrypoint.sh` si falta `APP_KEY` en producción, eliminación del silenciamiento `|| true` en migraciones, anulación de `SEED_DEMO` en producción y validación fail-fast en `AppServiceProvider::boot()` contra secretos o claves inseguras y conocidas).
- ISSUE-008: blindaje de autenticación passwordless OTP (rate limiting dual por IP y por correo de destino contra mail-bombing, rate limiting por cuenta de usuario contra fuerza bruta distribuida, invalidación atómica de tokens anteriores asegurando unicidad, hashing ultra-eficiente O(1) con HMAC-SHA256 anti DoS CPU, consumo atómico SQL anti-TOCTOU, límite de 5 intentos por código con bloqueo automático en `attempts` y auditoría en `security_breach_logs`, persistencia `remember` mediante opt-in voluntario y trait `Prunable` en `OneTimePassword`).
- ISSUE-018: blindaje y validación exhaustiva de estado en componentes Livewire (acotamiento de `editStage`, `setBlockStage` y selectores de vistas a listas blancas estrictas, límite de longitud `max:50000` en textos dramáticos y `max:5000` en notas editoriales y de biblia, bloqueo con `#[Locked]` de propiedades de borrado en `ContextBibleDrawer`, re-auditoría obligatoria de usos en servidor en `confirmDelete()` inmune a manipulación en cliente, validación de unicidad scoped por proyecto con `Rule::unique` en creación de personajes y localizaciones, y validación de `status` en `ProjectList`).
- ISSUE-019: idempotencia estricta en peticiones GET y eliminación de N+1 en detección dramática (eliminación de llamadas mutadoras a `syncProjectCharacters` y `syncProjectLocations` en `ScriptEditor::mount()`, precarga en memoria de personajes y localizaciones para eliminar consultas N+1 en inserción y sincronización).
- ISSUE-020: preservación inmutable de entidades de la Biblia de producción y saneamiento sintáctico de inferencia dramática (eliminación radical de borrados automáticos `delete()` o renombrados destructivos de localizaciones al modificar sluglines, salvaguardando notas de arte e iluminación, y eliminación de la lista fija de nombres españoles hardcodeados en favor de sintaxis Fountain y roles dramáticos con artículo).
- ISSUE-021: autorización y trazabilidad forense en el panel de administración (registro de Gate `admin` y middleware persistente de Livewire `EnsureUserIsAdmin`, creación del modelo y tabla `admin_audit_logs`, salvaguardas de auto-revocación y preservación de quórum de administradores en `UserList`, bloqueo de borrado de tarifa activa en `PricingManager`, trazabilidad indeleble en `ProjectManager::transferProject` y registro de purga en `SecurityLogsViewer`).
- ISSUE-028: desacoplamiento y erradicación de autologin demo enmascarado (eliminación del middleware `EnsureDemoUserAuthenticated` de la pila web global, confinamiento estricto a `APP_ENV=local` con toggle explícito `app.demo_autologin`, erradicación total de auto-login en `testing` y `production`, y eliminación de contraseñas estáticas de desarrollo).
- ISSUE-022: paginación y optimización de agregaciones administrativas (implementación de `WithPagination` a 20-25 registros en `UserList` y `ProjectManager`, acotamiento de métricas y telemetría a los IDs visibles de la página, carga diferida de usuarios en modal de transferencia e índices compuestos en `script_blocks` y `agent_request_logs`).
- ISSUE-026: armonización y robustez de límites de subida de archivos (configuración de subida temporal en Livewire a 100 MB con `max:102400`, throttle de 15/min y ventana de 30 minutos, y unificación de límites en PHP y Nginx a 120 MB mitigando amplificación de DoS).
- ISSUE-023: atomicidad y exclusión mutua en contadores y numeraciones de guión (envoltura de `createNewBlock`, `createScene`, `addShot` y `saveNewTake` en transacciones `DB::transaction()` con `lockForUpdate()` erradicando condiciones de carrera).
- ISSUE-025: erradicación de ficheros de vídeo huérfanos y limpieza física en cascada (limpieza desacoplada con `DB::afterCommit` en `Take::deleted`, borrado en cascada de tomas en `Scene::deleting`, supresión íntegra de directorio en `Project::deleted` y comando Artisan `media:prune-orphans`).
- ISSUE-024: optimización del grafo de renderizado y caché de telemetría de cuotas (reutilización en memoria de `$scenes` para `$activeScene` eliminando la consulta redundante O(N) por interacción, almacenamiento en caché de 30 segundos con invalidación por eventos en `AgentRequestLog::booted()` para `User::getTokensUsedToday()` eliminando dobles joins masivos con sumatorio en cada render, y memoización en ciclo de vida de `getGoodTakes()` en `RoughCutTimeline`).
- ISSUE-027: sondeo técnico real de vídeo con FFprobe y erradicación de metadatos fabricados (reemplazo de metadatos hardcodeados a 5.0s, 1080p, 24fps y códecs inferidos por extensión por extracción estructurada JSON de streams mediante `ffprobe` en `ScriptEditor::probeVideoFile`, distinción explícita de ficheros no sondeados con `probed = false` y saneamiento de exportación JSON en `RoughCutTimeline`).
- ISSUE-030: saneamiento de configuración y compatibilidad estricta con `config:cache` (eliminación de lectura y parseo manual de `.env` en tiempo de ejecución, selección determinista de API keys de LLM sin riesgo de mezcla inter-proveedores, y eliminación de llamadas a `env()` en el constructor de `AgentClientService`).
- ISSUE-031: robustecimiento del protocolo HMAC y correlación distribuida (incorporación de `X-Key-ID` para soporte de rotación de secretos inter-servicio, serialización `JSON_THROW_ON_ERROR` para evitar peticiones vacías firmadas `{}` y propagación de `X-Correlation-ID` en trazas de advertencia y error del cliente).
- ISSUE-032: optimización de concurrencia en SQLite (activación de `journal_mode=WAL`, `synchronous=NORMAL` y `busy_timeout=5000` en `config/database.php` erradicando fallos intermitentes de `database is locked` bajo concurrencia).
- ISSUE-033: sincronización y fidelidad entre especificaciones y código real (declaración formal de `services.filmmaker.mock_ffmpeg` en `config/services.php`, eliminación de contratos no soportados y conciliación total de especificaciones vivas con la implementación de producción).
- ISSUE-034: blindaje de despliegue inmutable en Docker Compose (eliminación de bind mounts de directorios de código en `docker-compose.yml`, aislamiento de desarrollo en caliente a `docker-compose.dev.yml` y saneamiento de permisos en `docker/entrypoint.sh`).
- ISSUE-039: erradicación de credenciales por defecto en Compose del plugin (sustitución de contraseñas estáticas por validación obligatoria fail-fast `${VAR:?}` en PostgreSQL y microservicios).
- ISSUE-040: retención prunable, deduplicación de snapshots y purga en cascada de actividad (implementación de trait `Prunable` a 90 días en `UserActivityLog`, eliminación en cascada en `Project::deleting`, deduplicación de snapshots y atribución estricta a `auth()->id()`).
- ISSUE-041: saneamiento de heurísticas y generalización de prompts dramáticos (eliminación de reglas sobreajustadas a textos particulares en `AgentClientService`, prompts estandarizados para la industria cinematográfica y paridad total de contratos).
- ISSUE-009: robustez en ensamblado FFmpeg (conservación del descriptor temporal concat mediante bloque `finally` para reintento seguro con `libx264` ante disparidad de códecs, eliminación total de falsos positivos con archivos corruptos simulados, nombres de archivo con UUIDv4 deterministas `rough_cut_{uuid}.mp4`, saneamiento estricto de rutas de tomas contra path traversal, y política de retención automática que purga renders antiguos conservando únicamente los 5 más recientes).
- ISSUE-010: saneamiento de `AgentClientService` y erradicación de bypass no autorizado (logging estructurado de fallos HTTP y excepciones sin volcar PII, reintentos de red automáticos con `retry`, bloqueo de llamadas a LLM directos comerciales en producción con salvaguarda `services.llm.direct_enabled`, validación del flag de éxito del contrato `ToolResult` y reporte de origen `source` y `degraded`).
- ISSUE-011: incorporación formal de `plot_hole_detector` en `jg_plugin_filmmaker` (creación del módulo de detección semántica y heurística `plot_holes.py`, definición del esquema Pydantic v2 `PlotHoleDetectorArgs`, registro como 4.ª herramienta canónica del plugin satélite y actualización de metadatos v2.3.0).
- ISSUE-012: sincronización canónica del contrato de la Biblia dramática (creación de `Character::toAgentContext()` y `Location::toAgentContext()`, eliminación de accesos a propiedades espurias como `role`, `personality_notes` y `description`, enriquecimiento de prompts en `jg_plugin_filmmaker` con `bio`, `psychology` y `costume_notes`, y activación de `Model::preventAccessingMissingAttributes()` en `AppServiceProvider`).
- ISSUE-013: gobernanza de concurrencia y erradicación de pérdida de `user_locks` (columna `lock_version` para bloqueo optimista en `saveBlock`/`saveBlockFromDrawer`, re-chequeo tardío de candados y transacciones con `lockForUpdate()` en `transitionToStructured`/`transitionToScreenplay`/`generateShotsBreakdown`, método atómico `mutateUserLocks` para alternar candados o términos fijados, y aislamiento atómico en auditoría y descarte de agujeros de guión).
- ISSUE-014: extracción de telemetría IA de `user_locks` y erradicación de anidamiento recursivo (almacenamiento de telemetría técnica estructurada en `agent_request_logs.meta`, método `sanitizeUserLocksForAgent()` que extrae exclusivamente directrices de autoría hacia el agente, eliminación de `ai_telemetry` de la columna `user_locks` en `script_blocks`, desacoplamiento de `openAiTrace` para consultar `AgentRequestLog` y migración de purga histórica).
- ISSUE-015: preservación de candados, diffs manuales y protección de planos técnicos (archivado no destructivo de diffs en `user_locks['archived_diffs']` con marca ISO-8601 `archived_at` ante regeneraciones, respeto al candado `isStageLocked('shots')` en pipeline y editor, protección de planos existentes con tomas grabadas en claqueta contra sobreescritura destructiva, sustitución posicional anclada de discrepancias con `substr_replace`, y registro de contenido previo en `activity_logs`).
- ISSUE-017: control centralizado de cuota diaria de tokens de IA y blindaje contra check-then-act (creación de `QuotaExceededException`, método `assertUserQuotaAvailable()` obligatorio en `ScriptPipelineService` para todas las etapas del pipeline, adición de `user_id` e índice `['user_id', 'created_at']` en `agent_request_logs` con imputación indeleble inmune a transferencias de proyectos, y auditoría resiliente por fases en `ScriptEditor`).
- ISSUE-016: sanitización de backreferences, lookarounds adaptativos en `pinned_terms` y creación interactiva en UI (sustitución literal con `preg_replace_callback` en PHP y lambda en Python, compatibilidad con términos simbólicos como `C++` o `#1`, límites de 50 términos y 80 chars, y método `addPinnedTerm()` con formulario en el Drawer lateral). Autenticación passwordless por email con OTP y detección de replay attacks, Panel de Administración `/admin` con control de roles `is_admin`, auditoría cuantitativa de obras con privacidad estricta, telemetría y costes de tokens en `agent_request_logs`, y catálogo interactivo de tarifas en `token_pricing_rates`.

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
  - Carga reactiva de archivos multimedia mediante Livewire (`WithFileUploads`) con validación estricta de extensiones (`mp4`, `mov`, `webm`, `m4v`) y derivación forzada de extensión según el tipo MIME real verificado por el servidor (mitigación CWE-434 / anti-RCE, [ISSUE-001]).
  - Almacenamiento particionado por UUID: `media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.{ext}` dentro del disco público de Laravel, con lista blanca inmutable en modelo (`Take::ALLOWED_EXTENSIONS`) y protección en Nginx (`location ^~ /storage/`) que prohíbe la ejecución de scripts PHP y añade `X-Content-Type-Options: nosniff`.
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
  - Servicio de renderizado y ensamblado continuo con FFmpeg (`RoughCutAssemblyService`), que concatena los archivos físicos de vídeo de las tomas aprobadas generando un único máster de pre-corte en `media/projects/{project_id}/renders/rough_cut_{uuid}.mp4` con reintento seguro con `libx264` ante incompatibilidades de formato, borrado garantizado de descriptores temporales en bloque `finally`, retención máxima de los 5 renders más recientes y supresión absoluta de falsos positivos ante fallos reales.

---

## 3. Mecanismo de Retención de Modificaciones Manuales (`user_locks`)

Para evitar que una regeneración cognitiva destruya las correcciones deliberadas introducidas por el cineasta, cada bloque de guión cuenta con una columna JSONB `user_locks`.

### 3.1. Estructura JSONB
```json
{
  "locked_fields": ["structured", "screenplay", "shots"],
  "manual_diffs": [
    {
      "stage": "screenplay",
      "original_ai": "Javier entra al bar.",
      "user_override": "Javier irrumpe en el local visiblemente alterado."
    }
  ],
  "archived_diffs": [
    {
      "stage": "structured",
      "original_ai": "Texto de borrador inicial",
      "user_override": "Texto corregido manualmente",
      "archived_at": "2026-10-18T20:15:00+02:00"
    }
  ],
  "pinned_terms": {
    "Javi": "Javier",
    "Bar Manolo": "BAR CENTRAL"
  }
}
```

### 3.2. Gobernanza y Control de Concurrencia (Pesimista y Optimista)
- **Control Optimista (`lock_version`):** Cada bloque en `script_blocks` posee una columna `lock_version` entera autoincremental. Al abrir la edición en el grid o drawer de `ScriptEditor`, se retiene la versión leída. Si un proceso concurrente o la IA modifica el bloque antes de pulsar guardar, la transacción rechaza la actualización y notifica al autor para evitar sobrescrituras perdidas o diffs manuales falsos.
- **Transaccionalidad Post-Inferencia:** Las llamadas a la IA corren fuera de transacciones DB para no retener conexiones del pool. La persistencia posterior en `ScriptPipelineService` re-lee el bloque con `lockForUpdate()`, comprueba si el usuario bloqueó la etapa concurrentemente (abortando la sobreescritura si así fue) y fusiona sobre los `user_locks` frescos de la base de datos, preservando intactos términos fijados, justificaciones y candados de otras etapas.
- **Mutación Atómica (`mutateUserLocks`):** Centraliza modificaciones atómicas sobre `user_locks` bajo transacción con bloqueo exclusivo de fila para `toggleStageLock`, `removePinnedTerm` y `savePlotHoleJustification`.
- **Archivado No Destructivo de Diffs (`archived_diffs`):** Al regenerar una etapa con IA, los `manual_diffs` activos de esa etapa se trasladan a `archived_diffs` con su marca de tiempo ISO-8601 (`archived_at`), manteniendo la trazabilidad histórica del autor y evitando su eliminación irreversible.
- **Protección de Desglose de Planos y Rodaje:** La etapa `shots` cuenta con soporte canónico de candado en `isStageLocked('shots')`. Tanto el pipeline como el editor abortan la regeneración si el candado está activo. Asimismo, los planos técnicos existentes que ya cuenten con tomas grabadas (`takes()->count() > 0`) quedan protegidos contra eliminación o alteración destructiva aunque la etapa no tenga candado activo.
- **Sustitución Anclada de Diffs:** `AgentClientService::applyUserLocks()` ejecuta sustitución posicional única con `substr_replace` anclada a la primera coincidencia exacta, evitando sustituciones colaterales masivas.
- **Aislamiento en Auditoría de Agujeros:** `analyzePlotHoles` y `clearPlotHoles` actualizan únicamente `plot_hole` y `plot_hole_stage` con `lockForUpdate()`, sin machacar la columna `user_locks` con colecciones en memoria desfasadas.

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
  - **Auditoría Determinista y Borrado Seguro:** Incorpora opción de eliminación segura (`requestDeleteCharacter` y `requestDeleteLocation`) con inspección determinista previa de todo el proyecto:
    - **Detección de Uso y Bloqueo Ineludible:** Si el personaje aparece en el texto de algún bloque (`screenplay_content`, `structured_text`, `raw_text`) o la localización está vinculada a una escena (`location_id`), figura en un slugline o en bloques de guión, el sistema **impide su borrado** y abre una ventana modal enumerando de forma exhaustiva cada ocurrencia (`ESC. {n} · BLOQUE {m}` o `ESC. {n} (Asignada a la escena)`).
    - **Confirmación para Entidades sin Uso:** Si el elemento no tiene ninguna ocurrencia en el guión, se despliega una ventana de confirmación antes de ejecutar su eliminación definitiva de la base de datos.

---

## 5. Arquitectura Técnica, Seguridad y Comunicación Inter-servicio

### 5.1. Stack Tecnológico
- **Framework:** Laravel 12 (v12.69.3), PHP 8.4.
- **Capa Reactiva:** Livewire 3/4 con subidas en streaming (`WithFileUploads`).
- **Diseño y Estilos:** Tailwind CSS v4 con selector dual de tema Claro/Oscuro y soporte `@custom-variant dark`.
- **Motor Multimedia:** Integración directa con FFmpeg (`ffmpeg` binary en PATH) y demuxer concat.
- **Aislamiento de Base de Datos en Testing:** `config/database.php` y `phpunit.xml` blindan la base de datos de desarrollo mediante `force="true"` y asignación estricta de `:memory:` cuando `APP_ENV === 'testing'`, garantizando que la ejecución de suites de prueba con `RefreshDatabase` jamás altere ni vacíe el archivo `database.sqlite` en el host o contenedor.

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
- `User.php`: Autenticación, plan, slots (`paid_project_slots`) y rol de administrador (`is_admin`).
- `OneTimePassword.php`: Código hash efímero OTP de acceso con validez de 15 minutos y seguimiento de un solo uso.
- `SecurityBreachLog.php`: Registro de auditoría y detección de ataques replay y tokens reutilizados/caducados.
- `TokenPricingRate.php`: Catálogo interactivo de tarifas por millón de tokens (Input / Output) de proveedores LLM.
- `AgentRequestLog.php`: Telemetría granular de consumo de tokens y coste en USD por petición y proyecto.
- `Project.php`: Contenedor de producción cinematográfica.
- `Character.php`: Entidad de Biblia de personajes.
- `Location.php`: Entidad de Biblia de localizaciones.
- `Scene.php`: Secuencia dramática con orden y slugline.
- `ScriptBlock.php`: Bloque de guión con etapas y `user_locks`.
- `Shot.php`: Plano técnico de rodaje con desglose de cámara.
- `Take.php`: Toma audiovisual con archivo físico y notas editoriales.

### 6.2. Servicios de Negocio (`app/Services/`)
- `ScriptPipelineService.php`: Orquesta las transiciones entre etapas narrativas y registra telemetría de costes.
- `AgentTelemetryService.php`: Extrae/estima tokens (1 token ~ 4 caracteres) y calcula el coste en USD cruzando con la tarifa activa.
- `UserLockResolver.php`: Resuelve candados, diffs y sustituciones de términos fijados.
- `RoughCutAssemblyService.php`: Ensamblado y renderizado de pre-corte con FFmpeg.
- `JgAgente/AgentClientService.php`: Cliente HTTP firmado con HMAC-SHA256 y fallback local.

### 6.3. Componentes Livewire (`app/Livewire/` & `resources/views/livewire/`)
- `Auth/Login.php` (`login.blade.php`): Acceso passwordless seguro con OTP y banner destacado en localhost.
- `Admin/UserList.php` (`user-list.blade.php`): Gobernanza de usuarios, slots, rol admin y gasto acumulado.
- `Admin/UserDetail.php` (`user-detail.blade.php`): Auditoría cuantitativa de obras con estricta privacidad de guiones.
- `Admin/PricingManager.php` (`pricing-manager.blade.php`): Catálogo interactivo de tarifas y modelo activo.
- `Admin/SecurityLogsViewer.php` (`security-logs-viewer.blade.php`): Visor de eventos de seguridad y replay attacks.
- `Projects/ProjectList.php` (`project-list.blade.php`): Panel de proyectos y cuotas de usuario.
- `Studio/ScriptEditor.php` (`script-editor.blade.php`): Editor continuo StoryArchitect con Gutenberg y candados.
- `Studio/RoughCutTimeline.php` (`rough-cut-timeline.blade.php`): Timeline NLE, reproductor y ensamble.
- `Studio/ContextBibleDrawer.php` (`context-bible-drawer.blade.php`): Drawer lateral (`Cmd+B`) para personajes y localizaciones.

### 6.4. Rutas Web (`routes/web.php`)
- `GET /login`: Formulario de acceso passwordless (`Login`).
- `POST /logout`: Cierre de sesión seguro y revocación de sesión.
- `GET /`: Redirección a `/projects`.
- `GET /projects`: Listado de proyectos cinematográficos (`ProjectList`).
- `GET /projects/{project}`: Estudio principal de rodaje y guión (`ScriptEditor`).
- `GET /admin`: Redirección al panel de administración (`admin.index` -> `admin.users`).
- `GET /admin/users`: Listado y control global de usuarios (`UserList`).
- `GET /admin/users/{user}`: Auditoría cuantitativa y privacidad del autor (`UserDetail`).
- `GET /admin/pricing`: Gestión de tarifas por millón de tokens (`PricingManager`).
- `GET /admin/security`: Visor forense de incidentes y brechas (`SecurityLogsViewer`).

### 6.5. Pruebas Automatizadas (`tests/Feature/`)
- `DomainModelArchitectureTest.php`: Verifica UUIDv4, integridad referencial y cascadas en BD (6 tests).
- `ScriptEditorLivewireTest.php`: Verifica reactividad del editor y drawer de biblia (4 tests).
- `AgentClientHmacTest.php`: Verifica firma canónica HMAC y llamadas a `jg_agente` (5 tests).
- `TakeMediaManagementTest.php`: Verifica subida física de vídeo, validaciones y tomas válidas (5 tests).
- `RoughCutAssemblyTest.php`: Verifica ordenación de timeline, renderizado con FFmpeg y fallbacks (4 tests).
- `LivewirePipelineReactiveTest.php`: Verifica flujo end-to-end entre etapas y candados (4 tests).
- `LivewireScriptEditorTest.php`: Verifica renderizado del slugline, feed de bloques, inserción Gutenberg, drawer lateral, tomas, auditoría determinista de borrado seguro de personajes y localizaciones, protocolo de 3 iteraciones de agujeros de guión, análisis multi-etapa independiente de consolidados (Idea, Tratamiento, Guion) con prioridad estricta al origen más temprano, menú desplegable con alternancia de visibilidad de avisos y justificación archivada (21 tests).
- `PasswordlessAuthTest.php`: Verifica generación y envío de OTP, banner en localhost, un solo uso y detección de replay attacks en `security_breach_logs` (6 tests).
- `AdminPanelAndTelemetryTest.php`: Verifica middleware `admin` 403, auditoría sin filtrar textos, persistencia de `agent_request_logs` con costes USD y actualización de tarifas (5 tests).
- `ProjectTransferAdminTest.php`: Verifica permisos 403, privacidad sin filtrar textos y reasignación/transferencia atómica de obras entre usuarios tanto desde `UserDetail` como desde `ProjectManager` (4 tests).
- `TokenQuotaEnforcementTest.php`: Verifica la gobernanza centralizada de cuotas diarias de tokens de IA, excepciones `QuotaExceededException` e imputación indeleble de costes (4 tests).
- `PinnedTermsSafetyTest.php`: Verifica la sanitización estricta de backreferences `$1` y escapes octales en términos fijos (3 tests).
- `ReDosMitigationTest.php`: Verifica la protección contra ataques ReDoS, cuantificadores acotados, fast-path de expresiones regulares y resiliencia ante diálogos residuales vacíos (4 tests).
- `ContractParityTest.php`: Verifica la paridad contractual bidireccional contra golden fixtures compartidos entre Laravel y el plugin satélite de Python (4 tests).
- **Blindaje Hermético de Testing (ISSUE-029):** Toda la suite de pruebas ejecuta con `Http::preventStrayRequests()` en `TestCase::setUp()`, forzando `services.jg_agente.mock = true` y `JG_AGENTE_URL=http://agent.invalid` en `phpunit.xml`, impidiendo llamadas no simuladas hacia el exterior o consumo accidental de tokens.
- **Total:** 148 tests PHPUnit pasando al 100% (708 aserciones).

---

## 7. Estado Actual y Deudas Técnicas / Puntos de Mejora

### 7.1. Estado Operativo Real
- La aplicación es plenamente funcional en local y desplegable mediante contenedor autónomo Docker (`http://127.0.0.1:8010`).
- Dispone de pipeline CI/CD en `.gitlab-ci.yml` con etapas de pruebas (`test:phpunit`) y construcción de contenedor Docker limpio sin ofuscación (`build:docker`).
- Soporta el ciclo completo desde la creación del proyecto hasta el renderizado de vídeo concatenado con FFmpeg.
- Dispone de interfaz adaptativa en tema Claro (blanco puro estilo `jg_hub`/`jg_dashboard`) y tema Oscuro con selector interactivo y persistencia en `localStorage`.
- **Normalización Infalible de `user_locks` (Fase 16):** Para blindar el motor ante errores `TypeError` por serialización anidada en SQLite o strings residuales (`"{}"`), `ScriptBlock` implementa un cast de atributo dinámico `userLocks(): Attribute` y `ScriptPipelineService::resolveUserLocks()` que garantizan en tiempo de ejecución que el parámetro siempre sea un `array` asociativo antes de interactuar con `AgentClientService`.
- **Feed Continuo de Lectura Cinematográfica (Fase 10):** Se eliminaron las cabeceras de bloque redundantes (`BLOQUE #N 1. Bruto...`) y los bordes pesados. El texto de la historia fluye como una lectura continua alineada con el logotipo (`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6`), con el botón `Detalles` flotando sutilmente a la derecha a la misma altura que la primera línea de texto.
- **Menú Superior Sticky Reordenado:** La barra de navegación se mantiene siempre visible (`sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-4 sm:px-6 lg:px-8`) al hacer scroll. Su orden estricto es **`Idea` > `Tratamiento` > `Guión` > `Planos / Tomas` > `🎬 Montaje`**. Al conmutar de etapa, el feed renderiza únicamente el campo correspondiente a dicha etapa en todos los bloques.
- **Inserción de Bloques Intermedios estilo Gutenberg (WordPress):** Entre cada par de bloques se ubica un divisor interactivo invisible por defecto (`opacity-0`) que solo al pasar el cursor por encima (`group-hover:opacity-100`) revela una línea naranja continua y un círculo naranja central (`w-8 h-8 rounded-full bg-amber-500 hover:scale-125 shadow-lg`) con el símbolo `+`. Al pulsarlo, se despliega el modal que solicita exclusivamente el texto bruto/idea original y reordena automáticamente la escena.
- **Selección Persistente de Bloques y Sincronización de Scroll:** Hacer clic en un bloque lo selecciona (`selectedBlockId`). Al conmutar de etapa en el menú sticky, el sistema mantiene el foco en el bloque seleccionado mediante auto-scroll reactivo continuo vía Livewire y Alpine.js.
- **Modal Lateral Derecho (Drawer al 60% Garantizado):** El drawer se implementa mediante un contenedor `fixed inset-y-0 right-0 z-50` con ancho estricto `style="width: 60vw; max-width: 60vw;"` sobre un backdrop suave independiente (`fixed inset-0 bg-slate-950/25 backdrop-blur-[2px]`). Permite consultar y editar todas las etapas de la escena, alternar tomas buenas, ejecutar acciones de IA y ver trazabilidad cognitiva sin expandirse a toda la pantalla.
- **Inferencia Cognitiva y Fallback Semántico:** Corrección de la inyección de API keys en Docker sin sobreescrituras vacías, y enriquecimiento del fallback semántico de `treatment_structuring` y `screenplay_formatter` para asegurar transformaciones dramáticas tangibles y vivas en lugar de copias literales.
- **Inferencia Cognitiva Real:** Integra llamada semántica en vivo al modelo `llama-3.3-70b-versatile` en Groq (o el modelo configurado en `LLM_MODEL`), resolviendo transformaciones narrativas complejas (estilo indirecto a diálogos canónicos Fountain con acotaciones y personajes en mayúsculas). En entorno de pruebas y CI/CD opera bajo aislamiento mock determinista estricto con consumo zero de tokens.
- **Limpieza de Interfaz y Navegación por Pestañas:** Cada bloque de guion renderiza una sola etapa a la vez. El cambio entre etapas se realiza mediante pestañas en la cabecera que muestran **únicamente aquellas etapas que hayan sido completadas** (`raw` siempre; `structured` si tiene tratamiento; `screenplay` si tiene guión; `shots` si tiene planos).
- **Botones de Acción Contextuales y Conmutación Automática:** En la cabecera de cada bloque de guion se renderiza de forma exclusiva el botón correspondiente a la etapa actualmente activa en la pestaña (`raw` ➔ `⚡ Estructurar`; `structured` ➔ `🎬 Convertir a Guión`; `screenplay` ➔ `📹 Desglosar Planos`; `shots` ➔ `📹 Desglosar Planos` / `+ Añadir Plano`). Al accionar dicho botón, el sistema procesa el siguiente paso con IA y conmuta de forma automática e inmediata la vista a dicha pestaña resultante.
- **Desglose Técnico de Planos y Tomas Embebido:** El desglose completo de planos (con sintaxis canónica `--- shot N (tipo) ---`), formularios de registro de tomas, control reactivo de tomas buenas/descartes, subida/reproducción de archivos de vídeo y anotaciones de dirección reside de forma natural dentro de la pestaña `Planos` de cada bloque, eliminando la duplicación inferior que saturaba la vista general de la escena.
- **Mini Menú de Usuario con Contador de Slots (Estilo jg_hub):** Integrado en el extremo superior derecho del layout con tarjeta de perfil desplegable vía Alpine.js, estado de plan PRO, selector de tema claro/oscuro y monitor interactivo de slots de proyectos usados frente a la cuota contratada (`X / Y slots usados`) con barra visual de capacidad.
- **Modal de la Biblia Contextual Estandarizado:** El modal de la Biblia de Proyecto se reestructuró para abandonar el panel lateral derecho y compartir **exactamente la misma geometría, proporciones fijas en los cuatro lados (`min(840px, 92vw)` x `min(630px, 86vh)`), centrado y scroll interno** que el resto de modales de la suite, con backdrop oscurecido unificado (`bg-black/90 backdrop-blur-md`).
- **Navegación de Etapas y Carga Inicial Canónica:** Al ingresar a un proyecto, todos los bloques cargan por defecto **siempre la primera pestaña (`raw`)**, permitiendo al autor avanzar secuencialmente a través del pipeline en lugar de saltar a etapas terminales.
- **Protección y Edición Contextual por Etapa:** Al activar el modo edición en un bloque, la interfaz renderiza **únicamente el textarea correspondiente a la etapa/step actualmente activo** (`raw` ➔ Bruto, `structured` ➔ Tratamiento, `screenplay` ➔ Guión Fountain, `shots` ➔ Planos técnicos con delimitadores) en lugar de amontonar todos los campos a la vez. Los botones de acción reflejan la etapa (`✓ Guardar Tratamiento` / `✏️ Editar Tratamiento`), y el cambio entre pestañas queda bloqueado a nivel reactivo y visual (`disabled`, `cursor-not-allowed`) hasta guardar o cancelar.
- **Gobernanza de Candados y Borrado de Términos Fijos:** La barra de candados permite eliminar interactivamente cualquier término fijo individual (`pinned_terms`) mediante un botón de borrado (`✕`), sincronizando al instante la base de datos y la telemetría.
- **Búsqueda Continua de Personajes y Localizaciones:** La inferencia dramática analiza continuamente tanto la Biblia de Proyecto como el texto libre del bloque (detectando interlocutores explícitos como Juan y entornos dramáticos).
- **Tipografía Homogénea del Ecosistema (Estilo jg_hub):** Unificación de todas las fuentes bajo `JetBrains Mono` e `Inter` con tamaño base de 13px y altura de línea 1.6, asegurando coherencia visual idéntica con la consola `jg_hub`. Los bloques de código y telemetría utilizan Highlight.js (`atom-one-dark`) con texto claro de alto contraste sobre fondo oscuro slate-950 / #0f172a.
- **Lectura Continua de Prosa y Drag & Drop de Bloques (Fase 11):** 
  - Ajuste ergonómico de márgenes y padding vertical (`py-1`, `space-y-0.5`, `px-3 py-1.5`) para conseguir una sensación genuina de lectura continua de novela o guión, eliminando espacios muertos entre párrafos. Los divisores Gutenberg intermedios adoptan altura mínima en reposo (`h-3 -my-0.5`), revelando la línea naranja y el botón `+` únicamente en hover sin forzar separaciones visuales.
  - Implementación de Drag and Drop nativo con Alpine.js mediante un manipulador táctil vertical discreto (`⋮⋮`) que aparece suavemente a la izquierda al hacer hover sobre cualquier bloque. Al arrastrar y soltar, `reorderBlock` reordena los bloques atómicamente dentro de una transacción `DB::transaction`, recalculando los `block_order` sin saltos ni duplicados.
  - Eliminación de roundtrips pesados en el Modal de Trazabilidad IA: la navegación interna entre pestañas (`⚡ Grafo & Saltos`, `💬 System Prompt & Entrada`, `📄 Salida Cinematográfica`, `{ } JSON Técnico`) pasa a gestionarse puramente en el cliente vía Alpine.js (`x-data="{ activeTab: 'steps' }"`, `x-show="activeTab === '...'"`), garantizando 0 ms de latencia, eliminando congelamientos de interfaz y erradicando el error `PayloadTooLargeException`, respaldado además por una ampliación de `payload.max_size` a 10MB en `config/livewire.php`.
- **Inferencia de Diálogos Fountain, Estilo Homogéneo y Pulido de Interfaz (Fase 12):**
  - **Inferencia de Habla Indirecta en `screenplay_formatter`:** El motor de guión literario ahora detecta automáticamente construcciones en habla indirecta (e.g., *"Juan le pregunta qué quien está llegando"*, *"Javier mira a Juan con miedo y le dice que ya están llegando"*), separando acciones descriptivas y generando parlamentos canónicos en formato Fountain (`PERSONAJE` en mayúsculas centrado y texto del diálogo/pregunta formateado: *"JUAN \n ¿Quién está llegando?"*).
  - **Cero Auto-Selección Inicial y Feedback de Hover:** Al cargar la escena o cambiar de escena, ningún bloque se pre-selecciona automáticamente (`selectedBlockId = null`). El bloque resalta con gris claro en hover (`hover:bg-slate-100 dark:hover:bg-slate-800/80`) y solo se activa la selección ámbar cuando el usuario hace clic deliberadamente.
  - **Rediseño del Botón Gutenberg:** El botón de inserción intermedia se amplió a un círculo perfecto de 36px (`w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-amber-500`) con sombra naranja vibrante y un icono vectorial SVG `+` nítido y centrado.
  - **Eliminación de Sangrías en `<pre>` y Bloques de Texto:** Se eliminaron todos los espacios y saltos de línea residuales entre el cierre del tag Blade y la variable, asegurando que todos los textos, prompts y JSONs comiencen alineados a la izquierda (`text-left`) y desde arriba sin descuadres visuales.
  - **Unificación de Tarjetas en Drawer Lateral:** Los textos de las etapas de Bruto (`raw`), Tratamiento (`structured`) y Guión (`screenplay`) comparten exactamente el mismo contenedor (`p-5 rounded-xl bg-indigo-50/20 border border-indigo-200/50`), la misma tipografía y márgenes homogéneos.
  - **Limpieza de Badges Innecesarios:** Eliminación del badge superfluo `200 OK` en la cabecera del modal de trazabilidad.
- **Modo StoryArchitect, Drag & Drop Ergonómico y Refinamiento Teatral Directo (Fase 14):**
  - **Organización de Escenas en Acordeón Vertical (Estilo StoryArchitect):** Se reemplazaron las pestañas horizontales de escenas por un listado continuo vertical donde todas las escenas aparecen una tras otra. Cada escena actúa como un acordeón desplegable/colapsable (expandidas todas por defecto para lectura fluida) que agrupa sus bloques respectivos, permitiendo leer el guión de extremo a extremo sin saltar de pantalla.
  - **Reordenación y Drag & Drop de Escenas:** Cada encabezado de escena cuenta con su tirador Drag & Drop nativo y soporta reordenación atómica `reorderScene` con transacciones de base de datos.
  - **Inserción Intermedia de Escenas (+ Gutenberg):** Entre escena y escena se sitúa una línea divisoria horizontal con botón circular `+ Nueva Escena Aquí` para crear escenas intermedias asignando el orden secuencial automáticamente.
  - **Tiradores Físicos en Hover y Drag & Drop de Fila Completa:** El tirador de arrastre se alinea con la primera línea de texto y permanece invisible en reposo (`opacity-0`), revelándose mediante Alpine.js al pasar el cursor (`isBlockHovered`) o cuando el bloque está seleccionado. Al iniciar el arrastre directamente desde el tirador, se asigna `setDragImage(blockEl)` sobre el contenedor completo, desplazando visualmente la fila entera como un bloque unificado tanto en escenas como en bloques.
  - **Divisores Gutenberg WordPress Reactivos con Alpine.js:** Se solventó el desfase de compilación CSS dotando a los divisores intermedios de una zona reactiva cómoda (`h-6` y `h-8`) gobernada por Alpine.js (`isPlusHovered`). Permanecen invisibles durante la lectura, pero al mover el ratón entre dos bloques o escenas, la línea ámbar y el botón circular `+` se revelan con suavidad de inmediato.
  - **Barra Superior Limpia con Título de Proyecto:** Se eliminó la etiqueta redundante "Modo Redacción · Story Architect" y el bloque "Escenas: 2...", reemplazándolo por el título institucional del proyecto (`$project->title`), el recuento de escenas y el botón de nueva escena al final del guión.
  - **Chevron Dinámico de Escena:** Se reemplazó la rotación CSS por dos SVGs con trazados vectoriales físicamente distintos (`M19 9l-7 7-7-7` hacia abajo cuando está expandida y `M9 5l7 7-7 7` hacia la derecha cuando está colapsada), garantizando que el icono cambie de forma al instante.
  - **Cabecera del Pipeline Integrada en Cada Escena:** El selector de etapas (`Idea | Tratamiento | Guión | Planos / Tomas | Montaje`) reside en el extremo derecho de la cabecera de cada escena, reduciendo el tamaño del slugline (`ESC. N · SLUGLINE`) al estilo minimalista y profesional de Story Architect.
  - **Eliminación Definitiva de Botones Superfluos de Bloque:** Eliminado cualquier botón residual `+ Bloque` de la cabecera de la escena, delegando la creación a los divisores Gutenberg.
  - **Limpieza de Slots en Cabecera:** Eliminado el texto de cuota residual bajo el nombre de usuario y suprimida la frase explicativa del menú dropdown.
  - **Refinamiento de Diálogos a Estilo Directo Teatral (Iteración Dramática):** Implementado el motor iterativo `refineDialogueToDirectSpeech` (en `AgentClientService.php` de Laravel y `formatting.py` de Python) que transforma automáticamente construcciones de tratamiento narrativo en 3ª persona subordinada (e.g. *"que no se ha comportado bien y tiene que volverse con él para recibir su castigo"*) en parlamentos directos canónicos de segunda/primera persona (e.g. *"No te has comportado bien. Tienes que volverte conmigo para recibir tu castigo."*). Soporta oraciones con gerundios de acción y habla indirecta compuesta (e.g. *"lo mira diciendo que ha llegado el momento de dejar de huir de sus actos. Que el que la hace la paga."* -> acción *"lo mira."* + parlamento *"Ha llegado el momento de dejar de huir de tus actos. El que la hace la paga."*) tanto en inferencia directa LLM (Groq, OpenAI, Gemini) como en fallback determinista robusto.
- **Control de Pipeline por Escena Individual y Pulido Gutenberg (Fase 15):**
  - **Pipeline Independiente por Escena:** El selector de etapas (`Idea | Tratamiento | Guión | Planos / Tomas | Montaje`) en la cabecera de cada escena opera de manera individual (`$scenePipelineStages[$scene->id]`), garantizando que conmutar la vista o etapa en una escena concreta no afecte a las demás escenas del guión.
  - **Divisor Gutenberg Superior (Previo a Escena 1):** Se retiró el botón `+ Nueva Escena` de la barra superior derecha y se habilitó un divisor interactivo horizontal previo a la Escena 1 (`openNewSceneModal(0)`). Al pulsarlo, crea una nueva escena en la primera posición (`scene_order = 1`) y desplaza el orden del resto atómicamente.
  - **Limpieza de Identidad en Barra de Navegación Principal:** En `resources/views/layouts/app.blade.php`, se eliminó el título del proyecto y el badge de estado que aparecían tras la barra `/` junto al logotipo institucional, dejando el branding limpio y despejado.
  - **Línea Horizontal Continua en Divisores Gutenberg y Hover Infalible de Bloques:** Se implementaron reglas CSS dedicadas e infalibles para `.script-block-item:not(.is-selected):hover` (fondo `#f1f5f9` en tema claro y `#1e293b` en tema oscuro) independizándolo del compilador JIT, y se fijó `.gutenberg-divider-line` con `height: 2px !important; background-color: #f59e0b !important; left: 0; right: 0;` activado tanto por Alpine como por CSS nativo `.gutenberg-container:hover`, garantizando que la línea ámbar sea 100% visible de lado a lado por debajo del botón central `+`.
- **Autenticación Passwordless, Panel de Administración y Telemetría de Costes (Fase 8 / 16):**
  - **Autenticación Passwordless por Email (Estilo jg_hub):** Flujo de acceso en `/login` donde el usuario introduce su correo y recibe un PIN OTP alfanumérico seguro (`XXXX-XXXX`) de 8 caracteres con validez de 15 minutos. El hash criptográfico se almacena en la tabla `one_time_passwords`.
  - **Modo Especial en Localhost / Desarrollo:** En entorno local (`app()->environment('local')` o conexiones desde localhost/127.0.0.1), la pantalla de verificación muestra un banner destacado con el código generado y un botón para autocompletar el campo sin requerir configuración SMTP ni revisar logs.
  - **Detección de Replay Attacks y Tokens Revocados:** Si un código introducido coincide con un registro donde `used_at IS NOT NULL` o `expires_at < now()`, el acceso se rechaza de inmediato y se registra el evento forense en `security_breach_logs` (IP, user agent, usuario, timestamp y motivo `REUSED_OR_EXPIRED_TOKEN`).
  - **Gobernanza y Roles de Usuario (`is_admin`):** Middleware `EnsureUserIsAdmin` protegiendo `/admin/*` con denegación 403 estricta. En `/admin/users` se ofrece la lista completa de usuarios, cuotas de slots, consumo de tokens, gasto estimado y toggle de permisos de administrador.
  - **Auditoría Cuantitativa con Privacidad Estricta:** En `/admin/users/{user}` se auditan las obras del autor de forma puramente cuantitativa (conteo de bloques por etapa `raw`, `structured`, `screenplay`, `shots` y consumo de IA) garantizando que ningún fragmento de texto dramático sensible sea cargado o expuesto en memoria.
  - **Catálogo de Tarifas y Telemetría de Costes (`token_pricing_rates` y `agent_request_logs`):** Cálculo automatizado de costes por petición cruzando con el modelo activo (Groq Llama 3.3 70B, Llama 3.1 8B, Gemini 1.5 Flash, Ollama). Gestión interactiva en `/admin/pricing` para editar tarifas de entrada/salida y activar el modelo vigente.
  - **Auditoría Determinista de Borrado en Biblia Contextual y Navegación a Detalle (Fase 17):**
  - **Eliminación Segura de Personajes y Localizaciones:** Auditoría previa exhaustiva de referencias en todas las escenas y bloques antes del borrado. Si el elemento está en uso, se bloquea la eliminación desglosando las ubicaciones exactas (`ESC. N · BLOQUE M`). Si no está en uso, se requiere confirmación explícita para la eliminación irreversible.
  - **Jerarquía y Apilamiento Estricto de Modales (`z-index: 100000`):** El modal de confirmación o bloqueo de borrado reside en capa superior independiente (`z-index: 100000`) por encima del modal de la Biblia (`z-index: 9999`), oscureciendo el fondo con backdrop propio. El evento `Esc` se gestiona selectivamente para cerrar primero la confirmación manteniendo abierta la Biblia.
  - **Navegación Directa al Detalle del Bloque en Uso (`inspectUsage`):** Las ubicaciones listadas en el diálogo de bloqueo son botones interactivos (`Ver detalle ➔`). Al pulsar sobre cualquiera de ellas, se cierran automáticamente el popup de confirmación y la Biblia, y se despacha el evento global `open-block-details` a `ScriptEditor`, abriendo de inmediato el drawer lateral de detalle de ese bloque específico para editarlo o desvincular el personaje/localización.
  - **Restauración y Compactación Ergonómica de Divisores Gutenberg:** Se ajustó la zona activa de los divisores intermedios (`py-0.5 my-0` en escenas y `py-1 my-0` en bloques) situándolos prácticamente pegados a los bloques y escenas contiguos sin espacios muertos, revelando la línea ámbar y los botones `+` con suavidad al pasar el cursor.
- **Formateo Story Architect, Autoría y Refinamientos de Cabecera (Fase 18):**
  - **Formateo Canónico de Diálogos Estilo Story Architect (`ScreenplayFormatterService`):** Implementación de analizador determinista Fountain a HTML que estructura los guiones literarios con la tipografía y geometría canónica de software profesional de guion (Story Architect / Final Draft):
    - Encabezados de escena (*sluglines*) en mayúsculas negrita con separador superior (`.screenplay-slugline`).
    - Nombres de personajes centrados, en mayúsculas y destacados (`.screenplay-character`).
    - Acotaciones dramáticas centradas entre paréntesis y en cursiva (`.screenplay-parenthetical`).
    - Parlamento y texto de diálogo en columna centrada de ancho controlado (`.screenplay-dialogue` con sangría canónica).
    - Acciones narrativas y descripciones de escena en ancho completo alineadas a la izquierda (`.screenplay-action`).
    - Transiciones técnicas alineadas a la derecha (`.screenplay-transition`).
  - **Enlace de Autoría y Despliegue (`films.javiergalvez.com`):** En la cabecera institucional junto al título "Pipeline Cinematográfico IA", se incorpora el crédito interactivo `hecho por <a href="https://javiergalvez.com">Javier Gálvez</a>`, desacoplado de la navegación interna del proyecto.
  - **Unificación Tipográfica de Pestañas de Pipeline:** Se eliminó el emoji `🎬` de la pestaña `Montaje`, unificando su estilo minimalista con `Idea | Tratamiento | Guión | Planos / Tomas | Montaje`.
  - **Identidad Completa de Usuario en Cabecera:** El mini-menú superior sustituye el nombre abreviado por el correo electrónico completo del usuario logueado (`auth()->user()->email`) junto al badge de nivel de suscripción y avatar.
- **Gobernanza de Modificaciones Manuales y Protección contra Sobreescritura (Fase 19):**
  - **Desactivación Instantánea (0ms) en Salto de Etapa:** En la barra de acciones del panel lateral de detalle del bloque, los botones de transformación (`structureBlock`, `formatBlockToScreenplay`, `breakdownBlockShots`) se deshabilitan inmediatamente al pulsarse mediante Alpine.js síncrono (`@click="isProcessing = true; $el.disabled = true; $el.classList.add('opacity-50', 'pointer-events-none')"`) y `wire:loading.delay.0ms.attr="disabled"`, eliminando cualquier retardo de red o doble clic accidental.
  - **Supresión de Trazabilidad en Texto Bruto:** Se eliminó el botón "Trazabilidad IA" en la pestaña de texto bruto (`raw`), manteniéndolo exclusivamente en etapas generadas por IA (`structured`, `screenplay`, `shots`).
  - **Marcado Visual y Gobernanza de Bloqueo (`user_locks`):** Cuando el autor modifica manualmente un texto generado por la IA (tratamiento o guión), se registra el diff en `manual_diffs` y se añade automáticamente la etapa a `locked_fields`.
  - **Bloqueo en Cascada y Protección contra Regeneraciones:** Si el guión literario (`screenplay`) está bloqueado, el tratamiento (`structured`) queda automáticamente protegido contra regeneraciones desde el texto bruto original (`isStageLocked('structured') === true`), impidiendo que una re-estructuración desde el bruto invalide o destruya la coherencia del guión protegido.
  - **Activación y Desbloqueo Interactivo (`toggleStageLock`):** La interfaz incluye distintivos visuales (`🔒 Modificado manualmente · Protegido`), avisos de advertencia contextuales y un botón interactivo para bloquear o desbloquear la etapa (`[🔓 Desbloquear]` / `[🔒 Proteger]`), otorgando al autor el control total de permitir o bloquear la intervención de la IA.
- **Menú Superior y Consolidado Continuo Story Architect (Fase 20):**
  - **Menú Sticky Alineado a la Derecha en la Barra de Proyecto:** Se incorpora un selector de vistas compacto en la barra superior sticky junto al título y contador de escenas con las opciones: **`Pipeline` - `Idea` - `Tratamiento` - `Guion` - `Planos/Tomas`** (el tab de montaje queda retirado temporalmente hasta finalizar el pulido de tomas).
  - **Alineación y Ancho Homogéneo (`max-w-7xl`):** El contenedor de la vista consolidada adopta exactamente la misma geometría y ancho que el pipeline (`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`), quedando perfectamente alineado con el logotipo institucional superior y los márgenes de la aplicación.
  - **Modo `Pipeline`:** Mantiene el listado de bloques interactivo actual estilo Gutenberg (acordeones por escena, arrastre Drag & Drop, divisores intermedios `+`, drawer lateral al 60% y modales de creación).
  - **Modo Consolidado Continuo (`Idea`, `Tratamiento`, `Guion`, `Planos/Tomas`):** Genera una vista de lectura continua de todo el proyecto en lienzo cinematográfico limpio al estilo de Story Architect:
    - Encabezado canónico de cada escena (`scene_order. slugline` y localización asociada).
    - Texto ordenado secuencialmente de cada bloque perteneciente a dicha escena:
      * **`Idea`:** Prosa original / texto bruto (`raw_text`).
      * **`Tratamiento`:** Párrafos dramáticos estructurados (`structured_text`).
      * **`Guion`:** Guión literario completo formateado canónicamente mediante `ScreenplayFormatterService::formatToHtml()`.
      * **`Planos/Tomas`:** Desglose técnico de planos de cámara (`shot_number`, tipo, descripción) y tomas registradas.
    - Cada bloque en el consolidado incluye un acceso flotante discreto (`✏️ Bloque #N`) que permite abrir directamente el panel lateral de edición sin perder el contexto de lectura.
    - Siguiente escena inmediatamente a continuación con separador de lectura continua.
    - **Nomenclatura Limpia de Consolidados:** Se eliminó el prefijo "Consolidado Story Architect", mostrando directamente el dato seguido de "CONSOLIDADO" (e.g. `TRATAMIENTO CONSOLIDADO`, `IDEA CONSOLIDADA`, `GUION CONSOLIDADO`, `PLANOS/TOMAS CONSOLIDADO`).
  - **Eliminación Total de Atajos en Biblia:** Se retiraron completamente los indicadores de atajo de teclado (`Cmd+B` / `Option+B`) del botón de la cabecera y del menú de usuario, dejando el acceso mediante clic limpio sin colisiones con el navegador.
- **Filtro de Agujeros de Guión y Reubicación de Herramientas de Proyecto (Fase 21):**
  - **Reubicación de Herramientas a Nivel de Proyecto:** Se eliminó el botón de la "Biblia de Proyecto" de la barra superior de la aplicación (`layouts/app.blade.php`) y se trasladó a la cabecera sticky del propio proyecto (`resources/views/livewire/studio/script-editor.blade.php`), situándolo a la misma altura que el título del proyecto y el contador de escenas.
  - **Herramienta "Filtro de Agujeros de Guión":** Se incorporó el botón interactivo `🕳️ Agujeros de Guión` en la cabecera del proyecto junto a la Biblia. Dispone de indicador de carga reactivo (`wire:loading`) y un badge pulsante con el total de agujeros detectados en el proyecto.
  - **Detección Dramática y Persistencia de Inconsistencias (`plot_hole`):**
    - Se añadió la columna `plot_hole` (text nullable) a la tabla `script_blocks` mediante migración (`2026_10_10_220001_add_plot_hole_to_script_blocks_table.php`).
    - `ScriptEditor::analyzePlotHoles` audita la secuencia cronológica de bloques del proyecto usando `AgentClientService::auditPlotHoles` (`plot_hole_detector`), cotejando personajes y localizaciones.
    - Marca los bloques afectados registrando el motivo exacto de la incongruencia dramática.
    - **Persistencia garantizada:** Las marcas no se eliminan al navegar, recargar o editar otros elementos; persisten en el bloque hasta que el autor vuelva a ejecutar el filtro de agujeros.
  - **Señalización Visual Multivista:**
    - **Pipeline Gutenberg:** Tarjeta de advertencia (`🕳️ Agujero de Guión Detectado`) con el motivo detallado bajo el texto del bloque, además del distintivo `🕳️ Agujero` junto a `Detalles`.
    - **Panel Lateral (Drawer al 60%):** Banner explicativo en la parte superior del cuerpo del drawer al inspeccionar el bloque.
    - **Consolidado Continuo:** Bloque de advertencia con el motivo bajo el texto del bloque en las vistas de lectura continua (`Idea`, `Tratamiento`, `Guion`, `Planos/Tomas`).
- **Protocolo Anti-Falsos Positivos de 3 Iteraciones, Justificación de Agujeros y Desbloqueo Reactivo de Pipeline (Fase 22):**
  - **Protocolo de 3 Iteraciones contra Falsos Positivos:** El detector de agujeros de guión (`plot_hole_detector`) ejecuta un procedimiento de triple verificación:
    1. *Iteración 1: Detección Preliminar:* Mapeo de inconsistencias cronológicas y dramáticas.
    2. *Iteración 2: Cotejo Literal de Evidencia Textual:* Cotejo riguroso entre el motivo imputado y las palabras literales del bloque analizado. Si el motivo menciona la presencia o acción de un personaje de la biblia (e.g., Pedro) y dicho personaje no figura textualmente en la escena o bloque, el agujero se descarta automáticamente como alucinación de la IA.
  - **Auditoría Multi-Etapa de Consolidados con Prioridad al Paso Más Temprano:**
    - Al accionar el botón de agujeros de guión, el sistema audita cada consolidado por separado en orden cronológico estricto:
      1. Paso 1: **Idea / Bruto** (`raw_text`).
      2. Paso 2: **Tratamiento** (`structured_text`).
      3. Paso 3: **Guion Literario** (`screenplay_content`).
    - **Regla del Paso Más Temprano:** Si una inconsistencia tiene su origen en el texto bruto, se marca y conserva como `Detectado en: Idea (Bruto)` aunque persista en el tratamiento o guion. Si nace en el tratamiento, se marca en `Tratamiento`. Y si aparece exclusivamente en el guión literario, se marca en `Guion`.
    - **Registro e Indicación de Etapa:** Se almacena en `user_locks['plot_hole_stage']` y se visualiza un badge distintivo (`Detectado en: Idea (Bruto)`, `Detectado en: Tratamiento` o `Detectado en: Guion`) en las alertas del Pipeline, del Consolidado continuo, del Drawer lateral y en el archivo de "Agujeros justificados". El mensaje flash desglosa el cómputo (e.g., *2 en Idea, 1 en Guion*).
  - **Menú Desplegable y Alternancia de Visibilidad de Avisos:**
    - El botón de agujeros en la cabecera del proyecto se convierte en un split-button con menú desplegable vía Alpine.js (`holeMenuOpen`).
    - Ofrece opciones interactivas:
      * `⚡ Ejecutar Análisis IA`: audita los consolidados de forma independiente.
      * `🔕 Desactivar avisos` / `🔔 Activar avisos` (`togglePlotHoleAlerts`): permite ocultar o mostrar las tarjetas y badges de inconsistencias en el guión mientras el autor lee o edita, sin borrar las marcas de la base de datos.
      * `🗑️ Borrar todas las marcas` (`clearPlotHoles`): limpia de un solo clic todas las inconsistencias registradas en el proyecto cuando se desee reiniciar la auditoría.
  - **Justificación y Retiro de Agujeros de Guión (`justified_plot_holes`):**
    - Se habilita un botón `✅ Justificar` en las tarjetas de alerta de agujero (Pipeline, Consolidado y Drawer).
    - Despliega un modal interactivo donde el usuario introduce una explicación de por qué no es una inconsistencia.
    - Al confirmar, el agujero se retira del bloque (`plot_hole` = null), se descuenta del contador global y se archiva en `user_locks['justified_plot_holes']` (almacenando motivo de la IA, texto del autor y fecha).
    - El panel lateral (Drawer al 60%) incluye la sección permanente **"Agujeros justificados"**, donde se consulta el historial de aclaraciones dramáticas del autor.
    - Las subsiguientes ejecuciones de la herramienta respetan las justificaciones y no vuelven a marcar incongruencias ya descartadas.
  - **Desbloqueo Reactivo en el Drawer ("Convertir a Guión"):**
    - Se aisló el estado `isProcessing` en Alpine.js con `wire:key` únicos por etapa y reseteo reactivo en el watch de la pestaña (`$watch('$wire.detailActiveTab', ...)`), eliminando el bloqueo que dejaba desactivado "Convertir a Guión" tras estructurar el tratamiento desde el bruto.
  - **Corrección de Detección de Modificaciones Manuales:**
    - `ScriptBlock::hasManualOverride` ahora verifica si el texto modificado manualmente (`user_override`) está realmente presente en el contenido actual (`structured_text` o `screenplay_content`), evitando avisos falsos por registros residuales.
    - Al regenerar limpiamente una etapa con IA (`transitionToStructured` o `transitionToScreenplay`), los diffs obsoletos se purgan automáticamente de `manual_diffs` y `locked_fields`.
- **Filtro Estricto Anti-Falsos Positivos de Nombres, Personajes en Mayúsculas, Menú Conmutable y Edición de Escenas (Fase 23):**
  - **Erradicación de Falsos Positivos Tipográficos y de Capitalización:**
    - Se descartó categóricamente cualquier reporte o alucinación del detector de agujeros que mencione diferencias de mayúsculas, minúsculas, formato o capitalización de nombres (e.g. *"El nombre de la muñeca aparece escrito como 'Estrella Casilda' en este bloque, diferente a 'ESTRELLA CASILDA' usado en el bloque del bosque, creando una inconsistencia de nombre entre escenas"*).
    - `AgentClientService::auditPlotHoles` implementa un filtro de expresión regular y cotejo de cadenas entre comillas: si se comparan dos cadenas idénticas al normalizar a minúsculas (`mb_strtolower($q1) === mb_strtolower($q2)`) o si el motivo alude a discrepancias de formato de nombre, se descarta automáticamente.
    - Los prompts de `plot_hole_detector` prohíben explícitamente a los modelos de lenguaje reportar variaciones de capitalización como inconsistencias dramáticas.
  - **Nombres de Personajes Estrictamente en MAYÚSCULAS en Toda Generación IA:**
    - `ScriptPipelineService::capitalizeCharacterNames` normaliza de manera terminante todos los nombres de los personajes de la biblia y contexto a **MAYÚSCULAS SOSTENIDAS** (`mb_strtoupper`, e.g. `ESTRELLA CASILDA`, `JUAN`, `PEDRO`) en todas las etapas generadas por la IA (tanto en tratamiento narrativo como en las acciones y diálogos del guion literario).
    - Los system prompts de `treatment_structuring` y `screenplay_formatter` instruyen estrictamente a los modelos a redactar los nombres propios en mayúsculas completas en cada mención.
  - **Menú Desplegable Conmutable en Botón Agujeros ("Ocultar / Ver" y "Borrar"):**
    - El menú desplegable conmuta dinámicamente en cualquier momento entre **`Ocultar`** (cuando las alertas están visibles) y **`Ver`** (cuando están ocultas), manteniendo un listado limpio de sólo dos opciones junto a **`Borrar`** (`clearPlotHoles`).
  - **Detección Automática de Roles Dramáticos y Sincronización en la Biblia de Proyecto:**
    - `ScriptPipelineService::inferCharactersFromText` y `syncProjectCharacters` ahora detectan no solo nombres propios aislados, sino también roles dramáticos y oficios (e.g. *"el camarero"*, *"camarero"*, *"policía"*, *"médico"*, *"guardia"*, etc.) y nombres entre comillas (e.g. *"Estrella Casilda"*).
    - Los personajes inferidos se sincronizan y registran automáticamente en la tabla `characters` del proyecto (`project->characters()`), garantizando que personajes que intervienen en una escena (como el Camarero en la Escena 2) formen parte permanente de la Biblia del proyecto y estén disponibles en todas las escenas posteriores (como la Escena 3).
  - **Edición y Renombrado de Escenas (`openEditSceneModal` / `updateScene`):**
    - Se incorporó la capacidad de cambiar el nombre y metadatos de las escenas cinematográficas:
      * Botón de acceso directo con icono de lápiz (✏️) en la cabecera de la escena tanto en la vista Pipeline Gutenberg como en la vista consolidada Story Architect.
      * Modal interactivo `showEditSceneModal` para modificar el encabezado canónico (*slugline*, e.g. `INT. BAR CENTRAL - NOCHE 1`), el momento del día (*time_of_day*) y la localización asociada de la Biblia.
      * Validación reactiva y persistencia inmediata en la tabla `scenes` con mensaje flash confirmatorio.
  - **Sincronización Bidireccional de Localizaciones y Biblia de Proyecto (Fase 24):**
    - **Deducción y Parsing Canónico de Sluglines (`parseSlugline`):**
      * Extrae robustamente tipo de entorno (`INT`, `EXT`, `INT/EXT`), nombre de localización (`BAR`, `CALLE PUERTA BAR`, `BOSQUE`) y momento del día (`NOCHE`, `DÍA`, `CONTINUO`) desde cualquier formato estándar de slugline cinematográfico.
    - **Sincronización y Creación Automática en Biblia (`syncProjectLocations`):**
      * Al entrar al editor (`ScriptEditor::mount`), al crear una escena (`createScene`), al editar un slugline (`updateScene`) o al transicionar bloques en el pipeline (`transitionToStructured` / `transitionToScreenplay`), el sistema audita las escenas del proyecto.
      * Si una escena incluye una nueva localización no registrada en la Biblia (e.g. `EXT. CALLE PUERTA BAR - NOCHE 1`), se crea inmediatamente la entidad en la tabla `locations` con su tipo deducido (`EXT`) y se asocia a la escena (`location_id`).
      * Si el usuario renombra el slugline de una escena modificando la localización (e.g. de `BAR CENTRAL` a `BAR`), el sistema detecta el cambio, renombra la localización en la Biblia si era exclusiva de esa escena y actualiza la relación, manteniendo la coherencia sin obligar al usuario a crearlas manualmente en la Biblia.
    - **Actualización Reactiva de la Biblia (`bible-updated`):**
      * Emite el evento `bible-updated` al modificar o crear escenas, permitiendo que el panel lateral `ContextBibleDrawer` refresque de inmediato las entidades de personajes y localizaciones sin recarga de página.
  - **Borrado Seguro con Trazabilidad Histórica y Telemetría Diaria de Tokens (Fase 25):**
    - **Tabla de Auditoría y Trazabilidad Histórica (`user_activity_logs`):**
      * Almacena registros permanentes en PostgreSQL de acciones críticas del cliente (`delete_block`, `delete_scene`), capturando `user_id`, `project_id`, `action`, `entity_type`, `entity_id`, descripción legible y un `snapshot` en JSON con el contenido completo previo para auditoría histórica irreversible.
    - **Borrado Controlado de Bloques (`openDeleteBlockModal` / `confirmDeleteBlock`):**
      * Modal de advertencia explícita informando de que los datos no podrán ser recuperados.
      * Al confirmar: persiste en `user_activity_logs`, elimina el bloque, reordena los bloques restantes de la escena secuencialmente (`block_order`), sincroniza la Biblia y refresca los agujeros de guión.
    - **Regla Estricta para Borrado de Escenas (`openDeleteSceneModal` / `confirmDeleteScene`):**
      * Para eliminar una escena, primero debe quedar completamente vacía de bloques.
      * Si la escena contiene bloques (`scriptBlocks()->count() > 0`), el modal bloquea la eliminación y advierte: *"Para borrar una escena, primero no debe haber ningún bloque en ella. Elimina primero sus bloques"*.
      * Si la escena no tiene bloques, despliega la confirmación irreversible, guarda la trazabilidad en `user_activity_logs`, reordena las escenas restantes del proyecto (`scene_order`) y sincroniza la Biblia.
    - **Telemetría y Contabilidad de Tokens en Agujeros de Guión:**
      * Cada ejecución de `analyzePlotHoles` registra su telemetría real en `agent_request_logs` con `tool_name = 'plot_hole_detector'`, cruzando tokens de entrada, salida y coste estimado en USD visible en `/admin/users` y `/admin/projects`.
    - **Contador Diario de Tokens Disponibles (`daily_token_limit`):**
      * Atributo `daily_token_limit` en `users` (100.000 tokens por defecto).
      * Cálculo dinámico de tokens consumidos en el día en curso (`created_at >= today()->startOfDay()`), garantizando reseteo automático a las 00:00 cada medianoche sin necesidad de tareas de mantenimiento.
      * Indicador interactivo en la cabecera superior del editor de guión mostrando tokens disponibles hoy frente al cupo diario (`⚡ X / Y tokens`).
      * Tarjeta de monitorización diaria de cuota en el panel de administración (`/admin/users/{user}`).
  - **Trazabilidad Global del Usuario y Modal de Confirmación de Tokens (Fase 26):**
    - **Trazabilidad Exhaustiva de Todas las Acciones del Usuario (`logActivity`):**
      * La trazabilidad histórica en `user_activity_logs` se extendió a todas las acciones operativas del usuario sobre la obra:
        - Creación y edición de escenas (`create_scene`, `update_scene`).
        - Reordenación secuencial de escenas y bloques (`reorder_scene`, `reorder_block`).
        - Creación y edición manual de bloques (`create_block`, `update_block`).
        - Borrado definitivo de bloques y escenas (`delete_block`, `delete_scene`).
        - Transformaciones cognitivas con IA a tratamiento (`structure_block_ai`) y a guion (`format_screenplay_ai`).
        - Alternancia de candados de protección de etapa (`toggle_stage_lock`).
        - Justificación y archivo de inconsistencias dramáticas (`justify_plot_hole`).
        - Limpieza de marcas (`clear_plot_holes`) y ejecuciones de auditoría (`analyze_plot_holes`).
      * Cada evento captura un snapshot JSON estructurado que congela el estado exacto del fragmento en ese instante.
    - **Modal de Historial de Actividad en Orden Cronológico Inverso (`openActivityHistoryModal`):**
      * Botón `📜 Historial` en la barra superior sticky del editor.
      * Modal interactivo que renderiza los eventos en orden cronológico inverso (`latest()`), con insignia de acción tipificada por colores, fecha relativa y absoluta, usuario autor y acordeón colapsable con el snapshot JSON estructurado.
    - **Confirmación Previa de Coste en Tokens para Agujeros de Guión (`promptAnalyzePlotHoles`):**
      * Al hacer clic en el botón principal de "Agujeros", el sistema ya no dispara la petición directamente a ciegas.
      * Calcula una estimación heurística del volumen de tokens requerido en función del contenido textual de los bloques activos y el contexto de la Biblia.
      * Despliega un modal informativo `showPlotHoleConfirmModal` con el desglose de bloques a auditar, coste estimado en tokens, saldo diario disponible y saldo restante previsto tras la ejecución.
      * Ofrece al autor la decisión soberana de "Cancelar" o "Confirmar y Auditar", protegiéndolo de consumos inesperados o agotamiento prematuro de su cuota.
    - **Roadmap para "Evolución de la Historia" (`docs/TODO_HISTORIA_EVOLUCION.md`):**
      * Hoja de ruta técnica que define el aprovechamiento analítico de los snapshots de `user_activity_logs` para construir un visualizador de ramificación dramática, métricas de transformación del texto bruto a guion final y análisis retrospectivo de la obra.
  - **Optimización Responsive Móvil y Visor Enriquecido de Snapshots (Fase 27):**
    - **Enriquecimiento Estructural de Snapshots (`ScriptEditor.php`):**
      * Los snapshots de eventos ahora registran datos completos y legibles en vez de identificadores huérfanos:
        - `analyze_plot_holes`: desglose por etapas y array detallado de inconsistencias (`detected_holes`) con `scene_order`, `scene_slugline`, `block_order`, `stage`, `stage_label`, `reason` y previsualización textual del bloque.
        - `saveBlock`, `structureBlock`, `formatBlockToScreenplay`: capturan `scene_id`, `scene_order`, `scene_slugline`, `block_order`, `stage`, textos completos de Idea, Tratamiento y Guion.
        - `confirmDeleteBlock`: registra la escena y orden exacto del fragmento eliminado.
        - `savePlotHoleJustification`: registra la inconsistencia detectada, la etapa y la justificación explicativa redactada por el autor.
    - **Visor Dual de Snapshots y Desglose Directo de Inconsistencias en Historial:**
      * **Detección Directa en Tarjetas de Auditoría:** Al auditar agujeros (`analyze_plot_holes`), la tarjeta del log en el historial renderiza directamente un bloque con el desglose de todas las inconsistencias detectadas (escena, bloque, etapa, fallo narrativo y previsualización textual).
      * **Modo JSON con Texto Negro:** Selector de snapshot con bloque `<pre>` en texto negro puro (`#000000 !important; font-weight: 500;`) sobre fondo suave (`bg-slate-100`), garantizando lectura inmediata y eliminando cualquier estilización verde o fondo oscuro ilegible.
    - **Restauración de Cabecera y Estética Minimalista del Editor:**
      * **Cabecera Horizontal Limpia:** Preservación estricta del layout horizontal con título de proyecto, escenas, Biblia, Agujeros e Historial a la izquierda y el pipeline a la derecha, sin colapsos ni apilamientos verticales indebidos.
      * **Lienzo de Escritura Despejado:** Botón "Detalles" discreto y visible exclusivamente en hover (`opacity-0 group-hover:opacity-100`), retirando elementos flotantes que interferían con la lectura de los bloques de guion.
  - **Reubicación de Tokens a la Barra de Usuario y Blindaje CI/CD (Fase 28):**
    - **Reubicación de Tokens Diarios de IA a la Barra Superior de Usuario:**
      * Se retiró el contador de tokens de la barra contextual de herramientas del proyecto (`script-editor.blade.php`), dado que los tokens diarios son un recurso asignado al usuario y no una propiedad del proyecto individual.
      * Se ubicó la píldora de tokens diarios (`⚡ X / Y tokens`) en la barra superior global de navegación (`resources/views/layouts/app.blade.php`), exactamente entre el selector de tema (claro/oscuro) y el desplegable del perfil de usuario (con su email).
      * Se enriqueció el menú desplegable del usuario con una tarjeta dedicada a los tokens diarios de IA, mostrando saldo disponible, límite diario, consumidos hoy y barra de progreso dinámica con colores según disponibilidad.
    - **Resolución y Blindaje contra `ViteManifestNotFoundException` en GitLab CI:**
      * Causa raíz: En el runner de GitLab CI (`composer:2`), no se ejecutan compilaciones de NodeJS/Vite previas a la ejecución de PHPUnit, por lo que `public/build/manifest.json` no existía al evaluar `@vite(...)` en tests HTTP completos.
      * Solución canónica: Incorporación de `$this->withoutVite()` en el método `setUp()` de `tests/TestCase.php`.
      * Salvaguarda en pipeline: Creación de dummy manifest `public/build/manifest.json` en `before_script` de `.gitlab-ci.yml` para el stage de test.
      * Verificación: Ejecución exitosa de los 78 tests unitarios y de integración con y sin directorio de build.
  - **Despliegue Autónomo en Producción en la VM de OVH (Fase 29):**
    - **Servidor y Ruta de Producción:** Desplegado en la máquina virtual OVH (`ubuntu@webdev.cabocom.com`) bajo `/cabocom/web/apps/films.javiergalvez.com`.
    - **Dominio Público y Certificado SSL:** Operativo en `https://films.javiergalvez.com` bajo proxy inverso Nginx con certificado SSL activo de Let's Encrypt y redirección automática 80 -> 443. Soporte para cargas de vídeo hasta 500MB (`client_max_body_size 500M`) y WebSockets.
    - **Arquitectura y Desacoplamiento de Servicios:**
      * `jg_filmmaker` (`registry.cabocom.net/javiergalvez-ia/jg_filmmaker:latest`): Contenedor web Laravel Livewire escuchando en `127.0.0.1:8924` (expuesto al host vía Nginx) operando sobre motor PostgreSQL 16 (`DB_CONNECTION=pgsql`, `DB_HOST=jg_filmmaker_postgres`, `DB_DATABASE=jg_filmmaker`).
      * `jg_filmmaker_postgres` (`postgres:16-alpine`): Base de datos relacional PostgreSQL 16 dedicada para la plataforma cinematográfica, permitiendo escalabilidad y almacenamiento de textos extensos de guion con campos nativos `jsonb`.
      * `jg_agente_filmmaker` (`registry.cabocom.net/javiergalvez-ia/jg_agente:latest`): Execution Plane autónomo con el plugin `jg_plugin_filmmaker` montado localmente en `/app/plugins/jg_plugin_filmmaker:ro`. Capacidades verificadas en boot: 3 herramientas cinematográficas (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector`). Operando sin Dashboard (`DASHBOARD_URL=""`, `ENABLE_HEARTBEAT=false`).
      * `jg_agente_postgres_filmmaker` (`postgres:16-alpine`): Base de datos relacional para LangGraph checkpointer del agente (`jg_agente_filmmaker`).
      * Red interna compartida: `jg_network` (externa bridge).
    - **Persistencia Física Estricta en Disco:**
      * Base de datos PostgreSQL de filmmaker persistida físicamente en `/cabocom/web/apps/films.javiergalvez.com/data/postgres_filmmaker/`.
      * Base de datos PostgreSQL del agente persistida físicamente en `/cabocom/web/apps/films.javiergalvez.com/data/postgres_agente/`.
      * Storage de medios y tomas persistido en `/cabocom/web/apps/films.javiergalvez.com/data/storage/`.
      * Datos y estado del agente persistidos en `/cabocom/web/apps/films.javiergalvez.com/data/agent_data/`.
    - **Usuario Administrador y Datos Iniciales:**
      * Administrador inicial configurado en `jgalvez@cabocom.com` con privilegios `is_admin = true` y plan `pro`.
      * Obra cinematográfica de demostración *El Susurro del Viento* sembrada y asociada a dicho usuario.
    - **Script de Despliegue Automatizado (`deploy.sh`):**
      * Creado e integrado `deploy.sh` en la raíz de `/cabocom/web/apps/films.javiergalvez.com` (y versionado en el repositorio), replicando la convención de `/cabocom/ia/javiergalvez-ia/deploy.sh` con 5 fases automáticas: pull de imágenes, arranque con `--remove-orphans`, sondeo de salud (healthcheck con timeout), migraciones (Alembic y Laravel en PostgreSQL), purga y precalentamiento de cachés, y reporte final de endpoints.
    - **Proxy de Depuración y Desarrollo Remoto (`films.cabocom.net`):**
      * Configurada regla de Nginx en la VM (`/etc/nginx/sites-available/8010-films.cabocom.net` y enlace simbólico en `sites-enabled`) enrutando `https://films.cabocom.net` con SSL activo hacia la máquina local de desarrollo por la VPN (`http://10.8.0.8:8010`).
      * Habilita pruebas en tiempo real desde dispositivos móviles sin necesidad de redesplegar imágenes en producción ni saltarse el aislamiento de desarrollo.
    - **Optimización de Interfaz Móvil y Responsiva:**
      * Topbar global (`layouts/app.blade.php`): Logo compacto, título y subtítulo unificados en una sola línea continua fluida sin columnas flex intermedias (`Pipeline Cinematográfico IA hecho por Javier Gálvez`), selector de tema y tokens ajustados para eliminar overflow en viewport móvil.
      * Estudio y Editor de Guion (`script-editor.blade.php`): Barra superior del proyecto unificada en una sola fila horizontal fluida (`overflow-x-auto no-scrollbar`), selector de etapas contraído en un único botón con menú desplegable activo, y botones de estado/detalles (`🔒 Protegido`, `🕳️ Agujero`, `📑 Detalles`) posicionados de forma flotante (`absolute top-1.5 right-2`) en cada bloque para que el texto aproveche el 100% del ancho sin apelotonarse.
      * Listado de Proyectos (`project-list.blade.php`): Encabezado con `flex-wrap` y botón `shrink-0`.
    - **Gestión Segura de Secretos y Configuración:**
      * Variables inyectadas de forma unificada desde Vault (`APP_KEY`, `JG_AGENTE_SECRET`, `GLOBAL_GROQ_API_KEY`, `GLOBAL_GOOGLE_API_KEY`, `GLOBAL_MAIL_*`).
      * Archivo `.env` en la VM protegido con permisos `600`.

### 7.2. Deudas Técnicas Conocidas
1. **Actualización Dinámica de Tarifas de Tokens:**
   - Actualmente las tarifas se configuran manualmente desde el panel de administración (`/admin/pricing`) o vía seeder inicial (`TokenPricingRatesSeeder`).
   - *Mejora pendiente:* Implementar un sincronizador periódico automatizado (comando / job en segundo plano) contra las APIs de facturación o catálogos públicos de proveedores externos (Groq, OpenRouter, Google AI, OpenAI) para mantener los precios por millón de tokens actualizados en tiempo real.
2. **Motor de Ensamblado FFmpeg:**
   - La concatenación actual utiliza el demuxer `concat` (`-f concat -safe 0 -c copy`), asumiendo que los clips de vídeo subidos comparten códec, contenedor y resolución.
   - *Mejora pendiente:* Diseñar un pipeline de transcodificación intermedia normalizada (e.g. H.264 1080p a 24fps unificado) para evitar inconsistencias cuando los rodajes mezclen cámaras con tasas de fotogramas o formatos heterogéneos.
3. **Formatos de Exportación NLE:**
   - Actualmente exporta la lista de corte secuencial en formato JSON estructurado.
   - *Mejora pendiente:* Desarrollar exportadores a formatos estándar de la industria como FCPXML (Final Cut Pro / DaVinci Resolve) y CMX 3600 EDL para interoperabilidad con salas de etalonaje y montaje profesional.
4. **Almacenamiento Distribuido en la Nube:**
   - Los vídeos se almacenan en el sistema de archivos local (`storage/app/public/media/...`).
   - *Mejora pendiente:* Desacoplar el driver de almacenamiento para soportar proveedores compatibles con S3 (AWS S3, MinIO, Cloudflare R2) para despliegues en clúster y alta concurrencia.
