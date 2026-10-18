# [ISSUE-033] Discrepancias entre la documentación (`docs/`, `docs/specs/`) y la implementación real

- **Severidad:** Media
- **Componente:** `jg_filmmaker` / `jg_plugin_filmmaker`
- **Ubicación:** `docs/specs/01_architecture_and_models.md:9`, `docs/01_architecture.md:67-70`, `docs/08_auth_admin_telemetry.md`, `docs/07_testing_quality_cicd.md`, `docs/05_hmac_agent_integration.md`, `docs/specs/03_jg_plugin_filmmaker_contract.md`, `jg_plugin_filmmaker/docs/02_tools_specification.md`
- **Categoría:** Calidad de Código

## 1. Descripción del Problema
| # | Documentación afirma | Realidad en código |
|---|---|---|
| 1 | Claves **UUIDv4** (`HasUuids`) en todas las entidades | `HasUuids` de Laravel 12 genera **UUIDv7** (ISSUE-006) |
| 2 | "Anti-IDOR: aislamiento multitenant por UUID" | No existen policies ni scopes (ISSUE-003/004/005) |
| 3 | OTP: "Hash SHA-256 + caducidad" | `Hash::make()` (bcrypt) por defecto; se compara en bucle sobre todo el historial (ISSUE-008) |
| 4 | OTP visible "si LOCALHOST", producción por email | Condición basada en Host/IP y propiedad de cliente (ISSUE-002) |
| 5 | Suite de "48 pruebas y 260 aserciones" / "28 pruebas" en CI / `DomainModelArchitectureTest` 6 tests / `TakeMediaUploadTest` 4 | ≈ 80 métodos de test; `DomainModel…` 4 y `TakeMediaUpload…` 3 |
| 6 | Tabla de tools: 3 herramientas (docs 05, spec 03) | El cliente invoca además `plot_hole_detector` (ISSUE-011) |
| 7 | Plugin: `characters_context: list[str]` (`02_tools_specification.md`) | Código: `list[dict[str, Any]]` y Laravel envía claves distintas a las que el plugin lee (ISSUE-012) |
| 8 | Ensamblado FFmpeg con reintento a `libx264` | El reintento usa una lista concat ya borrada (ISSUE-009) |
| 9 | Fallback determinista sólo "si falla HTTP o estamos en testing" | Fuera de testing se interpone un LLM directo con clave global (ISSUE-010) |
| 10 | "Base de datos PostgreSQL" en varios documentos y diagramas | Runtime SQLite (ISSUE-032) |
| 11 | Livewire 3/4 | `composer.lock`: Livewire 4.4.7 |
| 12 | Privacidad estricta (admin sin acceso a textos) | `transferProject` otorga la propiedad total sin consentimiento y el admin puede abrir cualquier proyecto por la ausencia de policy (ISSUE-003/021) |
| 13 | Cuota diaria de tokens | Sólo aplicada a la auditoría de agujeros (ISSUE-017) |
| 14 | `config('services.filmmaker.mock_ffmpeg')` mencionado en el servicio | La clave no existe en `config/services.php` (siempre `false`) |

También: `docs/walkthrough-initial.md`, `docs/develop/*` y `PROJECT_CONTEXT.md` (70 KB) acumulan historia de fases; la "foto real" requerida por AGENTS.md §1.2 no se corresponde con los puntos anteriores.

## 2. Evidencia de Código
```php
(bool) config('services.filmmaker.mock_ffmpeg', false)    // sin definición en config/services.php
```
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;       // v7 en Laravel 12
```

## 3. Impacto Operativo o de Seguridad
Decisiones de seguridad tomadas sobre premisas falsas (p. ej. "UUID ⇒ anti-IDOR"), onboarding erróneo, tests y despliegues basados en cifras y diagramas obsoletos.

## 4. Solución Recomendada
- Corregir la documentación tras resolver (o reconociendo explícitamente como deuda) cada ISSUE enlazado; incorporar una sección "Deuda técnica conocida" que apunte a `docs/issues/`.
- Añadir al CI un job que valide métricas verificables (nº de tests, versión Livewire, tools registradas vs documentadas) o generar las tablas automáticamente.
- Declarar `services.filmmaker.mock_ffmpeg` en config o retirar la referencia.
- Reducir `PROJECT_CONTEXT.md` a estado vigente y mover el histórico a `docs/develop/`.
