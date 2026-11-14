# [ISSUE-027] Clases Monolíticas (`ScriptEditor` 3.283 Líneas y `AgentClientService` 1.564 Líneas) y Duplicación Heurística PHP/Python (Pendiente de `audit-20261018 / ISSUE-041`)

- **Severidad:** Deuda Técnica
- **Componente:** `jg_filmmaker` | `jg_plugin_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Livewire/Studio/ScriptEditor.php:1-3283`, `jg_filmmaker/app/Services/JgAgente/AgentClientService.php:1-1564`
- **Categoría:** Deuda Técnica

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-041`) se identificó el acoplamiento monolítico en `ScriptEditor.php` (entonces con 1.932 líneas) y la duplicación de ~500 líneas de lógica heurística NLP en PHP dentro de `AgentClientService::executeDeterministicFallback()` replicando manualmente los módulos de Python de `jg_plugin_filmmaker`.

En la resolución de aquel issue únicamente se limpiaron los ejemplos de nombres en los prompts, quedando pendiente la refactorización estructural. Tras las funcionalidades añadidas hasta `ISSUE-069`:
1. **`ScriptEditor.php` ha crecido hasta 3.283 líneas**, concentrando más de 95 métodos y 80 propiedades públicas en una sola clase Livewire (gestión de escenas, bloques, candados, términos fijos, desglose de planos, subida y sondeo de tomas, montaje FFmpeg, auditoría de agujeros, justificaciones, generación de storyboards, exportación PDF/MP3, procesamiento por lotes e historial de actividad).
2. **`AgentClientService.php` alcanza 1.564 líneas**, manteniendo un motor heurístico completo en PHP (`executeDeterministicFallback`, `refineDialogueToDirectSpeech`, `parseShotsFromText`, detección de agujeros y extracción de personajes) duplicado respecto a `jg_plugin_filmmaker` (`structuring.py`, `formatting.py`, `breakdown.py`, `plot_holes.py`, `characters.py`).
3. **Ausencia de Enums y DTOs Tipados:** Los nombres de etapas (`raw`, `structured`, `screenplay`, `shots`, `editing`) y estructuras como `user_locks` o `telemetry` siguen pasándose como `string` y `array` asociativos sin tipado de dominio (`BlockStage` Enum).

## 2. Evidencia de Código
En [`jg_filmmaker/app/Livewire/Studio/ScriptEditor.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Studio/ScriptEditor.php#L1-L35):
```php
// 3.283 líneas en una única clase de componente Livewire
class ScriptEditor extends Component
```

En [`jg_filmmaker/app/Services/JgAgente/AgentClientService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/JgAgente/AgentClientService.php#L477-L1150):
```php
    /**
     * Fallback determinista local para entornos de prueba o desconexión temporal del agente.
     */
    protected function executeDeterministicFallback(string $toolName, array $payload): array
```

## 3. Impacto Operativo o Riesgo
- **Alta Complejidad Ciclomática y Superficie de Regresión:** Al convivir 80 propiedades públicas en `ScriptEditor`, cualquier re-render serializa un snapshot Livewire voluminoso y dificulta auditar las reglas de autorización de cada sub-flujo (origen directo de hallazgos como `ISSUE-001`, `ISSUE-002` e `ISSUE-016`).
- **Doble Mantenimiento PHP/Python:** Cualquier ajuste en las reglas de desglose de planos o formateo Fountain obliga a editar y sincronizar manualmente expresiones regulares en dos lenguajes distintos.

## 4. Solución o Mejora Recomendada
1. Extraer las responsabilidades de `ScriptEditor` mediante Livewire Traits por dominio cohesivo (`ManagesScenes`, `ManagesScriptBlocks`, `ManagesShotsAndTakes`, `ManagesStoryboards`, `ManagesPlotHoles`, `ManagesBatchPipeline`) o subcomponentes especializados (`StoryboardComposerModal`, `BatchPipelineModal`, `ActivityHistoryModal`).
2. Extraer de `AgentClientService` el motor heurístico offline a una clase separada `OfflineDeterministicEngine` e introducir el Enum respaldado en string `App\Enums\BlockStage: string`.

## 5. Resolución y Verificación Técnica
- **Modularización y Congelación de Monolitos (`ScriptEditor` y `AgentClientService`):**
  - `ScriptEditor.php` descompuesto en Concerns específicos (`WithEditorBlockCrud`, `WithEditorBlockDrawer`, `WithEditorImportExportBatch`, `WithEditorNavigation`, `WithEditorPipelineExecution`, `WithEditorProperties`, `WithEditorShots`, `WithEditorStoryboard`, `WithEditorTakes`), reduciendo la clase principal a solo 107 líneas (< 300 LOC, regla 8.1).
  - `AgentClientService.php` refactorizado con delegación a clientes dedicados (`StructuringAgentClient`, `ScreenplayAgentClient`, `StoryboardAgentClient`, `AuditingAgentClient`) y gestores de fallback modularizados (`FormattingFallbackHandler`, `StoryboardFallbackHandler`), dejando la clase en 229 líneas (< 300 LOC).
- **Tipado Fuerte de Dominio con `BlockStage` Enum:** Creado el Enum tipado `App\Enums\BlockStage: string` (`RAW`, `STRUCTURED`, `SCREENPLAY`, `SHOTS`) con métodos de etiqueta, jerarquía de ordenación (`isAdvancedThan`) y resolución canónica en `ScriptBlock::stageEnum()`.
- **Validación Automatizada:** Suite unitaria `BlockStageEnumTest` y suite completa de `jg_filmmaker` al 100% (299 tests, 1.389 assertions), Pint con 0 infracciones.

