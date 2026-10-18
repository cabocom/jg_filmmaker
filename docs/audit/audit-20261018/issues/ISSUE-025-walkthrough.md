# ISSUE-025 Walkthrough: Limpieza Física de Medios en Cascada y Purga de Huérfanos

## Resumen de Cambios

1. **Gobernanza de Eliminación en Modelos Eloquent:**
   - En `Take::booted()`: se sustituyó `static::deleting` por `static::deleted`, envolviendo la eliminación física del archivo en `DB::afterCommit`.
   - En `Scene::booted()`: se añadió `static::deleting` que localiza todas las tomas de sus planos y programa su eliminación física en `DB::afterCommit`.
   - En `Project::booted()`: se añadió `static::deleted` que purga recursivamente `Storage::disk('local')->deleteDirectory("media/projects/{$project->id}")` y `public` mediante `DB::afterCommit`.

2. **Comando Artisan de Reconciliación:**
   - En `routes/console.php`: comando `media:prune-orphans` que escanea los directorios de medios en disco, verifica la existencia del registro en la tabla `takes`, y elimina cualquier archivo huérfano.

3. **Verificación Automatizada:**
   - `tests/Feature/NumberingRacesAndOrphanMediaTest.php` valida que:
     - El borrado de una toma elimina su archivo físico.
     - El borrado de una escena elimina los archivos de todas sus tomas.
     - El borrado de un proyecto purga el directorio completo.
     - El comando `media:prune-orphans` detecta y purga archivos huérfanos.
