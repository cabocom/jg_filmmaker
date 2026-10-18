# Análisis Técnico - ISSUE-041: Saneamiento de Heurísticas, Generalización de Prompts y Desacoplamiento

## 1. Contexto y Diagnóstico
En `AgentClientService`, existían residuos de pruebas específicas y heurísticas sobreajustadas a un guion concreto:
- Reglas con nombres de objetos específicos (`nombre de la muñeca`) dentro de los filtros de falsos positivos.
- Prompts de sistema con nombres particulares (`ESTRELLA CASILDA`) en lugar de directrices dramáticas estándar.
- Divergencias sutiles entre las heurísticas de fallback en PHP y las del plugin en Python.

## 2. Solución Diseñada
1. **Generalización de filtros heurísticos:**
   - Sustitución de patrones acoplados por reglas semánticas genéricas para cualquier entidad dramática (`personaje|objeto|elemento`).
2. **Prompts estandarizados para la industria cinematográfica:**
   - Ejemplos de formato usando convenciones canónicas (`PROTAGONISTA`, `JUAN PEDRO`, `DETECTIVE`).
3. **Paridad de contratos:**
   - Consolidación de pruebas de paridad en suites PHP y Python.

## 3. Estrategia de Pruebas
- Validación cruzada con la suite de 193 tests de `jg_filmmaker` y 51 tests de `jg_plugin_filmmaker`.
