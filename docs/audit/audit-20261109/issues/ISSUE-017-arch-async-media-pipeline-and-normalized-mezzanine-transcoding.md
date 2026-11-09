# [ISSUE-017] Pipeline Asíncrono de Ingesta Multimedia y Transcodificación Intermedia Normalizada (Mezzanine CFR)

- **Severidad:** Idea de Mejora
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/RoughCutAssemblyService.php:17-146`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:2425-2485`
- **Categoría:** Arquitectura

## 1. Descripción
Actualmente, cuando el cineasta sube una toma de vídeo (`Take`) desde dispositivos heterogéneos (iPhone con HEVC/H.265 y Variable Frame Rate VFR a 29.97/60 fps, cámaras DSLR a 23.976/24/25 fps en MOV, o grabaciones web en WebM/VP9):
1. El archivo original se almacena tal cual en `media/projects/{project_id}/scenes/{scene_id}/takes/{take_id}.{ext}` extrayendo sus metadatos con `ffprobe` síncronamente durante la subida.
2. Al ensamblar el montaje (`RoughCutAssemblyService::assemble()`), FFmpeg intenta primero `-f concat -c copy`. Como las tomas provienen de cámaras/móviles con distintos códecs, resoluciones, *timebases* o frecuencias de muestreo de audio (44.1 kHz vs 48 kHz), `-c copy` falla o genera archivos MP4 con desincronización progresiva de audio/vídeo (especialmente con vídeo VFR de smartphones), forzando la recodificación completa de todas las tomas cada vez que se añade o reordena un plano.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/RoughCutAssemblyService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/RoughCutAssemblyService.php#L90-L120):
```php
        $concatCmd = [
            'ffmpeg',
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $concatListPath,
            '-c', 'copy',
            $absoluteOutputPath,
        ];

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
```

## 3. Impacto Operativo o Riesgo
- **Re-codificación O(N) Repetitiva en Cada Montaje:** Si un proyecto tiene 40 tomas buenas y el director cambia el orden de una sola toma, el servidor vuelve a recodificar las 40 tomas desde cero porque sus cabeceras de stream originales no son idénticas.
- **Incompatibilidad de Reproducción Web con HEVC/MOV:** Los archivos `.mov` grabados en ProRes o HEVC 10-bit no se reproducen nativamente en todos los navegadores de escritorio.

## 4. Solución o Mejora Recomendada
Implementar un **Pipeline de Ingesta y Normalización Intermedia (Proxy Mezzanine)** ejecutado en segundo plano mediante un Job de cola (`NormalizeTakeMediaJob`) al subir cada toma:

```text
[Subida de Toma (MOV/MP4/WebM VFR)]
          │
          ▼ (Cola: media-ingest)
[NormalizeTakeMediaJob (FFmpeg)]
  ├── 1. Normaliza a H.264 (yuv420p, High@L4.1), 1920x1080 (pad/scale), CFR 24 fps, GOP fijo (-g 48)
  ├── 2. Normaliza pista de audio a AAC-LC 48000 Hz Stereo (-ar 48000 -ac 2)
  ├── 3. Genera miniatura poster JPEG (frame al 20% de duración)
  └── 4. Marca Take status = 'ready' y guarda proxy_file_path
          │
          ▼ (Al pulsar "Ensamblar Montaje")
[AssembleRoughCutJob]
  └── Concatenación instantánea 100% Stream Copy (-c copy) en < 1 segundo sin recodificar
```

Al estar todas las tomas normalizadas al mismo perfil de códec, resolución (1080p con *letterbox* automático), framerate constante (24 fps) y audio (48 kHz estéreo), el ensamblado posterior del *Rough Cut* funciona **siempre en modo `-c copy` en menos de 1 segundo**, sin consumir CPU ni sufrir derivas de sincronización labial.

