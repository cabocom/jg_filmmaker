# [ISSUE-020] La sincronización automática de la Biblia borra localizaciones del usuario y crea personajes falsos a partir de listas fijas

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Services/ScriptPipelineService.php:364-435,577-609`
- **Categoría:** Robustez / Bugs

## 1. Descripción del Problema
- `syncProjectLocations()`: cuando el nombre de una localización cambia en el slugline y ninguna otra escena la usa, llama a `$currentLoc->delete()` o la **renombra**, perdiendo `art_department_notes`, `lighting_notes` y `address_reference` introducidos manualmente en la Biblia. Es destrucción de datos del usuario sin confirmación ni log (`UserActivityLog`).
- `inferCharactersFromText()` crea filas reales en `characters` a partir de regex heurísticos: lista fija de nombres (`Juan|Pedro|Carlos|Javi|...|Estrella Casilda` — un nombre de un guion concreto codificado en producción), roles ("seguridad", "agente", "cliente", "cura"...), cualquier nombre entre comillas capitalizado y **cualquier línea en MAYÚSCULAS de 2-25 caracteres** (`^[A-ZÁÉÍÓÚÑ\s]{2,25}$`: sluglines parciales, "SÍ", "NO", "CORTE", gritos...). El filtro de reservados es una lista corta. Resultado: Biblia contaminada con falsos positivos que además se reinyectan como contexto al LLM.
- Los personajes inferidos se crean con `bio`/`psychology` textuales fijos ("Detectado automáticamente...") que luego se envían a la IA como si los hubiera escrito el autor.
- El borrado manual de un personaje inferido es inútil: reaparece en el siguiente `mount()` (ISSUE-019).

## 2. Evidencia de Código
```php
if ($otherUsages === 0) { $currentLoc->update(['name' => $parsedName, 'type' => $parsedType]); }   // sobrescribe
...
$currentLoc->delete();
...
preg_match_all('/\b(Juan|Pedro|Carlos|Javi|Javier|...|Estrella\s+Casilda)\b/u', $text, $mCommon);
```

## 3. Impacto Operativo o de Seguridad
Pérdida de notas de arte/iluminación, Biblia ruidosa y regenerada contra la voluntad del usuario, contexto IA contaminado y comportamiento específico de un guion dentro del código de producto.

## 4. Solución Recomendada
- Marcar personajes/localizaciones como `source = auto|manual`; no tocar nunca los `manual` ni los que tengan notas.
- No borrar: proponer sugerencias en la UI ("Se detectaron estos personajes: aceptar/ignorar") y persistir las ignoradas en una lista de exclusión por proyecto.
- Sacar la lista fija de nombres del código (configurable por proyecto o eliminada) y exigir al menos coincidencia con encabezado de diálogo + frecuencia mínima.
- Registrar en `UserActivityLog` cualquier cambio de la Biblia.
- Tests con guiones distintos para evitar sobreajuste al caso de ejemplo.
