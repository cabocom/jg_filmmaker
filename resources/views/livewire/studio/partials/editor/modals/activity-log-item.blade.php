                            @php
                                $actionIcon = match($log->action) {
                                    'create_block', 'create_scene' => '➕',
                                    'update_block', 'update_scene' => '✏️',
                                    'delete_block', 'delete_scene' => '🗑️',
                                    'reorder_block', 'reorder_scene' => '↕️',
                                    'structure_block_ai' => '🤖',
                                    'format_screenplay_ai' => '🎬',
                                    'toggle_stage_lock' => '🔒',
                                    'justify_plot_hole' => '✅',
                                    'clear_plot_holes' => '🧹',
                                    'analyze_plot_holes' => '🕳️',
                                    default => '📌',
                                };

                                $badgeClass = match($log->action) {
                                    'create_block', 'create_scene' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                    'update_block', 'update_scene' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                                    'delete_block', 'delete_scene' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                    'reorder_block', 'reorder_scene' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                                    'structure_block_ai', 'format_screenplay_ai' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                    'toggle_stage_lock' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                                    'justify_plot_hole' => 'bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300 border-teal-200 dark:border-teal-800',
                                    'analyze_plot_holes' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300 border-orange-200 dark:border-orange-800',
                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                                };
                            @endphp

                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-xs transition hover:border-slate-300 dark:hover:border-slate-700 space-y-1.5"
                                 x-data="{ showSnapshot: false }">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm">{{ $actionIcon }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase border {{ $badgeClass }}">
                                            {{ str_replace('_', ' ', $log->action) }}
                                        </span>
                                        <span class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                                            {{ $log->user?->name ?? 'Usuario' }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] font-mono text-slate-400" title="{{ $log->created_at->format('d/m/Y H:i:s') }}">
                                        {{ $log->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                <p class="text-slate-800 dark:text-slate-200 font-sans leading-relaxed">
                                    {{ $log->description }}
                                </p>

                                @if($log->action === 'analyze_plot_holes')
                                    @php
                                        $detectedHoles = $log->snapshot['detected_holes'] ?? [];
                                        $totalDetected = $log->snapshot['total_detected'] ?? 0;
                                    @endphp
                                    @if(!empty($detectedHoles))
                                        <div class="mt-2 p-3 rounded-xl bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 space-y-2">
                                            <div class="flex items-center justify-between text-[11px] font-mono font-bold text-rose-700 dark:text-rose-400">
                                                <span>🕳️ Fallos detectados en este análisis ({{ count($detectedHoles) }}):</span>
                                                @if(isset($log->snapshot['total_blocks_audited']))
                                                    <span class="text-[10px] text-slate-500 font-normal">({{ $log->snapshot['total_blocks_audited'] }} bloques auditados)</span>
                                                @endif
                                            </div>
                                            <div class="space-y-1.5">
                                                @foreach($detectedHoles as $hole)
                                                    <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-rose-200/80 dark:border-rose-900/50 text-xs space-y-1 shadow-2xs">
                                                        <div class="flex items-center justify-between text-[11px] font-mono">
                                                            <span class="font-bold text-slate-900 dark:text-white">
                                                                Escena #{{ $hole['scene_order'] ?? '?' }} @if(!empty($hole['scene_slugline'])) ({{ $hole['scene_slugline'] }}) @endif · Bloque #{{ $hole['block_order'] ?? '?' }}
                                                            </span>
                                                            <span class="px-1.5 py-0.2 rounded bg-rose-100 dark:bg-rose-900/70 text-rose-800 dark:text-rose-200 uppercase font-bold text-[9px]">
                                                                {{ $hole['stage_label'] ?? $hole['stage'] ?? '' }}
                                                            </span>
                                                        </div>
                                                        <p class="text-slate-800 dark:text-slate-200 font-sans text-xs leading-relaxed">
                                                            <strong class="font-bold text-rose-700 dark:text-rose-400">Inconsistencia:</strong> {{ $hole['reason'] ?? '' }}
                                                        </p>
                                                        @if(!empty($hole['block_preview']))
                                                            <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400 italic pt-0.5 truncate">
                                                                Fragmento: "{{ $hole['block_preview'] }}"
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif($totalDetected > 0)
                                        <div class="mt-2 p-2 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/40 text-xs text-rose-700 dark:text-rose-300 font-mono">
                                            <span>🕳️ Inconsistencias detectadas: {{ $totalDetected }}</span>
                                        </div>
                                    @endif
                                @endif

                                @if(!empty($log->snapshot))
                                    <div class="pt-1.5" x-data="{ showSnapshot: false, viewMode: 'visual' }">
                                        <button type="button"
                                                @click="showSnapshot = !showSnapshot"
                                                class="text-[11px] font-mono text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 flex items-center gap-1.5 cursor-pointer font-medium">
                                            <span x-text="showSnapshot ? '▼ Ocultar snapshot' : '▶ Ver snapshot guardado'"></span>
                                        </button>

                                        <div x-show="showSnapshot"
                                             x-collapse
                                             style="display: none;"
                                             class="mt-2 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2.5">
                                            
                                            <!-- Barra de Selección: Vista Estructurada / Código JSON -->
                                            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                    Datos del evento
                                                </span>
                                                <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-slate-800 text-[10px] font-mono">
                                                    <button type="button"
                                                            @click="viewMode = 'visual'"
                                                            :class="viewMode === 'visual' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                                            class="px-2 py-0.5 rounded-md cursor-pointer transition">
                                                        Visual
                                                    </button>
                                                    <button type="button"
                                                            @click="viewMode = 'json'"
                                                            :class="viewMode === 'json' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                                            class="px-2 py-0.5 rounded-md cursor-pointer transition">
                                                        JSON
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- 1. VISTA VISUAL ESTRUCTURADA -->
                                            <div x-show="viewMode === 'visual'" class="space-y-2.5 text-xs">
                                                <!-- Caso A: Auditoría de Agujeros de Guión -->
                                                @if(isset($log->snapshot['total_detected']))
                                                    <div class="space-y-2">
                                                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[11px] font-mono">
                                                            <span class="text-slate-600 dark:text-slate-300">Total detectados:</span>
                                                            <span class="font-bold {{ ($log->snapshot['total_detected'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                                                {{ $log->snapshot['total_detected'] ?? 0 }}
                                                            </span>
                                                        </div>

                                                        @if(isset($log->snapshot['by_stage']))
                                                            <div class="flex items-center gap-3 text-[10px] font-mono text-slate-500 dark:text-slate-400 px-1">
                                                                <span>Idea: <strong class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['by_stage']['raw'] ?? 0 }}</strong></span>
                                                                <span>·</span>
                                                                <span>Tratamiento: <strong class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['by_stage']['structured'] ?? 0 }}</strong></span>
                                                                <span>·</span>
                                                                <span>Guión: <strong class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['by_stage']['screenplay'] ?? 0 }}</strong></span>
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['detected_holes']))
                                                            <div class="space-y-1.5 pt-1">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-rose-600 dark:text-rose-400 block">Inconsistencias Reportadas:</span>
                                                                @foreach($log->snapshot['detected_holes'] as $hole)
                                                                    <div class="p-2.5 rounded-lg bg-rose-50/80 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-[11px] space-y-1">
                                                                        <div class="flex items-center justify-between font-mono text-[10px] text-rose-800 dark:text-rose-300">
                                                                            <span>Escena #{{ $hole['scene_order'] ?? '?' }} · Bloque #{{ $hole['block_order'] ?? '?' }}</span>
                                                                            <span class="px-1.5 py-0.2 rounded bg-rose-200/80 dark:bg-rose-900 text-rose-900 dark:text-rose-200 font-bold uppercase text-[9px]">{{ $hole['stage_label'] ?? $hole['stage'] ?? '' }}</span>
                                                                        </div>
                                                                        <p class="text-slate-900 dark:text-slate-100 font-sans leading-relaxed">
                                                                            {{ $hole['reason'] ?? '' }}
                                                                        </p>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Caso B: Bloque de Guión (Creación, Edición, Borrado, IA) -->
                                                @if(isset($log->snapshot['raw_text']) || isset($log->snapshot['structured_text']) || isset($log->snapshot['screenplay_content']))
                                                    <div class="space-y-2">
                                                        @if(isset($log->snapshot['scene_order']) || isset($log->snapshot['block_order']))
                                                            <div class="flex items-center justify-between flex-wrap gap-1 p-1.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-[10px] text-slate-600 dark:text-slate-300">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="font-bold text-slate-900 dark:text-white">Escena #{{ $log->snapshot['scene_order'] ?? '?' }}</span>
                                                                    @if(!empty($log->snapshot['scene_slugline']))
                                                                        <span class="text-slate-500 truncate max-w-xs">({{ $log->snapshot['scene_slugline'] }})</span>
                                                                    @endif
                                                                    <span>·</span>
                                                                    <span class="font-bold text-slate-900 dark:text-white">Bloque #{{ $log->snapshot['block_order'] ?? '?' }}</span>
                                                                </div>
                                                                @if(!empty($log->snapshot['stage']))
                                                                    <span class="px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-800 uppercase font-bold text-[9px] text-slate-700 dark:text-slate-300">{{ $log->snapshot['stage'] }}</span>
                                                                @endif
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['raw_text']))
                                                            <div class="space-y-0.5">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-amber-700 dark:text-amber-400">Idea (Bruto):</span>
                                                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-sans text-xs whitespace-pre-wrap leading-relaxed">
                                                                    {{ $log->snapshot['raw_text'] }}
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['structured_text']))
                                                            <div class="space-y-0.5">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-sky-700 dark:text-sky-400">Tratamiento Narrativo:</span>
                                                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-sans text-xs whitespace-pre-wrap leading-relaxed">
                                                                    {{ $log->snapshot['structured_text'] }}
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['screenplay_content']))
                                                            <div class="space-y-0.5">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-purple-700 dark:text-purple-400">Guion Literario:</span>
                                                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-mono text-[11px] whitespace-pre-wrap leading-relaxed">
                                                                    {{ $log->snapshot['screenplay_content'] }}
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Caso C: Escena (Creación, Edición, Borrado) -->
                                                @if(isset($log->snapshot['slugline']))
                                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono text-xs">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-slate-500">Encabezado:</span>
                                                            <span class="font-bold text-slate-900 dark:text-white">{{ $log->snapshot['slugline'] }}</span>
                                                        </div>
                                                        @if(isset($log->snapshot['time_of_day']))
                                                            <div class="flex items-center gap-2 text-[11px]">
                                                                <span class="text-slate-500">Momento del día:</span>
                                                                <span class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['time_of_day'] }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Caso D: Justificación de Agujero de Guión -->
                                                @if(isset($log->snapshot['justification']))
                                                    <div class="p-2.5 rounded-lg bg-teal-50/80 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800 space-y-1.5 text-xs">
                                                        <div class="font-mono text-[10px] text-teal-800 dark:text-teal-300 font-bold uppercase">Explicación del Autor:</div>
                                                        <p class="text-slate-900 dark:text-slate-100 font-sans italic leading-relaxed">"{{ $log->snapshot['justification'] }}"</p>
                                                        @if(isset($log->snapshot['hole_reason']))
                                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono pt-1 border-t border-teal-200 dark:border-teal-800/60">
                                                                Inconsistencia previa: {{ $log->snapshot['hole_reason'] }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- 2. VISTA CÓDIGO TÉCNICO (JSON) -->
                                            <div x-show="viewMode === 'json'"
                                                 style="display: none;"
                                                 class="rounded-xl border border-slate-300 dark:border-slate-700 p-3 overflow-x-auto max-h-56 bg-slate-100 dark:bg-slate-900">
                                                <pre style="color: #000000 !important; background: transparent !important; white-space: pre-wrap; word-break: break-all; margin: 0; padding: 0; font-family: monospace; font-size: 11px; line-height: 1.45; font-weight: 500;">{{ json_encode($log->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
