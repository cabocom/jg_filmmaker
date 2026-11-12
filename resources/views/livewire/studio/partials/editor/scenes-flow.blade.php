        <!-- ================= CONTENEDOR PRINCIPAL STORYARCHITECT (MODO PIPELINE INTERACTIVO) ================= -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <!-- ================= FLUJO VERTICAL CONTINUO DE ESCENAS (MODO STORYARCHITECT) ================= -->
            <div class="space-y-1">
                <!-- Divisor Gutenberg Inicial por encima de la Escena 1 (+ Nueva Escena al inicio) -->
                <div x-data="{ isTopScenePlusHovered: false }"
                 @mouseenter="isTopScenePlusHovered = true"
                 @mouseleave="isTopScenePlusHovered = false"
                 class="gutenberg-container relative py-0.5 my-0 flex items-center justify-center cursor-pointer z-[5] w-full"
                 style="z-index: 5;">
                <div class="gutenberg-divider-line"
                     :class="isTopScenePlusHovered ? 'is-visible' : ''"
                     style="height: 2px; background-color: #f59e0b;"></div>
                <button wire:click="openNewSceneModal(0)"
                        type="button"
                        title="Insertar nueva escena al inicio del guión"
                        :class="isTopScenePlusHovered ? 'is-visible' : ''"
                        class="gutenberg-divider-btn opacity-0 scale-75 pointer-events-none relative z-[6] px-3 py-1 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-md hover:scale-105 transition-all duration-150 flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>+ Nueva Escena</span>
                </button>
            </div>

            @forelse($scenes as $scene)
                @php
                    $isExpanded = in_array($scene->id, $expandedSceneIds);
                    $sceneStage = $this->getScenePipelineStage($scene->id);
                @endphp

                <!-- Tarjeta de Escena con Soporte Drag & Drop y Acordeón -->
                <div id="scene-card-{{ $scene->id }}"
                     x-data="{ isDraggingScene: false, isOverScene: false, isSceneHovered: false }"
                     @mouseenter="isSceneHovered = true"
                     @mouseleave="isSceneHovered = false"
                     @dragover.prevent="isOverScene = true"
                     @dragleave="isOverScene = false"
                     @drop.prevent="
                         isOverScene = false;
                         const srcSceneId = event.dataTransfer.getData('scene-id');
                         if (srcSceneId && srcSceneId !== '{{ $scene->id }}') {
                             $wire.reorderScene(srcSceneId, {{ $scene->scene_order }});
                         }
                     "
                     :class="{
                         'opacity-40 scale-[0.99]': isDraggingScene,
                         'border-amber-500 ring-2 ring-amber-500/50 bg-amber-500/5': isOverScene
                     }"
                     class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-xl shadow-xs transition-all overflow-hidden mb-0">

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

                    <!-- Contenido de Bloques de la Escena (Acordeón expandible) -->
                    @if($isExpanded)
                        @if($sceneStage === 'timeline')
                            <div class="p-3 sm:p-5">
                                <livewire:studio.rough-cut-timeline :project="$project" :active-scene-id="$scene->id" :key="'timeline-'.$scene->id" />
                            </div>
                        @else
                            <div class="p-3 sm:p-5 space-y-0.5">
                                <!-- Divisor Inicial de Bloque: Gutenberg (Invisible en reposo, revelado al pasar el ratón) -->
                                <div x-data="{ isInitPlusHovered: false }"
                                     @mouseenter="isInitPlusHovered = true"
                                     @mouseleave="isInitPlusHovered = false"
                                     class="gutenberg-container relative py-1 my-0 flex items-center justify-center cursor-pointer z-[5] w-full"
                                     style="z-index: 5;">
                                    <div class="gutenberg-divider-line"
                                         :class="isInitPlusHovered ? 'is-visible' : ''"
                                         style="height: 2px; background-color: #f59e0b;"></div>
                                    <button wire:click="openNewBlockModal(0, '{{ $scene->id }}')"
                                            type="button"
                                            title="Insertar bloque al inicio de la escena"
                                            :class="isInitPlusHovered ? 'is-visible' : ''"
                                            class="gutenberg-divider-btn opacity-0 scale-75 pointer-events-none relative z-[6] w-7 h-7 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold flex items-center justify-center shadow-md hover:scale-110 transition-all duration-150 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>

                                @forelse($scene->scriptBlocks as $block)
                                    @php
                                        $isSelected = ($selectedBlockId === $block->id);
                                    @endphp

                                    <!-- Elemento Continuo de Bloque con Drag & Drop Completo de Fila y Efecto Hover Garantizado -->
                                    <div id="block-{{ $block->id }}"
                                         x-data="{ isBlockHovered: false, isDragging: false, isOver: false }"
                                         @mouseenter="isBlockHovered = true"
                                         @mouseleave="isBlockHovered = false"
                                         @dragover.prevent="isOver = true"
                                         @dragleave="isOver = false"
                                         @drop.prevent="
                                             isOver = false;
                                             const srcId = event.dataTransfer.getData('text/plain');
                                             if (srcId && srcId !== '{{ $block->id }}') {
                                                 $wire.reorderBlock(srcId, {{ $block->block_order }}, '{{ $scene->id }}');
                                             }
                                         "
                                         :class="{
                                             'opacity-40 scale-[0.99]': isDragging,
                                             'border-amber-500 ring-2 ring-amber-500/50 bg-amber-50/50 dark:bg-amber-950/30': isOver
                                         }"
                                         wire:click="selectBlock('{{ $block->id }}')"
                                         class="script-block-item group relative rounded-xl px-2.5 py-1.5 transition-all cursor-pointer border-l-2 {{ $isSelected ? 'is-selected border-amber-500 shadow-2xs' : 'border-transparent' }}">

                                        <div class="flex items-start gap-2">
                                            <!-- Tirador Drag & Drop con draggable=true directo y arrastre de fila completa -->
                                            <div draggable="true"
                                                 @dragstart.stop="
                                                     event.dataTransfer.setData('text/plain', '{{ $block->id }}');
                                                     event.dataTransfer.effectAllowed = 'move';
                                                     const blockEl = document.getElementById('block-{{ $block->id }}');
                                                     if (event.dataTransfer.setDragImage && blockEl) {
                                                         event.dataTransfer.setDragImage(blockEl, 20, 20);
                                                     }
                                                     isDragging = true;
                                                 "
                                                 @dragend="isDragging = false"
                                                 title="Arrastrar para mover este bloque de posición"
                                                 :class="(isBlockHovered || {{ $isSelected ? 'true' : 'false' }}) ? 'opacity-100' : 'opacity-0'"
                                                 class="shrink-0 w-5 h-6 mt-0.5 rounded flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-500/10 dark:text-slate-500 dark:hover:text-amber-400 cursor-grab active:cursor-grabbing transition-opacity duration-150 select-none group-hover:opacity-100">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <circle cx="7" cy="4" r="1.5" />
                                                    <circle cx="13" cy="4" r="1.5" />
                                                    <circle cx="7" cy="10" r="1.5" />
                                                    <circle cx="13" cy="10" r="1.5" />
                                                    <circle cx="7" cy="16" r="1.5" />
                                                    <circle cx="13" cy="16" r="1.5" />
                                                </svg>
                                            </div>

                                            <!-- Texto de la Historia según la etapa del menú de la escena individual -->
                                            <div class="flex-1 min-w-0 pr-14 sm:pr-24">
                                                @if($sceneStage === 'idea')
                                                    <!-- Etapa 1: Idea / Original / Texto Bruto -->
                                                    <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($block->raw_text ?: 'Sin notas o texto en bruto.') }}</div>
                                                @elseif($sceneStage === 'structured')
                                                    <!-- Etapa 2: Tratamiento Cinematográfico -->
                                                    @if(!empty($block->structured_text))
                                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($block->structured_text) }}</div>
                                                    @else
                                                        <div class="py-0.5 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                            <span>[Tratamiento pendiente de estructurar para este bloque]</span>
                                                            <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'structured')" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline cursor-pointer">
                                                                ⚡ Abrir detalles para estructurar ➔
                                                            </button>
                                                        </div>
                                                    @endif
                                                @elseif($sceneStage === 'screenplay')
                                                    <!-- Etapa 3: Guión Literario (Story Architect / Fountain) -->
                                                    @if(!empty($block->screenplay_content))
                                                        <div class="screenplay-rendered-container font-screenplay text-sm max-w-2xl mx-auto w-full py-1">
                                                            {!! \App\Services\ScreenplayFormatterService::formatToHtml($block->screenplay_content) !!}
                                                        </div>
                                                    @else
                                                        <div class="py-0.5 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                            <span>[Guión literario pendiente de formatear para este bloque]</span>
                                                            <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'screenplay')" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline cursor-pointer">
                                                                🎬 Abrir detalles para convertir a guión ➔
                                                            </button>
                                                        </div>
                                                    @endif
                                                @elseif($sceneStage === 'shots')
                                                    <!-- Etapa 4: Planos y Tomas -->
                                                    @if($block->shots->isNotEmpty())
                                                        <div class="space-y-1.5">
                                                            @foreach($block->shots as $shot)
                                                                <div class="p-2 rounded-lg bg-slate-50/80 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                                                                    <div class="flex items-center gap-2">
                                                                        @if($shot->hasStoryboard())
                                                                            <img src="{{ $shot->storyboard_url }}"
                                                                                 alt="Shot {{ $shot->shot_number }}"
                                                                                 class="h-8 w-14 rounded object-cover border border-slate-300 dark:border-slate-700 cursor-pointer shrink-0"
                                                                                 wire:click.stop="openStoryboardPreview('{{ $shot->id }}')">
                                                                        @endif
                                                                        <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-1.5 py-0.5 rounded text-[11px]">
                                                                            --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                                        </span>
                                                                        <span class="text-slate-700 dark:text-slate-300">{{ $shot->description }}</span>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="py-0.5 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                            <span>[Sin planos técnicos desglosados en este bloque]</span>
                                                            <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'shots')" class="text-cyan-600 dark:text-cyan-400 font-semibold hover:underline cursor-pointer">
                                                                📹 Abrir detalles para desglosar planos ➔
                                                            </button>
                                                        </div>
                                                    @endif
                                                @endif

                                                <!-- Aviso de Agujero de Guión (Persistente hasta nuevo análisis o justificación) -->
                                                @if($showPlotHoleAlerts && $block->hasPlotHole())
                                                    <div class="mt-2.5 p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-2.5 shadow-2xs">
                                                        <div class="flex items-start gap-2.5 min-w-0">
                                                            <span class="text-sm shrink-0">🕳️</span>
                                                            <div class="space-y-0.5">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="font-bold uppercase tracking-wider text-[10px] text-rose-900 dark:text-rose-300">
                                                                        Agujero de Guión Detectado
                                                                    </span>
                                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-200/90 dark:bg-rose-900/80 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-700 uppercase">
                                                                        Detectado en: {{ $block->getPlotHoleStageLabel() }}
                                                                    </span>
                                                                </div>
                                                                <p class="leading-relaxed font-sans text-xs text-rose-800 dark:text-rose-200">
                                                                    {{ $block->plot_hole }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <button wire:click.stop="openJustifyModal('{{ $block->id }}')"
                                                                type="button"
                                                                class="px-2 py-1 rounded text-[11px] font-bold bg-white hover:bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:hover:bg-rose-800 dark:text-rose-100 border border-rose-300 dark:border-rose-700 transition shadow-2xs cursor-pointer flex items-center gap-1 shrink-0"
                                                                title="Justificar por qué no es un agujero de guión y retirarlo">
                                                            <span>✅</span>
                                                            <span>Justificar</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Botones FLOTANTES en la esquina superior derecha (no en línea con el texto) -->
                                            <div class="absolute top-1 right-1 sm:top-1.5 sm:right-2 z-10 flex items-center gap-1 transition-opacity bg-white/90 dark:bg-slate-900/90 backdrop-blur-xs p-0.5 rounded-lg border border-slate-200/60 dark:border-slate-800/60 shadow-2xs">
                                                @if($showPlotHoleAlerts && $block->hasPlotHole())
                                                    <span class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30"
                                                          title="Agujero de guión detectado en este bloque">
                                                        🕳️
                                                    </span>
                                                @endif
                                                @if($block->isStageLocked('structured') || $block->isStageLocked('screenplay'))
                                                    <span class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30"
                                                          title="Este bloque tiene etapas protegidas contra sobreescritura por modificaciones manuales">
                                                        🔒
                                                    </span>
                                                @endif
                                                <button onclick="window.dispatchEvent(new CustomEvent('play-tts', { detail: { scope: 'block', blockId: '{{ $block->id }}' } }))"
                                                        type="button"
                                                        title="Escuchar locución de este bloque (TTS)"
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition shadow-2xs cursor-pointer">
                                                    <span>🔊</span>
                                                </button>
                                                <button wire:click.stop="openBlockDetails('{{ $block->id }}')"
                                                        type="button"
                                                        title="Abrir detalles y etapas en panel lateral"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-500 hover:text-amber-700 dark:hover:text-amber-300 transition shadow-2xs cursor-pointer">
                                                    <span class="text-xs">📑</span>
                                                    <span class="text-[11px] font-bold hidden sm:inline">Detalles</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Divisor Gutenberg Intermedio: Revelado al pasar el ratón -->
                                    <div x-data="{ isPlusHovered: false }"
                                         @mouseenter="isPlusHovered = true"
                                         @mouseleave="isPlusHovered = false"
                                         class="gutenberg-container relative py-1 my-0 flex items-center justify-center cursor-pointer z-[5] w-full"
                                         style="z-index: 5;">
                                        <div class="gutenberg-divider-line"
                                             :class="isPlusHovered ? 'is-visible' : ''"
                                             style="height: 2px; background-color: #f59e0b;"></div>
                                        <button wire:click="openNewBlockModal({{ $block->block_order }}, '{{ $scene->id }}')"
                                                type="button"
                                                title="Añadir bloque aquí"
                                                :class="isPlusHovered ? 'is-visible' : ''"
                                                class="gutenberg-divider-btn opacity-0 scale-75 pointer-events-none relative z-[6] w-7 h-7 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold flex items-center justify-center shadow-md hover:scale-110 transition-all duration-150 cursor-pointer">
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-center py-8 bg-slate-50 dark:bg-slate-950 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 p-4 space-y-2">
                                        <p class="text-xs text-slate-500 font-medium">No hay bloques en esta escena todavía.</p>
                                        <button wire:click="openNewBlockModal(null, '{{ $scene->id }}')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-lg shadow-2xs cursor-pointer">
                                            + Crear Primer Bloque
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Divisor Gutenberg entre Escenas (+ Nueva Escena: Revelado al pasar el ratón) -->
                <div x-data="{ isScenePlusHovered: false }"
                     @mouseenter="isScenePlusHovered = true"
                     @mouseleave="isScenePlusHovered = false"
                     class="gutenberg-container relative py-0.5 my-0 flex items-center justify-center cursor-pointer z-[5] w-full"
                     style="z-index: 5;">
                    <div class="gutenberg-divider-line"
                         :class="isScenePlusHovered ? 'is-visible' : ''"
                         style="height: 2px; background-color: #f59e0b;"></div>
                    <button wire:click="openNewSceneModal({{ $scene->scene_order }})"
                            type="button"
                            :class="isScenePlusHovered ? 'is-visible' : ''"
                            class="gutenberg-divider-btn opacity-0 scale-75 pointer-events-none relative z-[6] px-3 py-1 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-md hover:scale-105 transition-all duration-150 flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>Nueva Escena</span>
                    </button>
                </div>
            @empty
                <div class="text-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-8 space-y-4">
                    <span class="text-4xl">🎬</span>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">No hay escenas en este proyecto todavía</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">Crea la primera escena para redactar tu guión al estilo StoryArchitect.</p>
                    <button wire:click="openNewSceneModal()" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">
                        + Crear Primera Escena
                    </button>
                </div>
            @endforelse
        </div>
    </div>
