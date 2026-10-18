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

        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider">
                    Gestión y Migración de Obras
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Administración global de proyectos, gobernanza de autoría y transferencia entre cuentas
                </p>
            </div>
        </div>

        <!-- Banner de Privacidad Estricta -->
        <div class="p-3.5 rounded-xl bg-cyan-950/20 border border-cyan-500/30 text-cyan-800 dark:text-cyan-300 text-xs flex items-center gap-2.5">
            <span class="text-base">🔒</span>
            <span class="leading-relaxed font-sans">
                <strong>Protocolo de Privacidad Estricta:</strong> Esta consola administrativa procesa exclusivamente metadatos estructurales y agregaciones cuantitativas. En ningún caso se cargan ni exponen argumentos o textos de guion.
            </span>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-7">
                    <label class="block text-[11px] font-mono font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">
                        Buscar por Título o Autor
                    </label>
                    <div class="relative">
                        <input wire:model.live.debounce.300ms="search"
                               type="text"
                               placeholder="Ej: El Susurro, Javier, filmmaker..."
                               class="w-full px-3.5 py-2 pl-9 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 font-mono">
                        <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                    </div>
                </div>

                <div class="sm:col-span-5">
                    <label class="block text-[11px] font-mono font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">
                        Filtrar por Titular
                    </label>
                    <select wire:model.live="filterUserId"
                            class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 font-mono">
                        <option value="">Todos los autores ({{ $allUsers->count() }})</option>
                        @foreach($allUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }}) — {{ $u->projects_count }} obras</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabla de Proyectos Globales -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider">
                    Catálogo de Obras Cinematográficas ({{ $projects->count() }})
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono uppercase text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Obra / Estado</th>
                            <th class="px-4 py-3">Titular Actual</th>
                            <th class="px-4 py-3 text-center">Escenas</th>
                            <th class="px-4 py-3">Desglose Bloques</th>
                            <th class="px-4 py-3 text-right">Tokens IA</th>
                            <th class="px-4 py-3 text-right">Coste Est. (USD)</th>
                            <th class="px-4 py-3 text-right">Acción</th>
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
                                <td class="px-4 py-3.5">
                                    @if($project->user)
                                        <a href="{{ route('admin.users.show', $project->user->id) }}"
                                           class="group inline-block">
                                            <div class="font-semibold text-slate-900 dark:text-white group-hover:text-amber-500 transition">
                                                {{ $project->user->name }}
                                            </div>
                                            <div class="text-[11px] font-mono text-slate-500">
                                                {{ $project->user->email }}
                                            </div>
                                        </a>
                                    @else
                                        <span class="text-slate-400 font-mono italic">Sin asignar</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ $project->scenes_count }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-1.5 flex-wrap font-mono text-[11px]">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700" title="Bruto">
                                            B: <strong>{{ $rawCount }}</strong>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/50" title="Tratamiento">
                                            T: <strong>{{ $structuredCount }}</strong>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50" title="Guión">
                                            G: <strong>{{ $screenplayCount }}</strong>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/50" title="Planos">
                                            P: <strong>{{ $shotsCount }}</strong>
                                        </span>
                                    </div>
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
                                        <span>Cambiar Dueño</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500 font-mono">
                                    No se encontraron obras que coincidan con la búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($projects->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $projects->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal de Reasignación de Propietario -->
    @if($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <span class="text-[11px] font-mono text-amber-600 dark:text-amber-400 font-bold uppercase tracking-wider">
                            Reasignación de Obra
                        </span>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            Cambiar Propietario del Proyecto
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
                        <span class="text-slate-500 font-mono">Titular actual:</span>
                        <span class="text-slate-700 dark:text-slate-300 font-semibold">{{ $currentOwnerName }}</span>
                    </div>
                </div>

                <form wire:submit.prevent="transferProject" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-mono font-bold text-slate-700 dark:text-slate-300 uppercase">
                            Seleccionar Nuevo Propietario
                        </label>
                        <select wire:model="targetUserId"
                                class="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 font-mono">
                            <option value="">-- Elige un usuario de destino --</option>
                            @foreach($allUsers as $target)
                                @if($target->id !== $currentOwnerId)
                                    <option value="{{ $target->id }}">
                                        {{ $target->name }} ({{ $target->email }}) — [{{ $target->projects_count }} / {{ $target->paid_project_slots }} slots]
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('targetUserId')
                            <p class="text-xs text-rose-500 font-mono">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-[11px] text-slate-500 leading-relaxed font-sans">
                        ⚠️ Al transferir, el autor seleccionado pasará a ser el propietario exclusivo de la obra, sus escenas, bloques y registros asociados.
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
