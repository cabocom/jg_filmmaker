# Project Context - ISSUE-013: Gobernanza de Concurrencia y Bloqueos en ScriptBlocks

## 1. Identificación del Cambio
- **Issue:** ISSUE-013 (Race condition y actualización perdida en `user_locks`).
- **Subsistema:** `jg_filmmaker` (Pipeline de IA, Livewire Studio y Modelo de Dominio).
- **Alcance:** Control de concurrencia optimista (`lock_version`) y pesimista (`lockForUpdate`), aislamiento de mutaciones en `user_locks`.

## 2. Decisiones Arquitectónicas
- **Optimistic Locking para Edición Manual:** Se añadió el campo `lock_version` a `script_blocks`. Cada edición en el Livewire Studio (`saveBlock`, `saveBlockFromDrawer`) verifica que la versión esperada coincide con la versión en base de datos antes de persistir, evitando la sobreescritura de texto modificado en paralelo o por la IA.
- **Pessimistic Locking Post-Inferencia:** Las llamadas a modelos de IA (que consumen entre 30 y 75 segundos) se ejecutan fuera de transacciones de base de datos para no bloquear conexiones del pool. La persistencia posterior se realiza dentro de `DB::transaction()` con `lockForUpdate()`, fusionando los cambios sobre la versión fresca del bloque y comprobando si el usuario bloqueó la etapa concurrentemente.
- **Método Atómico `mutateUserLocks`:** Centraliza cualquier alteración a la columna JSON `user_locks` bajo una transacción con bloqueo exclusivo de fila, garantizando que adiciones de términos fijados (`pinned_terms`), candados (`toggleStageLock`) o justificaciones (`savePlotHoleJustification`) nunca se sobreescriban entre sí.
- **Aislamiento en Auditoría de Inconsistencias:** `analyzePlotHoles` y `clearPlotHoles` ahora actualizan únicamente las claves asociadas al estado de la auditoría (`plot_hole`, `plot_hole_stage`), en lugar de sobreescribir el JSON completo cargado minutos antes al inicio del análisis.

## 3. Estado de la Suite de Calidad
- **Tests automatizados:** 121 tests pasando (619 aserciones).
- **Linter de código:** Laravel Pint 100% limpio (0 advertencias).
- **Pipeline CI/CD:** `./scripts/check.sh --cicd -p jg_filmmaker` exitoso.
