# [ISSUE-011] Archivos Huérfanos al Eliminar Entidades, Columnas Inexistentes en `ScriptImportService` y Cron Duplicado

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Models/Project.php:41-51`, `jg_filmmaker/app/Models/Scene.php:49-67`, `jg_filmmaker/app/Services/ScriptImportService.php:80, 323-365`, `jg_filmmaker/routes/console.php:18-48`
- **Categoría:** Deuda Técnica

## 1. Descripción
Se han identificado tres defectos de ciclo de vida de datos y almacenamiento:

1. **Archivos Huérfanos en Disco `public` al Eliminar Proyectos, Escenas, Planos, Personajes y Localizaciones:**
   - `Project::booted()` elimina `media/projects/{projectId}` en los discos `local` y `public`. Sin embargo, las viñetas de storyboard, las imágenes de personajes/localizaciones y el atrezzo (`props`) se guardan en `projects/{projectId}/...` (sin el prefijo `media/`). Por tanto, al borrar un proyecto, **ninguna de sus imágenes de storyboard, personajes, localizaciones ni props se elimina del disco**.
   - Del mismo modo, `Scene::booted()` solo borra archivos de `Take`, pero no borra `projects/{projectId}/storyboards/scene_{sceneId}`. Tampoco existen hooks `deleted` en `Shot`, `Character` ni `Location` para borrar `storyboard_image_path`, `reference_image_path` ni `prop_reference_paths` cuando se elimina una entidad individual.
2. **Columnas Inexistentes (`description`) y Parámetro Muerto (`$useAi`) en `ScriptImportService`:**
   - En `ScriptImportService::importIntoProject()` (líneas 327 y 360), se pasa `'description' => ...` a `Location::firstOrCreate()` y `Scene::create()`. Ni la tabla `locations` (que usa `art_department_notes`) ni la tabla `scenes` poseen una columna `description`, ni está en `$fillable`, por lo que Laravel descarta silenciosamente esos datos al importar un guion.
   - En `ScriptImportService::parseScreenplay(string $text, bool $useAi = false)` (línea 80), el parámetro `$useAi` no se referencia en ninguna línea del método (código muerto).
3. **Duplicidad de Cron y Omisión de Tareas Programadas en `routes/console.php`:**
   - En `routes/console.php` (líneas 45 y 47), `Schedule::command('tts:prune-orphans --days=7 --force')->weeklyOn(0, '03:00');` está registrado dos veces en la misma ventana horaria.
   - En cambio, `media:prune-orphans` y `model:prune` (para los modelos `Prunable`: `OneTimePassword` y `UserActivityLog`) no están registrados en el `Schedule`, y dentro de `media:prune-orphans` (línea 29) cualquier archivo bajo `/renders/` se ignora incondicionalmente (`str_contains($filePath, '/renders/')`), impidiendo limpiar renders antiguos de proyectos cuyos montajes fueron regenerados.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Models/Project.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Models/Project.php#L41-L51):
```php
        static::deleted(function (Project $project) {
            $projectMediaDir = "media/projects/{$project->id}";

            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($projectMediaDir)) {
                    Storage::disk($disk)->deleteDirectory($projectMediaDir);
                }
            }
        });
```

En [`jg_filmmaker/app/Services/ScriptImportService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptImportService.php#L323-L330):
```php
                    $location = Location::firstOrCreate(
                        ['project_id' => $project->id, 'name' => $locData['name']],
                        [
                            'type' => $locData['type'] ?? 'INT',
                            'description' => $locData['description'] ?? 'Localización importada desde guión.',
                        ]
                    );
```

En [`jg_filmmaker/routes/console.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/routes/console.php#L44-L48):
```php
// Purga semanal automatizada de audios TTS huérfanos (> 7 días sin referencia en guiones activos)
Schedule::command('tts:prune-orphans --days=7 --force')->weeklyOn(0, '03:00');

Schedule::command('tts:prune-orphans --days=7 --force')->weeklyOn(0, '03:00');
```

## 3. Impacto Operativo o Riesgo
- **Crecimiento Indefinido de Almacenamiento:** Los directorios `storage/app/public/projects/{uuid}` y las tablas `one_time_passwords` / `user_activity_logs` crecen indefinidamente sin purga efectiva.
- **Pérdida Silenciosa de Metadatos en Importación:** Las descripciones de localización generadas en `ScriptImportService` nunca se guardan en `art_department_notes`.

## 4. Solución o Mejora Recomendada
1. En `Project::deleted()`, eliminar tanto `media/projects/{$project->id}` como `projects/{$project->id}`, y añadir hooks `deleted` en `Scene`, `Shot`, `Character` y `Location` para limpiar sus archivos asociados.
2. En `ScriptImportService::importIntoProject()`, mapear `'art_department_notes' => $locData['description'] ?? ...` en `Location::firstOrCreate()` y eliminar el campo inexistente `'description'` de `Scene::create()`.
3. En `routes/console.php`, eliminar la línea duplicada de `tts:prune-orphans` y añadir `Schedule::command('model:prune')->daily()` y `Schedule::command('media:prune-orphans')->weeklyOn(0, '03:30')`.

