# Walkthrough - ISSUE-041: Saneamiento de Heurísticas, Generalización de Prompts y Desacoplamiento

## 1. Modificaciones Implementadas
- **`app/Services/JgAgente/AgentClientService.php`**:
  - Generalización del filtro regex anti-falsos positivos de capitalización, eliminando tokens hardcodeados específicos.
  - Actualización de los ejemplos de nombres en prompts hacia referencias estándar de la dramaturgia cinematográfica (`PROTAGONISTA`, `JUAN PEDRO`, `DETECTIVE`).

## 2. Verificación de Calidad
- Pint: 0 errores.
- Suite PHP: 193 tests pasando al 100%.
- Suite Python: 51 tests pasando al 100%.
