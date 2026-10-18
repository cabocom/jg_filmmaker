# ISSUE-025 Análisis: Ficheros de Vídeo Huérfanos y Limpieza Física en Cascada

## 1. Diagnóstico del Problema
- `Take::deleting` intentaba eliminar el archivo físico de vídeo antes de la confirmación del borrado en base de datos. Si la transacción abortaba, quedaba un registro huérfano apuntando a un archivo inexistente.
- Al eliminarse una escena (`Scene`) o un proyecto completo (`Project`), las restricciones de clave foránea a nivel de BD (`cascadeOnDelete()`) eliminaban los planos y tomas por debajo sin disparar los eventos de ciclo de vida Eloquent de `Take`.
- Como resultado, los ficheros de vídeo (`media/projects/{id}/...`), que pesan hasta 100 MB cada uno, quedaban abandonados indefinidamente en disco, provocando fugas de almacenamiento y violaciones potenciales de la política de supresión de datos.

## 2. Estrategia de Mitigación
1. En `Take`: migrar la limpieza a `static::deleted` y diferir la eliminación física a `DB::afterCommit`.
2. En `Scene`: registrar en `static::deleting` la recopilación de ficheros de tomas dependientes y su eliminación física tras confirmación en `DB::afterCommit`.
3. En `Project`: registrar en `static::deleted` la purga recursiva del directorio de medios `media/projects/{id}` en discos `local` y `public` mediante `DB::afterCommit`.
4. Crear el comando Artisan `php artisan media:prune-orphans` para detectar y purgar ficheros no referenciados en disco.
