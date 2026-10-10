# Roadmap & TODO: Evolución de la Historia y Análisis Profundo de la Obra

**Fecha de registro:** 11 de octubre de 2026  
**Componente:** `jg_filmmaker` / Studio & Trazabilidad Creativa  
**Estado:** Fase 1 (Captura de Trazabilidad Total en `user_activity_logs`) Implementada al 100%.

---

## 1. Visión y Propósito
El sistema ahora registra un historial continuo y detallado de **todas las acciones del usuario** en la tabla PostgreSQL `user_activity_logs`:
- Creación, edición, reordenación y borrado de escenas y bloques de guión.
- Transformaciones cognitivas con IA (Tratamiento dramático, Guión literario, Desglose de planos).
- Candados y fijación de términos manuales (`user_locks`).
- Detección, justificación y resolución de agujeros de guión dramáticos.
- Snapshots íntegros del contenido previo en cada cambio.

Con esta base de datos histórica, la plataforma evolucionará hacia un **módulo analítico de "Evolución de la Historia"**, permitiendo al autor sumergirse en la arqueología de su propio proceso creativo.

---

## 2. Funcionalidades Planificadas (TODO)

### 2.1. Viaje en el Tiempo (Time Machine & Version Diff)
- [ ] **Línea de Vida de la Obra:** Slider interactivo o timeline scrubber que permita visualizar el estado exacto del guión en cualquier fecha u hora pasada.
- [ ] **Visualizador de Diffs Narrativos:** Comparar dos momentos históricos del proyecto (e.g. *Borrador del 9 de Octubre* vs *Versión actual*) con resaltado sintáctico de texto añadido (verde), modificado (ámbar) o descartado (rojo).
- [ ] **Restauración Selectiva:** Capacidad de recuperar un bloque o escena a partir de su snapshot histórico en `user_activity_logs`.

### 2.2. Métricas y Dinámica de Transformación
- [ ] **Velocidad y Ritmo Creativo:** Gráfica de calor (heatmap) mostrando las horas y días de mayor actividad creativa y ratio de generación vs pulido manual.
- [ ] **Ratio IA vs Humano:** Cómputo del porcentaje de palabras redactadas originalmente por el autor frente a las asistidas o consolidadas por la IA.
- [ ] **Evolución del Elenco (Character Arc Tracking):**
  - Detección del momento exacto en que cada personaje hace su primera aparición y cómo se expande o contrae su protagonismo a lo largo de las revisiones.
- [ ] **Evolución Espacio-Temporal:** Visualización de cómo mutan las localizaciones y franjas horarias a medida que el autor reordena y pule las escenas.

### 2.3. Detección de Patrones y Salud Dramática
- [ ] **Historial de Agujeros Resueltos:** Análisis de qué inconsistencias argumentales aparecieron con más frecuencia y cómo las justificaciones del autor transformaron las reglas del universo de la historia.
- [ ] **Densidad de Bloqueos:** Mapa de calor de bloques con más candados y modificaciones manuales, indicando los núcleos dramáticos más cuidados o difíciles de la obra.

---

## 3. Modelo de Datos Asociado
- **Tabla origen:** `user_activity_logs`
- **Campos principales:**
  - `user_id`, `project_id`, `action`, `entity_type`, `entity_id`, `description`, `snapshot`, `created_at`.
- **Estrategia de rendimiento:** Índices por `[project_id, created_at]` y particionamiento futuro si el volumen histórico crece exponencialmente.
