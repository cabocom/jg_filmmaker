<?php

namespace App\Livewire\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Gobernanza de Usuarios - Administración jG Filmmaker')]
class UserList extends Component
{
    public function toggleAdmin(string $userId): void
    {
        $this->authorize('admin');

        $user = User::findOrFail($userId);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes revocar tus propios privilegios de administrador.');

            return;
        }

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            session()->flash('error', 'Operación cancelada: El sistema debe conservar al menos un administrador activo.');

            return;
        }

        $previousRole = $user->is_admin;
        $user->is_admin = ! $user->is_admin;
        $user->save();

        AdminAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'toggle_admin',
            'target_type' => User::class,
            'target_id' => $user->id,
            'details' => [
                'target_email' => $user->email,
                'target_name' => $user->name,
                'previous_is_admin' => $previousRole,
                'new_is_admin' => $user->is_admin,
            ],
            'ip_address' => request()->ip(),
        ]);

        session()->flash('status', "Permisos de {$user->name} actualizados a ".($user->is_admin ? 'ADMIN' : 'USUARIO').'.');
    }

    public function render()
    {
        $users = User::withCount('projects')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calcular telemetría agrupada por usuario
        $telemetryByUser = DB::table('agent_request_logs')
            ->join('projects', 'agent_request_logs.project_id', '=', 'projects.id')
            ->select(
                'projects.user_id',
                DB::raw('SUM(agent_request_logs.total_tokens) as total_tokens'),
                DB::raw('SUM(agent_request_logs.estimated_cost_usd) as total_cost_usd')
            )
            ->groupBy('projects.user_id')
            ->get()
            ->keyBy('user_id');

        return view('livewire.admin.user-list', [
            'users' => $users,
            'telemetryByUser' => $telemetryByUser,
        ]);
    }
}
