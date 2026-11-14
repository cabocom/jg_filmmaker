# ISSUE-020 Walkthrough: Preservación Inmutable de Entidades de la Biblia y Eliminación de Lista Hardcodeada

## Resumen de Cambios

1. **Preservación Inmutable de Localizaciones:**
   - En `ScriptPipelineService::syncProjectLocations()`, se eliminaron de raíz las llamadas a `$currentLoc->delete()` y los renombrados destructivos de localizaciones antiguas.
   - Si una escena cambia de slugline, se busca o crea la nueva localización y se reasocia la escena, manteniendo la localización previa intacta con todas sus notas del departamento de arte, notas de iluminación y referencias geográficas.
   - La sincronización precarga todas las localizaciones del proyecto en memoria para eliminar el N+1 de queries.

2. **Limpieza de Inferencia de Personajes:**
   - Se removió la regex fija de nombres españoles (`Juan|Pedro|Carlos|Javi...|Estrella Casilda`).
   - La detección ahora se basa en patrones sintácticos neutros: encabezados Fountain (`CAMARERO`, `DETECTIVE`), roles dramáticos con artículo (`el camarero`, `la enfermera`) y roles en acción.

3. **Verificación Automatizada:**
   - `test_sync_project_locations_never_deletes_or_destroys_user_notes` en `tests/Feature/BibleSyncPreservationTest.php` verifica que las notas manuales de arte no se pierden al editar sluglines.
   - `test_infer_characters_from_text_does_not_inject_arbitrary_hardcoded_names` valida que textos neutros no introduzcan personajes inventados.
