<div class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors pb-12">
    <x-admin-nav />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if(session()->has('status'))
            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-200 text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2 font-mono">
                    <span>✓</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if(session()->has('error'))
            <div class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-800 dark:text-red-200 text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2 font-mono">
                    <span>⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider">
                    Usuarios Registrados ({{ $users->count() }})
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Control de cuotas, roles de acceso y consumo acumulado de inteligencia artificial
                </p>
            </div>
        </div>

        <!-- Tabla Global de Usuarios -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono uppercase text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Usuario / Email</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3 text-center">Slots</th>
                            <th class="px-4 py-3 text-center">Proyectos</th>
                            <th class="px-4 py-3 text-right">Tokens Consumidos</th>
                            <th class="px-4 py-3 text-right">Gasto Est. (USD)</th>
                            <th class="px-4 py-3 text-center">Rol</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                        @forelse($users as $user)
                            @php
                                $telemetry = $telemetryByUser->get($user->id);
                                $totalTokens = $telemetry ? (int) $telemetry->total_tokens : 0;
                                $totalCost = $telemetry ? (float) $telemetry->total_cost_usd : 0.000000;
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="font-mono text-[11px] text-slate-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $user->plan_tier === 'pro' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/30' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ $user->plan_tier }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ $user->paid_project_slots }}
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $user->projects_count }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ number_format($totalTokens) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    ${{ number_format($totalCost, 4) }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <button wire:click="toggleAdmin('{{ $user->id }}')"
                                            type="button"
                                            title="Cambiar rol de usuario / administrador"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-mono font-bold transition cursor-pointer {{ $user->is_admin ? 'bg-amber-500 text-slate-950 hover:bg-amber-400' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400' }}">
                                        <span>{{ $user->is_admin ? '🛡️ ADMIN' : '👤 USUARIO' }}</span>
                                    </button>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="px-2.5 py-1 rounded-lg text-xs font-mono font-semibold bg-slate-100 hover:bg-amber-500 hover:text-slate-950 dark:bg-slate-800 dark:hover:bg-amber-500 dark:hover:text-slate-950 text-slate-700 dark:text-slate-300 transition shadow-2xs">
                                        Ver Auditoría ➔
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                    No hay usuarios registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
