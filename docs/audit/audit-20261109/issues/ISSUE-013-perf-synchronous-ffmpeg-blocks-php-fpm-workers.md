# [ISSUE-013] Bloqueo Síncrono de Workers PHP-FPM Durante Ensamblaje de Vídeo FFmpeg y Exportación MP3

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/RoughCutAssemblyService.php:99-125`, `jg_filmmaker/app/Services/TextToSpeechService.php:623`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:2495`, `jg_filmmaker/app/Livewire/Studio/RoughCutTimeline.php:76`
- **Categoría:** Rendimiento / Escalabilidad

## 1. Descripción
Las operaciones intensivas de procesamiento multimedia con FFmpeg se ejecutan de manera **100% síncrona dentro del hilo de la petición HTTP / Livewire**, reteniendo un worker de PHP-FPM y la conexión de Nginx/navegador abierta durante minutos:

1. **Ensamblaje y Transcodificación de Vídeo (`RoughCutAssemblyService::assemble`):**
   - Invocado directamente desde `ScriptEditor::assembleRoughCut()` y `RoughCutTimeline::assembleVideo()`.
   - Primero lanza `Process::timeout(120)->run($concatCmd)` (hasta 2 minutos) y, si falla el stream copy por diferencias de códec/resolución entre tomas, lanza un segundo proceso `Process::timeout(300)->run($reencodeCmd)` (hasta 5 minutos de recodificación CPU-bound con `libx264` + `aac`).
   - En total, una sola petición HTTP puede bloquear un worker PHP-FPM durante **hasta 420 segundos (7 minutos)**, superando ampliamente el `fastcgi_read_timeout` estándar de Nginx/proxies inversos (60s-120s) y devolviendo un error `504 Gateway Timeout` al usuario mientras FFmpeg sigue consumiendo el 100% de CPU en el contenedor.
2. **Concatenación y Compresión MP3 de Guion Completo (`TextToSpeechService::exportProjectToMp3`):**
   - Invocado síncronamente desde `ScriptEditor::exportMp3()` y `AudioStreamController::exportMp3()`.
   - Si algún segmento WAV no estaba en caché o se invalidó, lo sintetiza síncronamente contra Google AI en el bucle previo y acto seguido ejecuta `Process::timeout(300)->run(...)` con `libmp3lame` sobre decenas o cientos de archivos WAV.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/RoughCutAssemblyService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/RoughCutAssemblyService.php#L99-L125):
```php
        $result = Process::timeout(120)->run($concatCmd);

        if (! $result->successful() || ! file_exists($absoluteOutputPath)) {
            // Fallback: Re-encode if codecs/resolutions differ slightly
            $reencodeCmd = [
                'ffmpeg',
                '-y',
                '-f', 'concat',
                '-safe', '0',
                '-i', $concatListPath,
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-crf', '28',
                '-c:a', 'aac',
                '-movflags', '+faststart',
                $absoluteOutputPath,
            ];

            $result = Process::timeout(300)->run($reencodeCmd);
```

En [`jg_filmmaker/app/Services/TextToSpeechService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/TextToSpeechService.php#L612-L625):
```php
            $cmd = [
                'ffmpeg',
                '-y',
                '-f', 'concat',
                '-safe', '0',
                '-i', $concatListPath,
                '-vn',
                '-ar', '44100',
                '-ac', '2',
                '-c:a', 'libmp3lame',
                '-b:a', '192k',
                $outputMp3Path,
            ];

            $result = Process::timeout(300)->run($cmd);
```

## 3. Impacto Operativo o Riesgo
- **Inanición del Pool PHP-FPM y Denegación de Servicio (DoS):** Aunque el pool de PHP-FPM se amplió a 25 workers, basta con que 5-10 usuarios pulsen simultáneamente "Ensamblar Montaje" (o hagan doble clic tras un timeout 504 de Nginx) para saturar la CPU del servidor con procesos `ffmpeg -c:v libx264` concurrentes y bloquear los workers web.

## 4. Solución o Mejora Recomendada
Desacoplar el ensamblaje de vídeo (`AssembleRoughCutJob`) y la exportación de audiolibro MP3 (`ExportProjectMp3Job`) hacia una cola dedicada (`--queue=media,default`) procesada por el worker de Supervisord con un límite de concurrencia controlado (`Cache::lock("ffmpeg_render_{$project->id}", 360)`), notificando el estado (`rendering` -> `ready` / `failed`) a la interfaz Livewire mediante `wire:poll.3s` cuando el render está en curso.

