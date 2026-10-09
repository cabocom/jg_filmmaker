# 02. Pipeline de Transformación, Retención de Cambios y Drawer Lateral

## 1. Etapas del Ciclo de Vida del Bloque
Cada bloque transiciona a través de 5 estados:
1. **Raw (Bruto):** Texto con palabras e ideas libres del autor.
2. **Structured (Estructurado / Tratamiento):** La IA normaliza acciones y tiempos narrativos.
3. **Screenplay (Guion literario):** Formato estándar cinematográfico (diálogos, nombres en mayúsculas, acotaciones).
4. **Shots (Planos):** Desglose del bloque en planos técnicos según la sintaxis `--- shot N (tipo) ---`.
5. **Editing (Montaje):** Asociación de tomas válidas, orden cronológico de edición y notas de posproducción.

## 2. Sistema de Retención de Modificaciones Manuales (User Locks)

### Problema
Si un usuario genera el texto estructurado o guion, corrige una palabra o diálogo manualmente y más adelante modifica el texto bruto, una re-estructuración ingenua borraría sus cambios manuales.

### Solución Técnica: Esquema `user_locks` (JSONB)
El campo `script_blocks.user_locks` almacena:
```json
{
  "locked_fields": ["dialogue_speaker", "shot_breakdown"],
  "manual_diffs": [
    {
      "stage": "structured",
      "original_ai": "Juan nota que Javi está preocupado.",
      "user_override": "Juan sospecha profundamente de Javi.",
      "applied_at": "2026-10-09T18:30:00Z"
    }
  ],
  "pinned_terms": {
    "Javi": "Javier"
  }
}
