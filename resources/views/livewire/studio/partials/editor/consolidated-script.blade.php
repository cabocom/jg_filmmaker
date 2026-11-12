        <!-- ================= VISTA CONSOLIDADA CONTINUA ESTILO STORY ARCHITECT ================= -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <!-- Tarjeta de Hoja de Lectura Continua Story Architect -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-3.5 sm:p-8 md:p-12 space-y-6 sm:space-y-8 min-h-[70vh]">
                <!-- Encabezado del Documento Consolidado -->
                <div class="pb-6 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 class="text-xs font-mono uppercase tracking-widest text-amber-600 dark:text-amber-400 font-bold">
                            {{ match($activeView) {
                                'idea' => 'IDEA CONSOLIDADA',
                                'structured' => 'TRATAMIENTO CONSOLIDADO',
                                'screenplay' => 'GUION CONSOLIDADO',
                                'shots' => 'PLANOS/TOMAS CONSOLIDADO',
                                default => strtoupper($activeView) . ' CONSOLIDADO',
                            } }}
                        </h2>
                        <h1 class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-white mt-1">
                            {{ $project->title }}
                        </h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            {{ $scenes->count() }} {{ $scenes->count() === 1 ? 'Escena' : 'Escenas' }}
                        </span>
                        <button wire:click="setActiveView('pipeline')"
                                type="button"
                                class="px-3 py-1 rounded-lg text-xs font-bold font-mono bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer">
                            <span>← Volver a Pipeline</span>
                        </button>
                    </div>
                </div>

                <!-- Flujo Continuo de Escenas y Bloques -->
                <div class="space-y-10">
                    @forelse($scenes as $scene)
                        <section class="space-y-4 pt-6 first:pt-0 border-t first:border-t-0 border-slate-200 dark:border-slate-800/80">
                            <!-- ENCABEZADO DE LA ESCENA (STORY ARCHITECT SLUGLINE) -->
                            <div class="py-2.5 px-3 rounded-lg bg-slate-50 dark:bg-slate-950/60 border-l-4 border-amber-500 dark:border-amber-400 flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-sm uppercase tracking-wide text-slate-950 dark:text-amber-400">
                                        {{ $scene->scene_order }}. {{ $scene->slugline }}
                                    </span>
                                    <button wire:click="promptRunScenePipeline('{{ $scene->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="promptRunScenePipeline('{{ $scene->id }}'), runScenePipeline('{{ $scene->id }}')"
                                            type="button"
                                            title="Estructurar con IA todos los bloques de la Escena {{ $scene->scene_order }} (Bruto ➔ Tratamiento ➔ Guión ➔ Planos)"
                                            class="p-1 rounded text-slate-400 hover:text-amber-500 hover:bg-slate-200 dark:hover:bg-slate-800 transition cursor-pointer disabled:opacity-50">
                                        <span wire:loading.remove wire:target="promptRunScenePipeline('{{ $scene->id }}'), runScenePipeline('{{ $scene->id }}')">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </span>
                                        <span wire:loading wire:target="promptRunScenePipeline('{{ $scene->id }}'), runScenePipeline('{{ $scene->id }}')">
                                            <svg class="animate-spin w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <button wire:click="openEditSceneModal('{{ $scene->id }}')"
                                            type="button"
                                            title="Editar nombre y datos de la Escena {{ $scene->scene_order }}"
                                            class="p-1 rounded text-slate-400 hover:text-amber-500 hover:bg-slate-200 dark:hover:bg-slate-800 transition cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button wire:click="openDeleteSceneModal('{{ $scene->id }}')"
                                            type="button"
                                            title="Eliminar Escena {{ $scene->scene_order }}..."
                                            class="p-1 rounded text-slate-400 hover:text-rose-500 hover:bg-slate-200 dark:hover:bg-slate-800 transition cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 text-xs font-mono text-slate-500 dark:text-slate-400">
                                    @if($scene->location)
                                        <span>📍 {{ $scene->location->name }} ({{ $scene->location->type }})</span>
                                    @endif
                                    <span class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-[11px]">
                                        {{ $scene->scriptBlocks->count() }} {{ $scene->scriptBlocks->count() === 1 ? 'bloque' : 'bloques' }}
                                    </span>
                                </div>
                            </div>

                            <!-- TEXTO DE CADA BLOQUE TAL Y COMO ESTÁN ORDENADOS -->
                            <div class="space-y-4 pl-2 sm:pl-4">
                                @forelse($scene->scriptBlocks as $block)
                                    <div class="group relative py-2">
                                        <!-- Botones flotantes para editar o borrar bloque directamente -->
                                        <div class="absolute right-0 top-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1">
                                            <button wire:click="openBlockDetails('{{ $block->id }}', '{{ match($activeView) { 'idea' => 'raw', 'structured' => 'structured', 'screenplay' => 'screenplay', 'shots' => 'shots', default => 'raw' } }}')"
                                                    type="button"
                                                    title="Editar bloque #{{ $block->block_order }}"
                                                    class="px-2 py-1 rounded text-[11px] font-mono bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-900 dark:bg-slate-800 dark:hover:bg-amber-950/40 dark:text-slate-300 dark:hover:text-amber-300 border border-slate-200 dark:border-slate-700 transition flex items-center gap-1 cursor-pointer">
                                                <span>✏️</span>
                                                <span>Bloque #{{ $block->block_order }}</span>
                                            </button>
                                            <button wire:click="openDeleteBlockModal('{{ $block->id }}')"
                                                    type="button"
                                                    title="Eliminar bloque permanentemente..."
                                                    class="p-1 rounded text-[11px] font-mono bg-slate-100 hover:bg-rose-100 text-slate-400 hover:text-rose-600 dark:bg-slate-800 dark:hover:bg-rose-950/40 dark:hover:text-rose-400 border border-slate-200 dark:border-slate-700 transition flex items-center cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>

                                        @if($activeView === 'idea')
                                            <!-- VISTA IDEA: TEXTO BRUTO -->
                                            @if(filled(trim($block->raw_text ?? '')))
                                                <div class="text-sm text-slate-800 dark:text-slate-200 font-sans leading-relaxed whitespace-pre-line">
                                                    {{ $block->raw_text }}
                                                </div>
                                            @else
                                                <p class="text-xs italic text-slate-400 font-mono py-1">
                                                    [Bloque #{{ $block->block_order }}: Sin texto bruto / idea registrada]
                                                </p>
                                            @endif

                                        @elseif($activeView === 'structured')
                                            <!-- VISTA TRATAMIENTO: TEXTO ESTRUCTURADO -->
                                            @if(filled(trim($block->structured_text ?? '')))
                                                <div class="text-sm text-slate-800 dark:text-slate-200 font-sans leading-relaxed whitespace-pre-line">
                                                    {{ $block->structured_text }}
                                                </div>
                                            @else
                                                <p class="text-xs italic text-slate-400 font-mono py-1">
                                                    [Bloque #{{ $block->block_order }}: Tratamiento no estructurado]
                                                </p>
                                            @endif

                                        @elseif($activeView === 'screenplay')
                                            <!-- VISTA GUION: FOUNTAIN FORMATEADO STORY ARCHITECT -->
                                            @if(filled(trim($block->screenplay_content ?? '')))
                                                <div class="screenplay-content font-mono text-xs sm:text-sm text-slate-900 dark:text-amber-100 leading-relaxed space-y-2">
                                                    {!! \App\Services\ScreenplayFormatterService::formatToHtml($block->screenplay_content) !!}
                                                </div>
                                            @else
                                                <p class="text-xs italic text-slate-400 font-mono py-1">
                                                    [Bloque #{{ $block->block_order }}: Guión no generado]
                                                </p>
                                            @endif

                                        @elseif($activeView === 'shots')
                                            <!-- VISTA PLANOS / TOMAS TÉCNICAS -->
                                            @if($block->shots->isNotEmpty())
                                                <div class="space-y-3">
                                                    @foreach($block->shots as $shot)
                                                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                                                            <div class="flex items-center justify-between font-mono pb-1 border-b border-slate-200 dark:border-slate-800">
                                                                <span class="font-bold text-cyan-700 dark:text-cyan-400 uppercase tracking-wide">
                                                                    PLANO {{ $shot->shot_number }} · {{ $shot->shot_type }}
                                                                </span>
                                                            </div>
                                                            <p class="text-slate-700 dark:text-slate-300 leading-relaxed font-sans">{{ $shot->description }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs italic text-slate-400 font-mono py-1">
                                                    [Bloque #{{ $block->block_order }}: Sin planos técnicos desglosados]
                                                </p>
                                            @endif
                                        @endif

                                        <!-- Aviso de Agujero de Guión en Consolidado -->
                                        @if($showPlotHoleAlerts && $block->hasPlotHole())
                                            <div class="mt-2.5 p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-2 shadow-2xs">
                                                <div class="flex items-start gap-2 min-w-0">
                                                    <span class="text-sm shrink-0">🕳️</span>
                                                    <div>
                                                        <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                            <span class="font-bold text-[10px] uppercase tracking-wide text-rose-900 dark:text-rose-300">Agujero de guión:</span>
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-200/90 dark:bg-rose-900/80 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-700 uppercase">
                                                                Detectado en: {{ $block->getPlotHoleStageLabel() }}
                                                            </span>
                                                        </div>
                                                        <span class="font-sans text-xs text-rose-800 dark:text-rose-200">{{ $block->plot_hole }}</span>
                                                    </div>
                                                </div>
                                                <button wire:click="openJustifyModal('{{ $block->id }}')"
                                                        type="button"
                                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-white hover:bg-rose-100 text-rose-800 dark:bg-rose-900 dark:hover:bg-rose-800 dark:text-rose-100 border border-rose-300 dark:border-rose-700 transition cursor-pointer flex items-center gap-1 shrink-0"
                                                        title="Justificar por qué no es un agujero de guión y retirarlo">
                                                    <span>✅</span>
                                                    <span>Justificar</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs italic text-slate-400 font-mono py-2">Esta escena no contiene bloques de texto todavía.</p>
                                @endforelse
                            </div>
                        </section>
                    @empty
                        <div class="text-center py-16 space-y-3">
                            <span class="text-4xl">🎬</span>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">No hay escenas en este proyecto</h3>
                            <p class="text-xs text-slate-500">Regresa a la vista Pipeline para añadir la primera escena.</p>
                            <button wire:click="setActiveView('pipeline')" type="button" class="px-4 py-2 bg-amber-500 text-slate-950 font-bold text-xs rounded-xl shadow-md">
                                Ir a Pipeline
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
