# Análisis de Resolución: ISSUE-018

## 1. Contexto y Diagnóstico
En la auditoría técnica de `jg_filmmaker` se detectaron propiedades públicas manipulables en componentes Livewire (`ScriptEditor`, `ContextBibleDrawer`, `ProjectList`):
1. **Etapas de Bloque no Validadas (`stage`):**
   `saveBlock` y `saveBlockFromDrawer` persistían el valor de `$this->editStage` directamente en la base de datos sin validar su pertenencia al conjunto de estados válidos (`raw`, `structured`, `screenplay`, `shots`, `editing`), provocando `QueryException` 500 ante valores espurios.
2. **Textos sin Límites de Longitud:**
   Campos como `newBlockRawText`, `editRawText` o `editingTakeNotesText` carecían de límite `max`, permitiendo payloads gigantes que saturaban memoria y el contexto de los modelos LLM.
3. **Bypass de Integridad Referencial en Biblia:**
   En `ContextBibleDrawer`, la verificación de borrado se basaba en la propiedad pública `$deletingUsages`. Un cliente podía sobreescribir este array a `[]` en el payload y eliminar personajes o localizaciones en uso activo en el guión.
4. **Falta de Validación de Unicidad en Creación de Biblia:**
   `createCharacter` y `createLocation` no validaban unicidad a nivel Livewire, produciendo excepciones SQL 500 no gestionadas.
5. **Estado de Proyecto no Acotado:**
   En `ProjectList`, `$status` se guardaba sin validar contra los estados permitidos del proyecto.

## 2. Decisiones de Diseño
- **Listas Blancas Estrictas en Acciones Livewire:**
  - `editStage`, `setBlockStage` y selectores de vistas acotados mediante `Rule::in` e `in_array`.
  - Validación de textos con límites realistas: `max:50000` para textos dramáticos y `max:5000` para notas.
- **Protección de Propiedades de Borrado con `#[Locked]`:**
  - `$deletingType`, `$deletingId`, `$deletingName` y `$deletingUsages` quedan blindadas contra manipulación en cliente.
- **Re-Auditoría de Usos en Servidor (Defensa en Profundidad):**
  - `confirmDelete()` audita de forma autónoma en base de datos las apariciones del personaje/localización antes de llamar a `delete()`.
- **Validación de Unicidad Scoped por Proyecto:**
  - `Rule::unique(...)->where('project_id', $this->project->id)` para evitar errores 500.

## 3. Plan de Verificación
- Pruebas automatizadas en `LivewireStateValidationTest` cubriendo el rechazo de etapas inválidas, payload masivo, re-auditoría en servidor contra borrado forzado y validación de unicidad.
- Suite completa de tests pasando al 100% y linter Pint sin fallos.
