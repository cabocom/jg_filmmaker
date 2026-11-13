    <!-- ================= MODAL DE TRAZABILIDAD E INSPECCIÓN DE IA ================= -->
    @if($showAiTraceModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-black/90 backdrop-blur-md transition"
             x-data="{ activeTab: 'steps' }"
             @keydown.escape.window="$wire.closeAiTraceModal()"
             wire:click.self="closeAiTraceModal">
            <!-- Contenedor del Modal con PROPORCIONES FIJAS POR LOS CUATRO LADOS Y CENTRADO -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col mx-auto shrink-0 transition-none"
                 style="width: min(840px, 92vw); min-width: min(840px, 92vw); max-width: min(840px, 92vw); height: min(630px, 86vh); min-height: min(630px, 86vh); max-height: min(630px, 86vh);">
                <!-- Modal Header (Fijo) -->
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 flex items-center justify-between gap-4 shrink-0" style="flex-shrink: 0;">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🔍</span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span>Trazabilidad Cognitiva IA</span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Bloque #{{ $aiTraceData['block_order'] ?? '1' }} · Escena #{{ $aiTraceData['scene_order'] ?? '1' }} ({{ $aiTraceData['scene_slugline'] ?? '' }})
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 hidden sm:inline">
                            {{ strtoupper($aiTraceData['provider'] ?? config('services.llm.provider', 'GROQ')) }} / {{ ($aiTraceData['model'] ?? '') === 'openai/gpt-oss-120b' ? 'llama-3.3-70b-versatile' : ($aiTraceData['model'] ?? config('services.llm.model', 'llama-3.3-70b-versatile')) }}
                        </span>
                        <button wire:click="closeAiTraceModal"
                                type="button"
                                title="Cerrar modal (Esc)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer shadow-xs ml-1">
                            <span>✕</span>
                            <span class="hidden sm:inline">Cerrar</span>
                        </button>
                    </div>
                </div>

                <!-- Meta info banner (Fijo) -->
                <div class="px-3.5 sm:px-6 py-2 bg-slate-100/70 dark:bg-slate-950/70 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-[11px] sm:text-xs text-slate-600 dark:text-slate-400 shrink-0" style="flex-shrink: 0;">
                    <div class="flex items-center gap-3 sm:gap-4 flex-wrap">
                        <span>⏱️ Latencia: <strong class="text-slate-900 dark:text-slate-100 font-mono">{{ $aiTraceData['duration_ms'] ?? 0 }} ms</strong></span>
                        <span>📅 Invocado: <strong class="text-slate-900 dark:text-slate-100 font-mono">{{ $aiTraceData['executed_at'] ?? $aiTraceData['timestamp'] ?? now()->toIso8601String() }}</strong></span>
                    </div>
                    <div>
                        <span>🎭 Personajes en biblia: <strong class="text-slate-900 dark:text-slate-100">{{ count($aiTraceData['characters_context'] ?? []) }}</strong></span>
                    </div>
                </div>

                <!-- Navigation Tabs inside Modal (Fijo con Alpine.js para 0 latencia y 0 payload) -->
                <div class="px-6 pt-2 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center gap-2 shrink-0" style="flex-shrink: 0;">
                    <button @click="activeTab = 'steps'" type="button"
                            :class="activeTab === 'steps' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer">
                        ⚡ Grafo & Saltos
                    </button>
                    <button @click="activeTab = 'prompt'" type="button"
                            :class="activeTab === 'prompt' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer">
                        💬 System Prompt & Entrada
                    </button>
                    <button @click="activeTab = 'output'" type="button"
                            :class="activeTab === 'output' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer">
                        📄 Salida Cinematográfica
                    </button>
                    <button @click="activeTab = 'json'" type="button"
                            :class="activeTab === 'json' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer">
                        { } JSON Técnico
                    </button>
                </div>

                <!-- Modal Body Content (ÁREA SCROLLEABLE INTERNA CON PROPORCIÓN FIJA) -->
                <div class="p-6 bg-white dark:bg-slate-900" style="flex: 1 1 0%; min-height: 0; overflow-y: auto; overflow-x: hidden;">
                    <div x-show="activeTab === 'steps'" class="space-y-3.5">
                        @foreach($aiTraceData['steps'] ?? [] as $step)
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-4 transition hover:border-slate-300 dark:hover:border-slate-700">
                                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 font-mono text-xl font-black shrink-0 border border-indigo-500/30 shadow-xs">
                                    {{ $step['number'] ?? 1 }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $step['title'] }}</h4>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-bold border border-emerald-500/30 shrink-0">
                                            {{ $step['status'] ?? 'COMPLETED' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-mono text-left">{{ $step['detail'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div x-show="activeTab === 'prompt'" class="space-y-5">
                        <div>
                            <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                                <span>⚙️</span>
                                <span>System Prompt Inyectado (Biblia de Proyecto + Reglas)</span>
                            </span>
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-xs whitespace-pre-wrap text-left leading-relaxed max-h-48 overflow-y-auto border border-slate-300 dark:border-slate-800 shadow-inner">{{ trim($aiTraceData['system_prompt'] ?? 'Sin prompt de sistema disponible.') }}</div>
                        </div>
                        <div>
                            <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                                <span>📝</span>
                                <span>Entrada del Autor / Texto Suministrado</span>
                            </span>
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-xs whitespace-pre-wrap text-left leading-relaxed max-h-48 overflow-y-auto border border-slate-300 dark:border-slate-800 shadow-inner">{{ trim($aiTraceData['user_prompt'] ?? $aiTraceData['input_text'] ?? 'Sin entrada registrada.') }}</div>
                        </div>
                    </div>

                    <div x-show="activeTab === 'output'" class="space-y-2">
                        <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <span>🎬</span>
                            <span>Salida Cinematográfica Generada</span>
                        </span>
                        <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-xs border border-slate-300 dark:border-slate-800 whitespace-pre-wrap text-left leading-relaxed overflow-y-auto shadow-inner" style="min-height: 280px; max-height: 380px;">{{ trim($aiTraceData['output'] ?? $aiTraceData['raw_response'] ?? 'Sin salida registrada.') }}</div>
                    </div>

                    <div x-show="activeTab === 'json'" class="flex flex-col space-y-2">
                        <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 shrink-0 flex items-center gap-1.5">
                            <span>{ }</span>
                            <span>Payload & Telemetría Completa (JSON)</span>
                        </span>
                        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-4 shadow-inner" style="min-height: 280px; max-height: 380px; overflow: auto;">
                            <pre class="m-0 p-0 bg-transparent border-0 text-left"><code class="language-json font-mono text-xs leading-relaxed block text-left" style="color: #38bdf8 !important;">{{ json_encode($aiTraceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer (Fijo) -->
                <div class="px-6 py-3.5 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0" style="flex-shrink: 0;">
                    <span class="text-xs text-slate-400 font-mono hidden sm:inline">Pulsa <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-[10px]">Esc</kbd> para salir</span>
                    <button wire:click="closeAiTraceModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-slate-200 dark:text-slate-900 transition shadow-sm cursor-pointer ml-auto">
                        Cerrar (Esc)
                    </button>
                </div>
            </div>
        </div>
    @endif
