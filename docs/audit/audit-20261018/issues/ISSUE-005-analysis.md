# Análisis Técnico: [ISSUE-005] `reorderBlock()` permite mover bloques de otros proyectos a una escena propia (robo/destrucción de guion)

- **Componente:** `jg_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Vulnerabilidad:** CWE-639 (Authorization Bypass Through User-Controlled Key) / CWE-284 (Improper Access Control) / Referential Integrity Violation

---

## 1. Diagnóstico del Estado Previo

En `ScriptEditor::reorderBlock(string $draggedBlockId, int $targetOrder, ?string $sceneId)`:

1. **Búsqueda Global sin Ámbito de Tenencia:**
   - La línea `ScriptBlock::find($draggedBlockId)` buscaba el bloque arrastrado en toda la base de datos sin acotar por el proyecto activo.
   - De igual forma, `$sceneTargetId` no se validaba contra las escenas pertenecientes a `$this->project`.

2. **Robo o Destrucción Trans-Proyecto:**
   - Un usuario malicioso podía invocar `reorderBlock` pasando como `$draggedBlockId` el UUID de un bloque perteneciente a una obra ajena y como `$sceneId` una escena propia. El código ejecutaba `$dragged->update(['scene_id' => $sceneTargetId])`, transfiriendo la titularidad del bloque al proyecto del atacante.
   - El cineasta víctima perdía de forma irreversible el bloque de su guión sin que quedara rastro en sus logs (ya que la auditoría `logActivity` se registraba en el proyecto del atacante).

3. **Inconsistencia y Corrupción Referencial:**
   - Los planos técnicos (`shots`) asociados al bloque arrastrado mantenían el `scene_id` de la escena de origen. Al mover el bloque entre escenas (o proyectos), los planos quedaban desincronizados respecto a la escena de su bloque contenedor.
   - La escena de origen no reindexaba sus `block_order`, dejando huecos en la numeración secuencial.

---

## 2. Alternativas Evaluadas

1. **Comprobar únicamente que `$dragged->scene->project_id === $this->project->id`:**
   - *Descartada:* Si el bloque no existe o es de otro proyecto, simplemente ignorar silenciosamente no emite la respuesta HTTP 404 esperada ante violaciones de control de acceso.
2. **Uso de `projectBlock()` y `projectScene()` con reasignación atómica de planos y reindexación bidireccional (Opción Seleccionada):**
   - *Ventajas:*
     - Tanto `$draggedBlockId` como `$sceneTargetId` se validan mediante los métodos de ámbito de proyecto `$this->projectBlock($draggedBlockId)` y `$this->projectScene($sceneTargetId)`. Si cualquiera no pertenece a este proyecto, arroja 404 inmediato.
     - Si el bloque se traslada entre escenas distintas:
       - Se actualiza `shots.scene_id` para todos los planos dependientes dentro de la misma transacción.
       - Se reindexa la escena de origen para garantizar la continuidad del orden de bloques (`block_order`).
       - Se indexa la escena de destino insertando el bloque en la posición solicitada.

---

## 3. Plan de Implementación

1. **Componente `ScriptEditor` (`app/Livewire/Studio/ScriptEditor.php`):**
   - En `reorderBlock()`:
     - Resolver `$targetScene = $this->projectScene($sceneTargetId);`.
     - Dentro de `DB::transaction()`: resolver `$dragged = $this->projectBlock($draggedBlockId);`.
     - Detectar si `$dragged->scene_id !== $targetScene->id`:
       - Almacenar `$originSceneId = $dragged->scene_id;`.
       - Actualizar `$dragged->update(['scene_id' => $targetScene->id]);`.
       - Actualizar planos: `Shot::where('script_block_id', $dragged->id)->update(['scene_id' => $targetScene->id]);`.
       - Reindexar bloques de `$originSceneId`.
     - Proceder a reindexar los bloques de `$targetScene->id` ubicando `$dragged` en `$targetOrder`.

2. **Pruebas Automatizadas de Aislamiento (`tests/Feature/BlockReorderCrossTenantIsolationTest.php`):**
   - Verificar que intentar mover un bloque de un proyecto B a una escena del proyecto A resulta en 404.
   - Verificar que el bloque del proyecto B conserva intactos su `scene_id` y `block_order`.
   - Verificar que al mover un bloque entre dos escenas legítimas del mismo proyecto, los planos asociados actualizan su `scene_id` y ambas escenas quedan ordenadas secuencialmente sin huecos.
