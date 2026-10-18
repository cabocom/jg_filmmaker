# Análisis de Resolución: ISSUE-020

## 1. Contexto y Diagnóstico
En la auditoría de `jg_filmmaker` se descubrió un defecto grave de pérdida de datos del usuario y contaminación heurística de la Biblia dramática en `ScriptPipelineService`:
1. **Destrucción Silenciosa de Localizaciones y Notas:**
   En `syncProjectLocations()`, si el slugline de una escena cambiaba de nombre y ninguna otra escena usaba la localización anterior, el servicio ejecutaba `$currentLoc->delete()` o la renombraba incondicionalmente (`$currentLoc->update(['name' => $parsedName])`), destruyendo de forma irreversible las notas de departamento de arte (`art_department_notes`), esquemas de iluminación (`lighting_notes`) o referencias reales introducidas manualmente por el cineasta en la Biblia.
2. **Lista Fija de Nombres Propios Hardcodeada en Producción:**
   En `inferCharactersFromText()`, se utilizaba una expresión regular con nombres españoles predefinidos (`Juan|Pedro|Carlos|Javi|Javier|...|Estrella Casilda`), derivado de un guion de prueba específico. Esto inyectaba nombres falsos en proyectos ajenos y contaminaba el contexto narrativo que se suministraba al modelo LLM.

## 2. Decisiones de Diseño
- **Preservación Inmutable de Datos de la Biblia:**
  - `syncProjectLocations()` nunca borrará (`delete()`) ni renombrará destructivamente una localización existente en el proyecto.
  - Al cambiar un slugline, el sistema asocia la escena a la nueva localización (creándola si no existe en la Biblia), conservando intacta la localización anterior junto con todas sus notas de arte y producción.
- **Erradicación de Nombres Hardcodeados en Heurísticas:**
  - Eliminación absoluta de la lista fija de nombres del código fuente.
  - La inferencia automática de personajes se restringe a:
    1. Encabezados formales de diálogo estilo Fountain (`^[ \t]*([A-ZÁÉÍÓÚÑ]{2,20})(?:\s*\([^)]*\))?[ \t]*$`) con lista exhaustiva de términos cinematográficos reservados.
    2. Roles dramáticos con artículo explícito (`el camarero`, `la recepcionista`, `el policía`).
    3. Nombres propios citados entre comillas en indicaciones dramáticas.
- **Protección contra Regresiones:**
  - Tests automatizados que verifiquen que cambiar el slugline de una escena no elimina localizaciones existentes ni purga sus notas de arte.

## 3. Plan de Verificación
- Pruebas automatizadas en `BibleSyncPreservationTest` que confirmen la no destrucción de notas y localizaciones y la ausencia de nombres hardcodeados arbitrarios.
