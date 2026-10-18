# Análisis Técnico: [ISSUE-012] Contexto de Biblia roto: atributos inexistentes (`role`, `personality_notes`, `description`) y claves incompatibles con el plugin (`bio`)

- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Severidad:** Alta
- **Fecha:** 18 de octubre de 2026
- **Categoría:** Robustez / Integración de Contratos de IA
- **Vulnerabilidades / Errores:** CWE-1068 (Inconsistency Between Implementation and Documented Design), CWE-703 (Improper Check for Unusual or Exceptional Conditions)

---

## 1. Diagnóstico del Estado Previo

1. **Atributos Inexistentes en Eloquent:**
   - En `Character.php`, los atributos mass-assignable son `project_id`, `name`, `bio`, `psychology`, `costume_notes`, `custom_attributes`. **No existen** las columnas `role`, `personality_notes` ni `description`.
   - En `Location.php`, los atributos son `project_id`, `name`, `type`, `art_department_notes`, `lighting_notes`, `address_reference`. **No existe** la columna `description`.
   - En `ScriptPipelineService::structureBlock()` y `formatBlockToScreenplay()`:
     ```php
     'role' => $c->role ?? 'personaje',
     'personality_notes' => $c->personality_notes ?? '',
     ```
     Al no estar activo `Model::preventAccessingMissingAttributes()`, Eloquent retornaba `null` silenciosamente sin emitir advertencias.
   - En `ScriptEditor::analyzePlotHoles()`:
     ```php
     $characters = $this->project->characters()->get()->map(fn ($c) => [
         'name' => $c->name,
         'role' => $c->role,
         'description' => $c->description,
     ]);
     ```
     Todos los personajes y localizaciones se enviaban con `role = null`, `description = null`.

2. **Incompatibilidad de Claves con el Plugin de IA (`jg_plugin_filmmaker`):**
   - El plugin Python leía `c.get('bio')`, pero Laravel enviaba únicamente `personality_notes` (vacío).
   - Como resultado, la biografía, notas psicológicas y notas de vestuario creadas minuciosamente por el cineasta en la Biblia del proyecto **jamás llegaban al contexto del LLM**. La Biblia dramática era inefectiva para guiar la inferencia narrativa.

3. **Inexistencia de un Mapeador Canónico:**
   - La conversión a arreglo para consumo de agentes estaba duplicada e inconsistente en múltiples sitios del código.

---

## 2. Alternativas Evaluadas

1. **Añadir columnas `role`, `personality_notes`, `description` a las tablas de base de datos:**
   - *Descartada:* La arquitectura canónica de `jg_filmmaker` define con precisión `bio`, `psychology`, `costume_notes` para `Character` y `art_department_notes`, `lighting_notes` para `Location`. Duplicar o crear columnas ambiguas introduce deuda técnica y confusión en la UI del drawer de la Biblia.
2. **Crear métodos canónicos `toAgentContext()` en los modelos, sincronizar el consumo en `jg_plugin_filmmaker` y habilitar `Model::preventAccessingMissingAttributes()` (Opción Seleccionada):**
   - *Ventajas:*
     - Centralización estricta del contrato en `Character::toAgentContext()` y `Location::toAgentContext()`.
     - Todo el pipeline (`ScriptPipelineService`, `ScriptEditor`, etc.) consume la misma estructura tipada.
     - `jg_plugin_filmmaker` formatea de manera enriquecida `bio`, `psychology`, `costume_notes`, `art_department_notes` y `lighting_notes` en los prompts del LLM.
     - La activación de `Model::preventAccessingMissingAttributes(! $this->app->isProduction())` previene regresiones en cualquier futuro acceso a modelos Eloquent durante desarrollo y CI.

---

## 3. Plan de Implementación

1. **Modelos `Character` y `Location` (`app/Models/`):**
   - Implementar método público `toAgentContext(): array` en ambos modelos retornando sus atributos reales.

2. **Servicio de Pipeline (`app/Services/ScriptPipelineService.php`):**
   - En `structureBlock()` y `formatBlockToScreenplay()`: invocar `$c->toAgentContext()` y `$block->scene->location->toAgentContext()`.
   - En `syncProjectCharacters()`: utilizar `toAgentContext()`, y al inferir personajes guardar la descripción en `bio`.

3. **Componente `ScriptEditor` (`app/Livewire/Studio/ScriptEditor.php`):**
   - En `analyzePlotHoles()`: utilizar `toAgentContext()` para personajes y localizaciones.

4. **Configuración de Eloquent (`app/Providers/AppServiceProvider.php`):**
   - Añadir `Model::preventAccessingMissingAttributes(! $this->app->isProduction())`.

5. **Plugin de IA (`jg_plugin_filmmaker/structuring.py`):**
   - Actualizar `structure_treatment()` para extraer y formatear `bio`, `psychology`, `costume_notes` en el prompt del sistema.

6. **Pruebas Automatizadas:**
   - Crear test en `tests/Unit/BibleContextContractTest.php` comprobando que `toAgentContext()` incluye `bio`, `psychology` y `costume_notes`, y que `Model::preventAccessingMissingAttributes()` está activo.
   - Ejecutar suites completas en `jg_filmmaker` y `jg_plugin_filmmaker`.
