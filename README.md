> ⚠️ **Nota:** Este repositorio es un *mirror* público sincronizado automáticamente desde [GitLab](https://gitlab.com/javiergalvez-ia). El desarrollo activo, pipelines CI/CD, *issues* y discusiones técnicas se gestionan en https://gitlab.com/javiergalvez-ia

<div align="center">

# 🎬 `jg_filmmaker`

### Suite Creativa y Pipeline Cinematográfico Asistido por Inteligencia Artificial
*Desde la Idea Bruta hasta el Guion Literario, Desglose Técnico, Locución Dramática y Montaje NLE*

[![GitLab CI/CD](https://img.shields.io/badge/GitLab_CI-Pipeline_Passing-108548?style=flat-square&logo=gitlab&logoColor=white)](https://gitlab.com/javiergalvez-ia/jg_filmmaker)
[![Mirror to GitHub](https://img.shields.io/badge/GitHub-Mirror_Active-181717?style=flat-square&logo=github&logoColor=white)](https://github.com/cabocom/jg_filmmaker)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![Livewire](https://img.shields.io/badge/Reactive-Livewire_4-FB70A9?style=flat-square&logo=livewire&logoColor=white)](https://livewire.laravel.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Audio & NLE](https://img.shields.io/badge/FFmpeg-libmp3lame-007808?style=flat-square&logo=ffmpeg&logoColor=white)](https://ffmpeg.org/)
[![TTS Engine](https://img.shields.io/badge/TTS-Google_Gemini_%2B_Speaches-blueviolet?style=flat-square)](#4-capacidades-principales)
[![Pint](https://img.shields.io/badge/Code_Style-Laravel_Pint-success?style=flat-square)](https://laravel.com/docs/pint)

</div>

---

## 1. Visión General y Propósito

`jg_filmmaker` es la plataforma web de creación y producción cinematográfica asistida por Inteligencia Artificial del ecosistema **`javiergalvez-ia`**. Diseñada sobre [Laravel 12](https://laravel.com/), [Livewire 4](https://livewire.laravel.com/) y [Tailwind CSS v4](https://tailwindcss.com/), cubre integralmente el ciclo de vida de una obra audiovisual:

$$\text{Idea / Bruto} \longrightarrow \text{Tratamiento} \longrightarrow \text{Guión Literario} \longrightarrow \text{Planos Técnicos} \longrightarrow \text{Rodaje / Tomas} \longrightarrow \text{Montaje / Pre-corte NLE}$$

A diferencia de editores de guion convencionales o generadores de texto aislados, `jg_filmmaker` proporciona un **entorno de estudio integral** estructurado en torno a cinco pilares arquitectónicos:

1. **Escritura Continua sin Fricción (Modo StoryArchitect):** Acordeón vertical reactivo de escenas continuas con reordenación atómica mediante Drag & Drop físico, divisores Gutenberg reactivos y edición inline sin saltos de contexto.
2. **Locución Dramática Neural Multivoz (TTS):** Síntesis de voz con Google AI Studio (Gemini Flash TTS) y failover a Speaches-AI (Piper), asignación automática de voces por personaje en la Biblia, streaming gapless en cliente, pre-warming asíncrono y exportación a **MP3 de alta fidelidad** mediante FFmpeg.
3. **Gobernanza Estricta de Candados (`user_locks`):** Protección absoluta de modificaciones humanas y términos fijos (`pinned_terms`) frente a re-ejecuciones de IA y transformaciones en lote.
4. **Montaje NLE y Ensamble Audiovisual:** Gestión de tomas de rodaje, aprobación de tomas buenas (`is_good_take`), streaming seguro de vídeo privado y ensamble físico automatizado con FFmpeg (`RoughCutAssemblyService`).
5. **Interconexión Segura con el Ecosistema Cognitivo:** Comunicación asíncrona mediante peticiones firmadas con **HMAC-SHA256** con el runtime `jg_agente` a través del plugin especializado `jg_plugin_filmmaker`.

### Documentación Técnica Modular
Estructurada bajo [`docs/`](docs/README.md):
- 🇪🇸 **Español (Fuente Canónica / SSOT):**
  - [01. Arquitectura General del Sistema](docs/es/01_architecture.md) — Capas del sistema, topología de servicios y aislamiento.
  - [02. Pipeline Cinematográfico en 6 Etapas](docs/es/02_pipeline_stages.md) — Flujo de transformación de texto bruto a planos y montaje.
  - [03. Gobernanza de Candados (`user_locks`)](docs/es/03_user_locks_governance.md) — Inmutabilidad de texto manual y términos protegidos.
  - [04. Biblia Contextual de Proyecto](docs/es/04_context_bible.md) — Personajes, voces asignadas, localizaciones y atrezzo.
  - [05. Integración Inter-Servicio HMAC-SHA256](docs/es/05_hmac_agent_integration.md) — Protocolo criptográfico con `jg_plugin_filmmaker`.
  - [06. Gestión de Medios y Ensamble FFmpeg](docs/es/06_media_takes_ffmpeg.md) — Pipeline de transcodificación, tomas y ensamble de vídeo.
  - [07. Estrategia de Testing y CI/CD](docs/es/07_testing_quality_cicd.md) — Cobertura automatizada, suite PHPUnit y Pint.
  - [08. Autenticación Passwordless, Panel de Administración y Telemetría](docs/es/08_auth_admin_telemetry.md) — PIN OTP, cuotas de tokens y auditoría.

---

## 2. Arquitectura del Sistema y Flujo de Datos

```text
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      NAVEGADOR / GUIONISTA (Web UI)                    │
 │                                                                        │
 │   ┌──────────────────────┐  ┌──────────────────┐  ┌────────────────┐   │
 │   │  Editor Continuo     │  │  Reproductor TTS │  │ Biblia Actores │   │
 │   │  (StoryArchitect UI) │  │  (Doble Búfer)   │  │ (Cmd+B Modal)  │   │
 │   └──────────┬───────────┘  └────────┬─────────┘  └───────┬────────┘   │
 └──────────────┼───────────────────────┼────────────────────┼────────────┘
                │ Livewire 4 / HTTPS    │ Audio Streaming    │ REST / JSON
                ▼                       ▼                    ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                     jg_filmmaker (Laravel 12 Backend)                  │
 │                                                                        │
 │  ┌──────────────────────────────────────────────────────────────────┐  │
 │  │                      Servicios de Aplicación                     │  │
 │  │  - ScriptPipelineService (Heurística Teatral y Conversión)       │  │
 │  │  - ScriptExportService (PDF Cinematográfico Estándar)            │  │
 │  │  - ScriptImportService (Ingesta Heurística de PDF/TXT)           │  │
 │  │  - TextToSpeechService (Síntesis Neural, Caché SHA256 y MP3)     │  │
 │  │  - RoughCutAssemblyService (Ensamble y Transcodificación FFmpeg) │  │
 │  │  - AgentClientService (Cliente HTTP HMAC-SHA256)                 │  │
 │  │  - AgentTelemetryService (Auditoría de Tokens y Costes USD)      │  │
 │  └──────┬──────────────────────┬───────────────────────┬────────────┘  │
 │         │                      │                       │               │
 │         ▼                      ▼                       ▼               │
 │  ┌──────────────┐     ┌──────────────────┐     ┌────────────────┐      │
 │  │  PostgreSQL  │     │ SSD NVMe Local   │     │ Queue Worker   │      │
 │  │  (Relacional │     │ (tts_cache /     │     │ (Prewarm TTS   │      │
 │  │   UUIDv4)    │     │  ffmpeg temp)    │     │  Background)   │      │
 │  └──────────────┘     └────────┬─────────┘     └────────────────┘      │
 └────────────────────────────────┼───────────────────────────────────────┘
                                  │
         ┌────────────────────────┴───────────────────────┐
         │                                                │
         ▼ (HTTP Inferencia)                              ▼ (HTTP HMAC-SHA256)
 ┌───────────────────────────────┐               ┌────────────────────────┐
 │ MOTORES DE VOZ EXTERNOS / TTS │               │  jg_plugin_filmmaker   │
 │ - Google AI Studio (Gemini)   │               │       (jg_agente)      │
 │ - Speaches-AI (Piper Local)   │               │ - Storyboard Gen       │
 │ - FFmpeg (libmp3lame MP3)     │               │ - Desglose de Planos   │
 └───────────────────────────────┘               └────────────────────────┘
```

---

## 3. El Pipeline Cinematográfico en 6 Etapas

El corazón de `jg_filmmaker` es un pipeline desacoplado por escenas donde cada bloque evoluciona de forma progresiva:

| Etapa | Nombre | Propósito y Naturaleza del Contenido | Asistencia de Inteligencia Artificial |
| :---: | :--- | :--- | :--- |
| **1** | **Idea / Bruto** | Notas desestructuradas, premisas dramáticas o transcripciones de voz. | Estructuración dramática y extracción de conflictos. |
| **2** | **Tratamiento** | Narrativa en prosa presente (`DÍA 1`, ambientación, tono, objetivos). | Detección de subtramas y balance de ritmo de escena. |
| **3** | **Guión Literario** | Formato canónico Fountain: sluglines `INT./EXT.`, acotaciones y réplicas. | Conversión de estilo indirecto a directo y pulido fonético. |
| **4** | **Planos Técnicos** | Desglose audiovisual: tamaño de plano, ángulo, movimiento y encuadre. | Propuesta de planificación de cámara según tensión narrativa. |
| **5** | **Rodaje / Tomas** | Ingesta de tomas audiovisuales reales vinculadas a cada plano. | Marcado de toma buena (`is_good_take`) y notas de script. |
| **6** | **Montaje NLE** | Pre-corte continuo sincronizado con timeline y audio de referencia. | Ensamble físico concatenado con transcodificación FFmpeg. |

---

## 4. Capacidades Principales

### 🎙️ Lectura Dramática Neural y Exportación MP3
- **Síntesis Multivoz con Google AI Studio (Gemini TTS):** Reparto dinámico de voces neurales diferenciadas por personaje (`Puck`, `Charon`, `Kore`, `Fenrir`, `Aoede`, etc.) con fallback orgánico a **Speaches-AI (Piper TTS)** local.
- **Expansión Fonética Cinematográfica:** Normalización automática de abreviaturas técnicas de guion (`INT.` $\rightarrow$ *"Interior, "*, `EXT.` $\rightarrow$ *"Exterior, "*, `INT./EXT.` $\rightarrow$ *"Interior y exterior, "*).
- **Streaming Gapless en Cliente:** Reproductor flotante Alpine.js con arquitectura de doble búfer nativo (`Audio.preload = 'auto'`) que elimina pausas muertas entre réplicas.
- **Protección Antirráfagas con Debounce:** La interfaz permite saltar instantáneamente entre segmentos a 60 FPS cancelando descargas previas y emitiendo la solicitud únicamente tras detener la navegación (180 ms).
- **Exportación Completa a MP3 con FFmpeg:** Concatenación y compresión de todo el guion a MP3 (192 kbps, 44.1 kHz) a más de **100x de velocidad real**, reduciendo el tamaño del archivo un 90% (~18 MB frente a ~250 MB en WAV).
- **Control Reactivo de Disponibilidad:** Botón `MP3` en toolbar con estado adaptativo: deshabilitado con tooltip guiado si faltan fragmentos por locutar, o activo para descarga en un clic cuando la obra está lista.

### ✍️ Experiencia de Escritura Continua (Modo StoryArchitect)
- **Editor sin Paginación Forzada:** Despliegue de todas las escenas en un lienzo unificado para lectura fluida de extremo a extremo.
- **Drag & Drop Físico y Transaccional:** Reordenación de bloques y escenas mediante tiradores táctiles (`⋮⋮`) con reindexación atómica (`DB::transaction`).
- **Divisores Reactivos WordPress Gutenberg:** Inserción rápida de escenas y bloques intermedios con feedback visual en hover.
- **Drawer Lateral de Bloque:** Panel contextual que permite alternar entre etapas (`Idea`, `Tratamiento`, `Guión`, `Planos`, `Tomas`), revisar tomas y consultar la telemetría de IA.
- **Biblia de Proyecto (`Cmd+B` / `Ctrl+B`):** Gestión centralizada de personajes, roles narrativos (`PROTAGONISTA`, `SECUNDARIO`, `REPARTO`), notas de vestuario multilínea, localizaciones y asignación persistente de voces.

### 📄 Ingesta y Exportación de Documentos
- **Importador Heurístico de Guiones (PDF y TXT):** Ingesta automática mediante `ScriptImportService` reconociendo encabezados de escena, diálogos y personajes, con ventana de confirmación previa antes de sobreescribir.
- **Exportación PDF Cinematográfico Estándar:** Generación con `DomPDF` respetando la tipografía Courier 12pt, márgenes canónicos de la industria y numeración formal de escenas y páginas.
- **Renderizado y Procesamiento en Lote (Batch):** Asistente para transformar de forma masiva brutos en tratamientos o tratamientos en guiones respetando los candados manuales.

### 🛡️ Gobernanza de Candados (`user_locks`)
- Cualquier bloque editado manualmente por el usuario adquiere un candado visual e inmutable.
- Las transformaciones asistidas por IA respetan estrictamente los bloques bloqueados, omitiéndolos durante ejecuciones masivas y protegiendo el trabajo del autor.

### 🎞️ Montaje Audiovisual y Ensamble FFmpeg
- Subida y streaming de tomas de vídeo con soporte multiplataforma (incluyendo conversión de imágenes HEIC a JPEG en cliente con `heic2any`).
- Generación de secuencias de montaje bruto (*Rough Cut Assembly*) ensambladas directamente en el servidor con FFmpeg.

---

## 5. Panel de Control y Administración (`/admin`)

- **Acceso Protegido por Roles:** Middleware `EnsureUserIsAdmin` protegiendo todas las rutas administrativas bajo HTTP 403.
- **Gobernanza de Usuarios y Cuotas (`/admin/users`):** Conteo de proyectos frente a cuota contratada (`paid_project_slots`), volumen de tokens consumidos y conmutación de privilegios de administrador.
- **Auditoría Cuantitativa con Privacidad Estricta:** Estadísticas agregadas de bloques por etapa sin exponer en memoria argumentos ni textos privados de los autores.
- **Catálogo Global y Transferencia de Obras (`/admin/projects`):** Buscador reactivo de obras con capacidad de transferir atómicamente la titularidad de cualquier proyecto entre creadores.
- **Tarifas y Costes de Inferencia (`/admin/pricing`):** Gestión dinámica de precios por millón de tokens para cada proveedor (Groq, Gemini, Ollama) y modelo activo.

---

## 6. Seguridad y Protocolos Inter-Servicio

- **Autenticación Passwordless con PIN OTP:** Códigos efímeros de 8 caracteres con ventana de 15 minutos almacenados mediante hash SHA-256 en base de datos.
- **Firmas Criptográficas HMAC-SHA256:** Comunicación con `jg_plugin_filmmaker` y `jg_agente` mediante firma canónica de cuerpo crudo y timestamp anti-repetición (tolerancia de 300 segundos).
- **Identificadores UUIDv4 Universales:** Todas las tablas de dominio (`projects`, `scenes`, `script_blocks`, `shots`, `takes`) utilizan claves UUIDv4 previniendo ataques de enumeración (IDOR).
- **Diseño Dual Adaptativo:** Modo Claro / Oscuro nativo con persistencia en cliente mediante Tailwind CSS v4.

---

## 7. Pila Tecnológica

| Componente | Tecnología | Versión | Propósito en el Ecosistema |
| :--- | :--- | :---: | :--- |
| **Backend Framework** | Laravel | `12.x` | Núcleo de aplicación, routing, queues y autorización |
| **Motor Reactivo** | Livewire | `4.x` | Componentes reactivos en tiempo real sin SPA compleja |
| **Diseño y Estilos** | Tailwind CSS | `v4` | Sistema de diseño cinemático con tema claro y oscuro |
| **Base de Datos** | PostgreSQL / SQLite | `16.x` | Persistencia relacional ACID con claves UUIDv4 |
| **Procesamiento Multimedia**| FFmpeg (`libmp3lame`) | `8.x` | Concatenación de audio, compresión MP3 y ensamble NLE |
| **Exportación PDF** | DomPDF | `3.x` | Generación de guiones en formato cinematográfico estándar |
| **Síntesis Neural (TTS)** | Google AI Studio | `Gemini Flash` | Voces neurales multivoz de estudio para cada personaje |
| **TTS Local (Fallback)** | Speaches-AI (Piper) | `Latest` | Síntesis neural local en red privada sin coste de API |
| **Servidor Web & Proxy** | Nginx + PHP-FPM | `8.4.x` | Pool de 25 workers optimizado para streaming de audio |
| **Supervisor de Procesos** | Supervisord | `Python 3` | Monitor de Nginx, PHP-FPM y `queue:work` continuo |
| **Testing Automatizado** | PHPUnit | `12.x` | 239 pruebas automatizadas (Feature y Unit) |
| **Estándar de Código** | Laravel Pint | `Latest` | Formateador estricto de sintaxis y estándares PHP |

---

## 8. Puesta en Marcha y Ejecución

### Entorno Local

```bash
# 1. Clonar el repositorio e instalar dependencias PHP y Node
git clone https://gitlab.com/javiergalvez-ia/jg_filmmaker.git
cd jg_filmmaker
composer install
npm install && npm run build

# 2. Configurar el entorno
cp .env.example .env
php artisan key:generate

# 3. Ejecutar migraciones de base de datos
php artisan migrate --force

# 4. Iniciar el servidor local
php artisan serve --port=8010

# 5. Ejecutar la suite completa de tests (239 tests pasando al 100%)
php artisan test

# 6. Validar estilo de código con Pint
./vendor/bin/pint --test
```

### Entorno Docker (Producción / Staging)

```bash
# Levantar el stack completo (Nginx, PHP-FPM 8.4, Queue Worker y Supervisord)
docker compose up -d --build

# Comprobar estado de salud
docker compose ps

# Visualizar logs unificados en tiempo real (incluye trazas [TTS Stream] y [TTS Manifest])
docker compose logs -f jg_filmmaker
```

---

## 9. Referencia Técnica Adicional

- **[`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md):** Contexto vivo del proyecto, registro consolidado de capacidades, decisiones de arquitectura y control de deudas técnicas.
- **[`docs/`](docs/README.md):** Manuales técnicos de arquitectura, modelo de datos y protocolos criptográficos.
- **[`AGENTS.md`](../AGENTS.md):** Directrices y restricciones operativas del workspace `javiergalvez-ia`.

---

<div align="center">

Desarrollado con arquitectura limpia, diseño cinematográfico y altos estándares de calidad por **Javier Gálvez**.  
Distribuido bajo licencia propietaria en el ecosistema **`javiergalvez-ia`**.

</div>
