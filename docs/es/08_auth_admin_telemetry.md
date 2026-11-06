# 08. Autenticación Passwordless, Panel de Administración y Telemetría (`jg_filmmaker`)

[← 07. Estrategia de Testing y CI/CD](07_testing_quality_cicd.md) | [Volver al Índice de Documentación](README.md)

---

## 1. Visión General y Objetivos

A partir de la Fase 8 y consolidadas en las Fases 15-16, `jg_filmmaker` incorpora una capa integral de seguridad, gobernanza multiusuario, administración global y auditoría de costes de Inteligencia Artificial inspirada en la arquitectura de `jg_hub`:

1. **Autenticación Passwordless por Email con PIN OTP:** Eliminación de contraseñas estáticas mediante códigos alfanuméricos efímeros de un solo uso con ventana de caducidad estricta (15 minutos).
2. **Experiencia Acelerada en Entorno de Desarrollo (Localhost):** Visualización reactiva en pantalla del PIN generado para facilitar el inicio de sesión inmediato sin requerir configuración de servidor SMTP saliente.
3. **Detección Forense de Replay Attacks:** Bloqueo y registro inmutable en auditoría de seguridad ante reintentos de códigos ya consumidos o caducados.
4. **Gobernanza y Roles de Usuario (`is_admin`):** Protección estricta de rutas `/admin/*` mediante middleware dedicado (`EnsureUserIsAdmin`).
5. **Auditoría Cuantitativa con Privacidad Estricta de Obras:** Acceso a métricas agregadas de proyectos de autores sin cargar ni exponer en ningún momento fragmentos literarios, sinopsis privadas o textos de guion.
6. **Catálogo Global y Migración / Reasignación de Proyectos:** Posibilidad de transferir la titularidad de cualquier proyecto cinematográfico entre cuentas de usuario de forma atómica y transaccional.
7. **Telemetría de Consumo de Tokens y Costes en USD:** Registro por petición de tokens de entrada/salida y estimación en tiempo real del gasto según tarifas de proveedores de IA configurables.

---

## 2. Autenticación Passwordless y Seguridad

### 2.1. Flujo de Generación y Verificación de OTP

```text
┌──────────────┐                 ┌───────────────────┐                 ┌───────────────────────┐
│ Usuario Web  │                 │    Livewire Login │                 │ Base de Datos (SQLite)│
└──────┬───────┘                 └─────────┬─────────┘                 └───────────┬───────────┘
       │                                   │                                       │
       │ 1. Introduce email                │                                       │
       ├──────────────────────────────────►│ 2. Genera PIN alfanumérico seguro     │
       │                                   │    (XXXX-XXXX, 8 caracteres)          │
       │                                   │ 3. Hash SHA-256 + caducidad (15 min)  │
       │                                   ├──────────────────────────────────────►│
       │                                   │                                       │ Guarda en
       │                                   │ 4. Si LOCALHOST: Muestra en vista     │ one_time_passwords
       │                                   │    Si PRODUCCIÓN: Envía Email Mailable│
       │ 5. Introduce o autofill del PIN   │                                       │
       ├──────────────────────────────────►│ 6. Verifica Hash y Estado              │
       │                                   ├──────────────────────────────────────►│
       │                                   │    - ¿Existe y coincide?              │
       │                                   │    - ¿Está expirado?                  │
       │                                   │    - ¿Fue usado previamente?          │
       │                                   │◄──────────────────────────────────────┤
       │                                   │                                       │
       │                                   │ [Si USADO o EXPIRADO]                 │
       │                                   │ 7. Registra brecha de seguridad       │
       │                                   ├──────────────────────────────────────►│
       │                                   │    (REUSED_OR_EXPIRED_TOKEN)          │ Guarda en
       │                                   │ 8. Deniega acceso                     │ security_breach_logs
       │◄──────────────────────────────────┤                                       │
       │                                   │ [Si VÁLIDO]                           │
       │                                   │ 9. Marca used_at = now()              │
       │                                   ├──────────────────────────────────────►│
       │                                   │ 10. Inicia sesión (Auth::login)       │
       │ 11. Redirección a Proyectos/Admin │                                       │
       │◄──────────────────────────────────┤                                       │
```

### 2.2. Mitigación de Replay Attacks y Logs de Seguridad

Cualquier intento de reutilizar un PIN ya consumido o de verificar un código fuera de la ventana de validez dispara un registro forense en la tabla `security_breach_logs`:
- **Campos almacenados:** `id` (UUIDv4), `user_id` (nullable), `ip_address`, `user_agent`, `reason` (`REUSED_OR_EXPIRED_TOKEN`), `created_at`.
- **Visor administrativo:** Accesible en `/admin/security` (`SecurityLogsViewer`) con trazabilidad completa de IPs sospechosas y agentes de usuario.

