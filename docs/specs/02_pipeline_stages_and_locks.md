# 02. Pipeline de Transformación, Retención de Cambios y Drawer Lateral

## 1. Etapas del Ciclo de Vida del Bloque
Cada bloque transiciona a través de 5 estados:
1. **Raw (Bruto):** Texto con palabras e ideas libres del autor.
2. **Structured (Estructurado / Tratamiento):** La IA normaliza acciones, objetivos dramáticos y tiempos narrativos en presente.
3. **Screenplay (Guion literario):** Formato estándar cinematográfico (diálogos, nombres en mayúsculas, acotaciones y encabezados).
4. **Shots (Planos):** Desglose del bloque en planos técnicos según la sintaxis canónica `--- shot (TIPO) ---` con numeración incremental automática por escena (soportando compatibilidad con el patrón anterior numerado `--- shot N (TIPO) ---`).
5. **Editing (Montaje):** Asociación de tomas válidas (`is_good_take`), orden cronológico de edición, detección de huecos y ensamblaje de vídeo rough cut.

## 2. Sistema de Retención de Modificaciones Manuales (`user_locks`) y Concurrencia Optimista

### Problema
Si un usuario edita manualmente el texto estructurado o guion, y más adelante ejecuta una regeneración por IA sobre el bloque o la escena, debe protegerse la soberanía editorial del autor.

### Solución Técnica: Esquema `user_locks` (JSONB)
El campo `script_blocks.user_locks` almacena el estado reactivo de bloqueos por etapa, términos inmutables y resoluciones editoriales:
```json
{
  "structured": true,
  "screenplay": false,
  "pinned_terms": {
    "Javi": "Javier",
    "Comisaría": "Jefatura Superior de Policía"
  },
  "plot_hole_stage": "raw",
  "justified_plot_holes": [
    {
      "id": "hole_01",
      "hole_reason": "El personaje llega antes del tren.",
      "justification": "Utilizó un atajo secreto por el túnel abandonado.",
      "justified_at": "2026-10-10T15:30:00Z"
    }
  ]
}
```

### Control de Concurrencia Optimista (`lock_version`)
Para prevenir condiciones de carrera cuando múltiples pestañas o peticiones asíncronas actualizan el mismo bloque, la columna entera `lock_version` se incrementa atómicamente (`UPDATE script_blocks SET lock_version = lock_version + 1 WHERE id = ? AND lock_version = ?`), abortando con conflicto si otra transacción modificó el registro.

## 3. Optimización en Lotes (`BatchPipelineProcessor`)
En ejecuciones masivas (`batchRunFullPipeline`, `batchStructureBlocks`, `batchFormatBlocksToScreenplay`):
- Se ejecuta la sincronización de personajes y localizaciones del proyecto **exactamente una vez por lote**, desacoplándola de las transiciones individuales (`syncProjectContext = false`).
- La inferencia de personajes por bloque utiliza concordancia determinista en memoria sobre la Biblia del proyecto (`allowLlmExtraction: false`), evitando llamadas LLM N+1 redundantes.
