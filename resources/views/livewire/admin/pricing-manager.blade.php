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
                    Tarifas de Modelos de Inteligencia Artificial
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Configuración de costes por millón de tokens (Input / Output) para la telemetría del estudio
                </p>
            </div>
        </div>

        <!-- Tabla Interactiva de Tarifas -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono uppercase text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Proveedor / Modelo</th>
                            <th class="px-4 py-3 text-center">Estado Facturación</th>
                            <th class="px-4 py-3">Input Cost / 1M Tokens ($)</th>
                            <th class="px-4 py-3">Output Cost / 1M Tokens ($)</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                        @forelse($rates as $rate)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition {{ $rate->is_active ? 'bg-amber-500/5 dark:bg-amber-500/5' : '' }}">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                            {{ $rate->provider }}
                                        </span>
                                        <span class="font-bold text-slate-900 dark:text-white font-mono text-xs">
                                            {{ $rate->model }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($rate->is_active)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-mono font-bold bg-amber-500 text-slate-950 shadow-2xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-pulse"></span>
                                            ACTIVO
                                        </span>
                                    @else
                                        <button wire:click="setActiveRate('{{ $rate->id }}')"
                                                type="button"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-mono font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 transition cursor-pointer">
                                            Activar para Costes
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-slate-500">$</span>
                                        <input wire:model="editingRates.{{ $rate->id }}.input_cost_per_million"
                                               type="number"
                                               step="0.000001"
                                               min="0"
                                               class="w-32 px-2.5 py-1 text-xs font-mono rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500" />
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-slate-500">$</span>
                                        <input wire:model="editingRates.{{ $rate->id }}.output_cost_per_million"
                                               type="number"
                                               step="0.000001"
                                               min="0"
                                               class="w-32 px-2.5 py-1 text-xs font-mono rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500" />
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-right space-x-2">
                                    <button wire:click="saveRate('{{ $rate->id }}')"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-2xs cursor-pointer">
                                        Guardar
                                    </button>
                                    @if(!$rate->is_active)
                                        <button wire:click="deleteRate('{{ $rate->id }}')"
                                                wire:confirm="¿Seguro que deseas eliminar esta tarifa?"
                                                type="button"
                                                class="px-2 py-1 rounded-lg text-xs text-rose-500 hover:text-rose-600 transition font-mono cursor-pointer">
                                            ✕
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500 font-mono">
                                    No hay tarifas de modelos configuradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Formulario para Añadir Nuevo Modelo al Catálogo -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4">
            <h3 class="text-xs font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider">
                + Añadir Nuevo Modelo de Lenguaje al Catálogo
            </h3>
            <form wire:submit.prevent="createRate" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-mono text-slate-500 mb-1">Proveedor</label>
                    <select wire:model="new_provider" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono">
                        <option value="groq">Groq</option>
                        <option value="gemini">Google Gemini</option>
                        <option value="openai">OpenAI</option>
                        <option value="ollama">Ollama (Local)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-mono text-slate-500 mb-1">Nombre del Modelo</label>
                    <input wire:model="new_model" type="text" placeholder="e.g. gpt-4o-mini" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono" />
                </div>

                <div>
                    <label class="block text-[11px] font-mono text-slate-500 mb-1">Input ($ / 1M)</label>
                    <input wire:model="new_input_cost" type="number" step="0.000001" min="0" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono" />
                </div>

                <div>
                    <label class="block text-[11px] font-mono text-slate-500 mb-1">Output ($ / 1M)</label>
                    <input wire:model="new_output_cost" type="number" step="0.000001" min="0" required class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono" />
                </div>

                <div>
                    <button type="submit" class="w-full py-2 px-3 rounded-xl font-bold font-mono text-xs bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-xs cursor-pointer">
                        + Registrar Tarifa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
