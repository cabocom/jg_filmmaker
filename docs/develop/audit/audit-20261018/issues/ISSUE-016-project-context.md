# Contexto del Proyecto tras ISSUE-016

## Estado de la Gobernanza de Términos Fijos (`pinned_terms`)

Con la resolución de `ISSUE-016`, el ecosistema consolida la inmutabilidad y usabilidad de los términos fijos de autoría:

1. **Inmunidad ante Backreferences en Reemplazos:**
   - La sustitución de `pinned_terms` en PHP (`AgentClientService`) y Python (`structuring.py`) utiliza funciones de reemplazo deterministas (`preg_replace_callback` y `re.sub(..., lambda)`), garantizando que caracteres como `$1` o `\1` se procesen como texto literal puro sin distorsión léxica ni excepciones de expresiones regulares.

2. **Fronteras Léxicas Adaptativas:**
   - Se erradica la dependencia frágil de `\b`. La coincidencia se gobierna por lookarounds dependientes de la naturaleza alfanumérica de los extremos del término, permitiendo fijar términos técnicos, artísticos o coloquiales con signos de puntuación o símbolos (e.g., `C++`, `#1`).

3. **Restricción de Complejidad y Rendimiento:**
   - Cada bloque de guión admite un máximo de 50 términos fijados activos, con un límite estricto de 80 caracteres por término y por sustituto, evitando degradaciones de rendimiento durante la reescritura.

4. **Autonomía del Autor en la UI:**
   - El Drawer de edición lateral de `ScriptEditor` incluye controles de adición y eliminación instantánea de términos fijos, persistidos atómicamente mediante transacciones con bloqueo exclusivo de fila (`mutateUserLocks`).
