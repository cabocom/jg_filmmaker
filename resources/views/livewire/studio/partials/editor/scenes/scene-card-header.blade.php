                    <!-- Cabecera de la Escena (Slugline a la Izquierda, Pipeline individual a la Derecha) -->
                    <div class="px-2.5 sm:px-3.5 py-2 bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-700/60 flex flex-col md:flex-row md:items-center justify-between gap-2 sm:gap-3">
                        <div class="flex items-center justify-between md:justify-start gap-2 w-full md:w-auto min-w-0">
                            <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                                <!-- Tirador Drag & Drop de Escena con draggable=true directo -->
                                <div draggable="true"
                                     @dragstart.stop="
                                         event.dataTransfer.setData('scene-id', '{{ $scene->id }}');
                                         event.dataTransfer.effectAllowed = 'move';
                                         const sceneEl = document.getElementById('scene-card-{{ $scene->id }}');
                                         if (event.dataTransfer.setDragImage && sceneEl) {
                                             event.dataTransfer.setDragImage(sceneEl, 20, 20);
                                         }
                                         isDraggingScene = true;
                                     "
                                     @dragend="isDraggingScene = false"
                                     title="Arrastrar para mover toda esta escena"
                                     :class="isSceneHovered ? 'opacity-100' : 'opacity-0'"
                                     class="shrink-0 w-4 h-5 rounded flex items-center justify-center text-slate-400 hover:text-amber-500 dark:text-slate-500 dark:hover:text-amber-400 cursor-grab active:cursor-grabbing transition-opacity duration-150 select-none">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <circle cx="7" cy="4" r="1.5" />
                                        <circle cx="13" cy="4" r="1.5" />
                                        <circle cx="7" cy="10" r="1.5" />
                                        <circle cx="13" cy="10" r="1.5" />
                                        <circle cx="7" cy="16" r="1.5" />
                                        <circle cx="13" cy="16" r="1.5" />
                                    </svg>
                                </div>

                                <!-- Botón Toggle de Acordeón con Icono Dinámico (Cambia de forma según esté expandida o colapsada) -->
                                <button wire:click="toggleScene('{{ $scene->id }}')"
                                        type="button"
                                        class="flex items-center gap-1.5 sm:gap-2 text-left min-w-0 cursor-pointer group/btn">
                                    @if($isExpanded)
                                        <!-- Icono Abierto (Flecha hacia abajo) -->
                                        <svg class="w-3.5 h-3.5 shrink-0 text-amber-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    @else
                                        <!-- Icono Cerrado / Colapsado (Flecha hacia la derecha) -->
                                        <svg class="w-3.5 h-3.5 shrink-0 text-slate-400 group-hover/btn:text-amber-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                        </svg>
                                    @endif

                                    <div class="flex items-center gap-1 sm:gap-1.5 font-mono min-w-0">
                                        <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400 shrink-0">
                                            ESC. {{ $scene->scene_order }}
                                        </span>
                                        <span class="text-slate-300 dark:text-slate-600 font-bold shrink-0">·</span>
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wide truncate max-w-[130px] sm:max-w-xs md:max-w-md">
                                            {{ $scene->slugline }}
                                        </span>
                                        @if($scene->time_of_day)
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 shrink-0 hidden sm:inline">
                                                ({{ $scene->time_of_day }})
                                            </span>
                                        @endif
                                    </div>
                                </button>
                            </div>

                            <!-- Botones de Edición Rápida, Borrado y Lectura de Audio -->
                            <div class="flex items-center gap-1 shrink-0">
                                <button onclick="window.dispatchEvent(new CustomEvent('play-tts', { detail: { scope: 'scene', sceneId: '{{ $scene->id }}' } }))"
                                        type="button"
                                        title="Escuchar locución completa de la Escena {{ $scene->scene_order }}"
                                        class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-200/80 dark:hover:bg-slate-700/80 transition cursor-pointer shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M11 5L6 9H2v6h4l5 4V5z" />
                                    </svg>
                                </button>

                                <!-- Botón Ejecutar Pipeline IA Completo para la Escena (Bruto ➔ Tratamiento ➔ Guión ➔ Planos) -->
                                <button wire:click="promptRunScenePipeline('{{ $scene->id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="promptRunScenePipeline('{{ $scene->id }}'), runScenePipeline('{{ $scene->id }}')"
                                        type="button"
                                        title="Estructurar con IA todos los bloques de la Escena {{ $scene->scene_order }} (Bruto ➔ Tratamiento ➔ Guión ➔ Descomposición en Planos)"
                                        class="p-1 rounded text-slate-400 hover:text-amber-500 hover:bg-slate-200/80 dark:hover:bg-slate-700/80 transition cursor-pointer shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
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
                                        class="p-1 rounded text-slate-400 hover:text-amber-500 hover:bg-slate-200/80 dark:hover:bg-slate-700/80 transition cursor-pointer shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>

                                <button wire:click="openDeleteSceneModal('{{ $scene->id }}')"
                                        type="button"
                                        title="Eliminar Escena {{ $scene->scene_order }}..."
                                        class="p-1 rounded text-slate-400 hover:text-rose-500 hover:bg-slate-200/80 dark:hover:bg-slate-700/80 transition cursor-pointer shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Cabecera del Pipeline Integrada dentro de la Escena (Exclusiva para esta escena) -->
                        <div class="flex items-center justify-between md:justify-end gap-1.5 sm:gap-2 w-full md:w-auto shrink-0 overflow-x-auto max-w-full pb-0.5 sm:pb-0">
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono hidden md:inline">
                                {{ $scene->scriptBlocks->count() }} {{ $scene->scriptBlocks->count() === 1 ? 'bloque' : 'bloques' }}
                            </span>

                            <div class="inline-flex rounded-lg bg-white/90 dark:bg-slate-900/90 p-0.5 border border-slate-200 dark:border-slate-700/80 text-[11px] shadow-2xs shrink-0">
                                <!-- 1. Idea -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'idea')"
                                        type="button"
                                        title="Listado de textos originales en bruto para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer whitespace-nowrap {{ $sceneStage === 'idea' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Idea
                                </button>

                                <!-- 2. Tratamiento -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'structured')"
                                        type="button"
                                        title="Listado de textos estructurados a tratamiento para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer whitespace-nowrap {{ $sceneStage === 'structured' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Tratamiento
                                </button>

                                <!-- 3. Guión -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'screenplay')"
                                        type="button"
                                        title="Listado de textos formateados a guión canónico Fountain para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer whitespace-nowrap {{ $sceneStage === 'screenplay' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Guión
                                </button>

                                <!-- 4. Planos / Tomas -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'shots')"
                                        type="button"
                                        title="Desglose técnico de planos de cámara y storyboards para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer whitespace-nowrap {{ $sceneStage === 'shots' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Planos / Tomas
                                </button>
                            </div>
                        </div>
                    </div>

