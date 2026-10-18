# Análisis Técnico: [ISSUE-009] Ensamblado FFmpeg: reintento usa lista concat ya borrada, éxito falso con datos simulados y colisión de nombres

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Categoría:** Robustez / Arquitectura de Medios
- **Vulnerabilidades / Errores:** CWE-703 (Improper Check for Unusual or Exceptional Conditions), CWE-390 (Detection of Error Condition Without Action), CWE-377 (Insecure Temporary File / Logic Race)

---

## 1. Diagnóstico del Estado Previo

1. **Bug Crítico de Borrado Prematuro:**
   - En `RoughCutAssemblyService::assemble()`:
     ```php
     $process = Process::run(['ffmpeg', ..., '-i', $tempListFile, '-c', 'copy', ...]);
     if (file_exists($tempListFile)) {
         @unlink($tempListFile); // Se destruye el archivo temporal
     }
     if (! $process->successful() || ! file_exists($outputAbsolutePath)) {
         $retryProcess = Process::run(['ffmpeg', ..., '-i', $tempListFile, '-c:v', 'libx264', ...]);
         // $tempListFile ya no existe. El segundo intento falla indefectiblemente.
     }
     ```
   - El reintento de transcodificación (cuando el copy demuxer falla por disparidad de códecs o framerates entre tomas) siempre fallaba debido a que el archivo temporal de lista había sido borrado en la línea anterior.

2. **Éxito Falso (False Positive) con Archivo Corrupto:**
   - Si el proceso de FFmpeg fallaba, el servicio escribía una cadena de texto plana (`FALLBACK_RENDER_AFTER_FFMPEG_ERROR_{$project->id}`) en la ruta `.mp4` y retornaba `['success' => true, ..., 'is_simulated' => false]`.
   - La interfaz de usuario mostraba un mensaje de éxito ("Pre-corte ensamblado con éxito") y cargaba un reproductor con un archivo de texto corrupto, engañando al cineasta y ocultando el fallo operativo.

3. **Detección Frágil de Binario y Simulación Silenciosa:**
   - `isFfmpegAvailable()` dependía de la utilidad Unix `which`, fallando en entornos donde no existe el comando o en shells restringidas.
   - Si FFmpeg no estaba disponible en producción, el servicio creaba silenciosamente un archivo de texto simulado en lugar de advertir el error de infraestructura.

4. **Colisión de Nombres y Crecimiento Indefinido de Disco:**
   - Los renders se nombraban como `rough_cut_{time()}.mp4`. Si dos solicitudes se procesaban en el mismo segundo, colisionaban y se sobreescribían con `-y`.
   - No existía política de limpieza ni retención de renders antiguos del proyecto.

5. **Riesgo de Path Traversal en Ficheros de Toma:**
   - No se validaba que las rutas de los archivos de toma estuvieran restringidas a la estructura autorizada del proyecto (`media/projects/{id}/...`).

---

## 2. Alternativas Evaluadas

1. **Mantener fallback simulado en producción si FFmpeg falla:**
   - *Descartada:* En producción, un render cinematográfico corrupto con mensaje de éxito rompe la integridad del pipeline y desorienta al cineasta. Los fallos deben ser visibles y explícitos.
2. **Reorganización estructurada con bloque `finally`, UUIDv4, saneamiento de rutas, retención y transcodificación confiable (Opción Seleccionada):**
   - *Ventajas:*
     - `$tempListFile` se crea antes de los procesos y se garantiza su borrado en un bloque `finally {}` únicamente tras finalizar todos los intentos.
     - Si FFmpeg falla en todos sus intentos, se purga cualquier salida parcial y se lanza una `RuntimeException` con el detalle del error de FFmpeg, evitando retornos falsos.
     - Las rutas de toma se validan asegurando que pertenezcan a la carpeta de medios autorizada del proyecto (`media/projects/{project->id}/`).
     - Los archivos de render utilizan UUIDv4 determinista (`rough_cut_{uuid}.mp4`), eliminando cualquier colisión.
     - Se implementa una política de retención automática de los últimos 5 renders por proyecto para evitar el agotamiento de espacio en disco.
     - La disponibilidad de FFmpeg se comprueba mediante `ffmpeg -version` y los procesos cuentan con timeouts estrictos (120s / 30s inactividad).
     - La simulación queda estrictamente acotada a entorno de testeo (`testing`) o configuración explícita (`mock_ffmpeg = true`).

---

## 3. Plan de Implementación

1. **Refactorización de `RoughCutAssemblyService` (`app/Services/RoughCutAssemblyService.php`):**
   - Incorporar validación de rutas de toma mediante `Str::startsWith` hacia `media/projects/{project->id}/`.
   - Asignar nombres con UUIDv4.
   - Envolver la ejecución en `try { ... } finally { @unlink($tempListFile); }`.
   - Ejecutar primer intento con `-c copy`; si falla, ejecutar reintento con `-c:v libx264 -pix_fmt yuv420p -c:a aac`.
   - Si ambos fallan en un entorno no simulado: limpiar fichero parcial y arrojar `RuntimeException`.
   - Implementar método de retención `pruneOldRenders(Project $project, int $keep = 5)`.
   - Mejorar `isFfmpegAvailable()` usando `ffmpeg -version`.

2. **Actualización de la Interfaz Livewire (`RoughCutTimeline.php`):**
   - Capturar `RuntimeException` y mostrar mensaje de error amigable al usuario en la sesión.

3. **Pruebas Automatizadas Rigurosas (`tests/Feature/RoughCutAssemblyTest.php`):**
   - Test con `Process::fake()` simulando fallo en el primer intento y éxito en el reintento, asegurando que la lista temporal permanece disponible.
   - Test con `Process::fake()` simulando fallo en ambos intentos, comprobando que se lanza `RuntimeException` y no se marca éxito falso.
   - Test de retención de renders antiguos.
