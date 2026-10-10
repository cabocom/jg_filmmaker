<div class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors">
    <style>
        /* Estilos directos e infalibles para hover de bloques */
        .script-block-item {
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .script-block-item:not(.is-selected):hover {
            background-color: #f1f5f9 !important; /* Tailwind slate-100 */
        }
        .dark .script-block-item:not(.is-selected):hover {
            background-color: #1e293b !important; /* Tailwind slate-800 */
        }
        .script-block-item.is-selected {
            background-color: rgba(245, 158, 11, 0.1) !important;
            border-left-color: #f59e0b !important;
        }

        /* Divisores Gutenberg: línea horizontal continua ámbar garantizada */
        .gutenberg-container {
            position: relative;
            z-index: 5 !important;
        }
        .gutenberg-divider-line {
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 2px !important;
            background-color: #f59e0b !important; /* amber-500 */
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            z-index: 5 !important;
        }
        .gutenberg-container:hover .gutenberg-divider-line,
        .gutenberg-divider-line.is-visible {
            opacity: 1 !important;
        }
        .gutenberg-container:hover .gutenberg-divider-btn,
        .gutenberg-divider-btn.is-visible {
            opacity: 1 !important;
            transform: scale(1) !important;
            pointer-events: auto !important;
            z-index: 6 !important;
        }

    </style>

    <!-- ================= BARRA SUPERIOR STICKY: TÍTULO DEL PROYECTO Y NAVEGACIÓN STORY ARCHITECT ================= -->
    <div class="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800 shadow-xs backdrop-blur-md px-4 sm:px-6 lg:px-8 py-2.5 transition-colors">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <!-- Título del Proyecto y Recuento de Escenas (Izquierda) -->
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-sm font-bold font-mono uppercase tracking-wider text-slate-900 dark:text-white truncate">
                    {{ $project->title }}
                </span>
                <span class="px-2 py-0.5 rounded-md text-xs font-bold font-mono bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                    {{ $scenes->count() }} {{ $scenes->count() === 1 ? 'escena' : 'escenas' }}
                </span>
            </div>

            <!-- Menú Superior Alineado a la Derecha: Pipeline - Idea - Tratamiento - Guion - Planos/Tomas -->
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200 dark:border-slate-700/80 shrink-0 overflow-x-auto">
                <button wire:click="setActiveView('pipeline')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-mono transition cursor-pointer whitespace-nowrap {{ $activeView === 'pipeline' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Pipeline
                </button>
                <button wire:click="setActiveView('idea')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-mono transition cursor-pointer whitespace-nowrap {{ $activeView === 'idea' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Idea
                </button>
                <button wire:click="setActiveView('structured')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-mono transition cursor-pointer whitespace-nowrap {{ $activeView === 'structured' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Tratamiento
                </button>
                <button wire:click="setActiveView('screenplay')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-mono transition cursor-pointer whitespace-nowrap {{ $activeView === 'screenplay' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Guion
                </button>
                <button wire:click="setActiveView('shots')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg text-xs font-mono transition cursor-pointer whitespace-nowrap {{ $activeView === 'shots' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Planos/Tomas
                </button>
            </div>
        </div>
    </div>

    <!-- Mensajes Flash Globales -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        @if(session()->has('success'))
            <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session()->has('error'))
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif
        @if(session()->has('info'))
            <div class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-800 dark:text-indigo-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>ℹ️</span>
                    <span>{{ session('info') }}</span>
                </div>
            </div>
        @endif
    </div>

    @if($activeView === 'pipeline')
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
                    <div class="px-3.5 py-2 bg-slate-100/75 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-700/60 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
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
                                    class="flex items-center gap-2 text-left truncate cursor-pointer group/btn">
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

                                <div class="flex items-center gap-1.5 flex-wrap font-mono">
                                    <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">
                                        ESC. {{ $scene->scene_order }}
                                    </span>
                                    <span class="text-slate-300 dark:text-slate-600 font-bold">·</span>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wide truncate">
                                        {{ $scene->slugline }}
                                    </span>
                                    @if($scene->time_of_day)
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500">
                                            ({{ $scene->time_of_day }})
                                        </span>
                                    @endif
                                </div>
                            </button>
                        </div>

                        <!-- Cabecera del Pipeline Integrada dentro de la Escena (Exclusiva para esta escena) -->
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono hidden md:inline">
                                {{ $scene->scriptBlocks->count() }} {{ $scene->scriptBlocks->count() === 1 ? 'bloque' : 'bloques' }}
                            </span>

                            <div class="inline-flex rounded-lg bg-white/90 dark:bg-slate-900/90 p-0.5 border border-slate-200 dark:border-slate-700/80 text-[11px] shadow-2xs">
                                <!-- 1. Idea -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'idea')"
                                        type="button"
                                        title="Listado de textos originales en bruto para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer {{ $sceneStage === 'idea' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Idea
                                </button>

                                <!-- 2. Tratamiento -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'structured')"
                                        type="button"
                                        title="Listado de textos estructurados a tratamiento para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer {{ $sceneStage === 'structured' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Tratamiento
                                </button>

                                <!-- 3. Guión -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'screenplay')"
                                        type="button"
                                        title="Listado de textos formateados a guión canónico Fountain para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer {{ $sceneStage === 'screenplay' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                                    Guión
                                </button>

                                <!-- 4. Planos / Tomas -->
                                <button wire:click="setScenePipelineStage('{{ $scene->id }}', 'shots')"
                                        type="button"
                                        title="Desglose técnico de planos de cámara y tomas para esta escena"
                                        class="px-2 py-0.5 rounded transition font-mono cursor-pointer {{ $sceneStage === 'shots' ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
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
                                            <div class="flex-1 min-w-0">
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
                                                                        <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-1.5 py-0.5 rounded text-[11px]">
                                                                            --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                                        </span>
                                                                        <span class="text-slate-700 dark:text-slate-300">{{ $shot->description }}</span>
                                                                    </div>
                                                                    <span class="text-[11px] text-slate-500 font-mono">{{ $shot->takes->count() }} tomas</span>
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
                                            </div>

                                            <!-- Botón de Detalles flotando a la derecha -->
                                            <div :class="isBlockHovered ? 'opacity-100' : 'opacity-25'"
                                                 class="shrink-0 pt-0.5 transition-opacity group-hover:opacity-100 flex items-center gap-1.5">
                                                @if($block->isStageLocked('structured') || $block->isStageLocked('screenplay'))
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30"
                                                          title="Este bloque tiene etapas protegidas contra sobreescritura por modificaciones manuales">
                                                        🔒 Protegido
                                                    </span>
                                                @endif
                                                <button wire:click.stop="openBlockDetails('{{ $block->id }}')"
                                                        type="button"
                                                        title="Abrir detalles y etapas en panel lateral"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-500 hover:text-amber-700 dark:hover:text-amber-300 transition shadow-2xs cursor-pointer">
                                                    <span class="text-xs">📑</span>
                                                    <span class="text-[11px] font-bold">Detalles</span>
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
    @else
        <!-- ================= VISTA CONSOLIDADA CONTINUA ESTILO STORY ARCHITECT ================= -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <!-- Tarjeta de Hoja de Lectura Continua Story Architect -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-12 space-y-8 min-h-[70vh]">
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
                                <div class="font-mono font-black text-sm uppercase tracking-wide text-slate-950 dark:text-amber-400">
                                    {{ $scene->scene_order }}. {{ $scene->slugline }}
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
                                        <!-- Botón flotante para abrir edición en drawer directamente -->
                                        <div class="absolute right-0 top-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button wire:click="openBlockDetails('{{ $block->id }}', '{{ match($activeView) { 'idea' => 'raw', 'structured' => 'structured', 'screenplay' => 'screenplay', 'shots' => 'shots', default => 'raw' } }}')"
                                                    type="button"
                                                    title="Editar bloque #{{ $block->block_order }}"
                                                    class="px-2 py-1 rounded text-[11px] font-mono bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-900 dark:bg-slate-800 dark:hover:bg-amber-950/40 dark:text-slate-300 dark:hover:text-amber-300 border border-slate-200 dark:border-slate-700 transition flex items-center gap-1 cursor-pointer">
                                                <span>✏️</span>
                                                <span>Bloque #{{ $block->block_order }}</span>
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
                                                                <span class="text-slate-500 font-mono text-[11px]">{{ $shot->takes->count() }} tomas</span>
                                                            </div>
                                                            <p class="text-slate-700 dark:text-slate-300 leading-relaxed font-sans">{{ $shot->description }}</p>
                                                            @if($shot->takes->isNotEmpty())
                                                                <div class="flex flex-wrap gap-1.5 pt-1.5">
                                                                    @foreach($shot->takes as $take)
                                                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border {{ $take->is_good_take ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-300 dark:border-slate-700' }}">
                                                                            Toma {{ $take->take_number }} {{ $take->is_good_take ? '✓ Buena' : '' }}
                                                                        </span>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs italic text-slate-400 font-mono py-1">
                                                    [Bloque #{{ $block->block_order }}: Sin planos técnicos desglosados]
                                                </p>
                                            @endif
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
    @endif

    <!-- ================= MODAL LATERAL DERECHO AL 60% (DETALLES Y EDICIÓN DE BLOQUE) ================= -->
    @if($detailBlockId)
        @php
            $detailBlock = \App\Models\ScriptBlock::with('scene.location', 'shots.takes')->find($detailBlockId);
            $detailLocks = $detailBlock?->user_locks ?? [];
        @endphp

        @if($detailBlock)
            <!-- Contenedor general del Drawer -->
            <div class="fixed inset-0 z-50 overflow-hidden"
                 x-data
                 @keydown.escape.window="$wire.closeBlockDetails()">

                <!-- Backdrop suave exterior: NO muy oscurecido -->
                <div class="fixed inset-0 bg-slate-950/25 backdrop-blur-[2px] transition-opacity cursor-pointer"
                     wire:click="closeBlockDetails"></div>

                <!-- Panel lateral deslizante alineado a la derecha con ancho exacto del 60% -->
                <div class="fixed inset-y-0 right-0 z-50 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col h-full"
                     style="width: 60vw; min-width: 320px; max-width: 60vw;">

                    <!-- Header del Drawer -->
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/80 flex items-center justify-between gap-4 shrink-0">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📑</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>BLOQUE #{{ $detailBlock->block_order }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 uppercase font-bold">
                                        Etapa: {{ $detailActiveTab }}
                                    </span>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Escena #{{ $detailBlock->scene?->scene_order }} · {{ $detailBlock->scene?->slugline }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button wire:click="closeBlockDetails"
                                    type="button"
                                    title="Cerrar panel lateral (Esc)"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer">
                                <span>✕</span>
                                <span>Cerrar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Pestañas internas para navegar por todas las etapas del bloque -->
                    <div class="px-6 pt-2 border-b border-slate-200 dark:border-slate-800 bg-slate-100/50 dark:bg-slate-950 flex items-center gap-2 overflow-x-auto shrink-0">
                        <button wire:click="setDetailTab('raw')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer {{ $detailActiveTab === 'raw' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            1. Idea / Bruto
                        </button>
                        <button wire:click="setDetailTab('structured')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $detailActiveTab === 'structured' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            <span>2. Tratamiento</span>
                            @if($detailBlock->isStageLocked('structured'))
                                <span class="text-xs" title="Tratamiento protegido contra sobreescritura (🔒)">🔒</span>
                            @endif
                        </button>
                        <button wire:click="setDetailTab('screenplay')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $detailActiveTab === 'screenplay' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            <span>3. Guión Literario</span>
                            @if($detailBlock->isStageLocked('screenplay'))
                                <span class="text-xs" title="Guión literario protegido contra sobreescritura (🔒)">🔒</span>
                            @endif
                        </button>
                        <button wire:click="setDetailTab('shots')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition cursor-pointer {{ $detailActiveTab === 'shots' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            4. Planos ({{ $detailBlock->shots->count() }})
                        </button>
                    </div>

                    <!-- Cuerpo scrolleable del Drawer -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Barra de Términos Fijos y Candados si existen -->
                        @if(!empty($detailLocks['pinned_terms']))
                            <div class="p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-900 dark:text-amber-300 flex items-center justify-between flex-wrap gap-2">
                                <span class="font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🔒</span>
                                    <span>Términos Fijos:</span>
                                </span>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @foreach($detailLocks['pinned_terms'] as $orig => $rep)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-[11px] font-mono">
                                            <span>{{ $orig }} ➜ {{ $rep }}</span>
                                            <button wire:click="removePinnedTerm('{{ $detailBlock->id }}', '{{ $orig }}')" class="text-rose-500 hover:text-rose-700 ml-1">✕</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Contenido y Edición según la pestaña seleccionada en el Drawer -->
                        @if($isEditingInDrawer)
                            <!-- Modo Edición dentro del Drawer -->
                            <div class="space-y-4">
                                @if($detailActiveTab === 'raw')
                                    <div>
                                        <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Texto Bruto / Idea
                                        </label>
                                        <textarea wire:model="editRawText" rows="12" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-sans leading-relaxed focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-inner"></textarea>
                                    </div>
                                @elseif($detailActiveTab === 'structured')
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Tratamiento Cinematográfico
                                        </label>
                                        <textarea wire:model="editStructuredText" rows="12" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-indigo-300 dark:border-indigo-700 text-slate-900 dark:text-white font-sans leading-relaxed focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition shadow-inner"></textarea>
                                    </div>
                                @elseif($detailActiveTab === 'screenplay')
                                    <div>
                                        <label class="block text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Guión Literario (Fountain)
                                        </label>
                                        <textarea wire:model="editScreenplayContent" rows="14" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-amber-300 dark:border-amber-500/50 text-slate-900 dark:text-amber-100 font-mono leading-relaxed focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-inner"></textarea>
                                    </div>
                                @elseif($detailActiveTab === 'shots')
                                    <div>
                                        <label class="block text-xs font-bold text-cyan-700 dark:text-cyan-400 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Delimitadores de Planos Técnicos
                                        </label>
                                        <textarea wire:model="editScreenplayContent" rows="14" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-cyan-300 dark:border-cyan-700 text-slate-900 dark:text-cyan-100 font-mono leading-relaxed focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition shadow-inner"></textarea>
                                    </div>
                                @endif

                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                                    <button wire:click="cancelEditingInDrawer" type="button" class="px-3 py-1.5 rounded-lg text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                                        Cancelar
                                    </button>
                                    <button wire:click="saveBlockFromDrawer" type="button" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                                        ✓ Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- Modo Visualización en el Drawer con Acciones de IA y Trazabilidad -->
                            <div class="space-y-5">
                                <!-- Barra de Acciones del Drawer según la etapa -->
                                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                    <div class="flex items-center gap-2">
                                        <button wire:click="startEditingInDrawer"
                                                type="button"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 hover:border-amber-400 transition shadow-2xs cursor-pointer">
                                            ✏️ Editar Texto
                                        </button>
                                        @if($detailActiveTab !== 'raw')
                                            <button wire:click="openAiTrace('{{ $detailBlock->id }}', '{{ $detailActiveTab }}')"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800/60 transition flex items-center gap-1.5 cursor-pointer">
                                                <span>⚡</span>
                                                <span>Trazabilidad IA</span>
                                            </button>
                                        @endif
                                    </div>

                                    <!-- Botones de Acción de IA Contextual -->
                                    <div>
                                        @if($detailActiveTab === 'raw')
                                            @php
                                                $isStructuredLocked = $detailBlock->isStageLocked('structured');
                                            @endphp
                                            @if($isStructuredLocked)
                                                <button type="button"
                                                        disabled
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-300 dark:border-slate-700 cursor-not-allowed flex items-center gap-1.5 opacity-60"
                                                        title="El tratamiento o guión tiene modificaciones manuales protegidas. Desbloquéalo para permitir regenerar desde el bruto.">
                                                    <span>🔒</span>
                                                    <span>Tratamiento Protegido</span>
                                                </button>
                                            @else
                                                <button wire:click="structureBlock('{{ $detailBlock->id }}')"
                                                        x-data="{ isProcessing: false }"
                                                        @click="isProcessing = true; $el.disabled = true; $el.classList.add('opacity-50', 'pointer-events-none');"
                                                        :disabled="isProcessing"
                                                        :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': isProcessing }"
                                                        wire:loading.delay.0ms.attr="disabled"
                                                        wire:loading.class="opacity-50 cursor-not-allowed pointer-events-none"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                                    <span wire:loading.remove wire:target="structureBlock">⚡ Estructurar Tratamiento</span>
                                                    <span wire:loading wire:target="structureBlock" class="flex items-center gap-1.5">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                        <span>Estructurando...</span>
                                                    </span>
                                                </button>
                                            @endif
                                        @elseif($detailActiveTab === 'structured')
                                            @php
                                                $isScreenplayLocked = $detailBlock->isStageLocked('screenplay');
                                            @endphp
                                            @if($isScreenplayLocked)
                                                <button type="button"
                                                        disabled
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-300 dark:border-slate-700 cursor-not-allowed flex items-center gap-1.5 font-mono opacity-60"
                                                        title="El guión literario tiene modificaciones manuales protegidas. Desbloquéalo en la pestaña 'Guión' para permitir regenerar.">
                                                    <span>🔒</span>
                                                    <span>Guión Protegido</span>
                                                </button>
                                            @else
                                                <button wire:click="formatBlockToScreenplay('{{ $detailBlock->id }}')"
                                                        x-data="{ isProcessing: false }"
                                                        @click="isProcessing = true; $el.disabled = true; $el.classList.add('opacity-50', 'pointer-events-none');"
                                                        :disabled="isProcessing"
                                                        :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': isProcessing }"
                                                        wire:loading.delay.0ms.attr="disabled"
                                                        wire:loading.class="opacity-50 cursor-not-allowed pointer-events-none"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none text-slate-950 shadow-sm transition flex items-center gap-1.5 font-mono cursor-pointer">
                                                    <span wire:loading.remove wire:target="formatBlockToScreenplay">🎬 Convertir a Guión</span>
                                                    <span wire:loading wire:target="formatBlockToScreenplay" class="flex items-center gap-1.5">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                        <span>Formateando...</span>
                                                    </span>
                                                </button>
                                            @endif
                                        @elseif($detailActiveTab === 'screenplay')
                                            <button wire:click="breakdownBlockShots('{{ $detailBlock->id }}')"
                                                    x-data="{ isProcessing: false }"
                                                    @click="isProcessing = true; $el.disabled = true; $el.classList.add('opacity-50', 'pointer-events-none');"
                                                    :disabled="isProcessing"
                                                    :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': isProcessing }"
                                                    wire:loading.delay.0ms.attr="disabled"
                                                    wire:loading.class="opacity-50 cursor-not-allowed pointer-events-none"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                                <span wire:loading.remove wire:target="breakdownBlockShots">📹 Desglosar Planos</span>
                                                <span wire:loading wire:target="breakdownBlockShots" class="flex items-center gap-1.5">
                                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                    <span>Desglosando...</span>
                                                </span>
                                            </button>
                                        @elseif($detailActiveTab === 'shots')
                                            <div class="flex items-center gap-2">
                                                <button wire:click="breakdownBlockShots('{{ $detailBlock->id }}')"
                                                        x-data="{ isProcessing: false }"
                                                        @click="isProcessing = true; $el.disabled = true; $el.classList.add('opacity-50', 'pointer-events-none');"
                                                        :disabled="isProcessing"
                                                        :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': isProcessing }"
                                                        wire:loading.delay.0ms.attr="disabled"
                                                        wire:loading.class="opacity-50 cursor-not-allowed pointer-events-none"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                                    <span wire:loading.remove wire:target="breakdownBlockShots">⚡ Regenerar Planos</span>
                                                    <span wire:loading wire:target="breakdownBlockShots" class="flex items-center gap-1.5">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                        <span>Regenerando...</span>
                                                    </span>
                                                </button>
                                                <button wire:click="startNewShotForBlock('{{ $detailBlock->id }}')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 cursor-pointer">
                                                    + Añadir Plano
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Vista de Texto según la etapa en el Drawer (Estilo homogéneo unificado) -->
                                @if($detailActiveTab === 'raw')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-2 shadow-2xs">
                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">1. Texto Bruto Original:</span>
                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($detailBlock->raw_text ?: 'Sin notas brutas.') }}</div>
                                    </div>
                                @elseif($detailActiveTab === 'structured')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-3 shadow-2xs">
                                        <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-indigo-100 dark:border-indigo-900/40">
                                            <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">2. Tratamiento Dramático:</span>
                                            @if($detailBlock->hasManualOverride('structured') || $detailBlock->isStageLocked('structured'))
                                                <div class="flex items-center gap-2">
                                                    @if($detailBlock->isStageLocked('structured'))
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                                            <span>🔒</span>
                                                            <span>Modificado manualmente · Protegido</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'structured')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 transition cursor-pointer"
                                                                title="Desbloquear para permitir que la IA regenere esta etapa">
                                                            🔓 Desbloquear
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                                            <span>🔓</span>
                                                            <span>Modificado manualmente · Desbloqueado</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'structured')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition cursor-pointer"
                                                                title="Bloquear para proteger contra regeneraciones de la IA">
                                                            🔒 Proteger
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        @if($detailBlock->hasManualOverride('structured'))
                                            <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                                <span class="text-sm">⚠️</span>
                                                <div class="leading-snug">
                                                    <strong class="font-bold">Modificación manual detectada:</strong>
                                                    @if($detailBlock->isStageLocked('structured'))
                                                        Este texto está <strong>protegido (🔒)</strong>. Si vuelves a regenerar desde el texto bruto, se conservarán tus cambios y no se sobreescribirán.
                                                    @else
                                                        Este texto está <strong>desbloqueado (🔓)</strong>. Si ejecutas la estructuración desde el texto bruto, <strong>perderás tus cambios manuales</strong>.
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($detailBlock->structured_text ?: 'Aún no se ha estructurado el tratamiento para este bloque.') }}</div>
                                    </div>
                                @elseif($detailActiveTab === 'screenplay')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-3 shadow-2xs">
                                        <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-indigo-100 dark:border-indigo-900/40">
                                            <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">3. Guión Literario (Story Architect):</span>
                                            @if($detailBlock->hasManualOverride('screenplay') || $detailBlock->isStageLocked('screenplay'))
                                                <div class="flex items-center gap-2">
                                                    @if($detailBlock->isStageLocked('screenplay'))
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                                            <span>🔒</span>
                                                            <span>Modificado manualmente · Protegido</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'screenplay')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 transition cursor-pointer"
                                                                title="Desbloquear para permitir que la IA regenere esta etapa">
                                                            🔓 Desbloquear
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                                            <span>🔓</span>
                                                            <span>Modificado manualmente · Desbloqueado</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'screenplay')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition cursor-pointer"
                                                                title="Bloquear para proteger contra regeneraciones de la IA">
                                                            🔒 Proteger
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        @if($detailBlock->hasManualOverride('screenplay'))
                                            <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                                <span class="text-sm">⚠️</span>
                                                <div class="leading-snug">
                                                    <strong class="font-bold">Modificación manual detectada:</strong>
                                                    @if($detailBlock->isStageLocked('screenplay'))
                                                        Este guión está <strong>protegido (🔒)</strong>. Si regeneras desde el tratamiento o el bruto, tus cambios de diálogo/acción se preservarán y no se sobreescribirán.
                                                    @else
                                                        Este guión está <strong>desbloqueado (🔓)</strong>. Si ejecutas la conversión a guión desde el tratamiento, <strong>perderás estos cambios manuales</strong>.
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        @if(!empty($detailBlock->screenplay_content))
                                            <div class="screenplay-rendered-container font-screenplay text-sm max-w-2xl mx-auto w-full py-1">
                                                {!! \App\Services\ScreenplayFormatterService::formatToHtml($detailBlock->screenplay_content) !!}
                                            </div>
                                        @else
                                            <div class="text-sm text-slate-400 dark:text-slate-500 italic font-screenplay">Aún no se ha formateado a guión literario.</div>
                                        @endif
                                    </div>
                                @elseif($detailActiveTab === 'shots')
                                    <div class="space-y-4">
                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">4. Desglose Técnico de Planos y Tomas:</span>
                                        @forelse($detailBlock->shots as $shot)
                                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2.5">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                                                    <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-2 py-0.5 rounded text-xs">
                                                        --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                    </span>
                                                    <span class="text-xs text-slate-500 font-mono">{{ $shot->takes->count() }} tomas</span>
                                                </div>
                                                <p class="text-xs text-slate-700 dark:text-slate-300">{{ $shot->description }}</p>

                                                @if($shot->takes->isNotEmpty())
                                                    <div class="space-y-1.5 pt-2 border-t border-slate-100 dark:border-slate-800/60">
                                                        @foreach($shot->takes as $take)
                                                            <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
                                                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">TOMA {{ $take->take_number }}</span>
                                                                <button wire:click="toggleGoodTake('{{ $take->id }}')"
                                                                        type="button"
                                                                        class="px-2 py-0.5 rounded text-[11px] font-mono font-bold border transition cursor-pointer {{ $take->is_good_take ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-300 dark:border-slate-700' }}">
                                                                    {{ $take->is_good_take ? '✓ TOMA BUENA' : '○ Descarte' }}
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-center py-6 text-xs text-slate-500">No hay planos desglosados en este bloque.</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- ================= MODAL DE CREAR NUEVO BLOQUE (ESTILO GUTENBERG, SOLO TEXTO BRUTO) ================= -->
    @if($showNewBlockModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs transition"
             x-data
             @keydown.escape.window="$wire.closeNewBlockModal()"
             wire:click.self="closeNewBlockModal">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-lg space-y-4 transition">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>+</span>
                            <span>Añadir Nuevo Bloque</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            @if($insertAfterOrder !== null && $insertAfterOrder > 0)
                                Se insertará a continuación del Bloque #{{ $insertAfterOrder }}.
                            @elseif($insertAfterOrder === 0)
                                Se insertará al principio de la escena.
                            @else
                                Se insertará al final de la escena.
                            @endif
                        </p>
                    </div>
                    <button wire:click="closeNewBlockModal" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold">✕</button>
                </div>

                <!-- SOLO EL CAMPO TEXTO BRUTO COMO PIDIÓ EL USUARIO -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider font-mono">
                        Texto bruto / Idea original *
                    </label>
                    <textarea wire:model="newBlockRawText"
                              rows="6"
                              placeholder="Escribe aquí la acción en bruto, notas, estímulos o diálogos..."
                              class="w-full px-4 py-3 text-sm rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-sans leading-relaxed focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-inner"
                              autofocus></textarea>
                    @error('newBlockRawText')
                        <span class="text-rose-500 text-xs block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeNewBlockModal" type="button" class="px-3.5 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl">
                        Cancelar
                    </button>
                    <button wire:click="createNewBlock" type="button" class="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition">
                        Crear e Insertar Bloque
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL NUEVA ESCENA ================= -->
    @if($showNewSceneModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.set('showNewSceneModal', false)"
             wire:click.self="$set('showNewSceneModal', false)">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-md space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Nueva Escena Cinematográfica</h3>
                    <button wire:click="$set('showNewSceneModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Encabezado Canónico (Slugline) *</label>
                        <input type="text"
                               wire:model="newSceneSlugline"
                               placeholder="INT. HABITACIÓN - DÍA 1"
                               class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono uppercase">
                        @error('newSceneSlugline') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Momento del Día *</label>
                        <select wire:model="newSceneTimeOfDay" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                            <option value="DÍA">DÍA</option>
                            <option value="NOCHE">NOCHE</option>
                            <option value="ATARDECER">ATARDECER</option>
                            <option value="AMANECER">AMANECER</option>
                            <option value="CONTINUO">CONTINUO</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Localización de la Biblia (Opcional)</label>
                        <select wire:model="newSceneLocationId" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                            <option value="">-- Sin asignar --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="$set('showNewSceneModal', false)" class="px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                        <button wire:click="createScene" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Guardar Escena</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL NUEVO PLANO TÉCNICO ================= -->
    @if($showNewShotModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.set('showNewShotModal', false)"
             wire:click.self="$set('showNewShotModal', false)">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-md space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Añadir Plano Técnico</h3>
                    <button wire:click="$set('showNewShotModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Escala de Plano *</label>
                        <select wire:model="newShotType" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono">
                            <option value="PG">PG - Plano General</option>
                            <option value="PE">PE - Plano Entero</option>
                            <option value="PA">PA - Plano Americano</option>
                            <option value="PM">PM - Plano Medio</option>
                            <option value="PMC">PMC - Plano Medio Corto</option>
                            <option value="PP">PP - Primer Plano</option>
                            <option value="PPP">PPP - Primerísimo Primer Plano</option>
                            <option value="PD">PD - Plano Detalle</option>
                            <option value="INSERT">INSERT - Inserto</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Descripción Técnica del Plano *</label>
                        <textarea wire:model="newShotDescription"
                                  rows="3"
                                  placeholder="Describe la cobertura visual o movimiento de cámara..."
                                  class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                        @error('newShotDescription') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="$set('showNewShotModal', false)" class="px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                        <button wire:click="addShot" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Guardar Plano</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

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
                <div class="px-6 py-2 bg-slate-100/70 dark:bg-slate-950/70 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 shrink-0" style="flex-shrink: 0;">
                    <div class="flex items-center gap-4">
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
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('scroll-to-selected-block', ({ blockId }) => {
            if (!blockId) return;
            setTimeout(() => {
                const el = document.getElementById('block-' + blockId);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 60);
        });
    });
</script>
@endpush
