# [ISSUE-039] El `docker-compose.yml` del plugin reproduce todo el ecosistema con credenciales débiles por defecto y mezcla responsabilidades

- **Severidad:** Media
- **Componente:** `jg_plugin_filmmaker`
- **Ubicación:** `jg_plugin_filmmaker/docker-compose.yml:14-16,47-62,77-79,106-124`, `jg_plugin_filmmaker/.env.example`
- **Categoría:** Seguridad

## 1. Descripción del Problema
El repositorio de un **plugin satélite** incluye un compose de 6 KB que levanta `jg_dashboard`, `jg_agente`, dos PostgreSQL y secretos compartidos: duplica la orquestación del ecosistema dentro de un repo que debería ser una librería (`pyproject.toml` + entry point). Contiene valores por defecto de credenciales (`${VAR:-<valor>}`) para:
- Contraseñas de PostgreSQL (`DB_PASSWORD`, `JG_AGENTE_POSTGRES_PASSWORD`).
- `APP_KEY` de `jg_dashboard`.
- `JG_AGENTE_SECRET`, reutilizado como `JG_CONVERSATIONAL_SECRET`, `JG_DEVOPS_SECRET` y `PERMANENT_AGENT_SECRET` (ISSUE-031).
- `ADMIN_EMAIL` / **`ADMIN_DEFAULT_PASSWORD` con contraseña débil por defecto** de administrador del dashboard.
- `LLM_API_KEY`/`GROQ_API_KEY` ← `GLOBAL_GROQ_API_KEY` por defecto.
Un `docker compose up` sin `.env` produce un entorno con credenciales previsibles y administrador conocido; si se expone un puerto (comentado en parte) el riesgo es inmediato. Además el plugin queda acoplado a la topología de otros proyectos (viola el desacoplamiento de AGENTS.md §4) y su `.env.example` incluye `ADMIN_*`, variables que no usa el plugin.

## 2. Evidencia de Código
```yaml
ADMIN_EMAIL: ${ADMIN_EMAIL:-<valor por defecto>}
ADMIN_DEFAULT_PASSWORD: "${ADMIN_DEFAULT_PASSWORD:-<contraseña débil por defecto>}"
JG_CONVERSATIONAL_SECRET: "${JG_AGENTE_SECRET:-<valor por defecto>}"
JG_DEVOPS_SECRET: "${JG_AGENTE_SECRET:-<valor por defecto>}"
```
(los valores reales se omiten deliberadamente en este informe)

## 3. Impacto Operativo o de Seguridad
Despliegues involuntarios con credenciales conocidas, mezcla de ciclos de vida (plugin vs plataforma) y confusión sobre cuál es el compose canónico.

## 4. Solución Recomendada
- Eliminar el compose del plugin (el plugin se instala en `jg_agente` vía entry point `jg_agente.plugins`) o limitarlo a un `docker-compose.test.yml` sin secretos.
- Variables obligatorias (`${VAR:?}`) y sin valores por defecto para contraseñas, claves y admin.
- Rotar cualquier valor que haya estado versionado.
- Una única fuente de verdad del compose del ecosistema.
