# ISSUE-023 Análisis: Transacciones Atómicas en Numeración y Mitigación de Carreras

## 1. Diagnóstico del Problema
- Los contadores de orden (`block_order`, `scene_order`, `shot_number`, `take_number`) se calculaban leyendo el valor máximo con `max(...)` y sumando 1 de forma desacoplada y fuera de transacciones atómicas.
- En situaciones de concurrencia (doble clic del usuario en botones de añadir, solicitudes simultáneas de drag-and-drop o dos pestañas abiertas), existía riesgo de condiciones de carrera produciendo duplicidad de números o saltos en la numeración, lo que alteraba el orden cronológico del montaje audiovisual (timeline, EDL y ensamblado FFmpeg).

## 2. Estrategia de Mitigación
1. Envolver la creación de bloques en `createNewBlock` dentro de una transacción `DB::transaction()` con bloqueo optimista/pesimista `lockForUpdate()` al calcular `max('block_order')` e incrementar órdenes superiores.
2. Envolver `createScene` en `DB::transaction()` con `lockForUpdate()` al desplazar `scene_order` y calcular el nuevo orden.
3. Envolver `addShot` en `DB::transaction()` con `lockForUpdate()` sobre `shot_number`.
4. Envolver `saveNewTake` en `DB::transaction()` con `lockForUpdate()` sobre `take_number`.
