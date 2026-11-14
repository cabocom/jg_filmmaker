# Análisis Técnico - ISSUE-024: Optimización del Grafo de Render Livewire y Caché de Agregaciones de Cuota

## 1. Contexto y Diagnóstico
Durante la auditoría técnica se identificaron dos cuellos de botella críticos de rendimiento en la capa de interfaz y renderizado:
1. **Doble consulta y recarga de árbol en `ScriptEditor::render`:** Cada interacción Livewire cargaba todas las escenas con sus relaciones y luego ejecutaba una segunda consulta completa para `$activeScene` (`Scene::with(...)->find($activeSceneId)`), duplicando el tráfico y la hidratación de modelos Eloquent.
2. **Re-ejecución repetida de agregaciones pesadas en BD (`User::getTokensUsedToday()`):** El cálculo de tokens consumidos en el día realizaba una consulta con `leftJoin` y `SUM(agent_request_logs.total_tokens)` en cada render del layout y vistas analíticas sin ningún tipo de memoización o caché.
3. **Múltiples llamadas a `RoughCutTimeline::getGoodTakes()`:** En una sola petición, el timeline ejecutaba hasta tres veces la consulta con joins y eager loading de tomas aprobadas.

## 2. Solución Diseñada
1. **Eliminación de consulta duplicada de `activeScene`:** Al cargar las escenas con sus relaciones (`shots.takes`, etc.), `$activeScene` se obtiene directamente en memoria desde la colección precargada `$scenes->firstWhere('id', $this->activeSceneId)`, reduciendo consultas O(N) a O(1) en memoria.
2. **Caché con invalidación por eventos para tokens de usuario:** `User::getTokensUsedToday()` se encapsula bajo `Cache::remember("user:{$id}:tokens_used_today", 30, ...)`. En el modelo `AgentRequestLog::booted()`, se escucha el evento `created` para invalidar de inmediato la clave de caché del usuario.
3. **Memoización en `RoughCutTimeline`:** Propiedad protegida `$memoizedGoodTakes` que almacena la colección en el ciclo de vida del componente y se invalida al cambiar filtros (`setSceneFilter`).

## 3. Estrategia de Pruebas
- Pruebas automatizadas en `tests/Feature/VideoProbingAndRenderGraphTest.php`:
  - `test_user_tokens_used_today_is_cached_and_invalidated_on_log_creation`.
  - `test_rough_cut_timeline_memoizes_good_takes_query`.
