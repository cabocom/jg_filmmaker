# [ISSUE-009] Ensamblado FFmpeg: reintento usa lista concat ya borrada, éxito falso con datos simulados, ejecución síncrona sin límites y colisión de nombres

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Services/RoughCutAssemblyService.php:29-30,42-44,69-112,152-157`, `app/Livewire/Studio/RoughCutTimeline.php:25-35`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
1. **Bug lógico:** `$tempListFile` se elimina (`@unlink`, línea 91) **antes** del reintento de transcodificación (líneas 96-106), que vuelve a usar `-i $tempListFile`. El segundo intento siempre falla porque el fichero ya no existe; el fallback `-c copy → libx264` descrito en la doc nunca funciona.
2. **Éxito falso:** si FFmpeg falla se escribe `FALLBACK_RENDER_AFTER_FFMPEG_ERROR_...` como `.mp4` y se devuelve `success: true, is_simulated: false`. La UI anuncia "Pre-corte ensamblado con éxito" con un fichero corrupto. Igual ocurre cuando `! isFfmpegAvailable()` o `empty($validFilePaths)` → se genera un MP4 falso en producción sin avisar.
3. **I/O síncrono en la petición Livewire:** `Process::run` bloquea el worker PHP-FPM hasta el timeout por defecto de Laravel Process (60 s) — en vídeos reales la codificación supera ese límite y lanza `ProcessTimedOutException`, dejando el fichero de salida parcial. Sin cola, sin progreso, sin límite de tamaño/duración/nº de tomas, sin cancelación y sin bloqueo de concurrencia (dos pulsaciones lanzan dos ffmpeg).
4. **Colisión:** `rough_cut_{time()}.mp4` colisiona dentro del mismo segundo y sobrescribe (`-y`); los renders antiguos nunca se purgan (disco ilimitado).
5. `isFfmpegAvailable()` lanza un proceso `which` en cada ensamblado; dependencia de `which` (no portable). `Storage::path()` con disco `public` usa rutas absolutas del host en el fichero concat: sin validar que cada ruta esté bajo el directorio de medios.
6. El concat demuxer con `-c copy` exige códecs/parámetros idénticos; el `technical_meta` por defecto (`prores422hq` 3840x2160) no coincide con lo que se declara al subir, nadie sondea el contenido real (ver ISSUE-027).
7. `$take->file_path` mezclado con vídeos de varios códecs → resultado inválido con "éxito".

## 2. Evidencia de Código
```php
if (file_exists($tempListFile)) { @unlink($tempListFile); }          // borra la lista
if (! $process->successful() || ! file_exists($outputAbsolutePath)) {
    $retryProcess = Process::run(['ffmpeg','-f','concat','-safe','0','-i', $tempListFile, ...]);  // lista inexistente
    if (! $retryProcess->successful() ...) {
        $disk->put($relativeRenderPath, "FALLBACK_RENDER_AFTER_FFMPEG_ERROR_{$project->id}");   // falso positivo
    }
}
return ['success' => true, ... 'is_simulated' => false];
```

## 3. Impacto Operativo o de Seguridad
El usuario recibe renders falsos o corruptos marcados como válidos; bloqueo de workers PHP-FPM (DoS por un solo usuario con muchas tomas); disco lleno; pérdida de confianza en el pre-corte.

## 4. Solución Recomendada
- Mover el ensamblado a un Job en cola (`ShouldBeUnique` por proyecto/escena) con `timeout`, estado (`pending|running|done|failed`) y notificación; `Process::timeout(...)`/`->idleTimeout(...)`.
- Borrar la lista temporal en un `finally` **tras** ambos intentos; ruta de salida `renders/{uuid}.mp4`.
- Nunca devolver `success: true` ante fallo: lanzar `RuntimeException` con `stderr` saneado y eliminar el fichero parcial.
- Separar claramente el modo simulado (solo si `config('services.filmmaker.mock_ffmpeg')` o `testing`) y no activarlo por ausencia de binario en producción (`fail`).
- Validar que cada ruta esté dentro de `storage/app/.../media/projects/{id}`; sondear con `ffprobe` antes de decidir `-c copy` vs re-encode.
- Política de retención de renders (último N).
- Test con `Process::fake()` que simule fallo del 1.er intento y compruebe que el 2.º recibe una lista existente.
