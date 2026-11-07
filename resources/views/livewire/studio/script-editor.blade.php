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
    <div class="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800 shadow-xs backdrop-blur-md px-3 sm:px-6 lg:px-8 py-2 transition-colors">
        <div class="max-w-7xl mx-auto flex items-center gap-2 flex-wrap py-1">
            <!-- Título del Proyecto con Botón de Edición -->
            <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-xs sm:text-sm font-bold font-mono uppercase tracking-wider text-slate-900 dark:text-white" title="{{ $project->title }}">
                    {{ $project->title }}
                </span>
                <button wire:click="openEditTitleModal"
                        type="button"
                        title="Modificar nombre del proyecto"
                        class="p-1 rounded-md text-slate-400 hover:text-amber-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </button>
            </div>

            <!-- Contador de Escenas -->
            <span class="px-2 py-1 rounded-lg text-xs font-bold font-mono bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                {{ $scenes->count() }} {{ $scenes->count() === 1 ? 'escena' : 'escenas' }}
            </span>

            <!-- Menú Desplegable Contraído de Etapas (Pipeline, Idea, Tratamiento, Guion, Planos/Tomas) -->
            @php
                $stageLabels = [
                    'pipeline' => ['icon' => '🎬', 'label' => 'Pipeline'],
                    'idea' => ['icon' => '💡', 'label' => 'Idea'],
                    'structured' => ['icon' => '📝', 'label' => 'Tratamiento'],
                    'screenplay' => ['icon' => '📜', 'label' => 'Guion'],
                    'shots' => ['icon' => '🎥', 'label' => 'Planos'],
                ];
                $currStage = $stageLabels[$activeView] ?? ['icon' => '🎬', 'label' => 'Pipeline'];
            @endphp
            <div class="relative shrink-0" x-data="{ stageMenuOpen: false }" @click.outside="stageMenuOpen = false">
                <button @click="stageMenuOpen = !stageMenuOpen"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition shadow-xs cursor-pointer shrink-0">
                    <span>{{ $currStage['icon'] }}</span>
                    <span>{{ $currStage['label'] }}</span>
                    <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': stageMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="stageMenuOpen"
                     x-transition
                     class="absolute left-0 top-full mt-1.5 w-44 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-1 z-50 space-y-0.5 font-mono text-xs"
                     style="display: none;">
                    @foreach($stageLabels as $key => $data)
                        <button wire:click="setActiveView('{{ $key }}')"
                                @click="stageMenuOpen = false"
                                type="button"
                                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-left {{ $activeView === $key ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition cursor-pointer">
                            <div class="flex items-center gap-2">
                                <span>{{ $data['icon'] }}</span>
                                <span>{{ $data['label'] }}</span>
                            </div>
                            @if($activeView === $key)
                                <span class="text-amber-600 dark:text-amber-400 text-xs">✓</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Botón de la Biblia de Proyecto -->
            <button type="button"
                    onclick="Livewire.dispatch('toggle-bible-drawer')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 hover:border-amber-500/40 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer shrink-0"
                    title="Biblia de Proyecto (Personajes y Localizaciones)">
                <span>📖</span>
                <span>Biblia</span>
            </button>

            <!-- Botón del Filtro de Agujeros de Guión con Menú Desplegable -->
            @php
                $totalPlotHoles = $scenes->flatMap->scriptBlocks->filter(fn($b) => filled($b->plot_hole))->count();
            @endphp
            <div class="relative inline-flex items-center shrink-0" x-data="{ holeMenuOpen: false }" @click.outside="holeMenuOpen = false">
                <div class="inline-flex items-center rounded-lg shadow-2xs border {{ $totalPlotHoles > 0 ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }} overflow-hidden">
                    <!-- Acción principal: Analizar previa confirmación de tokens -->
                    <button wire:click="promptAnalyzePlotHoles"
                            wire:loading.attr="disabled"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono font-medium {{ $totalPlotHoles > 0 ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 hover:bg-rose-100' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200' }} transition cursor-pointer shrink-0 disabled:opacity-50"
                            title="Pasar filtro de agujeros de guión con IA (informa del coste en tokens antes de ejecutar)">
                        <span wire:loading.remove wire:target="promptAnalyzePlotHoles, analyzePlotHoles">🕳️</span>
                        <span wire:loading wire:target="promptAnalyzePlotHoles, analyzePlotHoles" class="animate-spin text-xs">⏳</span>
                        <span>Agujeros</span>
                        @if($totalPlotHoles > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $showPlotHoleAlerts ? 'bg-rose-500 text-white animate-pulse' : 'bg-slate-400 text-white' }}"
                                  title="{{ $showPlotHoleAlerts ? 'Avisos activados' : 'Avisos desactivados' }}">
                                {{ $totalPlotHoles }}
                            </span>
                        @endif
                    </button>

                    <!-- Botón del Desplegable (Flecha) -->
                    <button @click="holeMenuOpen = !holeMenuOpen"
                            type="button"
                            class="px-1.5 py-1 text-xs border-l {{ $totalPlotHoles > 0 ? 'border-rose-200 dark:border-rose-800/80 bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 hover:bg-rose-100' : 'border-slate-200 dark:border-slate-700 bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300' }} transition cursor-pointer"
                            title="Opciones de agujeros de guión">
                        <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': holeMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Dropdown Panel de Agujeros de Guión -->
                <div x-show="holeMenuOpen"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-0 top-full mt-1.5 w-48 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-1 z-50 space-y-0.5 font-mono text-xs">
                    
                    <!-- 1. Analizar con IA -->
                    <button wire:click="promptAnalyzePlotHoles"
                            @click="holeMenuOpen = false"
                            type="button"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                        <span>🔍</span>
                        <span>Analizar con IA</span>
                    </button>

                    <!-- 2. Ver u Ocultar Avisos -->
                    @if($showPlotHoleAlerts)
                        <button wire:click="togglePlotHoleAlerts"
                                @click="holeMenuOpen = false"
                                type="button"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                            <span>🙈</span>
                            <span>Ocultar avisos</span>
                        </button>
                    @else
                        <button wire:click="togglePlotHoleAlerts"
                                @click="holeMenuOpen = false"
                                type="button"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-amber-600 dark:text-amber-400 font-bold hover:bg-amber-500/10 transition cursor-pointer">
                            <span>👁️</span>
                            <span>Mostrar avisos</span>
                        </button>
                    @endif

                    <div class="border-t border-slate-100 dark:border-slate-800 my-0.5"></div>

                    <!-- 3. Borrar marcas -->
                    <button wire:click="clearPlotHoles"
                            @click="holeMenuOpen = false"
                            type="button"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition cursor-pointer">
                        <span>🗑️</span>
                        <span>Borrar avisos</span>
                    </button>
                </div>
            </div>

            <!-- Botón de Historial y Trazabilidad del Proyecto -->
            <button type="button"
                    wire:click="openActivityHistoryModal"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 hover:border-sky-500/40 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer shrink-0"
                    title="Historial de acciones y trazabilidad en orden cronológico inverso">
                <span>📜</span>
                <span>Historial</span>
            </button>

            <!-- Botón de Exportar Guión Completo a PDF -->
            <button type="button"
                    wire:click="exportPdf"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-xs cursor-pointer shrink-0 disabled:opacity-50"
                    title="Exportar y descargar el guión cinematográfico completo en PDF estándar">
                <span wire:loading.remove wire:target="exportPdf">📄</span>
                <span wire:loading wire:target="exportPdf" class="animate-spin text-xs">⏳</span>
                <span>Exportar PDF</span>
            </button>

            <!-- Botón de Lectura de Guión Automatizada (TTS vía Speaches-AI) -->
            <button type="button"
                    @click="$dispatch('play-tts', { scope: 'project' })"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-xs cursor-pointer shrink-0"
                    title="Lectura dramática automatizada de todo el guión con voces sintetizadas por IA">
                <span>🔊</span>
                <span>Lectura de Guión</span>
            </button>

            <!-- Botón de Renderizado / Procesamiento en Lote ("Renderizar Todo de Golpe") -->
            <button type="button"
                    wire:click="openBatchModal"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-xs cursor-pointer shrink-0"
                    title="Renderizar todo de golpe: estructurar brutos, generar guiones y desglosar planos">
                <span>⚡</span>
                <span>Renderizar en Lote</span>
            </button>

            <!-- Botón de Importar Guión (PDF/TXT) -->
            <button type="button"
                    wire:click="openImportModal"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 hover:border-amber-500/40 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer shrink-0"
                    title="Importar guion en PDF o TXT reemplazando el contenido del proyecto">
                <span>📥</span>
                <span>Importar Guión</span>
            </button>
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
        @if(session()->has('warning'))
            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>🕳️</span>
                    <span>{{ session('warning') }}</span>
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
                                <button @click.stop="$dispatch('play-tts', { scope: 'scene', sceneId: '{{ $scene->id }}' })"
                                        type="button"
                                        title="Escuchar locución completa de la Escena {{ $scene->scene_order }}"
                                        class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-200/80 dark:hover:bg-slate-700/80 transition cursor-pointer shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M11 5L6 9H2v6h4l5 4V5z" />
                                    </svg>
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
                                        title="Desglose técnico de planos de cámara y tomas para esta escena"
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
                                                <button @click.stop="$dispatch('play-tts', { scope: 'block', blockId: '{{ $block->id }}' })"
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
    @else
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
                 x-data>

                <!-- Backdrop suave exterior: NO muy oscurecido (sin cierre accidental al hacer clic fuera) -->
                <div class="fixed inset-0 bg-slate-950/25 backdrop-blur-[2px] transition-opacity"></div>

                <!-- Panel lateral deslizante alineado a la derecha (adaptativo 100% en móvil, 60% en escritorio) -->
                <div class="fixed inset-y-0 right-0 z-50 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col h-full w-full sm:w-[540px] md:w-[620px] lg:w-[60vw] max-w-full">

                    <!-- Header del Drawer -->
                    <div class="px-3.5 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/80 flex items-center justify-between gap-2 sm:gap-4 shrink-0">
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <span class="text-lg sm:text-xl shrink-0">📑</span>
                            <div class="min-w-0">
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5 sm:gap-2">
                                    <span>BLOQUE #{{ $detailBlock->block_order }}</span>
                                    <span class="px-1.5 sm:px-2 py-0.5 rounded text-[9px] sm:text-[10px] font-mono bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 uppercase font-bold">
                                        Etapa: {{ $detailActiveTab }}
                                    </span>
                                </h3>
                                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                    Escena #{{ $detailBlock->scene?->scene_order }} · {{ $detailBlock->scene?->slugline }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                            <button @click="$dispatch('play-tts', { scope: 'block', blockId: '{{ $detailBlock->id }}' })"
                                    type="button"
                                    title="Escuchar locución de este bloque"
                                    class="px-2 sm:px-2.5 py-1.5 rounded-lg text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-800 transition flex items-center gap-1 cursor-pointer">
                                <span>🔊</span>
                                <span class="hidden sm:inline">Escuchar</span>
                            </button>
                            <button wire:click="openDeleteBlockModal('{{ $detailBlock->id }}')"
                                    type="button"
                                    title="Eliminar este bloque permanentemente..."
                                    class="px-2 sm:px-2.5 py-1.5 rounded-lg text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 border border-rose-200 dark:border-rose-800 transition flex items-center gap-1 cursor-pointer">
                                <span>🗑️</span>
                                <span class="hidden sm:inline">Eliminar</span>
                            </button>
                            <button wire:click="closeBlockDetails"
                                    type="button"
                                    title="Cerrar panel lateral"
                                    class="px-2 sm:px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-1 cursor-pointer">
                                <span>✕</span>
                                <span class="hidden sm:inline">Cerrar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Pestañas internas para navegar por todas las etapas del bloque -->
                    <div class="px-3 sm:px-6 pt-1 sm:pt-2 border-b border-slate-200 dark:border-slate-800 bg-slate-100/50 dark:bg-slate-950 flex items-center gap-1 sm:gap-2 overflow-x-auto shrink-0">
                        <button wire:click="setDetailTab('raw')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer {{ $detailActiveTab === 'raw' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            1. Idea / Bruto
                        </button>
                        <button wire:click="setDetailTab('structured')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer flex items-center gap-1 {{ $detailActiveTab === 'structured' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            <span>2. Tratamiento</span>
                            @if($detailBlock->isStageLocked('structured'))
                                <span class="text-xs" title="Tratamiento protegido contra sobreescritura (🔒)">🔒</span>
                            @endif
                        </button>
                        <button wire:click="setDetailTab('screenplay')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer flex items-center gap-1 {{ $detailActiveTab === 'screenplay' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            <span>3. Guión</span>
                            @if($detailBlock->isStageLocked('screenplay'))
                                <span class="text-xs" title="Guión literario protegido contra sobreescritura (🔒)">🔒</span>
                            @endif
                        </button>
                        <button wire:click="setDetailTab('shots')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer {{ $detailActiveTab === 'shots' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            4. Planos ({{ $detailBlock->shots->count() }})
                        </button>
                    </div>

                    <!-- Cuerpo scrolleable del Drawer -->
                    <div class="flex-1 overflow-y-auto p-3.5 sm:p-6 space-y-4 sm:space-y-6">
                        <!-- Alerta de Agujero de Guión si está marcado -->
                        @if($detailBlock->hasPlotHole())
                            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-3 shadow-xs">
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="text-xl shrink-0">🕳️</span>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold tracking-wide uppercase text-[11px] block text-rose-900 dark:text-rose-300">
                                                Agujero de Guión Detectado
                                            </span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-200/90 dark:bg-rose-900/80 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-700 uppercase">
                                                Detectado en: {{ $detailBlock->getPlotHoleStageLabel() }}
                                            </span>
                                        </div>
                                        <p class="leading-relaxed font-sans text-rose-800 dark:text-rose-200">
                                            {{ $detailBlock->plot_hole }}
                                        </p>
                                    </div>
                                </div>
                                <button wire:click="openJustifyModal('{{ $detailBlock->id }}')"
                                        type="button"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-white hover:bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:hover:bg-rose-800 dark:text-rose-100 border border-rose-300 dark:border-rose-700 transition shadow-2xs cursor-pointer flex items-center gap-1.5 shrink-0"
                                        title="Justificar por qué no es un agujero de guión y retirarlo">
                                    <span>✅</span>
                                    <span>Justificar</span>
                                </button>
                            </div>
                        @endif

                        <!-- Sección de Agujeros Justificados -->
                        @if($detailBlock->hasJustifiedPlotHoles())
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-3 shadow-2xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                        <span>✅</span>
                                        <span>Agujeros justificados ({{ count($detailBlock->getJustifiedPlotHoles()) }})</span>
                                    </span>
                                </div>
                                <div class="space-y-2.5">
                                    @foreach($detailBlock->getJustifiedPlotHoles() as $jHole)
                                        <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs space-y-1.5">
                                            <div class="text-slate-500 dark:text-slate-400 font-mono text-[10px] flex items-center justify-between gap-2 flex-wrap">
                                                <div class="flex items-center gap-1.5">
                                                    <span>🕳️ Motivo IA original:</span>
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-semibold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                        {{ $jHole['stage_label'] ?? 'Idea (Bruto)' }}
                                                    </span>
                                                </div>
                                                <span>{{ \Carbon\Carbon::parse($jHole['justified_at'])->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <p class="text-slate-600 dark:text-slate-400 italic">"{{ $jHole['hole_reason'] }}"</p>
                                            <div class="pt-1.5 border-t border-slate-100 dark:border-slate-800 text-emerald-800 dark:text-emerald-300">
                                                <span class="font-bold uppercase text-[10px] block">Motivo por el que no es un agujero:</span>
                                                <p class="text-slate-900 dark:text-slate-100 font-sans mt-0.5">{{ $jHole['justification'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Barra de Términos Fijos y Candados -->
                        <div class="p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-900 dark:text-amber-300 space-y-2">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <span class="font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🔒</span>
                                    <span>Términos Fijos:</span>
                                </span>
                                @if(!empty($detailLocks['pinned_terms']))
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @foreach($detailLocks['pinned_terms'] as $orig => $rep)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-[11px] font-mono">
                                                <span>{{ $orig }} ➜ {{ $rep }}</span>
                                                <button wire:click="removePinnedTerm('{{ $detailBlock->id }}', '{{ $orig }}')" class="text-rose-500 hover:text-rose-700 ml-1 cursor-pointer">✕</button>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 italic">Ningún término fijado en este bloque.</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 pt-1 border-t border-amber-200/50 dark:border-amber-500/10 flex-wrap">
                                <input type="text" wire:model="newPinnedTerm" placeholder="Término original (ej: Javi)" maxlength="80" class="text-xs px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white flex-1 min-w-[120px] focus:ring-1 focus:ring-amber-500" />
                                <span class="text-amber-700 dark:text-amber-400">➜</span>
                                <input type="text" wire:model="newPinnedReplacement" placeholder="Fijar como (ej: Javier)" maxlength="80" class="text-xs px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white flex-1 min-w-[120px] focus:ring-1 focus:ring-amber-500" />
                                <button type="button" wire:click="addPinnedTerm('{{ $detailBlock->id }}')" class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-2xs transition cursor-pointer">Fijar</button>
                            </div>
                        </div>

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
                                <div class="flex flex-wrap items-center justify-between gap-2.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
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
                                                <button wire:key="btn-action-structure-{{ $detailBlock->id }}"
                                                        wire:click="structureBlock('{{ $detailBlock->id }}')"
                                                        x-data="{ isProcessing: false }"
                                                        x-init="$watch('$wire.detailActiveTab', () => isProcessing = false)"
                                                        @click="isProcessing = true; $wire.structureBlock('{{ $detailBlock->id }}').then(() => { isProcessing = false; })"
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
                                                <button wire:key="btn-action-screenplay-{{ $detailBlock->id }}"
                                                        wire:click="formatBlockToScreenplay('{{ $detailBlock->id }}')"
                                                        x-data="{ isProcessing: false }"
                                                        x-init="$watch('$wire.detailActiveTab', () => isProcessing = false)"
                                                        @click="isProcessing = true; $wire.formatBlockToScreenplay('{{ $detailBlock->id }}').then(() => { isProcessing = false; })"
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
                                            <button wire:key="btn-action-shots-{{ $detailBlock->id }}"
                                                    wire:click="breakdownBlockShots('{{ $detailBlock->id }}')"
                                                    x-data="{ isProcessing: false }"
                                                    x-init="$watch('$wire.detailActiveTab', () => isProcessing = false)"
                                                    @click="isProcessing = true; $wire.breakdownBlockShots('{{ $detailBlock->id }}').then(() => { isProcessing = false; })"
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
                                        <!-- Barra de Estilo de Storyboard & Condicionamiento -->
                                        <div class="p-3 rounded-xl bg-slate-100/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-2">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                                                        🎨 Estilo Visual Storyboard (Google AI):
                                                    </span>
                                                    @php
                                                        $refCharCount = $project->characters->filter(fn($c) => $c->hasReferenceImage())->count();
                                                    @endphp
                                                    @if($refCharCount > 0)
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20" title="La IA condicionará la imagen según la ficha de modelo de personajes">
                                                            <span>👤</span> {{ $refCharCount }} modelo{{ $refCharCount > 1 ? 's' : '' }} turn-around
                                                        </span>
                                                    @endif
                                                </div>
                                                <!-- Selector de Estilos -->
                                                <div class="flex items-center gap-1 bg-white dark:bg-slate-950 p-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[11px] font-medium">
                                                    @foreach(\App\Services\StoryboardService::STYLES as $sKey => $sVal)
                                                        <button wire:click="setStoryboardStyle('{{ $sKey }}')"
                                                                type="button"
                                                                class="px-2.5 py-1 rounded transition cursor-pointer {{ $storyboardStyle === $sKey ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                                                            {{ $sVal['name'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">
                                                {{ \App\Services\StoryboardService::STYLES[$storyboardStyle]['description'] ?? '' }}
                                            </p>
                                        </div>

                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 block pt-1">
                                            4. Desglose Técnico de Planos y Viñetas:
                                        </span>
                                        @forelse($detailBlock->shots as $shot)
                                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                                                    <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-2 py-0.5 rounded text-xs">
                                                        --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                    </span>
                                                    <span class="text-xs text-slate-500 font-mono">{{ $shot->takes->count() }} tomas</span>
                                                </div>

                                                <!-- Viñeta de Storyboard -->
                                                @if($shot->hasStoryboard())
                                                    <div class="relative group rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-950 aspect-video shadow-md">
                                                        <img src="{{ $shot->storyboard_url }}"
                                                             alt="Storyboard shot {{ $shot->shot_number }}"
                                                             class="w-full h-full object-cover transition duration-300 group-hover:scale-102">
                                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-transparent to-transparent flex items-end justify-between p-3 opacity-90 group-hover:opacity-100 transition">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/70 text-amber-300 border border-amber-500/30 backdrop-blur-xs font-semibold">
                                                                    🎨 {{ \App\Services\StoryboardService::STYLES[$shot->storyboard_style]['name'] ?? 'Storyboard' }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button wire:click="openStoryboardPreview('{{ $shot->id }}')"
                                                                        type="button"
                                                                        title="Ampliar viñeta"
                                                                        class="px-2.5 py-1 rounded-lg bg-black/70 hover:bg-black/90 text-white text-xs border border-white/20 backdrop-blur-xs cursor-pointer flex items-center gap-1">
                                                                    <span>🔍</span> <span>Ampliar</span>
                                                                </button>
                                                                <button wire:click="generateShotStoryboard('{{ $shot->id }}')"
                                                                        wire:loading.attr="disabled"
                                                                        type="button"
                                                                        title="Regenerar con IA"
                                                                        class="px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold flex items-center gap-1 cursor-pointer shadow-xs transition">
                                                                    <span wire:loading.remove wire:target="generateShotStoryboard('{{ $shot->id }}')">🔄 Regenerar</span>
                                                                    <span wire:loading wire:target="generateShotStoryboard('{{ $shot->id }}')">⏳ Generando...</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="flex items-center justify-between p-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-900/60">
                                                         <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                             <span>🎨</span>
                                                             <span>Sin viñeta de storyboard generada</span>
                                                         </div>
                                                         <button wire:click="generateShotStoryboard('{{ $shot->id }}')"
                                                                 wire:loading.attr="disabled"
                                                                 type="button"
                                                                 class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs transition">
                                                             <span wire:loading.remove wire:target="generateShotStoryboard('{{ $shot->id }}')">✨ Generar Storyboard (IA)</span>
                                                             <span wire:loading wire:target="generateShotStoryboard('{{ $shot->id }}')">⏳ Generando viñeta con Google AI...</span>
                                                         </button>
                                                    </div>
                                                @endif

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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/50 backdrop-blur-xs transition">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-4 sm:p-6 w-full max-w-lg max-h-[92vh] flex flex-col overflow-y-auto space-y-4 transition">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.set('showNewSceneModal', false)"
             wire:click.self="$set('showNewSceneModal', false)">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-4 sm:p-6 w-full max-w-md max-h-[92vh] flex flex-col overflow-y-auto space-y-4">
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

    <!-- ================= MODAL EDITAR ESCENA ================= -->
    @if($showEditSceneModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.closeEditSceneModal()"
             wire:click.self="closeEditSceneModal">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-4 sm:p-6 w-full max-w-md max-h-[92vh] flex flex-col overflow-y-auto space-y-4"
                 @click.stop>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🎬</span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            Editar Escena #{{ $editingSceneOrder }}
                        </h3>
                    </div>
                    <button wire:click="closeEditSceneModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-mono font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Nombre de la Escena / Encabezado (Slugline) *
                        </label>
                        <input type="text"
                               wire:model="editingSceneSlugline"
                               placeholder="INT. DESPACHO - DÍA 1"
                               class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono uppercase focus:ring-2 focus:ring-amber-500 focus:outline-hidden">
                        @error('editingSceneSlugline') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-mono font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Momento del Día *
                        </label>
                        <select wire:model="editingSceneTimeOfDay" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono">
                            <option value="DÍA">DÍA</option>
                            <option value="NOCHE">NOCHE</option>
                            <option value="ATARDECER">ATARDECER</option>
                            <option value="AMANECER">AMANECER</option>
                            <option value="CONTINUO">CONTINUO</option>
                        </select>
                        @error('editingSceneTimeOfDay') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-mono font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Localización de la Biblia (Opcional)
                        </label>
                        <select wire:model="editingSceneLocationId" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono">
                            <option value="">-- Sin asignar --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="closeEditSceneModal" type="button" class="px-3.5 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="updateScene" type="button" class="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition cursor-pointer">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL ELIMINAR BLOQUE ================= -->
    @if($showDeleteBlockModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.closeDeleteBlockModal()"
             wire:click.self="closeDeleteBlockModal">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-4 sm:p-6 w-full max-w-md max-h-[92vh] flex flex-col overflow-y-auto space-y-4"
                 @click.stop>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                        <span class="text-base">🗑️</span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            Eliminar Bloque #{{ $deletingBlockOrder }}
                        </h3>
                    </div>
                    <button wire:click="closeDeleteBlockModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold cursor-pointer">✕</button>
                </div>

                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-800 dark:text-rose-300 text-xs space-y-2">
                    <p class="font-bold flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Esta acción no se puede deshacer</span>
                    </p>
                    <p>
                        Los datos de este bloque serán eliminados permanentemente y no podrán ser recuperados. Se guardará un registro de este momento en el historial de trazabilidad del proyecto.
                    </p>
                    @if($deletingBlockPreview)
                        <div class="mt-2 p-2 rounded bg-white/60 dark:bg-slate-950/60 border border-rose-500/20 text-slate-700 dark:text-slate-300 font-mono text-[11px] italic truncate">
                            "{{ $deletingBlockPreview }}"
                        </div>
                    @endif
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeDeleteBlockModal" type="button" class="px-3.5 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                        Cancelar
                    </button>
                    <button wire:click="confirmDeleteBlock" type="button" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer">
                        Confirmar y Eliminar Bloque
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL ELIMINAR ESCENA ================= -->
    @if($showDeleteSceneModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.closeDeleteSceneModal()"
             wire:click.self="closeDeleteSceneModal">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-4 sm:p-6 w-full max-w-md max-h-[92vh] flex flex-col overflow-y-auto space-y-4"
                 @click.stop>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                        <span class="text-base">🎬</span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            Eliminar Escena #{{ $deletingSceneOrder }}
                        </h3>
                    </div>
                    <button wire:click="closeDeleteSceneModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold cursor-pointer">✕</button>
                </div>

                @if($deletingSceneBlockCount > 0)
                    <!-- Caso 1: La escena contiene bloques -> PROHIBIDO borrar según regla de negocio -->
                    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 text-xs space-y-2">
                        <p class="font-bold flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                            <span>🚫</span>
                            <span>No se puede eliminar la escena</span>
                        </p>
                        <p>
                            Para borrar una escena, primero no debe haber ningún bloque en ella. Esta escena contiene actualmente <strong class="font-bold underline">{{ $deletingSceneBlockCount }} bloque(s)</strong> de guión.
                        </p>
                        <p class="text-[11px] text-amber-700/80 dark:text-amber-300/80">
                            Debes eliminar primero todos sus bloques para poder proceder al borrado de la escena.
                        </p>
                    </div>

                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="closeDeleteSceneModal" type="button" class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white font-bold text-xs rounded-xl transition cursor-pointer">
                            Entendido
                        </button>
                    </div>
                @else
                    <!-- Caso 2: La escena no tiene bloques -> Confirmación irreversible -->
                    <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-800 dark:text-rose-300 text-xs space-y-2">
                        <p class="font-bold flex items-center gap-1.5">
                            <span>⚠️</span>
                            <span>Esta acción no se puede deshacer</span>
                        </p>
                        <p>
                            ¿Estás seguro de que deseas eliminar permanentemente la Escena #{{ $deletingSceneOrder }} (<strong>{{ $deletingSceneSlugline }}</strong>)?
                        </p>
                        <p>
                            Los datos de esta escena no podrán ser recuperados. Se guardará un registro de este momento en el historial de trazabilidad del proyecto y se actualizará la Biblia.
                        </p>
                    </div>

                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="closeDeleteSceneModal" type="button" class="px-3.5 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmDeleteScene" type="button" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer">
                            Confirmar y Eliminar Escena
                        </button>
                    </div>
                @endif
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

    <!-- ================= MODAL PARA JUSTIFICAR Y DESCARTAR AGUJERO DE GUIÓN ================= -->
    @if($showJustifyModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.closeJustifyModal()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="closeJustifyModal"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative w-full max-w-xl max-h-[92vh] flex flex-col overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-6 space-y-4 sm:space-y-5"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">✅</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Justificar Agujero de Guión
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Explica el motivo por el cual no es una inconsistencia para retirarlo y archivarlo en Agujeros justificados.
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeJustifyModal" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Motivo detectado por la IA -->
                    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-800 dark:text-rose-200 space-y-1">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-rose-900 dark:text-rose-300 block">
                            Inconsistencia reportada por la IA:
                        </span>
                        <p class="font-sans leading-relaxed">
                            {{ $justifyingHoleReason }}
                        </p>
                    </div>

                    <!-- Campo de justificación del usuario -->
                    <div class="space-y-1.5">
                        <label for="justificationText" class="block text-xs font-bold font-mono text-slate-800 dark:text-slate-200 uppercase">
                            Motivo por el que no es un agujero de guión (Obligatorio):
                        </label>
                        <textarea id="justificationText"
                                  wire:model="justificationText"
                                  rows="4"
                                  placeholder="Ejemplo: Pedro no está en el bar porque sigue en el coche esperando a Javier, como se describe en la acción previa..."
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs leading-relaxed focus:ring-2 focus:ring-emerald-500 focus:outline-hidden"></textarea>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button wire:click="closeJustifyModal"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="savePlotHoleJustification"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span>✅</span>
                            <span>Confirmar Justificación</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL CONFIRMAR COSTE EN TOKENS ANTES DE AUDITAR AGUJEROS DE GUIÓN ================= -->
    @if($showPlotHoleConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.cancelAnalyzePlotHoles()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="cancelAnalyzePlotHoles"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🕳️</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Auditoría de Agujeros de Guión
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Confirmación previa de coste en tokens de IA
                                </p>
                            </div>
                        </div>
                        <button wire:click="cancelAnalyzePlotHoles" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Mensaje explicativo -->
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        El script analizará los <strong class="font-bold text-slate-900 dark:text-white">{{ $estimatedBlocksToAudit }} bloque(s)</strong> con contenido de tu proyecto a través de las 3 etapas (Idea, Tratamiento y Guion), contrastando coherencia narrativa, personajes y localizaciones con la Biblia de Proyecto.
                    </p>

                    <!-- Tarjeta de Desglose de Tokens -->
                    @php
                        $currentUser = auth()->user() ?? $project->user;
                        $userTokensAvail = $currentUser ? $currentUser->getTokensAvailableToday() : 0;
                        $hasEnough = $userTokensAvail >= $estimatedPlotHoleTokens;
                        $remainingAfter = max(0, $userTokensAvail - $estimatedPlotHoleTokens);
                    @endphp
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Bloques a auditar:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $estimatedBlocksToAudit }} bloques</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Coste estimado en tokens:</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">~{{ number_format($estimatedPlotHoleTokens, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-800/80 pt-2 flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Saldo disponible hoy:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userTokensAvail, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="flex items-center justify-between {{ $hasEnough ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            <span class="font-medium">Saldo tras ejecución:</span>
                            <span class="font-bold">~{{ number_format($remainingAfter, 0, ',', '.') }} tokens</span>
                        </div>
                    </div>

                    @if(! $hasEnough)
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2">
                            <span>⚠️</span>
                            <span>No dispones de suficientes tokens diarios para auditar todos los bloques. Tu saldo se renovará automáticamente a las 00:00.</span>
                        </div>
                    @endif

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button wire:click="cancelAnalyzePlotHoles"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmAnalyzePlotHoles"
                                wire:loading.attr="disabled"
                                @if(! $hasEnough) disabled @endif
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold {{ $hasEnough ? 'bg-amber-500 hover:bg-amber-400 text-slate-950' : 'bg-slate-300 text-slate-500 cursor-not-allowed' }} shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span wire:loading.remove wire:target="confirmAnalyzePlotHoles">🕳️ Confirmar y Auditar</span>
                            <span wire:loading wire:target="confirmAnalyzePlotHoles" class="flex items-center gap-1.5">
                                <span class="animate-spin text-xs">⏳</span>
                                <span>Auditando con IA...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL HISTORIAL DE ACTIVIDAD Y TRAZABILIDAD COMPLETA ================= -->
    @if($showActivityHistoryModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.closeActivityHistoryModal()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="closeActivityHistoryModal"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-2.5 sm:p-4">
                <div class="relative w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-3.5 sm:p-6 space-y-3 sm:space-y-4 max-h-[90vh] flex flex-col"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                        <div class="flex items-center gap-2 sm:gap-2.5">
                            <span class="text-xl">📜</span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>Historial de Actividad del Proyecto</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-300 border border-sky-300 dark:border-sky-800">
                                        {{ $activityLogs->count() }} eventos
                                    </span>
                                </h3>
                                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                                    Trazabilidad cronológica inversa de todas las acciones del usuario.
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeActivityHistoryModal" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Lista con scroll vertical en orden cronológico inverso -->
                    <div class="flex-1 overflow-y-auto pr-1 space-y-2.5 max-h-[60vh] sm:max-h-[64vh]">
                        @forelse($activityLogs as $log)
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
                        @empty
                            <div class="p-8 text-center text-slate-400 font-mono text-xs">
                                <span>No hay acciones registradas en el historial todavía.</span>
                            </div>
                        @endforelse
                    </div>

                    <!-- Pie del modal -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-[11px] font-mono text-slate-400">
                            Base para futura función "Evolución de la Historia"
                        </span>
                        <button wire:click="closeActivityHistoryModal"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-slate-200 dark:text-slate-900 transition cursor-pointer">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL: MODIFICAR NOMBRE Y DETALLES DEL PROYECTO ================= -->
    @if($showEditTitleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✏️</span>
                        <span>Modificar Nombre de la Obra</span>
                    </h3>
                    <button wire:click="$set('showEditTitleModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                <form wire:submit="updateProjectTitle" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="projectTitle"
                               placeholder="Ej: El Laberinto de las Sombras"
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                        @error('projectTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis</label>
                        <textarea wire:model="projectSynopsis"
                                  rows="3"
                                  placeholder="Sinopsis o premisa dramática..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                        @error('projectSynopsis') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showEditTitleModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            Guardar Título
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ================= MODAL: RENDERIZAR EN LOTE ("RENDERIZAR TODO DE GOLPE") ================= -->
    @if($showBatchModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⚡</span>
                        <span>Renderizar / Procesar en Lote</span>
                    </h3>
                    <button wire:click="$set('showBatchModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                <div class="mt-4 space-y-4 text-xs font-sans">
                    <p class="text-slate-600 dark:text-slate-300">
                        Ejecuta de forma secuencial y automatizada el pipeline cinematográfico. Se respetarán estrictamente todos los bloques protegidos con candado (🔒).
                    </p>

                    <!-- Selector de Alcance -->
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Alcance del Procesamiento</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                    wire:click="$set('batchScope', 'scene')"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $batchScope === 'scene' ? 'border-amber-500 bg-amber-500/10 text-amber-900 dark:text-amber-200 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <span class="block font-bold">🎬 Escena Activa</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400">Solo la escena seleccionada</span>
                            </button>
                            <button type="button"
                                    wire:click="$set('batchScope', 'project')"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $batchScope === 'project' ? 'border-amber-500 bg-amber-500/10 text-amber-900 dark:text-amber-200 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <span class="block font-bold">🌐 Todo el Proyecto</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400">Todas las escenas y bloques</span>
                            </button>
                        </div>
                    </div>

                    <!-- Selector de Etapa -->
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Etapa a Renderizar</label>
                        <div class="space-y-1.5">
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="all" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">🚀 Pipeline Completo (Recomendado)</span>
                                    <span class="block text-[11px] text-slate-500">Estructura brutos ➔ Formatea a guion ➔ Desglosa planos técnicos</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="structured" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">📝 Brutos ➔ Tratamiento</span>
                                    <span class="block text-[11px] text-slate-500">Estructura todos los textos brutos a prosa dramática</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="screenplay" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">📜 Tratamientos ➔ Guion Literario</span>
                                    <span class="block text-[11px] text-slate-500">Convierte los tratamientos a formato canónico Fountain</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="shots" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">🎥 Guiones ➔ Planos Técnicos</span>
                                    <span class="block text-[11px] text-slate-500">Desglosa los guiones en planos para claqueta y rodaje</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showBatchModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button"
                                wire:click="executeBatchProcessing"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            <span wire:loading.remove wire:target="executeBatchProcessing">⚡ Iniciar Renderizado en Lote</span>
                            <span wire:loading wire:target="executeBatchProcessing" class="flex items-center gap-1.5">
                                <span class="animate-spin text-sm">⏳</span>
                                <span>Procesando pipeline...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL: SUBIDA E IMPORTACIÓN DE GUIÓN (CON AVISO DESTRUCTIVO) ================= -->
    @if($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-2xl w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📥</span>
                        <span>Importar Guión (Reemplazo de Proyecto)</span>
                    </h3>
                    <button wire:click="$set('showImportModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                @if($importStep === 'upload')
                    <form wire:submit="processImportFile" class="mt-4 space-y-4">
                        <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-8 text-center bg-slate-50/50 dark:bg-slate-950/50 hover:border-amber-500/70 transition">
                            <span class="text-4xl block mb-2">📄</span>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1 cursor-pointer">
                                <span>Selecciona un archivo de guión (.txt o .pdf)</span>
                                <input type="file"
                                       wire:model="importFile"
                                       accept=".txt,.pdf"
                                       class="sr-only">
                            </label>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Detección automática de encabezados de escena (INT./EXT.), personajes y diálogos. Máx. 10 MB.
                            </p>

                            @if($importFile)
                                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs font-mono">
                                    <span>📎 {{ $importFile->getClientOriginalName() }}</span>
                                    <span>({{ number_format($importFile->getSize() / 1024, 1) }} KB)</span>
                                </div>
                            @endif

                            @error('importFile')
                                <span class="text-rose-500 dark:text-rose-400 text-xs mt-2 block font-medium">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <div>
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">Asistencia Cognitiva jg_plugin_filmmaker</span>
                                <span class="block text-[11px] text-slate-500">Refinar detección de escenas y personajes con IA si el guion no tiene formato estricto.</span>
                            </div>
                            <input type="checkbox" wire:model="useAiForImport" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 h-4 w-4">
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="button"
                                    wire:click="$set('showImportModal', false)"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                                <span wire:loading.remove wire:target="processImportFile">Analizar Guión</span>
                                <span wire:loading wire:target="processImportFile">Extrayendo texto...</span>
                            </button>
                        </div>
                    </form>
                @elseif($importStep === 'preview')
                    <div class="mt-4 space-y-4">
                        <!-- AVISO DESTRUCTIVO DESTACADO -->
                        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border-2 border-rose-500 text-rose-800 dark:text-rose-200">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl shrink-0">⚠️</span>
                                <div class="text-xs space-y-1">
                                    <span class="block font-bold text-sm uppercase tracking-wide text-rose-700 dark:text-rose-300">
                                        Atención: Reemplazo Completo del Proyecto Actual
                                    </span>
                                    <p>
                                        Si confirmas la importación, <strong>se sobreescribirá por completo el guion, todas las escenas, bloques de texto y tomas de rodaje</strong> de la obra actual <em>"{{ $project->title }}"</em>.
                                    </p>
                                    <p class="font-semibold text-rose-600 dark:text-rose-400">
                                        Esta acción no se puede deshacer. Se reemplazará con el contenido detectado a continuación.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen del Análisis -->
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block">Escenas detectadas</span>
                                <span class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">{{ $parsedImportData['scenes_count'] ?? 0 }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-blue-500/10 border border-blue-500/30">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block">Personajes detectados</span>
                                <span class="text-xl font-bold font-mono text-blue-600 dark:text-blue-400">{{ count($parsedImportData['characters'] ?? []) }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block">Locaciones detectadas</span>
                                <span class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ count($parsedImportData['locations'] ?? []) }}</span>
                            </div>
                        </div>

                        <!-- Previsualización de Escenas -->
                        <div>
                            <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Escenas que se importarán</span>
                            <div class="max-h-48 overflow-y-auto space-y-1.5 p-2 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs">
                                @forelse($parsedImportData['scenes'] ?? [] as $sc)
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">#{{ $sc['scene_order'] }} {{ $sc['slugline'] }}</span>
                                        <span class="text-[11px] text-slate-500">{{ count($sc['blocks'] ?? []) }} bloques</span>
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-slate-400">No se detectaron escenas</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Personajes Detectados -->
                        @if(! empty($parsedImportData['characters']))
                            <div>
                                <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Personajes detectados para la Biblia</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($parsedImportData['characters'] as $char)
                                        <span class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20 text-xs font-mono font-bold">
                                            {{ $char }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Checkbox de Confirmación Explícita -->
                        <div class="p-3 rounded-xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="confirmDestructiveImport"
                                       class="rounded border-rose-300 text-rose-600 focus:ring-rose-500 h-4 w-4 shrink-0">
                                <span class="text-xs font-semibold text-rose-900 dark:text-rose-200">
                                    Confirmo que deseo sobreescribir y reemplazar todo el guion actual de "{{ $project->title }}".
                                </span>
                            </label>
                            @error('confirmDestructiveImport')
                                <span class="text-rose-600 dark:text-rose-400 text-xs mt-1 block font-bold">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="pt-4 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
                            <button type="button"
                                    wire:click="$set('importStep', 'upload')"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                                ⬅️ Volver a subir
                            </button>
                            <button type="button"
                                    wire:click="confirmAndExecuteDestructiveImport"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-500 text-white font-bold shadow-lg shadow-rose-600/30 transition cursor-pointer">
                                <span wire:loading.remove wire:target="confirmAndExecuteDestructiveImport">🔥 Sobreescribir e Importar</span>
                                <span wire:loading wire:target="confirmAndExecuteDestructiveImport">Importando guión...</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ================= REPRODUCTOR FLOTANTE DE LOCUCIÓN TTS (SPEACHES-AI) ================= -->
    <div x-data="ttsPlayer({ projectId: '{{ $project->id }}' })"
         x-on:play-tts.window="startPlayback($event.detail)"
         x-on:stop-tts.window="stopPlayback()"
         x-show="isPlaying || isLoading"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[9999] w-[94vw] max-w-2xl bg-slate-900/95 dark:bg-slate-950/95 backdrop-blur-md border border-amber-500/40 rounded-2xl shadow-2xl p-3 sm:p-4 text-white font-mono"
         style="display: none;">

        <div class="flex items-center justify-between gap-3">
            <!-- Info del segmento actual -->
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-full bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                    <template x-if="isLoading">
                        <span class="animate-spin text-sm">⏳</span>
                    </template>
                    <template x-if="!isLoading && !isPaused">
                        <span class="animate-pulse text-sm">🔊</span>
                    </template>
                    <template x-if="!isLoading && isPaused">
                        <span class="text-sm">⏸️</span>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider"
                              :class="currentSpeaker === 'NARRADOR' ? 'bg-slate-800 text-slate-300 border border-slate-700' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                              x-text="currentSpeaker">
                        </span>
                        <span class="text-[11px] text-slate-400 font-sans" x-text="progressText"></span>
                    </div>
                    <p class="text-xs text-slate-200 truncate mt-0.5 font-sans italic" x-text="currentText || 'Cargando locución...'"></p>
                </div>
            </div>

            <!-- Controles de Reproducción -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Anterior -->
                <button @click="prevSegment()"
                        :disabled="currentIndex <= 0 || isLoading"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                        title="Segmento anterior">
                    ⏮️
                </button>

                <!-- Play / Pausa -->
                <button @click="togglePlayPause()"
                        :disabled="isLoading"
                        type="button"
                        class="px-2.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition cursor-pointer flex items-center gap-1 shadow-md">
                    <template x-if="isPaused">
                        <span>▶️ Reanudar</span>
                    </template>
                    <template x-if="!isPaused">
                        <span>⏸️ Pausa</span>
                    </template>
                </button>

                <!-- Siguiente -->
                <button @click="nextSegment()"
                        :disabled="currentIndex >= manifest.length - 1 || isLoading"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                        title="Siguiente segmento">
                    ⏭️
                </button>

                <!-- Selector de Velocidad -->
                <button @click="cycleSpeed()"
                        type="button"
                        class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 text-[11px] font-bold border border-slate-700 transition cursor-pointer"
                        title="Cambiar velocidad de reproducción">
                    <span x-text="speed + 'x'"></span>
                </button>

                <!-- Detener / Cerrar -->
                <button @click="stopPlayback()"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition cursor-pointer ml-1"
                        title="Cerrar y detener reproducción">
                    ✕
                </button>
            </div>
        </div>
    </div>

    <!-- ================= MODAL LIGHTBOX DE STORYBOARD ================= -->
    @if(!empty($previewStoryboardUrl))
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm transition"
             wire:click.self="closeStoryboardPreview">
            <div class="relative max-w-4xl w-full bg-slate-900 border border-slate-700 rounded-2xl overflow-hidden shadow-2xl">
                <div class="flex items-center justify-between px-4 py-3 bg-slate-950/90 border-b border-slate-800">
                    <span class="text-xs font-mono font-bold text-slate-200">
                        🎨 {{ $previewStoryboardTitle ?? 'Storyboard' }}
                    </span>
                    <button wire:click="closeStoryboardPreview"
                            type="button"
                            class="text-slate-400 hover:text-white text-sm font-bold p-1 cursor-pointer">
                        ✕
                    </button>
                </div>
                <div class="p-2 flex items-center justify-center bg-black aspect-video max-h-[80vh]">
                    <img src="{{ $previewStoryboardUrl }}"
                         alt="Previsualización de Storyboard"
                         class="w-full h-full object-contain">
                </div>
            </div>
        </div>
    @endif

    <script>
        function ttsPlayer({ projectId }) {
            return {
                projectId: projectId,
                isPlaying: false,
                isPaused: false,
                isLoading: false,
                manifest: [],
                currentIndex: 0,
                currentSpeaker: '',
                currentText: '',
                currentBlockId: null,
                speed: 1.0,
                audio: null,
                audioCache: {},
                abortController: null,
                isPrefetching: false,
                prefetchAheadCount: 2,

                get progressText() {
                    if (!this.manifest.length) return '';
                    return `(${this.currentIndex + 1} de ${this.manifest.length})`;
                },

                async startPlayback(detail) {
                    const scope = detail.scope || 'project';
                    const sceneId = detail.sceneId || '';
                    const blockId = detail.blockId || '';

                    this.stopPlayback();
                    this.isLoading = true;
                    this.isPlaying = true;
                    this.isPaused = false;
                    this.manifest = [];
                    this.currentIndex = 0;
                    this.audioCache = {};
                    this.abortController = new AbortController();

                    try {
                        const url = new URL(`/projects/${this.projectId}/tts/manifest`, window.location.origin);
                        if (sceneId) url.searchParams.set('scene_id', sceneId);
                        if (blockId) url.searchParams.set('block_id', blockId);

                        const res = await fetch(url.toString(), {
                            headers: { 'Accept': 'application/json' },
                            signal: this.abortController.signal
                        });

                        if (!res.ok) {
                            throw new Error('Error al generar manifiesto de lectura: ' + res.status);
                        }

                        const data = await res.json();
                        if (!data.manifest || data.manifest.length === 0) {
                            alert('No hay texto disponible para locutar en este elemento.');
                            this.stopPlayback();
                            return;
                        }

                        this.manifest = data.manifest;
                        this.playSegment(0);
                    } catch (e) {
                        if (e.name !== 'AbortError') {
                            console.error('TTS playback error:', e);
                            alert('No se pudo iniciar la locución: ' + e.message);
                        }
                        this.stopPlayback();
                    } finally {
                        this.isLoading = false;
                    }
                },

                async preloadSegment(index) {
                    if (index < 0 || index >= this.manifest.length) return null;
                    if (this.audioCache[index]) {
                        return this.audioCache[index].promise;
                    }

                    const seg = this.manifest[index];
                    const streamUrl = new URL(seg.stream_url, window.location.origin);
                    streamUrl.searchParams.set('speed', this.speed);

                    const entry = {
                        status: 'loading',
                        blobUrl: null,
                        promise: null
                    };

                    entry.promise = fetch(streamUrl.toString(), {
                        headers: { 'Accept': 'audio/wav, audio/*' },
                        signal: this.abortController ? this.abortController.signal : undefined
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.blob();
                    })
                    .then(blob => {
                        entry.blobUrl = URL.createObjectURL(blob);
                        entry.status = 'ready';
                        return entry.blobUrl;
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            console.warn(`[TTS] Error precargando segmento #${index}:`, err);
                        }
                        entry.status = 'error';
                        return null;
                    });

                    this.audioCache[index] = entry;
                    return entry.promise;
                },

                async queuePrefetch(fromIndex) {
                    if (this.isPrefetching) return;
                    this.isPrefetching = true;
                    try {
                        for (let offset = 1; offset <= this.prefetchAheadCount; offset++) {
                            const targetIndex = fromIndex + offset;
                            if (targetIndex >= this.manifest.length) break;
                            if (!this.isPlaying) break;
                            await this.preloadSegment(targetIndex);
                        }
                    } finally {
                        this.isPrefetching = false;
                    }
                    this.pruneOldCache(fromIndex);
                },

                pruneOldCache(currentIndex) {
                    Object.keys(this.audioCache).forEach(key => {
                        const idx = parseInt(key, 10);
                        if (idx < currentIndex - 1) {
                            const entry = this.audioCache[idx];
                            if (entry && entry.blobUrl) {
                                URL.revokeObjectURL(entry.blobUrl);
                            }
                            delete this.audioCache[idx];
                        }
                    });
                },

                async playSegment(index) {
                    if (index < 0 || index >= this.manifest.length) {
                        this.stopPlayback();
                        return;
                    }

                    this.currentIndex = index;
                    const seg = this.manifest[index];
                    this.currentSpeaker = seg.speaker || 'NARRADOR';
                    this.currentText = seg.text || '';
                    this.currentBlockId = seg.block_id || null;

                    // Resaltar el bloque actual en pantalla y hacer scroll hacia él
                    this.highlightBlock(this.currentBlockId);

                    if (this.audio) {
                        this.audio.pause();
                        this.audio = null;
                    }

                    // Iniciar de inmediato la precarga anticipada del siguiente segmento en segundo plano
                    this.queuePrefetch(index);

                    // Resolver fuente de audio (blob precargado o fallback directo)
                    let audioSrc = null;
                    const cached = this.audioCache[index];
                    if (cached) {
                        if (cached.status === 'ready' && cached.blobUrl) {
                            audioSrc = cached.blobUrl;
                        } else if (cached.status === 'loading' && cached.promise) {
                            audioSrc = await cached.promise;
                        }
                    }

                    // Fallback a URL directa si no existía precarga o si falló
                    if (!audioSrc) {
                        const streamUrl = new URL(seg.stream_url, window.location.origin);
                        streamUrl.searchParams.set('speed', this.speed);
                        audioSrc = streamUrl.toString();
                    }

                    // Si durante la espera el usuario detuvo o cambió de segmento, no reproducir
                    if (!this.isPlaying || this.currentIndex !== index) {
                        return;
                    }

                    this.audio = new Audio(audioSrc);
                    this.audio.playbackRate = this.speed;

                    this.audio.onended = () => {
                        this.playSegment(this.currentIndex + 1);
                    };

                    this.audio.onerror = (e) => {
                        console.error('Audio stream error:', e);
                        this.playSegment(this.currentIndex + 1);
                    };

                    this.audio.play().catch(err => {
                        console.warn('Autoplay prevented or interrupted:', err);
                    });
                    this.isPaused = false;
                },

                togglePlayPause() {
                    if (!this.audio) return;
                    if (this.audio.paused) {
                        this.audio.play();
                        this.isPaused = false;
                    } else {
                        this.audio.pause();
                        this.isPaused = true;
                    }
                },

                nextSegment() {
                    if (this.currentIndex < this.manifest.length - 1) {
                        this.playSegment(this.currentIndex + 1);
                    }
                },

                prevSegment() {
                    if (this.currentIndex > 0) {
                        this.playSegment(this.currentIndex - 1);
                    }
                },

                cycleSpeed() {
                    const speeds = [1.0, 1.25, 1.5];
                    const nextIdx = (speeds.indexOf(this.speed) + 1) % speeds.length;
                    this.speed = speeds[nextIdx];
                    if (this.audio) {
                        this.audio.playbackRate = this.speed;
                    }
                    // Limpiar caché de blobs con velocidad previa y precargar con la nueva
                    Object.values(this.audioCache).forEach(entry => {
                        if (entry && entry.blobUrl) {
                            URL.revokeObjectURL(entry.blobUrl);
                        }
                    });
                    this.audioCache = {};
                    this.queuePrefetch(this.currentIndex);
                },

                stopPlayback() {
                    if (this.abortController) {
                        this.abortController.abort();
                        this.abortController = null;
                    }
                    if (this.audio) {
                        this.audio.pause();
                        this.audio = null;
                    }
                    Object.values(this.audioCache).forEach(entry => {
                        if (entry && entry.blobUrl) {
                            URL.revokeObjectURL(entry.blobUrl);
                        }
                    });
                    this.audioCache = {};
                    this.isPlaying = false;
                    this.isPaused = false;
                    this.isLoading = false;
                    this.manifest = [];
                    this.currentIndex = 0;
                    this.clearHighlight();
                },

                highlightBlock(blockId) {
                    this.clearHighlight();
                    if (!blockId) return;

                    const el = document.getElementById('block-' + blockId);
                    if (el) {
                        el.classList.add('tts-active-block', 'ring-2', 'ring-indigo-500', 'bg-indigo-500/10', 'dark:bg-indigo-950/40');
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                },

                clearHighlight() {
                    document.querySelectorAll('.tts-active-block').forEach(el => {
                        el.classList.remove('tts-active-block', 'ring-2', 'ring-indigo-500', 'bg-indigo-500/10', 'dark:bg-indigo-950/40');
                    });
                }
            };
        }

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
</div>


