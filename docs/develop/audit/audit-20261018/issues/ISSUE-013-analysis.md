# Análisis Técnico - ISSUE-013: Pérdida de `user_locks` por actualización perdida (Race Condition en llamadas IA)

## 1. Contexto y Situación Inicial
En `jg_filmmaker`, las preferencias del usuario, términos fijados (`pinned_terms`), modificaciones manuales (`manual_diffs`), bloqueos de etapas (`locked_fields`) y estado de auditoría de inconsistencias (`plot_hole_stage`, `justified_plot_holes`) se serializan en una única columna JSON `script_blocks.user_locks`.

### Flujo Problemático Identificado:
1. **Llamadas IA de larga duración (30-75s) sin aislamiento transaccional:**
   - En `ScriptPipelineService::transitionToStructured`, `transitionToScreenplay` y `generateShotsBreakdown`, se lee `$userLocks = $this->resolveUserLocks($block)` **antes** de despachar la petición HTTP al agente de IA.
   - Durante los segundos o minutos que tarda la inferencia, el usuario puede fijar un término (`pinned_terms`), alternar un candado (`toggleStageLock`), justificar un agujero de guión (`savePlotHoleJustification`) o editar un texto desde otra pestaña o dispositivo.
   - Cuando el servicio de IA responde, el pipeline reescribe el bloque completo con `$block->update(['user_locks' => $userLocks])`, utilizando la instantánea en memoria obtenida antes de la llamada. Todas las mutaciones concurrentes se pierden irremediablemente (Lost Update Anomaly).
2. **Sobrescritura de etapas bloqueadas concurrentemente:**
   - Si un usuario activa un candado (`toggleStageLock`) mientras la IA está procesando la regeneración, la verificación inicial `isStageLocked()` ya se superó. Al volver la llamada, la IA sobrescribe el texto ignorando la decisión del autor.
3. **Pérdida masiva en `analyzePlotHoles`:**
   - `ScriptEditor::analyzePlotHoles` carga todos los bloques del proyecto al inicio. Tras una auditoría exhaustiva en 3 pasos (que puede tomar minutos), itera sobre la colección en memoria y ejecuta `$block->update(['user_locks' => $userLocks])` para cada bloque, machacando cualquier candado o edición guardada durante la auditoría.
4. **Ausencia de control de versiones optimista (Optimistic Locking):**
   - En `ScriptEditor::saveBlock` y `saveBlockFromDrawer`, el usuario edita textos en un formulario. Si la IA o un proceso en segundo plano actualiza el bloque mientras el formulario está abierto, al guardar se compara el contenido en BD con el del formulario y se registra un falso diff manual (`recordManualOverride`) con el texto viejo, o se sobreescribe el trabajo ajeno sin advertencia.

---

## 2. Plan de Solución Arquitectónica

### A. Migración de Base de Datos y Modelo `ScriptBlock`
- Crear migración para agregar `lock_version` (`unsignedInteger`, por defecto 1) a la tabla `script_blocks`.
- En `ScriptBlock`:
  - Registrar `lock_version` en `$fillable` y `$casts`.
  - Crear método transaccional atómico `mutateUserLocks(callable $mutator): self` con `lockForUpdate()` para aislar cualquier modificación directa a los candados y atributos del bloque.

### B. Transaccionalidad y Re-chequeo en `ScriptPipelineService`
- En `transitionToStructured`:
  - Mantener verificación previa rápida de `isStageLocked('structured')`.
  - Ejecutar llamada de IA fuera de transacción (para no retener bloqueos en la base de datos durante la latencia de red).
  - Al recibir la respuesta de IA, abrir `DB::transaction()`:
    1. Re-leer el bloque con `ScriptBlock::whereKey($block->id)->lockForUpdate()->first()`.
    2. Re-chequear `isStageLocked('structured')`. Si fue bloqueado mientras corría la IA, abortar la sobreescritura del texto respetando la soberanía del usuario.
    3. Obtener `$freshLocks` del registro bloqueado en BD.
    4. Fusionar la telemetría IA y purgar únicamente los diffs manuales de la etapa regenerada sobre `$freshLocks`, preservando intactos los `pinned_terms`, justificaciones y diffs de otras etapas.
    5. Actualizar el bloque incrementando `lock_version`.
- Replicar idéntico patrón en `transitionToScreenplay` y `generateShotsBreakdown`.
- En `recordManualOverride`, envolver la operación en transacción con `lockForUpdate()`.

### C. Aislamiento Atómico en `ScriptEditor`
- En `toggleStageLock`: Re-leer y mutar bajo bloqueo transaccional.
- En `removePinnedTerm`: Re-leer y mutar bajo bloqueo transaccional.
- En `savePlotHoleJustification`: Re-leer y mutar bajo bloqueo transaccional.
- En `analyzePlotHoles` y `clearPlotHoles`:
  - Actualizar cada bloque de forma atómica re-leyendo bajo transacción `lockForUpdate()`, modificando estrictamente `plot_hole` y `plot_hole_stage`, sin pisar los `pinned_terms`, `manual_diffs` ni candados concurrentes.
- En `saveBlock` y `saveBlockFromDrawer`:
  - Capturar `lock_version` al abrir el editor/drawer (`$editLockVersion`).
  - Al guardar, validar bajo transacción si `$fresh->lock_version !== $this->editLockVersion`. Si difiere, notificar error de concurrencia al usuario para evitar sobreescritura perdida o diffs falsos.

---

## 3. Estrategia de Pruebas
1. **Prueba de carrera en `transitionToStructured`:**
   - Iniciar la llamada simulada al agente (`AgentClientService`) e inyectar una mutación en `user_locks` (ej. añadir un término en `pinned_terms` o activar candado) dentro del callback.
   - Verificar que al terminar la llamada, el término fijado NO se ha perdido en base de datos.
2. **Prueba de protección de candado tardío:**
   - Bloquear la etapa durante la ejecución de la IA y verificar que el texto del bloque no es sobrescrito.
3. **Prueba de control optimista en `ScriptEditor`:**
   - Verificar que `saveBlock` detecta cuando la versión del bloque fue incrementada por otro proceso y previene la actualización perdida.
