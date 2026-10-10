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

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span>Alertas de Seguridad y Replay Attacks</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                        {{ $logs->total() }} eventos
                    </span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Detección forense de tokens OTP reutilizados, caducados o intentos de vulneración
                </p>
            </div>

            @if($logs->count() > 0)
                <button wire:click="clearLogs"
                        wire:confirm="¿Seguro que deseas purgar todos los registros de brechas?"
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-rose-600 hover:border-rose-500 transition cursor-pointer">
                    Purgar Historial
                </button>
            @endif
        </div>

        <!-- Tabla de Eventos de Seguridad -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono uppercase text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Fecha y Hora</th>
                            <th class="px-4 py-3">Motivo / Incidente</th>
                            <th class="px-4 py-3">Usuario Afectado</th>
                            <th class="px-4 py-3">Dirección IP</th>
                            <th class="px-4 py-3">User Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3.5 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    {{ $log->attempted_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-mono font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        {{ $log->reason }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($log->user)
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $log->user->name }}</div>
                                        <div class="font-mono text-[11px] text-slate-500">{{ $log->user->email }}</div>
                                    @else
                                        <span class="text-slate-400 font-mono text-[11px]">Desconocido / No registrado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 font-mono text-slate-700 dark:text-slate-300">
                                    {{ $log->ip_address }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-500 text-[11px] font-mono max-w-xs truncate" title="{{ $log->user_agent }}">
                                    {{ $log->user_agent ?: 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-slate-500 space-y-2">
                                    <div class="text-3xl">🛡️</div>
                                    <div class="font-mono font-bold text-slate-700 dark:text-slate-300">Cero incidentes de seguridad registrados</div>
                                    <p class="text-xs text-slate-400">No se han detectado intentos de reutilización de tokens ni brechas de acceso.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