---

## 3. Panel de Administración y Rutas Gobernadas

El panel de administración se ubica bajo el prefijo `/admin` y está protegido por el middleware `EnsureUserIsAdmin`:

```php
Route::prefix('admin')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', function () { return redirect()->route('admin.users'); })->name('admin.index');
    Route::get('/users', UserList::class)->name('admin.users');
    Route::get('/users/{user}', UserDetail::class)->name('admin.users.show');
    Route::get('/projects', ProjectManager::class)->name('admin.projects');
    Route::get('/pricing', PricingManager::class)->name('admin.pricing');
    Route::get('/security', SecurityLogsViewer::class)->name('admin.security');
});
```

### 3.1. Gobernanza de Usuarios (`/admin/users`)
- Vista de usuarios registrados con recuento de proyectos creados frente a slots contratados (`paid_project_slots`).
- Visualización de volumen total de tokens consumidos y gasto estimado en USD.
- Alternancia rápida de roles (`is_admin`) en caliente vía Livewire.

### 3.2. Auditoría Cuantitativa con Privacidad Estricta (`/admin/users/{user}`)
- Protocolo de Privacidad Estricta: La consulta a base de datos **nunca ejecuta `SELECT` sobre campos de texto** como `raw_text`, `structured_text` o `screenplay_content`.
- Agregación cuantitativa: Conteo de bloques agrupados exclusivamente por etapa (`raw`, `structured`, `screenplay`, `shots`).
- Acción directa de transferencia de obra por fila.

### 3.3. Catálogo Global y Migración de Proyectos (`/admin/projects`)
- Consola unificada para supervisar todas las obras de la plataforma.
- Búsqueda en tiempo real por título de obra o autor, y filtro desplegable por titular.
- **Mecanismo de Transferencia de Obra:**
  - Despliega modal interactivo indicando proyecto y propietario actual.
  - Valida que el nuevo titular exista en el sistema y no sea el propietario actual.
  - Ejecuta la reasignación dentro de una transacción `DB::transaction`, reasignando `project->user_id` y preservando intactas todas las escenas, bloques, tomas y logs de consumo asociados.

### 3.4. Gestión de Tarifas de Tokens (`/admin/pricing`)
- Configuración de costes en USD por cada 1.000.000 de tokens de entrada y salida (`input_cost_per_million`, `output_cost_per_million`).
- Soporte para múltiples proveedores y modelos (Groq Llama 3.3 70B, Llama 3.1 8B, Google Gemini 1.5 Flash, Ollama).
- Selección del modelo activo para cálculo automático de facturación en el pipeline.

---

## 4. Telemetría de Modelos de IA y Costes

Cada transición del pipeline cinematográfico procesada por `jg_agente` o los servicios de inferencia registra una entrada en la tabla `agent_request_logs`:

```sql
CREATE TABLE "agent_request_logs" (
    "id" varchar not null primary key,
    "project_id" varchar not null references "projects"("id") on delete cascade,
    "user_id" varchar references "users"("id") on delete set null,
    "pipeline_stage" varchar not null,
    "provider" varchar not null,
    "model" varchar not null,
    "input_tokens" integer not null default 0,
    "output_tokens" integer not null default 0,
    "total_tokens" integer not null default 0,
    "estimated_cost_usd" numeric(10, 6) not null default 0.000000,
    "latency_ms" integer,
    "created_at" datetime,
    "updated_at" datetime
);
```

El cálculo es efectuado de forma centralizada por `AgentTelemetryService`:
$$\text{Coste USD} = \left(\frac{\text{input\_tokens}}{1.000.000} \times \text{tarifa\_input}\right) + \left(\frac{\text{output\_tokens}}{1.000.000} \times \text{tarifa\_output}\right)$$

---

## 5. Pruebas y Cobertura Automatizada

El módulo cuenta con cobertura automatizada completa en:
- `PasswordlessAuthTest.php` (6 tests): Generación, visualización en localhost, expiración, reutilización ilícita y registro de brechas.
- `AdminPanelAndTelemetryTest.php` (5 tests): Denegación 403 a usuarios estándar, auditoría sin fugas de texto, toggle de rol admin y cálculo de costes USD.
- `ProjectTransferAdminTest.php` (4 tests): Reasignación atómica de proyectos tanto desde `UserDetail` como desde `ProjectManager`, validaciones de destino y aislamiento de datos.
