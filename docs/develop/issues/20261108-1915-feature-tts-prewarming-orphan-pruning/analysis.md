# Feature: Pre-warming en Background de Locución de Guiones, Reproductor con Búfer Continuo y Purga de Audios Huérfanos

**Fecha:** 2026-11-08  
**Proyecto:** `jg_filmmaker`  
**Autor:** Javier Gálvez / Antigravity  
**GitLab Issue:** [#5](https://gitlab.com/javiergalvez-ia/jg_filmmaker/-/work_items/5)  
**Estado:** En Implementación  

---

## 1. Contexto y Problemática

En la plataforma `jg_filmmaker`, la lectura dramatizada de guiones cinematográficos mediante síntesis de voz neural (Google AI Studio Gemini TTS y Speaches-AI) se ejecutaba bajo un esquema puramente *on-demand*:

1. **Latencia acumulada y micro-pausas:** Al avanzar secuencialmente diálogo a diálogo, el reproductor solicitaba cada parlamento en el momento en que debía sonar. Si el parlamento no estaba previamente sintetizado en disco (`tts_cache`), la petición HTTP requería esperar el tiempo de inferencia de la IA (1 a 3 segundos), generando silencios muertos que rompían el ritmo dramático de la escena.
2. **Saturación de límites de cuota (HTTP 429) por peticiones concurrentes descontroladas:** Intentos previos de precarga agresiva en el navegador disparaban múltiples peticiones simultáneas contra la API de Google AI Studio, provocando respuestas `429 Too Many Requests` (cuota por minuto o cuota diaria) y causando saltos involuntarios de parlamentos en el reproductor.
3. **Crecimiento de audios huérfanos en almacenamiento:** A medida que los guionistas iteran, modifican réplicas o reescriben escenas, los archivos `.wav` de las versiones previas permanecen en el volumen persistente de Google Drive (`/mnt/gdrive_filmmaker/private/tts_cache`), consumiendo almacenamiento sin utilidad futura.

---

## 2. Arquitectura de la Solución

```text
+---------------------------------------------------------------------------------------------------+
|                        ARQUITECTURA DE LOCUCIÓN DE GUIONES CONTINUA (VOD/STREAMING)               |
+---------------------------------------------------------------------------------------------------+
|                                                                                                   |
|  [Usuario pulsa 'Lectura de Guion' o 'Escena']                                                    |
|         │                                                                                         |
|         ├───► [GET /tts/manifest] ───► Devuelve Manifiesto JSON (132 segmentos) en < 50 ms        |
|         │                                 │                                                       |
|         │                                 ▼                                                       |
|         │                      [Reproductor Web (Alpine.js)]                                      |
|         │                      - Inicia segmento 0 de inmediato.                                  |
|         │                      - Búfer deslizante (precarga continua 2-3 segmentos adelante).     |
|         │                      - Reproducción fluida sin saltos ni pausas.                        |
|         │                                                                                         |
|         └───► [Pre-warming en Background] (Process asíncrono / PrewarmScopeTtsJob)                |
|                   │                                                                               |
|                   ├── Lock de concurrencia: Evita duplicar tareas sobre el mismo proyecto         |
|                   ├── Itera ordenadamente todos los segmentos del ámbito                          |
|                   ├── Salta segmentos que YA existen en disco (0 coste)                           |
|                   ├── Sintetiza los nuevos con espaciado de cortesía (200 ms) para proteger RPM   |
|                   └── Failover automático entre modelos ante 429 diario                           |
|                                                                                                   |
+---------------------------------------------------------------------------------------------------+
|                        MANTENIMIENTO Y PURGA DE AUDIOS HUÉRFANOS                                  |
+---------------------------------------------------------------------------------------------------+
|                                                                                                   |
|  [Cron Semanal / Comando Manual: php artisan tts:prune-orphans --days=7]                          |
|         │                                                                                         |
|         ├── 1. Recorre todos los ScriptBlocks de todos los Proyectos en BD                        |
|         ├── 2. Calcula el conjunto (Set) de hashes válidos activos                                |
|         ├── 3. Escanea storage/app/private/tts_cache/*.wav                                        |
|         └── 4. Elimina archivos huérfanos cuya antigüedad supere el umbral de seguridad           |
|                                                                                                   |
+---------------------------------------------------------------------------------------------------+
```

---

## 3. Componentes Técnicos

### 3.1. Pre-warming en Background
- **Servicio:** `TextToSpeechService::prewarmManifest(array $manifest, float $speed = 1.0, ?callable $onProgress = null)`.
- **Job:** `App\Jobs\PrewarmScopeTtsJob` (despachable y serializable).
- **Comando Artisan:** `php artisan tts:prewarm {project} {--scene_id=} {--block_id=} {--speed=1.0}`.
- **Disparo Asíncrono en Controlador:** `AudioStreamController::manifest` lanza el proceso en segundo plano con `Process::path(base_path())->start(...)` y protección por `Cache::lock('tts_prewarm_'.$project->id, 300)`.

### 3.2. Reproductor Frontend en `script-editor.blade.php`
- Ventana deslizante de precarga de 2 segmentos hacia adelante.
- Manejo elegante de estados (`loading`, `ready`, `error` sin salto destructivo).
- Consumo directo de archivos estáticos cacheados a velocidad local.

### 3.3. Comando de Purga de Huérfanos `tts:prune-orphans`
- `App\Console\Commands\PruneOrphanTtsAudiosCommand`.
- Opciones:
  - `--days=7`: Días mínimos de inactividad antes de considerar huérfano (evita borrar cachés recién probadas).
  - `--dry-run`: Muestra los ficheros y bytes que se liberarían sin borrarlos.
  - `--force`: Purga inmediata sin confirmación interactiva.
- Programado semanalmente en `routes/console.php`.

---

## 4. Criterios de Aceptación
1. Al iniciar la locución, el audio del primer segmento comienza de inmediato mientras los siguientes se sintetizan en segundo plano.
2. Los parlamentos se suceden sin silencios anormales ni saltos inesperados de diálogo.
3. El comando `php artisan tts:prune-orphans --dry-run` detecta correctamente los archivos no referenciados.
4. El conjunto de tests pasa al 100% (incluyendo nuevas pruebas para pre-warming y purga).

