# Contexto de Proyecto Actualizado: ISSUE-018

## Componentes y Arquitectura Modificada

### Validación de Estado en Componentes Livewire
- `ScriptEditor`: erradicación de estados no validados. `editStage`, `setBlockStage` y pestañas están acotadas a listas blancas canónicas. Protección contra payloads masivos mediante topes de 50.000 caracteres en textos de bloque y 5.000 en notas editoriales.
- `ContextBibleDrawer`: propiedades de borrado protegidas con `#[Locked]`. La integridad referencial ya no delega en la propiedad cliente `$deletingUsages`; el servidor re-audita deterministamente en base de datos la presencia del personaje o localización en escenas y bloques antes de consentir el borrado. Creación con validación `Rule::unique` scoped por proyecto.
- `ProjectList`: validación estricta de `$status` mediante `#[Rule('required|in:draft,in_progress,completed,archived')]`.

## Métricas de Calidad
- 163 tests pasando al 100% en PHPUnit (6 específicos en `LivewireStateValidationTest`).
- 0 incidencias en Laravel Pint.
