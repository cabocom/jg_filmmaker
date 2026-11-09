# [ISSUE-024] Pipelines GitLab CI Sin Verificación de `Pint`, Cobertura Mínima ni Auditoría de Dependencias (Pendiente de `audit-20261018 / ISSUE-029 & ISSUE-042`)

- **Severidad:** Deuda Técnica
- **Componente:** `jg_filmmaker` | `jg_plugin_filmmaker`
- **Ubicación:** `jg_filmmaker/.gitlab-ci.yml:9-31`, `jg_plugin_filmmaker/.gitlab-ci.yml:40-54`
- **Categoría:** Calidad

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-029` e `ISSUE-042`) se identificó que los pipelines de integración continua (`.gitlab-ci.yml`) de ambos repositorios omitían comprobaciones esenciales de estilo, cobertura y seguridad de dependencias. Al inspeccionar los archivos `.gitlab-ci.yml` actuales se constata que dichos controles no llegaron a incorporarse:

1. **En `jg_filmmaker/.gitlab-ci.yml`:**
   - El job `test:phpunit` ejecuta únicamente `./vendor/bin/phpunit`. **No ejecuta `./vendor/bin/pint --test`** (a pesar de ser obligatorio según las reglas de calidad del proyecto), ni ejecuta `composer audit`.
   - En la línea 24 sigue ejecutando `cp .env.example .env` (donde `APP_ENV=local` y `APP_DEBUG=true`), dependiendo exclusivamente de los overrides de `phpunit.xml`.
2. **En `jg_plugin_filmmaker/.gitlab-ci.yml`:**
   - El job `test:pytest` ejecuta `pytest tests/` sin umbral mínimo de cobertura (`--cov=jg_plugin_filmmaker --cov-fail-under=85`) ni chequeo de tipado estático (`mypy`).

## 2. Evidencia de Código
En [`jg_filmmaker/.gitlab-ci.yml`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/.gitlab-ci.yml#L21-L31):
```yaml
  before_script:
    - mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache
    - chmod -R 777 storage bootstrap/cache
    - cp .env.example .env
    - mkdir -p public/build && echo '{}' > public/build/manifest.json
    - composer install --no-interaction --prefer-dist
    - php artisan key:generate
  script:
    - echo "🐘 Ejecutando suite de pruebas automatizadas PHPUnit de jg_filmmaker..."
    - ./vendor/bin/phpunit
```

En [`jg_plugin_filmmaker/.gitlab-ci.yml`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_plugin_filmmaker/.gitlab-ci.yml#L51-L54):
```yaml
  script:
    - echo "🧪 Ejecutando suite de tests unitarios de jg_plugin_filmmaker..."
    - pytest tests/
```

## 3. Impacto Operativo o Riesgo
- **Código sin Formatear o con Regresiones de Cobertura en `main`:** Un commit que incumpla Laravel Pint o reduzca drásticamente la cobertura de tests supera el pipeline de GitLab CI y publica la imagen `:latest` en el Container Registry sin bloquearse.

## 4. Solución o Mejora Recomendada
1. En `jg_filmmaker/.gitlab-ci.yml`, añadir `./vendor/bin/pint --test` antes de `./vendor/bin/phpunit` en el stage `test`:
   ```yaml
   script:
     - echo "🎨 Verificando estándar de código con Laravel Pint..."
     - ./vendor/bin/pint --test
     - echo "🐘 Ejecutando suite de pruebas automatizadas PHPUnit de jg_filmmaker..."
     - ./vendor/bin/phpunit
   ```
2. En `jg_plugin_filmmaker/.gitlab-ci.yml`, añadir `--cov=jg_plugin_filmmaker --cov-fail-under=85` a la ejecución de `pytest`.

