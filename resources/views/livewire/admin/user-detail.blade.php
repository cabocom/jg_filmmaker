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

        <!-- Migas de Pan y Encabezado -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-mono text-slate-500">
                    <a href="{{ route('admin.users') }}" class="hover:text-amber-500 transition">Usuarios</a>
                    <span>/</span>
                    <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ $user->name }}</span>
                </div>
                <h2 class="text-lg font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider">
                    Auditoría Cuantitativa de Usuario
                </h2>
            </div>

            <a href="{{ route('admin.users') }}"
               class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-amber-500 transition">
                ← Volver al Listado
            </a>
        </div>

        <!-- Tarjetas de Resumen del Usuario -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[11px] font-mono text-slate-500 uppercase">Perfil / Plan</span>
                <div class="font-bold text-slate-900 dark:text-white truncate">{{ $user->name }}</div>
                <div class="flex items-center gap-2 pt-1">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        {{ $user->plan_tier }}
                    </span>
                    <span class="text-xs text-slate-500 font-mono">{{ $user->is_admin ? '🛡️ Admin' : '👤 Usuario' }}</span>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[11px] font-mono text-slate-500 uppercase">Slots de Proyecto</span>
                <div class="text-2xl font-extrabold font-mono text-slate-900 dark:text-white">
                    {{ $projects->count() }} <span class="text-sm font-normal text-slate-400">/ {{ $user->paid_project_slots }}</span>
                </div>
                <p class="text-[11px] text-slate-500">Proyectos creados sobre cuota contratada</p>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[11px] font-mono text-slate-500 uppercase">Tokens Hoy (Límite Diario)</span>
                <div class="text-2xl font-extrabold font-mono text-amber-600 dark:text-amber-400">
                    {{ number_format($userTokensUsedToday) }} <span class="text-xs font-normal text-slate-400">/ {{ number_format($userDailyTokenLimit) }}</span>
                </div>
                <p class="text-[11px] text-slate-500">{{ number_format($userTokensAvailableToday) }} restantes hoy (reset 00:00)</p>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[11px] font-mono text-slate-500 uppercase">Tokens IA Acumulados</span>
                <div class="text-2xl font-extrabold font-mono text-indigo-600 dark:text-indigo-400">
                    {{ number_format($userTotalTokens) }}
                </div>
                <p class="text-[11px] text-slate-500">Total histórico en todos los proyectos</p>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                <span class="text-[11px] font-mono text-slate-500 uppercase">Gasto Histórico Estimado</span>
                <div class="text-2xl font-extrabold font-mono text-emerald-600 dark:text-emerald-400">
                    ${{ number_format($userTotalCost, 4) }}
                </div>
                <p class="text-[11px] text-slate-500">Tarifa según modelos consumidos (USD)</p>
            </div>
        </div>

        <!-- Banner de Privacidad Estricta -->
        <div class="p-3.5 rounded-xl bg-cyan-950/20 border border-cyan-500/30 text-cyan-800 dark:text-cyan-300 text-xs flex items-center gap-2.5">
            <span class="text-base">🔒</span>
            <span class="leading-relaxed font-sans">
                <strong>Protocolo de Privacidad Estricta de Obras:</strong> Esta consola no almacena ni expone en memoria fragmentos de texto, argumentos ni diálogos de las creaciones del autor. Solo se presentan agregaciones cuantitativas y telemetría de costes.
            </span>
        </div>

        <!-- Tabla de Proyectos del Usuario -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider">
                    Obras Cinematográficas del Autor ({{ $projects->count() }})
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono uppercase text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Título / Estado</th>
                            <th class="px-4 py-3 text-center">Escenas</th>
                            <th class="px-4 py-3">Desglose Cuantitativo de Bloques</th>
                            <th class="px-4 py-3 text-center">Peticiones IA</th>
                            <th class="px-4 py-3 text-right">Tokens Consumidos</th>
                            <th class="px-4 py-3 text-right">Coste Est. (USD)</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                        @forelse($projects as $project)
                            @php
                                $stageCounts = $blockCountsByProject[$project->id] ?? [];
                                $rawCount = $stageCounts['raw'] ?? 0;
                                $structuredCount = $stageCounts['structured'] ?? 0;
                                $screenplayCount = $stageCounts['screenplay'] ?? 0;
                                $shotsCount = $stageCounts['shots'] ?? 0;

                                $ai = $aiMetricsByProject[$project->id] ?? ['total_requests' => 0, 'total_tokens' => 0, 'total_cost_usd' => 0];
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $project->title }}</div>
                                    <div class="flex items-center gap-2 pt-1 font-mono text-[11px]">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold {{ $project->status === 'completed' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600' }}">
                                            {{ $project->status }}
                                        </span>
                                        <span class="text-slate-400">{{ $project->created_at->format('d/m/Y') }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ $project->scenes_count }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <!-- Métricas Cuantitativas de Bloques por Etapa (Sin exponer ningún texto) -->
                                    <div class="flex items-center gap-1.5 flex-wrap font-mono text-[11px]">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700" title="Bloques en bruto">
                                            Bruto: <strong>{{ $rawCount }}</strong>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/50" title="Bloques con tratamiento estructurado">
                                            Tratamiento: <strong>{{ $structuredCount }}</strong>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50" title="Bloques con guion literario">
                                            Guión: <strong>{{ $screenplayCount }}</strong>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/50" title="Bloques con desglose de planos">
                                            Planos: <strong>{{ $shotsCount }}</strong>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono text-slate-700 dark:text-slate-300">
                                    {{ $ai['total_requests'] }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ number_format($ai['total_tokens']) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    ${{ number_format($ai['total_cost_usd'], 4) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono">
                                    <button wire:click="openTransferModal('{{ $project->id }}')"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 hover:border-amber-500/50 transition cursor-pointer flex items-center gap-1.5 ml-auto">
                                        <span>🔄</span>
                                        <span>Transferir</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500 font-mono">
                                    Este usuario no tiene obras registradas todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Transferencia de Proyecto -->
    @if($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span class="text-[11px] font-mono text-amber-600 dark:text-amber-400 font-bold uppercase tracking-wider">
                            Reasignación de Obra
                        </span>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            Transferir Proyecto
                        </h3>
                    </div>
                    <button wire:click="closeTransferModal"
                            type="button"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg leading-none cursor-pointer">
                        ✕
                    </button>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-mono">Proyecto:</span>
                        <strong class="text-slate-900 dark:text-white font-mono">{{ $selectedProjectTitle }}</strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-mono">Propietario actual:</span>
                        <span class="text-slate-700 dark:text-slate-300 font-semibold">{{ $user->name }}</span>
                    </div>
                </div>

                <form wire:submit.prevent="transferProject" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-mono font-bold text-slate-700 dark:text-slate-300 uppercase">
                            Seleccionar Nuevo Propietario
                        </label>
                        <select wire:model="targetUserId"
                                class="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                            <option value="">-- Elige un usuario de destino --</option>
                            @foreach($availableUsers as $target)
                                <option value="{{ $target->id }}">
                                    {{ $target->name }} ({{ $target->email }}) — [{{ $target->projects_count }} / {{ $target->paid_project_slots }} slots]
                                </option>
                            @endforeach
                        </select>
                        @error('targetUserId')
                            <p class="text-xs text-rose-500 font-mono">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-[11px] text-slate-500 leading-relaxed font-sans">
                        ⚠️ Al transferir, el nuevo usuario obtendrá la titularidad del guion, escenas, bloques y su respectiva telemetría técnica.
                    </p>

                    <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-200 dark:border-slate-800">
                        <button wire:click="closeTransferModal"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-mono font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-xl text-xs font-mono font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-xs transition cursor-pointer flex items-center gap-2">
                            <span wire:loading.remove>Confirmar Transferencia</span>
                            <span wire:loading>Procesando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
