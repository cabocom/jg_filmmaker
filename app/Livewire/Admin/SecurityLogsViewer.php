<?php

namespace App\Livewire\Admin;

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
        SecurityBreachLog::truncate();
        session()->flash('status', 'Historial de registros de seguridad purgado.');
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
