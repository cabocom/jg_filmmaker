# Walkthrough — Fase 7: Inferencia Cognitiva Real con LLM y Aislamiento Mock Determinista en Tests

**Fecha:** 2026-10-10  
**Proyecto:** `jg_plugin_filmmaker`, `jg_agente` & `jg_filmmaker`  
**Autor:** Antigravity (Ingeniero Senior de IA y NLP)

---

## 1. Prompt Recibido

```text
te lo voy a dejar pasar porque es solo documentación, pero tenías que haber ejecutado las pruebas antes de hacer el commit. vamos a activar la ia

[PROJECT: jg_plugin_filmmaker & jg_filmmaker] - Fase 7: Inferencia Cognitiva Real con LLM y Aislamiento Mock Determinista en Tests

Actúa como Ingeniero Senior de IA y NLP en el ecosistema jg_ia.

Vamos a sustituir las heurísticas regex por inferencia semántica real mediante LLM en las herramientas de `jg_plugin_filmmaker`, conectándolo a la factoría de modelos de `jg_agente/app/core/llm.py` (Groq, Gemini, Ollama, etc.).

### REGLA DE ORO TÉCNICA: AISLAMIENTO DETERMINISTA
Los tests automatizados (`pytest`, `php artisan test` y `bash scripts/check.sh --cicd`) DEBEN mantenerse 100% deterministas, offline y con consumo ZERO de tokens en APIs externas.

### DIRECTRICES DE IMPLEMENTACIÓN:

1. Arquitectura de Doble Modo (Producción/En Vivo vs Testing/CI):
   - En ejecución real: el agente invoca el LLM activo para resolver semántica dramática.
   - En testing / CI: inyectar `MockChatModel` / fixtures en Python y mantener `JG_AGENTE_MOCK=true` / `Http::fake()` en Laravel para asegurar respuestas deterministas instantáneas sin llamadas salientes de red.

2. Prompts Cinematográficos Semánticos en `jg_plugin_filmmaker`:
   - `structuring.py`:
     * System prompt que recibe texto libre e inyecta la biblia contextual (personajes y localización).
     * Normaliza a presente narrativo continuo y respeta estrictamente los `user_locks` (`pinned_terms` y `manual_diffs`).
   - `formatting.py`:
     * System prompt de guionista cinematográfico.
     * Convierte tratamiento a formato Fountain estándar (encabezado, personajes en mayúsculas, acotaciones y diálogos).
     * Resuelve el estilo indirecto (ej: transforma "Javier mira a Juan con miedo y le dice que ya están llegando" en una acción descriptiva y un bloque de diálogo real para JAVIER: "Ya están llegando.").
     * Infiere y da formato a personajes que intervienen en el diálogo aunque no estuviesen previamente dados de alta en la biblia.
   - `breakdown.py`:
     * System prompt que evalúa la tensión dramática y propone la cobertura de cámara utilizando el delimitador canónico:
       `--- shot N (tipo) ---`

3. Invocación desde `jg_agente`:
   - Las herramientas deben recibir el cliente LLM (`get_llm()` / `ChatModel`).
   - Si no hay API key configurada o se activa el modo mock, degradar ordenadamente al fallback determinista sin lanzar excepciones.

Explícame los system prompts diseñados, cómo conmuta la aplicación entre el LLM real y el mock de tests, y cómo probar una transformación real desde el navegador.
```

---

## 2. Acciones Realizadas

### 2.1. Conexión de LLM en `jg_plugin_filmmaker`
- Se implementó [llm.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/llm.py) con aislamiento agnóstico:
  - Función `is_mock_mode()` que detecta `JG_AGENTE_MOCK=true` o entorno de testing (`PYTEST_CURRENT_TEST`).
  - Función `invoke_llm_text()` que soporta duck-typing sobre cualquier modelo (`invoke`, `ainvoke`, callables o dicts de mensajes) sin exigir dependencias obligatorias de LangChain en el plugin.
- Se implementaron los System Prompts cinematográficos en:
  - [structuring.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/structuring.py): `STRUCTURING_SYSTEM_PROMPT` con inyección de personajes, localización y candados inmutables.
  - [formatting.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/formatting.py): `SCREENPLAY_SYSTEM_PROMPT` con resolución de estilo indirecto e inferencia de personajes no registrados.
  - [breakdown.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/jg_plugin_filmmaker/breakdown.py): `BREAKDOWN_SYSTEM_PROMPT` con delimitador canónico `--- shot N (tipo) ---` y parseo de planos técnicos.
- Se agregaron 3 pruebas unitarias en [test_filmmaker_tools.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/tests/test_filmmaker_tools.py) validando la inferencia con LLM mockeado y el parseo de planos (13 tests pasando en total).

### 2.2. Exposición del Endpoint en `jg_agente`
- Se creó [app/api/tools.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/app/api/tools.py):
  - Ruta `POST /api/v1/tools/{tool_name}`.
  - Verificación de firma criptográfica HMAC-SHA256 y ventana anti-replay (300 segundos).
  - Inyección del modelo activo `get_chat_model(settings)` en la invocación de la herramienta.
- Se registró el router en [app/main.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/app/main.py).
- Se crearon pruebas unitarias en [tests/test_api_tools.py](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/tests/test_api_tools.py) validando el flujo de autenticación y ejecución.

### 2.3. Sincronización en `jg_filmmaker`
- Se añadió la cabecera `X-Agent-ID: jg_filmmaker` en [AgentClientService.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Services/JgAgente/AgentClientService.php).
- Se garantizó que en testing (`JG_AGENTE_MOCK=true`) el servicio opere de forma instantánea sin salida de red.

---

## 3. Verificación de Calidad

- **Pytest (`jg_plugin_filmmaker`):** 13 tests pasados al 100% (0.11s).
- **Ruff (`jg_plugin_filmmaker`):** Formato y linters aprobados.
- **Pytest (`jg_agente`):** Pruebas de API tools pasando al 100%.
- **PHPUnit (`jg_filmmaker`):** 28 tests y 144 aserciones pasando al 100% (0.49s).
- **Laravel Pint (`jg_filmmaker`):** 100% aprobado.
- **Pipeline CI/CD Unificado (`scripts/check.sh --cicd`):** 100% aprobado.
