# Contexto del Proyecto tras ISSUE-015

## Estado de la Gobernanza de Candados y Preservación de Datos

Con la resolución de `ISSUE-015`, el sistema garantiza la integridad y soberanía del autor cinematográfico sobre el texto y los desgloses de rodaje:

1. **Ciclo de Vida de los Diffs Manuales:**
   - La estructura `user_locks` en `script_blocks` mantiene:
     - `pinned_terms`: Mapeo de términos fijos inviolables.
     - `manual_diffs`: Lista activa de sustituciones manuales por etapa.
     - `archived_diffs`: Histórico de sustituciones manuales archivadas tras regeneraciones de IA, con marca temporal ISO-8601 (`archived_at`).
     - `locked_fields`: Lista de etapas con candado activo (`['structured', 'screenplay', 'shots']`).

2. **Protección Anti-Destructiva de Planos Técnicos:**
   - La regeneración de planos técnicos vía IA comprueba `isStageLocked('shots')`. Si el candado está activo, ninguna llamada externa es despachada y los planos existentes se mantienen íntegros.
   - Si la etapa no tiene candado pero existen planos que ya cuentan con tomas registradas (`takes()->count() > 0`), estos planos se excluyen de sobreescrituras destructivas.

3. **Reemplazo Determinista Anclado:**
   - Las sustituciones de `manual_diffs` en `AgentClientService` se realizan de forma anclada con `substr_replace`, evitando colisiones o modificaciones espurias en ocurrencias homónimas del texto.

4. **Trazabilidad de Versiones:**
   - Cada regeneración de tratamiento y guion literario deja constancia en la tabla `activity_logs` mediante `logActivity()` con el texto previo antes de la sobreescritura, habilitando auditoría completa de cambios.
