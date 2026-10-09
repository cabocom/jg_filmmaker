# 01. Arquitectura General del Sistema (`jg_filmmaker`)

[← Volver al Índice de Documentación](README.md) | [02. Pipeline Cinematográfico en 6 Etapas →](02_pipeline_stages.md)

---

## 1. Visión Holística y Propósito

`jg_filmmaker` es la aplicación web de producción cinematográfica asistida por agentes cognitivos dentro del ecosistema `javiergalvez-ia`.

Su misión principal es desacoplar el proceso de creación audiovisual en etapas controladas por el cineasta, permitiendo la asistencia iterativa de la IA sin que el autor pierda el control sobre sus decisiones creativas.

```text
┌─────────────────────────────────────────────────────────────────────────┐
│               NAVEGADOR WEB (Cineasta / Equipo Técnico)                 │
│               - Tema Claro / Oscuro con selector y persistencia         │
│               - Atajos de teclado (Cmd+B para Biblia Contextual)        │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │ HTTPS / Livewire 3 DOM Diffs
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       jg_filmmaker (Laravel 12)                         │
│                                                                         │
│  ┌───────────────────────┐         ┌─────────────────────────────────┐  │
│  │   Livewire Studio UI  │         │       Servicios de Negocio      │  │
│  │ - ScriptEditor        │ ──────► │ - ScriptPipelineService         │  │
│  │ - RoughCutTimeline    │         │ - UserLockResolver              │  │
│  │ - ContextBibleDrawer  │         │ - RoughCutAssemblyService       │  │
│  │ - ProjectList         │         │ - AgentClientService (HMAC)     │  │
│  └───────────────────────┘         └────────────────┬────────────────┘  │
│                                                     │                   │
│                                                     ▼                   │
│                                    ┌─────────────────────────────────┐  │
│                                    │   Base de Datos (UUIDv4)        │  │
│                                    │   - projects, scenes, blocks    │  │
│                                    │   - characters, locations       │  │
│                                    │   - shots, takes                │  │
│                                    └─────────────────────────────────┘  │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │ HTTP / HMAC-SHA256 (Red Privada)
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                     jg_agente + jg_plugin_filmmaker                     │
│                 (treatment, screenplay, shot breakdown)                 │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Stack Tecnológico

- **Framework Web:** Laravel 12 (v12.69.3) bajo PHP 8.4.
- **Frontend Reactivo:** Livewire 3/4 (componentes full-page, subida de archivos vía streaming `WithFileUploads`).
- **Motor de Estilos:** Tailwind CSS v4 con soporte de `@custom-variant dark (&:where(.dark, .dark *))` y selector dinámico persistido en `localStorage` (`jg_theme`/`console_theme`).
- **Bases de Datos:** Compatible con PostgreSQL y SQLite (entorno local).
- **Procesamiento Multimedia:** FFmpeg para la concatenación y renderizado de tomas aprobadas.
- **Calidad de Código:** PHPUnit 12 para pruebas automatizadas y Laravel Pint para verificación de estándares PSR-12.

---

## 3. Modelo de Seguridad Anti-IDOR (UUIDv4 Estricto)

Para garantizar aislamiento multitenant y neutralizar vulnerabilidades de referencia directa a objetos inseguros (IDOR):
1. **Claves Primarias:** Todas las tablas de dominio (`users`, `projects`, `characters`, `locations`, `scenes`, `script_blocks`, `shots`, `takes`) utilizan identificadores UUIDv4 (`$table->uuid('id')->primary()`).
2. **Trait `HasUuids`:** Todos los modelos Eloquent correspondientes implementan `Illuminate\Database\Eloquent\Concerns\HasUuids`.
3. **Claves Foráneas Tipadas:** Las relaciones se vinculan con `foreignUuid('campo_id')->constrained()->cascadeOnDelete()`.
4. **Exposición Cero de IDs Secuenciales:** Las rutas web (`/projects/{project}`) resuelven implícitamente por el UUID de la entidad, impidiendo la enumeración de recursos.

---

## 4. Desacoplamiento de Ecosistema

- `jg_filmmaker` **no comparte base de datos** con ningún otro servicio del ecosistema (`jg_agente`, `jg_dashboard`, `jg_hub`).
- El acceso a las capacidades cognitivas del plugin `jg_plugin_filmmaker` se realiza exclusivamente a través de peticiones HTTP autenticadas punto a punto con firma criptográfica HMAC-SHA256 mediante `AgentClientService`.
