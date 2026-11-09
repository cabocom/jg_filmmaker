# [ISSUE-021] Destrucción Forense con `SecurityBreachLog::truncate()` en `clearLogs()` y Tarifas de Tokens sin Versionado Temporal (Pendiente de `audit-20261018 / ISSUE-021`)

- **Severidad:** Media
- **Componente:** `jg_filmmaker`
- **Ubicación:** `jg_filmmaker/app/Livewire/Admin/SecurityLogsViewer.php:17-37`, `jg_filmmaker/app/Livewire/Admin/PricingManager.php:66-125`
- **Categoría:** Seguridad

## 1. Descripción
En la auditoría `audit-20261018` (`ISSUE-021`) se identificaron dos debilidades de gobernanza administrativa que quedaron sin resolver en el código actual:

1. **Borrado Masivo Irreversible (`truncate`) de Evidencia Forense en `SecurityLogsViewer::clearLogs()`:**
   - Aunque se añadió `$this->authorize('admin')` y un registro previo en `AdminAuditLog`, el método `SecurityLogsViewer::clearLogs()` sigue ejecutando `SecurityBreachLog::truncate()`.
   - Cualquier administrador (o un atacante que comprometa una sesión de administrador) puede vaciar con un solo clic el 100% del historial forense de ataques de fuerza bruta y reutilización de tokens OTP (`security_breach_logs`), incluyendo eventos recientes en investigación, en lugar de aplicar una política de retención temporal (`Prunable` > 90/180 días).
2. **Ausencia de Versionado Histórico de Tarifas en `PricingManager` (`token_pricing_rates`):**
   - `PricingManager::saveRate()` sobreescribe in-place `input_price_per_million` y `output_price_per_million` sobre la fila existente sin conservar un historial de vigencia (`effective_from` / `effective_until`) ni guardar en `agent_request_logs` qué tarifa se aplicó en el momento de la petición.

## 2. Evidencia de Código
En [`jg_filmmaker/app/Livewire/Admin/SecurityLogsViewer.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_filmmaker/app/Livewire/Admin/SecurityLogsViewer.php#L17-L37):
```php
    public function clearLogs(): void
    {
        $this->authorize('admin');

        $count = SecurityBreachLog::count();

        AdminAuditLog::record(
            action: 'clear_security_logs',
            targetType: SecurityBreachLog::class,
            targetId: null,
            details: ['purged_records_count' => $count],
        );

        SecurityBreachLog::truncate();

        session()->flash('status', 'Registros de incidentes de seguridad purgados correctamente.');
    }
```

## 3. Impacto Operativo o Riesgo
- **Pérdida de Evidencia Forense Reciente:** Un `truncate()` elimina incluso las alertas de intrusión ocurridas segundos antes, impidiendo investigar direcciones IP o patrones de ataque activos.
- **Pérdida de Trazabilidad de Cambios de Precios:** Modificar una tarifa en `PricingManager` no deja registro de qué precio regía en fechas anteriores.

## 4. Solución o Mejora Recomendada
1. Sustituir `SecurityBreachLog::truncate()` en `SecurityLogsViewer::clearLogs()` por una purga acotada a registros antiguos (ej. `SecurityBreachLog::where('created_at', '<', now()->subDays(90))->delete()`), prohibiendo la eliminación de incidentes de seguridad de los últimos 90 días desde la interfaz web, e implementar el trait `Prunable` en `SecurityBreachLog`.
2. Guardar en `agent_request_logs.meta` el snapshot del `input_price_per_million` y `output_price_per_million` aplicado en el instante del cálculo.

