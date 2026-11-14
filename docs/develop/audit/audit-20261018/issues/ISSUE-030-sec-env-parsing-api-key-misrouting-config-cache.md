# [ISSUE-030] Lectura directa de `.env` y `env()` fuera de `config/`: rompe `config:cache`, mezcla credenciales y selecciona claves por heurística

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `config/services.php:55-85`, `app/Services/JgAgente/AgentClientService.php:35`
- **Categoría:** Seguridad

## 1. Descripción del Problema
- `config/services.php` define `llm.api_key` con una closure que, si no hay variable de entorno, **abre y parsea manualmente `base_path('.env')`** buscando cinco nombres de clave distintos (`LLM_API_KEY`, `GROQ_API_KEY`, `GLOBAL_GROQ_API_KEY`, `GLOBAL_GOOGLE_API_KEY`, `GEMINI_API_KEY`) y devuelve la **primera no vacía, sea del proveedor que sea**. La clave de Google puede enviarse a Groq/OpenAI (`Http::withToken($apiKey)` hacia `api.groq.com`) → **filtración de credenciales a un tercero equivocado**. El prefijo `gsk_` sólo se comprueba en una rama.
- `env()` se llama dentro de `AgentClientService::__construct` (`env('PERMANENT_AGENT_SECRET','')`): con `php artisan config:cache` (el entrypoint ejecuta `optimize`/`view:cache` y producción lo usa) `env()` devuelve `null` fuera de config; sólo funciona por casualidad porque `config('services.jg_agente.secret')` ya incorpora ese valor.
- Lectura de ficheros en cada resolución de config (se evalúa por petición si no hay caché) y comentarios con nombres de secretos del ecosistema (`GLOBAL_*`) crean acoplamiento con otros proyectos.
- `LLM_MODEL` fuerza silenciosamente `llama-3.3-70b-versatile` → `openai/gpt-oss-120b`: cambio de modelo no documentado.

## 2. Evidencia de Código
```php
$envPath = base_path('.env');
$lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
foreach (['LLM_API_KEY','GROQ_API_KEY','GLOBAL_GROQ_API_KEY','GLOBAL_GOOGLE_API_KEY','GEMINI_API_KEY'] as $envKey) { ... return $val; }
```
```php
$secret = (string) (config('services.jg_agente.secret') ?: env('PERMANENT_AGENT_SECRET', ''));
```

## 3. Impacto Operativo o de Seguridad
Envío de una API key a un proveedor distinto del propietario (fuga de credenciales), comportamiento distinto con y sin `config:cache`, configuración opaca difícil de auditar.

## 4. Solución Recomendada
- Una variable por proveedor y selección explícita por `LLM_PROVIDER`:
```php
'llm' => ['provider' => env('LLM_PROVIDER','groq'), 'keys' => ['groq' => env('GROQ_API_KEY'), 'openai' => env('OPENAI_API_KEY'), 'gemini' => env('GEMINI_API_KEY')], ...]
```
- Eliminar el parseo manual de `.env` y toda llamada a `env()` fuera de `config/`.
- Quitar el LLM directo del cliente (ISSUE-010) o aislar su configuración.
- Test que ejecute `config:cache` y verifique `config('services.jg_agente.secret')`.
