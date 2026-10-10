# CONTEXTO DE PROYECTO: jg_filmmaker

**Versión del documento:** 3.0.0  
**Fecha de consolidación:** 10 de octubre de 2026  
**Componente:** Plataforma Web de Pipeline Cinematográfico Asistido por IA  
**Stack Tecnológico:** Laravel 12 (v12.69.3), Livewire 3/4, Tailwind CSS v4, PHP 8.4, PostgreSQL / SQLite, FFmpeg, PHPUnit 12, Laravel Pint  
**Estado:** Fases 1 a 16 implementadas al 100% (Autenticación passwordless por email idéntica a jg_hub con OTP en Localhost y detección de replay attacks, Panel de Administración `/admin` con control de roles `is_admin`, auditoría cuantitativa de obras con privacidad estricta, telemetría y costes de tokens en `agent_request_logs`, y catálogo interactivo de tarifas en `token_pricing_rates`).

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
- `LivewireScriptEditorTest.php`: Verifica renderizado del slugline, feed de bloques, inserción Gutenberg, drawer lateral, tomas, auditoría determinista de borrado seguro de personajes y localizaciones, protocolo de 3 iteraciones de agujeros de guión, justificación de inconsistencias archivadas y limpieza de manual overrides al regenerar (19 tests).
- `PasswordlessAuthTest.php`: Verifica generación y envío de OTP, banner en localhost, un solo uso y detección de replay attacks en `security_breach_logs` (6 tests).
- `AdminPanelAndTelemetryTest.php`: Verifica middleware `admin` 403, auditoría sin filtrar textos, persistencia de `agent_request_logs` con costes USD y actualización de tarifas (5 tests).
- `ProjectTransferAdminTest.php`: Verifica permisos 403, privacidad sin filtrar textos y reasignación/transferencia atómica de obras entre usuarios tanto desde `UserDetail` como desde `ProjectManager` (4 tests).
- `ScriptPipelineIntegrationTest.php`: Verifica transiciones entre etapas, inyección semántica de biblia de proyecto y normalización estricta de `user_locks` ante valores serializados o malformados (6 tests).
- **Total:** 64 tests PHPUnit pasando al 100% (394 aserciones).

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
    3. *Iteración 3: Consenso Crítico y Validación Dramática Final:* Validación de causalidad narrativa antes de confirmar la alerta.
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
