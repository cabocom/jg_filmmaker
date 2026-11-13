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

                    @include('livewire.studio.partials.editor.scenes.scene-card-header', ['scene' => $scene, 'isExpanded' => $isExpanded, 'sceneStage' => $sceneStage])
                    @include('livewire.studio.partials.editor.scenes.scene-card-blocks', ['scene' => $scene, 'isExpanded' => $isExpanded, 'sceneStage' => $sceneStage])
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
