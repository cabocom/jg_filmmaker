# [ISSUE-012] Ausencia Generalizada de `declare(strict_types=1);` en PHP y Desalineación de Especificaciones (`docs/specs/`)

- **Severidad:** Deuda Técnica
- **Componente:** `jg_filmmaker` | `jg_plugin_filmmaker`
- **Ubicación:** `jg_filmmaker/app/**/*.php`, `jg_filmmaker/docs/specs/01_architecture_and_models.md`, `02_pipeline_stages_and_locks.md`, `03_jg_plugin_filmmaker_contract.md`, `04_shots_takes_media_management.md`
- **Categoría:** Deuda Técnica

## 1. Descripción
Durante la revisión de calidad de código y consistencia documental se han constatado dos deudas técnicas transversales:

1. **Ausencia de Tipado Estricto (`declare(strict_types=1);`) en PHP 8.4:**
   - De los **37 archivos PHP** bajo `jg_filmmaker/app/` (Modelos, Servicios, Controladores, Componentes Livewire, Jobs, Comandos y Middleware), **solo 5 archivos** incluyen `declare(strict_types=1);` (`AudioStreamController.php`, `PrewarmTtsCommand.php`, `PruneOrphanTtsAudiosCommand.php`, `PrewarmScopeTtsJob.php` y `TextToSpeechService.php`).
   - Los **32 archivos restantes (86,5%)** carecen de `declare(strict_types=1);`, permitiendo conversiones implícitas débiles de escalares en controladores críticos, servicios de telemetría, pipeline y políticas.

2. **Desfase entre `jg_filmmaker/docs/specs/` y la Implementación Real:**
   - **`01_architecture_and_models.md`:**
     - Afirma que *"Todos los modelos dependientes implementan Global Scopes (`BelongsToUser` / `BelongsToProject`)"* y un `CreateProjectAction` validado contra `paid_project_slots` y `plan_tier`. En el código real no existen esos Global Scopes ni `CreateProjectAction`; el control de creación en `ProjectList` utiliza la columna booleana `users.can_create_projects` y `users.daily_token_limit`.
     - No documenta los modelos implementados posteriormente (`OneTimePassword`, `SecurityBreachLog`, `AdminAuditLog`, `UserActivityLog`, `AgentRequestLog`, `TokenPricingRate`) ni los campos multimedia (`reference_image_path`, `storyboard_image_path`, `storyboard_prompt`, `prop_reference_paths`, `final_video_path`).
   - **`02_pipeline_stages_and_locks.md`:**
     - Define un esquema complejo JSON para `user_locks` (`locked_fields`, `manual_diffs`, `pinned_terms`), mientras que `ScriptBlock::lockStage()` en la implementación real almacena un mapa booleano por etapa (`{"structured": true, "screenplay": true}`) más `pinned_terms`.
   - **`03_jg_plugin_filmmaker_contract.md`:**
     - Tiene un bloque markdown anidado mal cerrado al inicio (`### Fichero 3: ... \`\`\`markdown`) y solo documenta **3 herramientas** (`treatment_structuring`, `screenplay_formatter`, `shot_breakdown_detector`), omitiendo las **4 herramientas adicionales** en producción: `plot_hole_detector`, `generate_storyboard`, `extract_characters` y `audit_character_usage`, así como la cabecera obligatoria `X-Key-Id`.
   - **`04_shots_takes_media_management.md`:**
     - Documenta el patrón `--- shot N (tipo) ---` como único estándar, cuando el sistema evolucionó al delimitador canónico sin número `--- shot (TIPO) ---` con numeración automática.

## 2. Evidencia de Código
En [`jg_filmmaker/docs/specs/01_architecture_and_models.md`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/specs/01_architecture_and_models.md#L10-L14):
```markdown
- **Aislamiento de Datos:** Todos los modelos dependientes implementan Global Scopes (`BelongsToUser` / `BelongsToProject`) y Laravel Policies asociadas a `auth()->id()`.
- **Límites de Proyecto (Cuotas):**
  - Tabla `users`: campos `plan_tier` ('free', 'pro') y `paid_project_slots` (integer).
  - Regla: Un usuario puede crear $N$ proyectos gratuitos. Para superar la cuota, la creación se valida contra `paid_project_slots` mediante un `CreateProjectAction`.
```

En [`jg_filmmaker/docs/specs/03_jg_plugin_filmmaker_contract.md`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/docs/specs/03_jg_plugin_filmmaker_contract.md#L1-L7):
```markdown
---

### Fichero 3: `03_jg_plugin_filmmaker_contract.md`

```markdown
# 03. Contratos Canónicos del Plugin `jg_plugin_filmmaker`
```

## 3. Impacto Operativo o Riesgo
- **Riesgo de Coerción Silenciosa de Tipos:** Sin `declare(strict_types=1);`, PHP realiza conversiones implícitas (`"123abc"` -> `123`, `null` -> `""` o `0`) que pueden enmascarar errores lógicos.
- **Desorientación Arquitectónica:** Mientras `PROJECT_CONTEXT.md` está actualizado a la versión 3.51.0 (`ISSUE-069`), los documentos en `docs/specs/` reflejan el diseño inicial pre-MVP y contradicen el comportamiento actual del código.

## 4. Solución o Mejora Recomendada
1. Añadir `declare(strict_types=1);` en todos los archivos de `jg_filmmaker/app/`, `config/`, `database/` y `tests/`, incorporando la regla `"declare_strict_types": true` en `pint.json` para garantizar su cumplimiento automático en el pipeline CI/CD.
2. Actualizar `docs/specs/01..04` para alinearlos con los 14 modelos Eloquent reales, las 7 herramientas de `jg_plugin_filmmaker`, el protocolo HMAC con `X-Key-Id` y el delimitador `--- shot (TIPO) ---`.

