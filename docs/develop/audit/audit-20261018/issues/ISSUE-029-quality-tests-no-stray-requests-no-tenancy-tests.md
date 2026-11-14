# [ISSUE-029] Suite PHPUnit: sin `preventStrayRequests`, sin forzar `JG_AGENTE_MOCK`, sin pruebas de tenencia/IDOR y CI sobre `.env.example`

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `phpunit.xml:21-38`, `tests/TestCase.php`, `tests/Feature/*`, `.gitlab-ci.yml` (job `test:phpunit`)
- **Categoría:** Calidad de Código

## 1. Descripción del Problema
- **Aislamiento de IA no determinista:** `phpunit.xml` fija `JG_AGENTE_SECRET` pero **no** `JG_AGENTE_MOCK=true` ni vacía `JG_AGENTE_URL`, `GROQ_API_KEY`, `LLM_API_KEY`. `AgentClientService::callTool` sólo evita el LLM directo por `app()->environment('testing')`, pero **sí intenta un `POST` real** a `JG_AGENTE_URL` (por defecto `127.0.0.1:8001`) en los tests que no usan `Http::fake`. Si el desarrollador tiene `jg_agente` levantado, los tests consumen el agente y tokens reales; en caso contrario, cada test paga un timeout/connect error. No existe `Http::preventStrayRequests()` en `TestCase`.
- Sólo 1 fichero (`AgentClientHmacSignatureTest`) usa `Http::fake`; el resto depende implícitamente del entorno.
- **Cero pruebas de aislamiento multitenant:** ningún test comprueba que el usuario B no puede abrir/editar/borrar recursos de A (ISSUE-003/004/005), ni que un OTP forzado con `isLocalEnv=true` no filtra el código (ISSUE-002), ni subida con extensión `.php` (ISSUE-001).
- El middleware demo (ISSUE-028) hace que casi todas las peticiones sean admin.
- `LivewireScriptEditorTest` (33 tests, ~900+ líneas) mezcla UI y reglas de negocio; los tests de falsos positivos construyen un servicio anónimo en vez de ejercitar `AgentClientService` real (línea ~892-920).
- **CI:** `cp .env.example .env` (APP_ENV=local, LOG_LEVEL=debug, JG_AGENTE_MOCK=false) y sólo `phpunit`: no ejecuta `pint --test`, ni análisis estático (Larastan), ni `composer audit`, ni escaneo de imagen.
- Documentación desfasada: `docs/07_testing_quality_cicd.md` afirma "48 pruebas" (y 28 en otro apartado); el repositorio contiene ~80 métodos de test.
- `.phpunit.result.cache` (10 KB) está presente en el árbol de trabajo (ignorado por git, pero revela residuos).

## 2. Evidencia de Código
```xml
<env name="JG_AGENTE_SECRET" value="phpunit_agent_secret_not_for_production"/>
<!-- sin JG_AGENTE_MOCK, sin JG_AGENTE_URL, sin GROQ_API_KEY -->
```
```php
if (! app()->environment('testing')) { $directResult = $this->callDirectLlm(...); }
```

## 3. Impacto Operativo o de Seguridad
Consumo accidental de tokens/servicios reales, tests lentos y flakys, y regresiones de seguridad críticas sin detección automática.

## 4. Solución Recomendada
```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();
    Http::preventStrayRequests();
    config(['services.jg_agente.mock' => true, 'services.llm.api_key' => null]);
}
```
- `phpunit.xml`: `JG_AGENTE_MOCK=true`, `JG_AGENTE_URL=http://agent.invalid`, `GROQ_API_KEY=`, `LLM_API_KEY=`.
- Suite `Security/` con `TenancyIsolationTest`, `LoginOtpTamperTest`, `UploadExtensionTest`, `AdminAuthorizationTest`.
- CI: añadir `pint --test`, `larastan`, `composer audit`, `npm audit`; ejecutar con `APP_ENV=testing` y `.env.ci` dedicado.
- Actualizar `docs/07` con cifras reales (o generarlas automáticamente).
