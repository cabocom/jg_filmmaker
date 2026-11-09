# [ISSUE-010] Efectos Colaterales de Escritura en BD Durante Lecturas TTS y Corrupción UTF-8 de Nombres en Español

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/TextToSpeechService.php:733-757, 877-906`
- **Categoría:** Deuda Técnica

## 1. Descripción
En `TextToSpeechService`, los métodos de consulta `buildManifestForScope()`, `synthesizeBlockScope()` y `collectActiveCacheHashesForProject()` llaman a `parseBlockToSegments()`. Dentro de `parseBlockToSegments()` y `resolveSegmentVoice()`, el servicio ejecuta operaciones de escritura en la base de datos (`$char->saveQuietly()` y `$project->characters()->create(...)`) y aplica funciones de cadenas de un solo byte (`strtolower`, `ucwords`, `rtrim(..., '.')`) sobre nombres de personajes en español:

1. **Mutación de Base de Datos en Peticiones `GET` y Comandos de Limpieza:**
   - Una simple petición HTTP `GET /projects/{project}/tts/manifest` o `GET /projects/{project}/tts/stream`, o incluso la ejecución del comando de mantenimiento `php artisan tts:prune-orphans --dry-run`, dispara `CREATE` y `UPDATE` sobre la tabla `characters`.
   - Además, cuando el navegador solicita en paralelo varios segmentos de audio (`GET /tts/stream`), múltiples workers intentan crear simultáneamente el mismo personaje si no existía en la Biblia, pudiendo colisionar en el índice único `(project_id, name)`.
2. **Corrupción de Caracteres UTF-8 Multibyte:**
   - En la línea 877, `$formattedName = ucwords(strtolower($cleanSpeaker));` utiliza `strtolower()` de un solo byte sobre cadenas UTF-8 en mayúsculas. En PHP sin locale ISO-8859-1, `strtolower('LUCÍA')` o `strtolower('ÍÑIGO')` no convierte los bytes multibyte o puede alterar secuencias, produciendo nombres mal capitalizados (`LUCíA`, `ÍÑIGO`) en la Biblia de Personajes.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/TextToSpeechService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/TextToSpeechService.php#L868-L904):
```php
        $assignedVoice = $this->pickDeterministicVoiceForSpeaker($cleanSpeaker, $usedVoices);

        if ($project) {
            try {
                $formattedName = ucwords(strtolower($cleanSpeaker));
                $newChar = $project->characters()->create([
                    'name' => $formattedName,
                    'custom_attributes' => ['voice_id' => $assignedVoice],
                ]);
                $characterVoices[mb_strtolower($formattedName)] = $assignedVoice;
                if ($project->relationLoaded('characters')) {
                    $project->characters->push($newChar);
                }
            } catch (\Throwable $e) {
                // Ignore unique constraint or race conditions silently
            }
        }
```

## 3. Impacto Operativo o Riesgo
- **Violación de Idempotencia en `GET` y `--dry-run`:** Ejecutar `php artisan tts:prune-orphans --dry-run` modifica la base de datos de producción creando personajes y asignando voces en todos los proyectos escaneados.
- **Nombres de Personajes Corruptos en la Biblia:** Personajes con tildes o eñes (`LUCÍA`, `ÁNGEL`, `SEÑOR GÓMEZ`) se insertan con capitalización inconsistente.

## 4. Solución o Mejora Recomendada
1. Añadir un flag booleano `$persistBibleChanges = false` en `parseBlockToSegments()` y `resolveSegmentVoice()` (activándolo únicamente cuando se construye el manifiesto interactivo desde el editor y nunca durante `streamAudio` ni `collectActiveCacheHashesForProject` / `tts:prune-orphans`).
2. Reemplazar `ucwords(strtolower($cleanSpeaker))` por `mb_convert_case($cleanSpeaker, MB_CASE_TITLE, 'UTF-8')`.

