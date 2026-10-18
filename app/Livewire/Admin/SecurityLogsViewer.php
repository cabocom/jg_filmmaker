<?php

namespace App\Livewire\Admin;

use App\Models\AdminAuditLog;
use App\Models\SecurityBreachLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Alertas de Seguridad y Replay Attacks - Administración jG Filmmaker')]
class SecurityLogsViewer extends Component
{
    use WithPagination;

    public function clearLogs(): void
    {
        $this->authorize('admin');

        $totalCount = SecurityBreachLog::count();
        if ($totalCount === 0) {
            session()->flash('status', 'No existen registros de seguridad para purgar.');

            return;
        }

        // Registrar obligatoriamente la evidencia forense de la purga en admin_audit_logs antes de eliminar
        AdminAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'purge_security_breach_logs',
            'target_type' => SecurityBreachLog::class,
            'details' => [
                'purged_count' => $totalCount,
                'retention_policy' => 'manual_admin_purge',
            ],
            'ip_address' => request()->ip(),
        ]);

        SecurityBreachLog::query()->delete();
        session()->flash('status', "Historial de registros de seguridad purgado ({$totalCount} eventos auditados).");
    }

    public function render()
    {
        $logs = SecurityBreachLog::with('user')
            ->orderBy('attempted_at', 'desc')
            ->paginate(20);

        return view('livewire.admin.security-logs-viewer', [
            'logs' => $logs,
        ]);
    }
}
