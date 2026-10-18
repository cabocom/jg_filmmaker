# Análisis - ISSUE-029: Blindaje de Red en Pruebas con preventStrayRequests, Mock Obligatorio y Actualización de Métricas

## 1. Situación Inicial
- En `phpunit.xml`, no se forzaba explícitamente `JG_AGENTE_MOCK=true`, `JG_AGENTE_URL`, `GROQ_API_KEY` ni `LLM_API_KEY`.
- En `tests/TestCase.php`, no se invocaba `Http::preventStrayRequests()`, lo cual permitía que peticiones HTTP no interceptadas intentasen comunicarse con servicios externos o instancias locales de `jg_agente` o APIs LLM reales si estaban activas.
- La documentación en `docs/07_testing_quality_cicd.md` contenía conteos desfasados de suites y pruebas históricas ("48 pruebas").
- Era necesario certificar que toda la suite de pruebas del sistema (148 tests en Laravel) opere bajo aislamiento hermético determinista, sin fuga de sockets ni llamadas a la red.

## 2. Solución Propuesta

### A. Configuración Hermética en `phpunit.xml`
Añadir variables de entorno obligatorias con `force="true"`:
- `<env name="JG_AGENTE_MOCK" value="true" force="true"/>`
- `<env name="JG_AGENTE_URL" value="http://agent.invalid" force="true"/>`
- `<env name="GROQ_API_KEY" value="" force="true"/>`
- `<env name="LLM_API_KEY" value="" force="true"/>`

### B. Blindaje en `tests/TestCase.php`
- En el método `setUp()` de la clase base `TestCase`, invocar `Http::preventStrayRequests()`.
- Fijar `config(['services.jg_agente.mock' => true, 'services.llm.api_key' => null])`.
- De este modo, cualquier intento no simulado de realizar peticiones HTTP hacia el exterior lanzará inmediatamente una excepción de Laravel impidiendo llamadas a la red.

### C. Actualización de `docs/07_testing_quality_cicd.md`
- Actualizar la documentación técnica para reflejar las 148 pruebas automatizadas existentes, las suites de seguridad multitenant, mitigación de ReDoS, paridad de contratos y el protocolo de mock determinista.

## 3. Plan de Verificación
- Ejecutar `./vendor/bin/pint --test` y `php artisan test`.
- Comprobar que los 148 tests pasen con 0 stray requests.
- Validar el pipeline CI/CD con `bash scripts/check.sh --cicd -p jg_filmmaker`.
