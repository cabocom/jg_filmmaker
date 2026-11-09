# [ISSUE-005] Síntesis TTS No Acotada en `demoVoice`, Ausencia de Rate-Limiting y Bypass de Cuota de Usuario

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Http/Controllers/AudioStreamController.php:108-147`, `jg_filmmaker/routes/web.php:48-57`, `jg_filmmaker/app/Services/TextToSpeechService.php:129-174`
- **Categoría:** Seguridad

## 1. Descripción
Se han detectado tres debilidades combinadas en los endpoints de síntesis de voz neural (`TextToSpeechService` / `AudioStreamController`):
1. **Texto Arbitrario de Longitud Ilimitada en `demoVoice`:** El endpoint `GET /projects/{project}/tts/demo-voice` recibe el parámetro `text` por query string (`$request->query('text')`) sin ningún límite de caracteres (`max`) ni restricción a frases de demostración. Cualquier usuario autenticado con un proyecto puede utilizar este endpoint como un proxy abierto hacia `gemini-3.8-flash-tts` para sintetizar textos arbitrarios de gran tamaño y almacenarlos en `storage/tts_cache`.
2. **Ausencia de Rate-Limiting (`throttle`) en Rutas TTS y Exportación:** En `routes/web.php`, mientras la subida de archivos tiene `throttle:15,1` y el login OTP tiene `RateLimiter`, las rutas `tts/manifest`, `tts/stream`, `tts/demo-voice` y `export-mp3` carecen de middleware `throttle`.
3. **Consumo de API Google AI sin Registro en Telemetría ni Control de Cuota Diaria:** Ninguna llamada a `synthesizeWithGoogleAi()` verifica `$user->hasDailyTokensAvailable()` ni registra el consumo en `agent_request_logs` mediante `AgentTelemetryService`. Un usuario con `daily_token_limit = 0` o con su cuota diaria agotada puede seguir consumiendo indefinidamente la cuota de pago de Google AI Studio TTS.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Http/Controllers/AudioStreamController.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Http/Controllers/AudioStreamController.php#L108-L127):
```php
    public function demoVoice(Request $request, Project $project): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user || $project->user_id !== $user->id) {
            abort(403, 'No tienes permiso para acceder a este proyecto.');
        }

        $request->session()->save();

        $voice = trim((string) $request->query('voice', ''));
        $customText = trim((string) $request->query('text', ''));

        $audioPath = $this->ttsService->synthesizeVoiceSample(
            $voice !== '' ? $voice : null,
            $customText !== '' ? $customText : null
        );
```

En [`jg_filmmaker/routes/web.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/routes/web.php#L48-L57):
```php
    Route::get('/projects/{project}/tts/manifest', [AudioStreamController::class, 'manifest'])
        ->name('projects.tts.manifest');
    Route::get('/projects/{project}/tts/stream', [AudioStreamController::class, 'streamAudio'])
        ->name('projects.tts.stream');
    Route::get('/projects/{project}/tts/demo-voice', [AudioStreamController::class, 'demoVoice'])
        ->name('projects.tts.demo-voice');
    Route::get('/projects/{project}/export-mp3', [AudioStreamController::class, 'exportMp3'])
        ->name('projects.export.mp3');
```

## 3. Impacto Operativo o Riesgo
- **Agotamiento de Cuota de API (DoS Financiero y Operativo):** Un único usuario puede automatizar peticiones contra `/tts/demo-voice?text=...` con textos aleatorios, agotando en minutos el límite diario de peticiones de Google AI Studio (`gemini-3.8-flash-tts` y modelos de respaldo) para toda la plataforma y llenando el disco NVMe local en `storage/tts_cache`.

## 4. Solución o Mejora Recomendada
1. En `AudioStreamController::demoVoice()`, eliminar la aceptación de texto libre arbitrario por query string (o acotarlo estrictamente a `mb_substr($customText, 0, 120)` sólo para el nombre del personaje) y aplicar `throttle:30,1` en `demo-voice` y `throttle:120,1` en `stream`.
2. Registrar las invocaciones reales a Google AI TTS (cuando hay *cache miss*) en `AgentTelemetryService` para trazabilidad de costes y control de cuota diaria por usuario.

