# [ISSUE-014] Multiplicación N+1 de Llamadas HTTP/LLM Síncronas en `ScriptPipelineService`

- **Severidad:** Alta
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Services/ScriptPipelineService.php:56-95, 165-202, 461-538, 867-881`, `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:1299-1340, 1451-1511`
- **Categoría:** Rendimiento / Escalabilidad

## 1. Descripción
En `ScriptPipelineService`, cada transición individual de un bloque (`transitionToStructured` y `transitionToScreenplay`) ejecuta **tres llamadas HTTP/LLM secuenciales** a `jg_agente` en lugar de una:

1. **Llamada 1 (`syncProjectCharacters`):** Concatena el texto de **todos los bloques de todas las escenas del proyecto** y llama a `AgentClientService::extractCharacters($fullCorpus, ...)` (`jg_agente` -> LLM). Además, a continuación ejecuta `syncProjectLocations($project)`, que recorre todas las escenas del proyecto haciendo consultas y updates en base de datos.
2. **Llamada 2 (`inferCharactersFromText`):** Vuelve a llamar a `AgentClientService::extractCharacters($rawText, ...)` pasando el texto del bloque individual para inferir personajes presentes en ese bloque.
3. **Llamada 3 (`structureTreatment` o `formatScreenplay`):** Finalmente invoca la herramienta de transformación del bloque (`treatment_structuring` o `screenplay_formatter`).

Cuando el usuario ejecuta `batchRunFullPipeline()` o `runScenePipeline()` sobre una escena o proyecto con $N$ bloques:
- Por cada bloque se invoca `transitionToStructured` (3 llamadas LLM) + `transitionToScreenplay` (3 llamadas LLM) + `generateShotsBreakdown` (1 llamada LLM) = **7 llamadas HTTP/LLM secuenciales por bloque**.
- Para un guion moderado de 20 bloques, un solo clic en "Ejecutar Pipeline en Lote" dispara **140 llamadas HTTP/LLM síncronas en serie** dentro de una única petición HTTP de Livewire, reenviando 40 veces el corpus completo del proyecto a `extract_characters`.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Services/ScriptPipelineService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php#L73-L95):
```php
        $project = $block->scene->project;
        $this->syncProjectCharacters($project);
        $this->syncProjectLocations($project);
        $project->load('characters');
        // ...
        $inferredCharacters = $this->inferCharactersFromText($rawText, $charactersContext);
        $mergedCharactersContext = array_values(array_merge($charactersContext, $inferredCharacters));

        $result = $this->agentClient->structureTreatment(
            rawText: $rawText,
            charactersContext: $mergedCharactersContext,
            locationContext: $locationContext,
            userLocks: $userLocks
        );
```

Y el mismo patrón se repite íntegramente en [`transitionToScreenplay()`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php#L180-L202) y dentro del bucle de [`batchRunFullPipeline()`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/ScriptPipelineService.php#L867-L881):
```php
    public function batchRunFullPipeline(Collection $blocks): array
    {
        $stats = ['structured' => 0, 'screenplay' => 0, 'shots' => 0, 'skipped_locked' => 0];

        foreach ($blocks as $block) {
            if (! $block->isStageLocked('structured')) {
                $this->transitionToStructured($block);
                $stats['structured']++;
            }
            // ...
            if (! $block->isStageLocked('screenplay')) {
                $this->transitionToScreenplay($block);
                $stats['screenplay']++;
            }
            // ...
            if (! $block->isStageLocked('shots')) {
                $this->generateShotsBreakdown($block);
                $stats['shots']++;
            }
        }
```

## 3. Impacto Operativo o Riesgo
- **Latencia Extrema y Timeouts HTTP:** Procesar 10 bloques en lote requiere 70 viajes de red + inferencia LLM (fácilmente >90-180 segundos), provocando timeouts en el cliente y agotando los límites de RPM (Requests Per Minute) del proveedor de IA.
- **Inflación de Consumo de Tokens (~3x a 5x):** Enviar el guion entero dos veces por cada bloque procesado multiplica innecesariamente los `prompt_tokens` facturados.

## 4. Solución o Mejora Recomendada
1. Añadir un parámetro `$syncProjectContext = true` en `transitionToStructured()` y `transitionToScreenplay()`. En las operaciones por lote (`batchTransitionToStructured`, `batchTransitionToScreenplay`, `batchRunFullPipeline`, `runScenePipeline`), ejecutar `syncProjectCharacters($project)` y `syncProjectLocations($project)` **una única vez al inicio del lote** y pasar `$syncProjectContext = false` a cada bloque individual.
2. En `inferCharactersFromText()`, si `syncProjectCharacters()` acaba de ejecutarse sobre el corpus (que ya incluye el texto del bloque), reutilizar la colección `$project->characters` en memoria sin realizar una segunda llamada HTTP a `extract_characters`.

