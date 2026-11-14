# Análisis Técnico - ISSUE-032: Hardening de Concurrencia SQLite para Evitar Bloqueos Intermitentes

## 1. Contexto y Diagnóstico
En la configuración por defecto de Laravel para SQLite en `config/database.php`, los parámetros de rendimiento y concurrencia se encontraban sin definir:
- `busy_timeout => null` (0 segundos, fallo instantáneo al competir por el fichero).
- `journal_mode => null` (modo DELETE por defecto, bloquea lectores mientras se escribe).
- `synchronous => null`.
- `transaction_mode => 'DEFERRED'`.

Bajo peticiones concurrentes de workers PHP-FPM o tareas en segundo plano, esto derivaba en excepciones `SQLSTATE[HY000]: database is locked`.

## 2. Solución Diseñada
1. **Configuración de WAL y Timeout Activo:**
   - `busy_timeout`: fijado a `5000` ms (5 segundos de reintento activo de bloqueo a nivel PDO SQLite).
   - `journal_mode`: configurado en `WAL` (Write-Ahead Logging), permitiendo lectura y escritura concurrentes sin bloqueo mutuo.
   - `synchronous`: configurado en `NORMAL` (óptimo con WAL manteniendo integridad ACID).
   - `transaction_mode`: configurado en `IMMEDIATE`.

## 3. Estrategia de Pruebas
- `tests/Feature/HmacAgentClientConfigAndSqliteTest.php`:
  - `test_sqlite_configuration_has_wal_and_busy_timeout`.
