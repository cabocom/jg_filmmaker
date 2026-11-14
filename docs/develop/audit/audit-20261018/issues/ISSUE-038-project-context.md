# Project Context Update - ISSUE-038: Manejo Seguro de Diálogos Vacíos o Residuales

## Módulos Afectados
- `jg_plugin_filmmaker/formatting.py`
- `jg_plugin_filmmaker/tests/test_refine_dialogue_safety.py`
- `jg_filmmaker/app/Services/JgAgente/AgentClientService.php`
- `jg_filmmaker/tests/Feature/ReDosMitigationTest.php`

## Decisiones Técnicas y Arquitectura
1. **Prevención de Excepciones No Controladas en Parsing Heurístico:**
   - La conversión de estilo indirecto a directo no debe fallar catastróficamente ante entradas atípicas del usuario como frases compuestas solo por signos de interrogación o exclamación ("¿?", "le pregunta que ?").
   - El acceso por índice a caracteres (`[0]`) siempre debe estar precedido por una validación de longitud no nula (`if not core: return "..."`).
2. **Consistencia Transversal:**
   - Tanto el runtime Python del plugin como el motor de contingencia determinista en Laravel PHP aplican la misma normalización para generar bloques canónicos consistentes.
