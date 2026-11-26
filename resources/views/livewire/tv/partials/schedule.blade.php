<div class="space-y-4" @if ($channelConfig->planning_in_progress) wire:poll.4s @endif>
    {{-- Barra de Control de Fecha y Acciones de Parrilla --}}
    <div class="p-4 bg-white dark:bg-[#121622] border border-gray-200 dark:border-gray-800 rounded-lg flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-3">
            <label for="tv-selected-date" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Fecha de Emisión:
            </label>
            <input
                id="tv-selected-date"
                type="date"
                wire:model.live="selectedDate"
                class="px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
            />
            @if ($channelConfig->planning_in_progress)
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    <svg class="animate-spin h-3.5 w-3.5 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Replanificación IA en progreso...</span>
                </span>
            @elseif ($schedule->locked_by_admin)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    🔒 Bloqueada por Admin
                </span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    🤖 Propuesta IA (Desbloqueada)
                </span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="saveManualSchedule"
                @if ($channelConfig->planning_in_progress) disabled @endif
                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-semibold rounded-md shadow-2xs text-white bg-indigo-600 hover:bg-indigo-700 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Guardar Parrilla Manual
            </button>
            <button
                type="button"
                wire:click="forceReplanWithAi"
                wire:confirm="¿Seguro que deseas replanificar el día con IA? Se sobrescribirá la parrilla actual."
                @if ($channelConfig->planning_in_progress) disabled @endif
                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-700 text-xs font-semibold rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                @if ($channelConfig->planning_in_progress)
                    <svg class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Replanificando...
                @else
                    Replanificar día con IA
                @endif
            </button>
        </div>
    </div>

    {{-- Alerta Real de Cobertura 24h --}}
    @if ($coverageReport['is_complete'] ?? false)
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded-md text-emerald-800 dark:text-emerald-300 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span><strong>Cobertura completa:</strong> 1440 min ({{ $coverageReport['coverage_percentage'] }}%) cubiertos sin huecos de continuidad.</span>
            </div>
            <span class="text-[11px] font-mono">Renders pendientes: {{ $coverageReport['pending_renders'] }}</span>
        </div>
    @else
        <div class="p-3 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded-md text-amber-800 dark:text-amber-300 text-xs">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2 font-semibold">
                    <span>⚠️</span>
                    <span>Cobertura incompleta: {{ $coverageReport['covered_minutes'] }}/1440 min ({{ $coverageReport['coverage_percentage'] }}%) — {{ $coverageReport['gaps_count'] }} hueco(s) detectado(s).</span>
                </div>
                <span class="text-[11px] font-mono">Renders pendientes: {{ $coverageReport['pending_renders'] }}</span>
            </div>
            @if (! empty($coverageReport['gaps']))
                <div class="mt-2 text-[11px] text-amber-700 dark:text-amber-400 space-y-0.5 pl-6 list-disc">
                    @foreach ($coverageReport['gaps'] as $gap)
                        <div>• Hueco sin emisión de {{ $gap['start'] }} a {{ $gap['end'] }} ({{ $gap['duration_minutes'] }} min)</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Tabla de Bloques 00:00 - 24:00 --}}
    <div class="bg-white dark:bg-[#121622] border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
            <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                Bloques de Emisión ({{ count($schedule->blocks_json ?? []) }})
            </h3>
            <span class="text-[11px] text-gray-400">Total: 1440 minutos / 24 horas</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 uppercase text-[10px] font-bold">
                    <tr>
                        <th class="px-4 py-2.5">Horario</th>
                        <th class="px-4 py-2.5">Tipo</th>
                        <th class="px-4 py-2.5">Título del Bloque</th>
                        <th class="px-4 py-2.5">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($schedule->blocks_json ?? [] as $block)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-2.5 font-mono text-gray-700 dark:text-gray-300 font-medium whitespace-nowrap">
                                {{ $block['start_time'] ?? '' }} - {{ $block['end_time'] ?? '' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase
                                    @if (($block['type'] ?? '') === 'news') bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300
                                    @elseif (($block['type'] ?? '') === 'debate') bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300
                                    @elseif (($block['type'] ?? '') === 'pill') bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 @endif
                                ">
                                    {{ $block['type'] ?? 'desconocido' }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-800 dark:text-gray-200">
                                {{ $block['title'] ?? 'Sin título' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono
                                    @if (($block['stage'] ?? '') === 'ready') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                                    @elseif (($block['stage'] ?? '') === 'rendering') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                                    @elseif (($block['stage'] ?? '') === 'failed') bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300
                                    @else bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400 @endif
                                ">
                                    {{ $block['stage'] ?? 'pending' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">
                                No hay bloques definidos para esta fecha. Usa "Replanificar día con IA" para generar la propuesta.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
