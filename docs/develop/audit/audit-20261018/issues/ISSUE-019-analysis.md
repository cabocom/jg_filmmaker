# Análisis de Resolución: ISSUE-019

## 1. Contexto y Diagnóstico
En la auditoría técnica de `jg_filmmaker` se identificó un cuello de botella grave de rendimiento y efectos secundarios no deseados en `App\Livewire\Studio\ScriptEditor::mount()`:
1. **Mutación de Base de Datos en Petición GET:**
   Al acceder a la URL `/projects/{project}` (operación puramente de lectura y visualización), `mount()` ejecutaba incondicionalmente:
   - `$pipeline->syncProjectCharacters($this->project)`
   - `$pipeline->syncProjectLocations($this->project)`
2. **Escrituras Masivas y N+1 Queries:**
   Se cargaban en memoria todos los textos del proyecto (`raw`, `structured`, `screenplay`), se aplicaban expresiones regulares pesadas y por cada coincidencia se ejecutaban consultas individuales de existencia y escrituras `create()` o `update()` en bucle.
3. **Bloqueos de Base de Datos y Degradación en SQLite/PostgreSQL:**
   En entornos con SQLite en producción, abrir la página generaba bloqueos de escritura concurrentes innecesarios, incompatibilidad con mecanismos de lectura concurrente e imposibilidad de cachear la vista.

## 2. Decisiones de Diseño
- **Erradicación de Escrituras en `mount()`:**
  - `mount()` se transforma en un método estrictamente idempotente y de sólo lectura: carga el proyecto, autoriza mediante la política `view` e inicializa el estado visual del componente sin disparar escrituras en la base de datos.
- **Sincronización en Puntos de Mutación Real:**
  - La sincronización contextual de personajes y localizaciones se acota exclusivamente a acciones donde el usuario edita o avanza etapas del guión (`structureBlock`, `formatBlockToScreenplay`, `saveScene`), evitando sobrecargas en la navegación general.
- **Optimización de Consultas en Lote:**
  - Eliminación de consultas repetitivas N+1 mediante precarga de colecciones en memoria (`pluck('name')`).

## 3. Plan de Verificación
- Prueba unitaria/feature que compruebe que al montar el componente `ScriptEditor` no se emite ninguna sentencia SQL `INSERT`, `UPDATE` o `DELETE` sobre las tablas `characters` ni `locations`.
- Comprobación de integridad de carga rápida y 0 regresiones en la suite de pruebas.
