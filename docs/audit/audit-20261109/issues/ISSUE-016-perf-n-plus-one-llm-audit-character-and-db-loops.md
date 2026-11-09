# [ISSUE-016] Llamadas N+1 a IA en `ContextBibleDrawer::auditCharacterUsages`, Cómputo `isAudioComplete` en Cada Render y Métodos Huérfanos en `ScriptEditor`

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php:337-370, 417-427, 456-468`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:2990-2993, 3182-3244`
- **Categoría:** Rendimiento / Escalabilidad

## 1. Descripción
Se han detectado tres problemas de rendimiento y superficie expuesta en los componentes Livewire del estudio:

1. **Llamadas HTTP/LLM N+1 y Ejecución Duplicada en `ContextBibleDrawer::auditCharacterUsages()`:**
   - La herramienta `audit_character_usage` de `jg_plugin_filmmaker` fue diseñada para recibir una lista de fragmentos (`text_snippets: list[str]`) y auditarlos en una sola llamada al LLM.
   - Sin embargo, `ContextBibleDrawer::auditCharacterUsages()` recorre escena por escena y bloque por bloque, y **por cada bloque donde aparece el nombre del personaje realiza una llamada HTTP individual** a `$agentClient->auditCharacterUsage($charName, $texts)`. Si el personaje aparece en 25 bloques del guion, pulsar el icono de papelera ejecuta **25 peticiones HTTP/LLM secuenciales** antes de mostrar el modal de confirmación.
   - Además, cuando el usuario pulsa "Confirmar" en el modal, `confirmDelete()` (línea 459) **vuelve a invocar `$this->auditCharacterUsages($this->deletingId)`**, repitiendo las 25 llamadas HTTP/LLM por segunda vez.

2. **Re-parseo Completo del Guion y Sondeo de Disco en Cada Render (`getIsAudioCompleteProperty`):**
   - En cada ciclo de renderizado de `ScriptEditor` (`script-editor.blade.php` evalúa `$this->isAudioComplete`), `ScriptEditor::getIsAudioCompleteProperty()` invoca `$tts->isProjectAudioComplete($this->project)`, que llama a `buildManifestForScope($project, 'project', $project->id)`.
   - Esto ejecuta `$project->load(['scenes.scriptBlocks', 'characters'])`, parsea con regex todos los bloques del guion, ejecuta `saveQuietly()`/`create()` si detecta hablantes nuevos y comprueba `file_exists()` en disco por cada segmento en cada interacción de Livewire (incluso al abrir un modal o editar un título).

3. **Métodos Públicos Huérfanos de Importación Destructiva en `ScriptEditor` (`3182-3244`):**
   - Aunque en `ISSUE-058` se eliminó de la interfaz del estudio el botón y el modal de reemplazo destructivo de guion para impedir sobreescrituras accidentales dentro de un proyecto existente, los métodos públicos `openImportModal()`, `processImportFile()` y `confirmAndExecuteDestructiveImport()` permanecen expuestos en `ScriptEditor.php` (y sin llamada explícita a `$this->authorize('update', $this->project)`).

## 2. Evidencia de Código
En [`jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ContextBibleDrawer.php#L343-L370):
```php
        foreach ($scenes as $scene) {
            foreach ($scene->scriptBlocks as $block) {
                $texts = array_values(array_filter([
                    $block->raw_text,
                    $block->structured_text,
                    $block->screenplay_content,
                ]));
                // ...
                if ($hasMatch) {
                    $auditResult = $agentClient->auditCharacterUsage($charName, $texts);
                    $isDramaticCharacter = (bool) ($auditResult['output']['is_dramatic_character'] ?? true);
```

Y en [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php#L2990-L2993):
```php
    public function getIsAudioCompleteProperty(TextToSpeechService $tts): bool
    {
        return $tts->isProjectAudioComplete($this->project);
    }
```

## 3. Impacto Operativo o Riesgo
- **Bloqueo al Intentar Eliminar un Personaje Principal:** Intentar borrar un personaje recurrente que interviene en 30 bloques dispara 30 llamadas HTTP/LLM seguidas solo para abrir el modal de advertencia.
- **Sobrecarga en Cada Interacción Livewire:** Re-parsear el guion completo para calcular `isAudioComplete` penaliza la latencia de cualquier interacción en el editor en guiones largos.

## 4. Solución o Mejora Recomendada
1. En `ContextBibleDrawer::auditCharacterUsages()`, agrupar los fragmentos candidatos en una sola llamada batch a `$agentClient->auditCharacterUsage($charName, $allCandidateSnippets)` (limitado a los primeros 10 fragmentos).
2. Cachear el resultado de `isProjectAudioComplete($project)` durante 15-30 segundos invalidándolo al sintetizar nuevos segmentos o al modificar un `ScriptBlock`.
3. Eliminar de `ScriptEditor.php` los métodos muertos `openImportModal()`, `processImportFile()` y `confirmAndExecuteDestructiveImport()` junto con sus propiedades públicas asociadas.
