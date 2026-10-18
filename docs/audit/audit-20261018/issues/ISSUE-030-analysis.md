# Análisis Técnico - ISSUE-030: Saneamiento de Configuración, Erradicación de Parseo Manual de .env y Compatibilidad con config:cache

## 1. Contexto y Diagnóstico
1. `config/services.php` contenía una función anónima en `llm.api_key` que abría directamente el fichero `.env` en cada invocación, buscando una lista de claves heterogéneas (`LLM_API_KEY`, `GROQ_API_KEY`, `GLOBAL_GROQ_API_KEY`, `GLOBAL_GOOGLE_API_KEY`, `GEMINI_API_KEY`) y devolviendo la primera encontrada. Esto provocaba un riesgo de fuga de claves al proveedor equivocado y acoplamiento innecesario.
2. `AgentClientService::__construct` llamaba directamente a `env('PERMANENT_AGENT_SECRET', '')`. Cuando se ejecuta `php artisan config:cache` (estándar en entornos productivos), `env()` devuelve `null` fuera de los archivos de configuración en `config/`.

## 2. Solución Diseñada
1. **Configuración limpia y estricta en `config/services.php`:**
   - Selección explícita de clave por proveedor mediante expresión `match` limpia sobre `LLM_PROVIDER`.
   - Eliminación radical del bucle de parseo manual de líneas en `.env`.
   - Resolución de `secret` para `jg_agente` con fallback en cascada dentro del propio archivo de config: `env('JG_AGENTE_SECRET', env('PERMANENT_AGENT_SECRET'))`.
2. **Uso exclusivo de `config()` en `AgentClientService`:**
   - `AgentClientService` ahora lee únicamente `config('services.jg_agente.secret')`, garantizando inmunidad ante `config:cache`.

## 3. Estrategia de Pruebas
- `tests/Feature/HmacAgentClientConfigAndSqliteTest.php`:
  - `test_agent_client_resolves_secret_from_config_and_includes_key_id_and_correlation_id`.
  - `test_llm_config_does_not_mix_keys_across_providers`.
