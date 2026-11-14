# ISSUE-023 Walkthrough: Transacciones Atómicas en Numeración y Mitigación de Carreras

## Resumen de Cambios

1. **Aislamiento Atómico en Métodos del Editor de Guión:**
   - En `ScriptEditor::createNewBlock()`: la consulta de orden máximo y el desplazamiento de bloques posteriores se ejecutan dentro de `DB::transaction()` con `lockForUpdate()`.
   - En `ScriptEditor::createScene()`: el cálculo de `scene_order` y la creación de la escena con su bloque inicial se ejecutan de manera atómica con `lockForUpdate()`.
   - En `ScriptEditor::addShot()`: la determinación de `shot_number` y la toma por defecto se envuelven en una transacción con `lockForUpdate()`.
   - En `ScriptEditor::saveNewTake()`: el incremento de `take_number` opera bajo transacción con `lockForUpdate()`.

2. **Verificación Automatizada:**
   - `test_atomic_numbering_on_block_scene_shot_and_take_creation` en `tests/Feature/NumberingRacesAndOrphanMediaTest.php` valida que la numeración resultante es estrictamente correlativa sin huecos ni colisiones.
