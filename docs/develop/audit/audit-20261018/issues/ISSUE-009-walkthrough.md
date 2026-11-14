# Walkthrough: [ISSUE-009] Reintento seguro con FFmpeg, supresión de falsos positivos y retención de renders

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Estado:** Implementado y Validado

---

## 1. Resumen de Cambios

Se ha refactorizado integralmente `App\Services\RoughCutAssemblyService`:

1. **Corrección del Borrado Prematuro de la Lista Concat:**
   - Se reestructuró la ejecución de FFmpeg dentro de un bloque `try ... finally`. El archivo temporal de lista (`$tempListFile`) permanece intacto tanto para el primer intento (`-c copy`) como para el reintento de transcodificación (`-c:v libx264 -c:a aac`), y se elimina de forma garantizada en el bloque `finally`.

2. **Supresión Absoluta de Falsos Positivos:**
   - Se eliminó la inyección de cadenas simuladas (`FALLBACK_RENDER_AFTER_FFMPEG_ERROR_...`) ante fallos de codificación.
   - Si los intentos de FFmpeg fallan o el archivo resultante queda vacío, se purga cualquier residuo parcial del disco y se lanza una excepción `RuntimeException` con los detalles reales de error de FFmpeg.
   - La simulación determinista queda estrictamente acotada a entornos de prueba (`testing`) o cuando se configura explícitamente `services.filmmaker.mock_ffmpeg = true`.

3. **Nombres con UUID y Prevención de Colisiones:**
   - La nomenclatura de los archivos ensamblados migró de marcas de tiempo simples a identificadores únicos globales: `rough_cut_{uuid}.mp4`.

4. **Validación de Rutas (Anti-Path Traversal):**
   - Cada toma procesada se valida asegurando que su `file_path` esté contenido dentro del prefijo autorizado del proyecto (`media/projects/{project->id}/`) y que no contenga secuencias de escape de directorio `..`.

5. **Política Automática de Retención de Renders:**
   - Se implementó `pruneOldRenders(Project $project, int $keep = 5)`, que purga automáticamente los renders antiguos del proyecto conservando únicamente los 5 más recientes para prevenir el consumo descontrolado de disco.

6. **Detección Confiable de FFmpeg y Timeouts Controlados:**
   - La verificación de disponibilidad de FFmpeg ejecuta directamente `ffmpeg -version` en lugar del comando Unix `which`.
   - Se fijaron timeouts explícitos de ejecución e inactividad en los procesos de concatenación y transcodificación.

---

## 2. Modificaciones en el Código

### `app/Services/RoughCutAssemblyService.php`

```php
// Nomenclatura con UUID
$renderUuid = (string) Str::uuid();
$relativeRenderPath = "media/projects/{$project->id}/renders/rough_cut_{$renderUuid}.mp4";

// Validación de ámbito de archivo
$allowedPrefix = "media/projects/{$project->id}/";
foreach ($goodTakes as $take) {
    if ($take->file_path && str_starts_with($take->file_path, $allowedPrefix) && ! str_contains($take->file_path, '..')) {
        if ($disk->exists($take->file_path)) {
            $validFilePaths[] = $disk->path($take->file_path);
        }
    }
}

// Concat demuxer con reintento seguro y borrado en finally
$tempListFile = tempnam(sys_get_temp_dir(), 'ffmpeg_concat_');
try {
    file_put_contents($tempListFile, $listContent);

    // Intento 1: copy
    $process = Process::timeout(120)->idleTimeout(30)->run([
        'ffmpeg', '-f', 'concat', '-safe', '0', '-i', $tempListFile, '-c', 'copy', $outputAbsolutePath, '-y',
    ]);

    if (! $process->successful() || ! file_exists($outputAbsolutePath) || filesize($outputAbsolutePath) === 0) {
        if (file_exists($outputAbsolutePath)) {
            @unlink($outputAbsolutePath);
        }

        // Intento 2: transcodificación con libx264
        $retryProcess = Process::timeout(300)->idleTimeout(45)->run([
            'ffmpeg', '-f', 'concat', '-safe', '0', '-i', $tempListFile,
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac',
            $outputAbsolutePath, '-y',
        ]);

        if (! $retryProcess->successful() || ! file_exists($outputAbsolutePath) || filesize($outputAbsolutePath) === 0) {
            if (file_exists($outputAbsolutePath)) {
                @unlink($outputAbsolutePath);
            }
            $errorDetails = trim($retryProcess->errorOutput() ?: $process->errorOutput());
            throw new RuntimeException("Error al ensamblar el pre-corte con FFmpeg: {$errorDetails}");
        }
    }

    $this->pruneOldRenders($project, 5);
} finally {
    if (file_exists($tempListFile)) {
        @unlink($tempListFile);
    }
}
```

---

## 3. Pruebas Automatizadas

Se amplió la suite `tests/Feature/RoughCutAssemblyTest.php` a 6 tests:
1. `test_assembly_service_produces_rendered_video_file_from_good_takes`: comprueba el ensamblado exitoso con simulación en testing.
2. `test_assembly_service_throws_exception_when_no_good_takes_exist`: verifica excepción cuando no existen tomas aprobadas.
3. `test_livewire_rough_cut_timeline_triggers_assembly_and_exposes_video_url`: verifica la interacción del componente Livewire.
4. `test_prune_old_renders_retains_only_most_recent_files`: verifica que al existir más de 5 renders, solo los 5 más recientes se conservan.
5. `test_ffmpeg_retry_executes_transcoding_and_preserves_temp_list`: simula el fallo de `-c copy` y el éxito subsiguiente de `libx264`, validando que `$tempListFile` se reutiliza correctamente.
6. `test_ffmpeg_throws_runtime_exception_and_does_not_produce_false_positive_when_both_fail`: verifica que el fallo de ambos comandos arroja una excepción sin emitir éxito falso ni archivos residuales.

Resultado: 6 tests pasando con 15 aserciones.
