# Análisis Técnico - ISSUE-015: Preservación de Modificaciones Manuales y Candados en Regeneraciones de IA

## 1. Contexto y Situación Inicial
En `jg_filmmaker`, cuando el autor decide regenerar una etapa (por ejemplo, estructuración a tratamiento o formateo a guion literario):
1. **Destrucción irreversible de `manual_diffs`:**
   - El pipeline ejecutaba `array_filter` eliminando todos los diffs manuales de esa etapa en `user_locks['manual_diffs']`. Tras regenerar, el historial de ediciones manuales del autor desaparecía por completo sin copia de seguridad ni snapshot en el registro de actividad (`UserActivityLog`).
2. **Desglose de planos (`generateShotsBreakdown`) ignora candados:**
   - `generateShotsBreakdown()` no verificaba `isStageLocked('shots')`. Si el autor pulsa "Desglosar planos" o el pipeline procesa la etapa, se ejecutaba `updateOrCreate` sobreescribiendo descripciones y tipos de plano configurados a mano por el equipo de rodaje.
   - En `ScriptEditor::breakdownBlockShots` no existía comprobación de candado en interfaz, permitiendo disparar llamadas de IA que pisaban planos preexistentes.
3. **Falta de validación y estado ambiguo en `toggleStageLock`:**
   - `toggleStageLock` admitía cualquier cadena arbitraria sin validar pertenencia a las etapas del pipeline (`structured`, `screenplay`, `shots`).
   - `isStageLocked('structured')` devuelve `true` si `screenplay` está bloqueado. Si el usuario desbloqueaba `structured`, pero `screenplay` permanecía en `locked_fields`, el tratamiento continuaba bloqueado internamente, confundiendo al usuario.
4. **Sustitución voraz en `applyUserLocks`:**
   - `str_replace($original, $override, $result)` reemplaza todas las instancias globales. Si el texto original modificado era una palabra común o corta (ej. "él"), modificaba inadvertidamente todas las ocurrencias del guion.

---

## 2. Plan de Solución Arquitectónica

### A. Archivado No Destructivo de Diffs Manuales
- En `ScriptPipelineService::transitionToStructured` y `transitionToScreenplay`:
  - Al regenerar una etapa, los diffs manuales de dicha etapa no se eliminan. Se trasladan a la clave `user_locks['archived_diffs']` añadiendo marca temporal `'archived_at' => now()->toIso8601String()`.
- En `ScriptEditor::structureBlock` y `formatBlockToScreenplay`:
  - En la llamada a `logActivity`, registrar en el payload el contenido previo (`previous_structured_text` o `previous_screenplay_content`), asegurando trazabilidad completa e histórico recuperable.

### B. Blindaje de la Etapa de Planos (`shots`)
- En `ScriptPipelineService::generateShotsBreakdown`:
  - Comprobar `if ($block->isStageLocked('shots')) { return $block->shots()->orderBy('shot_number')->get(); }`.
- En `ScriptEditor::breakdownBlockShots`:
  - Verificar `$block->isStageLocked('shots')` y detener la ejecución con mensaje informativo si la etapa está protegida.
- Preservar planos con tomas asociadas (`$shot->takes()->count() > 0`) para no degradar el estado editorial.

### C. Validación y Desacoplamiento en `toggleStageLock`
- Validar rígidamente `$stage` contra la lista blanca: `['structured', 'screenplay', 'shots']`.
- Si el usuario desbloquea `'structured'`, retirar también `'screenplay'` para garantizar que el tratamiento sea efectivamente regenerable por la IA según la intención explícita del usuario.

### D. Sustitución Anclada en `applyUserLocks`
- Sustituir `str_replace` por `substr_replace` en la primera ocurrencia (`strpos`), evitando reemplazos voraces o colaterales en subcadenas repetidas a lo largo de la escena.

---

## 3. Estrategia de Pruebas
1. **Prueba de Respeto al Candado de Planos:**
   - Bloquear la etapa `shots` y llamar a `generateShotsBreakdown()`. Verificar que no altera los planos en base de datos.
2. **Prueba de Archivado de Diffs:**
   - Añadir un diff manual a `structured`, regenerar el bloque y verificar que el diff fue trasladado a `archived_diffs` y no destruido.
3. **Prueba de Desbloqueo Cascada en `toggleStageLock`:**
   - Bloquear `screenplay`, desbloquear `structured` y verificar que `isStageLocked('structured')` pasa a ser `false`.
