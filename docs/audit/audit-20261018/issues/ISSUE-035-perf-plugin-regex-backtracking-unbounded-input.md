# [ISSUE-035] Expresiones regulares con backtracking polinómico y entradas sin límite de tamaño (ReDoS / DoS de CPU y tokens)

- **Severidad:** Alta
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/formatting.py:169-173,302-304`, `jg_plugin_filmmaker/tools.py:26-31,48-53,70-74`, `jg_plugin_filmmaker/structuring.py:54-55,140-141`; PHP equivalente en `app/Services/JgAgente/AgentClientService.php:690` y `app/Services/ScriptPipelineService.php:372-393`
- **Categoría:** Rendimiento

## 1. Descripción del Problema
- Los argumentos Pydantic de las tools (`raw_text`, `structured_text`, `screenplay_text`) sólo tienen `min_length=1`: **sin `max_length`**. Un autor (o un cliente comprometido con secreto HMAC válido) puede enviar megabytes.
- `indirect_pattern` en `format_screenplay` combina tres cuantificadores perezosos anidados con alternancias opcionales: `^(.*?)(?:[,\s]+y\s+|\s+y\s+|[,\s]+)?(?:(se|le|...)\s+)?{verbs}\s*(.*?)\s*{links}(.+)$` con `re.IGNORECASE`. Para párrafos largos que **contienen** un verbo pero no un conector válido (o con muchos `y`/`,`), el motor `re` de Python explora O(n³) combinaciones. Python no tiene timeout de regex y bloquea el hilo (y el event loop si la tool se ejecuta en el loop de FastAPI) → denegación de servicio del agente completo.
- Por cada personaje se compila y ejecuta `re.sub(rf"\b{re.escape(name)}\b", ...)` sobre cada párrafo (O(personajes × texto)); `characters_context` y `user_locks` carecen de límites.
- En PHP, el mismo patrón en `AgentClientService` retorna `null` al superar `pcre.backtrack_limit` (silenciosamente), tratándose como "sin coincidencia".
- Sin límite de entrada tampoco hay control de coste en el camino LLM: un texto enorme se reenvía íntegro al proveedor.

## 2. Evidencia de Código
```python
indirect_pattern = re.compile(
    rf"^(.*?)(?:[,\s]+y\s+|\s+y\s+|[,\s]+)?(?:(se|le|les|me|nos|te|lo|la|los|las)\s+)?{verbs}\s*(.*?)\s*{links}(.+)$",
    re.IGNORECASE,
)
```
```python
raw_text: str = Field(..., min_length=1, description="...")     # sin max_length
```

## 3. Impacto Operativo o de Seguridad
Un único request puede dejar el worker de `jg_agente` al 100 % de CPU durante minutos; afecta a todos los plugins (conversacional, devops) que comparten el proceso. También sobrecoste de tokens.

## 4. Solución Recomendada
- `max_length` por campo (p. ej. 20 000 caracteres), `max_length` en listas (`characters_context` ≤ 100, `pinned_terms` ≤ 50) y límites por elemento.
- Reescribir el patrón con anclajes y cuantificadores acotados (`.{0,300}?`) o mejor, tokenizar por frases y buscar el verbo con una búsqueda lineal; usar la librería `regex` con `timeout=` si se requiere.
- Truncar/segmentar párrafos > N caracteres antes del análisis heurístico.
- Test de rendimiento con `pytest` (`@pytest.mark.timeout(2)`) con 100 KB de texto adversarial.
- En PHP, comprobar `preg_last_error()` y registrar.
