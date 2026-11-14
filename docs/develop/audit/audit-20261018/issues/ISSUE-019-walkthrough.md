# ISSUE-019 Walkthrough: Eliminación de Mutaciones en `mount()` y Optimización N+1

## Resumen de Cambios

1. **Idempotencia de Peticiones GET:**
   - Se removieron las llamadas a `syncProjectCharacters` y `syncProjectLocations` de `ScriptEditor::mount()`.
   - La carga inicial del editor de guiones ahora es una operación puramente de lectura (read-only), evitando efectos colaterales de escritura en BD.

2. **Eliminación de N+1 Queries en Detección de Personajes:**
   - En `ScriptPipelineService::inferCharactersFromText()`, se precargan los nombres existentes del proyecto mediante `pluck('name')` en un conjunto en memoria.
   - Las comprobaciones de unicidad se ejecutan en O(1) sin consultar `whereRaw('LOWER(name) = ?')` por cada candidato.

3. **Verificación Automatizada:**
   - `test_mount_is_idempotent_and_does_not_mutate_bible_entities` en `tests/Feature/BibleSyncPreservationTest.php` valida que montar el componente no altera el recuento de personajes ni localizaciones.
