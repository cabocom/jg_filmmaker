# [ISSUE-015] Doble Disparo Simultáneo de Pre-warming TTS (Cola Laravel + Proceso OS) y Bloqueo en `QUEUE_CONNECTION=sync`

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Http/Controllers/AudioStreamController.php:52-74`, `jg_filmmaker/app/Console/Commands/PrewarmTtsCommand.php:37-42`, `jg_filmmaker/app/Jobs/PrewarmScopeTtsJob.php:46-66`
- **Categoría:** Rendimiento / Escalabilidad

## 1. Descripción
En `AudioStreamController::dispatchPrewarm()`, cada vez que el cliente solicita el manifiesto TTS (`GET /projects/{project}/tts/manifest` con `prewarm=1` por defecto) y existen segmentos pendientes de caché, el controlador lanza **dos mecanismos de ejecución en paralelo para la misma tarea**:
1. Primero despacha el job a la cola de Laravel: `PrewarmScopeTtsJob::dispatch($project->id, $scope, $targetId);`
2. Inmediatamente después, en el mismo bloque, lanza un subproceso del sistema operativo en segundo plano: `Process::start([PHP_BINARY, base_path('artisan'), 'tts:prewarm', ...])`, el cual a su vez ejecuta `PrewarmScopeTtsJob::dispatchSync(...)`.

Esto ocasiona dos problemas graves:
- **Si `QUEUE_CONNECTION=sync`** (configuración habitual en entornos locales o si no se sobreescribe la variable), `PrewarmScopeTtsJob::dispatch(...)` **se ejecuta de forma síncrona dentro de la petición HTTP `GET /tts/manifest`**, bloqueando la entrega del manifiesto JSON al navegador hasta que se hayan sintetizado todos los segmentos del guion (con pausas `usleep(150_000)` entre cada uno).
- **Si `QUEUE_CONNECTION=database` o `redis`** (con `queue-worker` activo en Supervisord), tanto el `queue-worker` como el subproceso `php artisan tts:prewarm` arrancan simultáneamente, compitiendo por el lock `Cache::lock(...)` y levantando un intérprete PHP CLI adicional e innecesario en cada clic de reproducción.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Http/Controllers/AudioStreamController.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Http/Controllers/AudioStreamController.php#L52-L74):
```php
    private function dispatchPrewarm(Project $project, string $scope, string $targetId): void
    {
        try {
            PrewarmScopeTtsJob::dispatch($project->id, $scope, $targetId);

            if (! app()->environment('testing')) {
                Process::path(base_path())
                    ->timeout(600)
                    ->start([
                        PHP_BINARY,
                        base_path('artisan'),
                        'tts:prewarm',
                        $project->id,
                        "--scope={$scope}",
                        "--id={$targetId}",
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[TTS Prewarm] No se pudo despachar el job de pre-warming', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
```

## 3. Impacto Operativo o Riesgo
- **Sobrecarga de Procesos (`fork/exec`) y Contención:** Cada apertura del reproductor TTS instancia un proceso `php artisan tts:prewarm` independiente del `queue-worker` gestionado por Supervisord, duplicando el consumo de memoria RAM (~40-60 MB por proceso CLI) sin control de concurrencia máxima.
- **Bloqueo Total del Manifiesto en Driver `sync`:** Si el driver de cola es `sync`, `manifest()` no responde hasta sintetizar todo el proyecto.

## 4. Solución o Mejora Recomendada
Utilizar `PrewarmScopeTtsJob::dispatch(...)` únicamente cuando el driver de cola sea asíncrono (`config('queue.default') !== 'sync'`), y reservar `Process::start(...)` exclusivamente como fallback cuando el driver sea `sync` (o usar `dispatchAfterResponse`):

```php
    private function dispatchPrewarm(Project $project, string $scope, string $targetId): void
    {
        try {
            if (config('queue.default') !== 'sync') {
                PrewarmScopeTtsJob::dispatch($project->id, $scope, $targetId);
                return;
            }

            if (! app()->environment('testing')) {
                Process::path(base_path())
                    ->timeout(600)
                    ->start([
                        PHP_BINARY,
                        base_path('artisan'),
                        'tts:prewarm',
                        $project->id,
                        "--scope={$scope}",
                        "--id={$targetId}",
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[TTS Prewarm] No se pudo despachar el job de pre-warming', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
```

