<?php

namespace App\Livewire\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Gobernanza de Usuarios - Administración jG Filmmaker')]
class UserList extends Component
{
    use WithPagination;

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
            ->paginate(25);

        // Calcular telemetría agrupada exclusivamente para los usuarios de la página actual
        $userIds = $users->pluck('id')->filter()->all();
        $telemetryByUser = empty($userIds) ? collect() : DB::table('agent_request_logs')
            ->whereIn('user_id', $userIds)
            ->select(
                'user_id',
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as total_cost_usd')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return view('livewire.admin.user-list', [
            'users' => $users,
            'telemetryByUser' => $telemetryByUser,
        ]);
    }
}
