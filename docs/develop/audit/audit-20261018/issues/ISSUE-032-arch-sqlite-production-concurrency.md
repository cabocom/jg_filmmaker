# [ISSUE-032] SQLite como base de datos de producción con peticiones largas y escrituras en lecturas: riesgo de `database is locked`

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `docker-compose.yml:18-19`, `.env.example:23`, `docker/entrypoint.sh:44-51`, `docs/08_auth_admin_telemetry.md`, `docs/TODO_HISTORIA_EVOLUCION.md`
- **Categoría:** Arquitectura

## 1. Descripción del Problema
El contenedor usa `DB_CONNECTION=sqlite` (un único fichero en un volumen Docker) mientras la documentación habla de PostgreSQL (`user_activity_logs` "en PostgreSQL", columnas `jsonb`, `docs/01` "Base de Datos"), y las migraciones usan `jsonb` y `after()` (sintaxis de otros motores). SQLite admite **un solo escritor** a la vez:
- `mount()` escribe en cada visita (ISSUE-019), cada acción registra `UserActivityLog`, la telemetría escribe `agent_request_logs`, y el pipeline mantiene la transacción lógica abierta durante llamadas HTTP a la IA de hasta 30-75 s (`ScriptPipelineService`).
- PHP-FPM atiende varios workers (`supervisord`): bajo carga concurrente aparecen `SQLSTATE[HY000]: database is locked` (`busy_timeout` por defecto 0 en PDO sqlite).
- `queue`, `cache` y `sessions` si usan `database` comparten el mismo fichero y lock.
- Sin backup/litestream, y el volumen `jg_filmmaker_database` se comparte con `RUN_MIGRATIONS` en cada arranque.
- Las columnas `enum` en SQLite se implementan con `CHECK`; cambios futuros de enum (`stage`) requieren recrear tabla.

## 2. Evidencia de Código
```yaml
DB_CONNECTION: sqlite
DB_DATABASE: /var/www/html/database/database.sqlite
```

## 3. Impacto Operativo o de Seguridad
Errores intermitentes de guardado (pérdida de trabajo creativo), imposibilidad de escalar horizontalmente y diferencias entre entornos (tests en SQLite en memoria vs producción) que ocultan problemas de concurrencia.

## 4. Solución Recomendada
- Decidir y documentar: PostgreSQL (alineado con el resto del ecosistema `jg`) con servicio en compose, o SQLite en modo WAL con `busy_timeout=5000`, `journal_mode=WAL`, `synchronous=NORMAL` configurado en `config/database.php` y backups programados.
- Sacar las llamadas HTTP a IA de cualquier transacción y moverlas a jobs en cola.
- Ejecutar los tests de integración contra el mismo motor que producción en CI.
- Alinear la documentación con la realidad.
