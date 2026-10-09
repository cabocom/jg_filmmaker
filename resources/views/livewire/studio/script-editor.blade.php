<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Barra Superior de Navegación de Escenas -->
    <div class="bg-white/90 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm dark:shadow-xl backdrop-blur-sm flex flex-col md:flex-row md:items-center justify-between gap-4 transition-colors">
        <!-- Selector de Escena Activa -->
        <div class="flex items-center gap-3 overflow-x-auto pb-1 md:pb-0 scrollbar-thin">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 shrink-0">Escenas:</span>
            <div class="flex items-center gap-2">
                @forelse($scenes as $scene)
                    <button wire:click="selectScene('{{ $scene->id }}')"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border {{ $activeSceneId === $scene->id ? 'bg-amber-500 text-slate-950 font-bold border-amber-400 shadow-md shadow-amber-950/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:border-slate-800' }}">
                        <span>#{{ $scene->scene_order }}</span>
                        <span class="truncate max-w-[150px] sm:max-w-[200px]">{{ $scene->slugline }}</span>
                    </button>
                @empty
                    <span class="text-xs text-slate-400 italic">No hay escenas creadas todavía.</span>
                @endforelse

                <button wire:click="$set('showNewSceneModal', true)"
                        type="button"
                        class="px-2.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-amber-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 transition flex items-center gap-1 shrink-0"
                        title="Añadir Escena">
                    <span>+</span>
                    <span>Nueva Escena</span>
                </button>
            </div>
        </div>

        <!-- Filtros de Vista del Pipeline y Acciones -->
        <div class="flex items-center gap-2 shrink-0 self-end md:self-auto">
            <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-950 p-1 border border-slate-200 dark:border-slate-800 text-xs">
                <button wire:click="$set('viewMode', 'all')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'all' ? 'bg-white dark:bg-slate-800 text-amber-700 dark:text-amber-300 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Pipeline
                </button>
                <button wire:click="$set('viewMode', 'screenplay')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'screenplay' ? 'bg-white dark:bg-slate-800 text-amber-700 dark:text-amber-300 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Guión
                </button>
                <button wire:click="$set('viewMode', 'structured')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'structured' ? 'bg-white dark:bg-slate-800 text-amber-700 dark:text-amber-300 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Tratamiento
                </button>
                <button wire:click="$set('viewMode', 'shots')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'shots' ? 'bg-white dark:bg-slate-800 text-amber-700 dark:text-amber-300 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    Planos / Tomas
                </button>
                <button wire:click="$set('viewMode', 'timeline')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'timeline' ? 'bg-white dark:bg-slate-800 text-amber-700 dark:text-amber-300 font-semibold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                    🎬 Montaje
                </button>
            </div>

            <button wire:click="addBlock"
                    type="button"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:bg-amber-500/20 dark:hover:bg-amber-500/30 dark:text-amber-300 border border-amber-500/30 transition flex items-center gap-1.5">
                <span>+</span>
                <span>Añadir Bloque</span>
            </button>
        </div>
    </div>

    <!-- Mensajes Flash -->
    @if(session()->has('success'))
        <div class="p-3.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Escena Activa -->
    @if($activeScene)
        <!-- Encabezado Cinematográfico de la Escena (Slugline) -->
        <div class="bg-white dark:bg-gradient-to-r dark:from-slate-900 dark:via-slate-900 dark:to-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm dark:shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-3 transition-colors">
            <div>
                <div class="flex items-center gap-2 text-xs font-mono text-amber-600 dark:text-amber-400 mb-1">
                    <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 font-bold">ESCENA {{ $activeScene->scene_order }}</span>
                    <span>•</span>
                    <span class="text-slate-500 dark:text-slate-400">{{ $activeScene->time_of_day }}</span>
                    @if($activeScene->location)
                        <span>•</span>
                        <span class="text-slate-700 dark:text-slate-300 font-bold">{{ $activeScene->location->name }} ({{ $activeScene->location->type }})</span>
                    @endif
                </div>
                <h2 class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white uppercase">
                    {{ $activeScene->slugline }}
                </h2>
            </div>
        </div>

        @if($viewMode === 'timeline')
            <!-- Subvista de Montaje / Pre-corte de Edición (RoughCutTimeline) -->
            <livewire:studio.rough-cut-timeline :project="$project" :active-scene-id="$activeSceneId" :key="'timeline-'.$activeSceneId" />
        @else
            <!-- ================= SECCIÓN DE BLOQUES Y PIPELINE ================= -->
            @if($viewMode !== 'shots')
                <div class="space-y-6">
                    @forelse($activeScene->scriptBlocks as $block)
                        @php
                            $userLocks = $block->user_locks ?? [];
                            $hasLocks = !empty($userLocks['locked_fields']) || !empty($userLocks['pinned_terms']) || !empty($userLocks['manual_diffs']);
                        @endphp

                    <div class="rounded-2xl bg-white dark:bg-slate-900/90 border {{ $hasLocks ? 'border-amber-400 dark:border-amber-500/40 shadow-amber-950/10' : 'border-slate-200 dark:border-slate-800' }} shadow-sm dark:shadow-xl overflow-hidden transition">
                        @php
                            $activeTab = $this->getActiveTabForBlock($block);
                        @endphp

                        <!-- Cabecera del Bloque: Orden, Pestañas de Etapas Completadas y Acción Contextual -->
                        <div class="bg-slate-50/90 dark:bg-slate-950/70 border-b border-slate-200 dark:border-slate-800 px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-300">
                                    BLOQUE #{{ $block->block_order }}
                                </span>

                                <!-- Pestañas de Etapas del Pipeline (SOLO las Completadas) -->
                                <div class="inline-flex items-center gap-1 bg-slate-200/70 dark:bg-slate-900/90 p-1 rounded-xl border border-slate-200 dark:border-slate-800">
                                    <!-- Pestaña 1: Bruto (Siempre Completada) -->
                                    <button wire:click="setBlockTab('{{ $block->id }}', 'raw')"
                                            type="button"
                                            class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ $activeTab === 'raw' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                                        Bruto
                                    </button>

                                    <!-- Pestaña 2: Tratamiento (Solo si Completada) -->
                                    @if(!empty($block->structured_text))
                                        <button wire:click="setBlockTab('{{ $block->id }}', 'structured')"
                                                type="button"
                                                class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ $activeTab === 'structured' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                                            Tratamiento
                                        </button>
                                    @endif

                                    <!-- Pestaña 3: Guión (Solo si Completada) -->
                                    @if(!empty($block->screenplay_content))
                                        <button wire:click="setBlockTab('{{ $block->id }}', 'screenplay')"
                                                type="button"
                                                class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ $activeTab === 'screenplay' ? 'bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                                            Guión
                                        </button>
                                    @endif

                                    <!-- Pestaña 4: Planos (Solo si Completada) -->
                                    @if($block->shots->isNotEmpty())
                                        <button wire:click="setBlockTab('{{ $block->id }}', 'shots')"
                                                type="button"
                                                class="px-3 py-1 rounded-lg text-xs font-semibold transition {{ $activeTab === 'shots' ? 'bg-white dark:bg-slate-800 text-cyan-600 dark:text-cyan-400 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                                            Planos ({{ $block->shots->count() }})
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Acciones del Bloque Contextuales a la Etapa Activa -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if($editingBlockId !== $block->id)
                                    @if($activeTab === 'raw')
                                        <!-- Botón Estructurar (pasa a Tratamiento y activa dicha pestaña) -->
                                        <button wire:click="structureBlock('{{ $block->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="structureBlock('{{ $block->id }}')"
                                                type="button"
                                                title="Normalizar texto bruto a tratamiento dramático con IA"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 transition disabled:opacity-50">
                                            <span wire:loading.remove wire:target="structureBlock('{{ $block->id }}')">⚡</span>
                                            <svg wire:loading wire:target="structureBlock('{{ $block->id }}')" class="animate-spin h-3 w-3 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Estructurar</span>
                                        </button>
                                    @elseif($activeTab === 'structured')
                                        <!-- Botón Convertir a Guion (pasa a Guión y activa dicha pestaña) -->
                                        <button wire:click="formatBlockToScreenplay('{{ $block->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="formatBlockToScreenplay('{{ $block->id }}')"
                                                type="button"
                                                title="Convertir tratamiento a formato estándar de guion Fountain con IA"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 transition disabled:opacity-50">
                                            <span wire:loading.remove wire:target="formatBlockToScreenplay('{{ $block->id }}')">🎬</span>
                                            <svg wire:loading wire:target="formatBlockToScreenplay('{{ $block->id }}')" class="animate-spin h-3 w-3 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Convertir a Guion</span>
                                        </button>
                                    @elseif($activeTab === 'screenplay')
                                        <!-- Botón Desglosar Planos (pasa a Planos y activa dicha pestaña) -->
                                        <button wire:click="breakdownBlockShots('{{ $block->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="breakdownBlockShots('{{ $block->id }}')"
                                                type="button"
                                                title="Generar planos técnicos canónicos --- shot N (tipo) --- con IA"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30 transition disabled:opacity-50">
                                            <span wire:loading.remove wire:target="breakdownBlockShots('{{ $block->id }}')">📹</span>
                                            <svg wire:loading wire:target="breakdownBlockShots('{{ $block->id }}')" class="animate-spin h-3 w-3 text-cyan-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Desglosar Planos</span>
                                        </button>
                                    @elseif($activeTab === 'shots')
                                        <!-- En la pestaña de planos: Desglosar Planos (regenerar con IA) y Añadir Plano -->
                                        <button wire:click="breakdownBlockShots('{{ $block->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="breakdownBlockShots('{{ $block->id }}')"
                                                type="button"
                                                title="Regenerar desglose técnico con IA"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30 transition disabled:opacity-50">
                                            <span wire:loading.remove wire:target="breakdownBlockShots('{{ $block->id }}')">📹</span>
                                            <svg wire:loading wire:target="breakdownBlockShots('{{ $block->id }}')" class="animate-spin h-3 w-3 text-cyan-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Desglosar Planos</span>
                                        </button>
                                        <button wire:click="startNewShotForBlock('{{ $block->id }}')"
                                                type="button"
                                                title="Añadir plano técnico a este bloque manualmente"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 transition">
                                            <span>+</span>
                                            <span>Añadir Plano</span>
                                        </button>
                                    @endif
                                @endif

                                @if($editingBlockId === $block->id)
                                    <button wire:click="saveBlock"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-sm">
                                        ✓ Guardar Bloque
                                    </button>
                                    <button wire:click="$set('editingBlockId', null)"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-white text-xs">
                                        Cancelar
                                    </button>
                                @else
                                    <button wire:click="startEditBlock('{{ $block->id }}')"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700 transition">
                                        ✏️ Editar
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Detalle de Términos Fijos con candado -->
                        @if(!empty($userLocks['pinned_terms']))
                            <div class="px-5 py-2 bg-amber-50/80 border-b border-amber-200 text-xs text-amber-900 dark:bg-amber-950/20 dark:border-amber-500/20 dark:text-amber-300/90 flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🔒</span>
                                    <span>Términos Fijos:</span>
                                </span>
                                @foreach($userLocks['pinned_terms'] as $original => $fixed)
                                    <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200 font-mono text-[11px]">
                                        {{ $original }} ➜ {{ $fixed }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Contenido del Bloque -->
                        <div class="p-5">
                            @if($editingBlockId === $block->id)
                                <!-- Modo Edición de Bloque -->
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                                1. Texto Bruto / Estímulo Libre:
                                            </label>
                                            <textarea wire:model="editRawText"
                                                      rows="4"
                                                      class="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-sans"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                                2. Texto Estructurado / Tratamiento Cinematográfico:
                                            </label>
                                            <textarea wire:model="editStructuredText"
                                                      rows="4"
                                                      class="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-sans"></textarea>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-amber-700 dark:text-amber-400 mb-1">
                                            3. Guión Literario (Formato Estándar Fountain / Diálogos):
                                        </label>
                                        <textarea wire:model="editScreenplayContent"
                                                  rows="6"
                                                  class="w-full px-4 py-3 text-xs sm:text-sm rounded-xl bg-white dark:bg-slate-950 border border-amber-300 dark:border-amber-500/40 text-slate-900 dark:text-amber-100 font-screenplay leading-relaxed"></textarea>
                                    </div>
                                </div>
                            @else
                                <!-- Modo Visualización: SOLO UNA ETAPA A LA VEZ SEGÚN LA PESTAÑA ACTIVA -->
                                @if($activeTab === 'raw')
                                    <!-- Vista Etapa 1: Bruto -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800/60">
                                            <span class="text-[11px] font-mono uppercase tracking-wider font-bold text-slate-500">
                                                1. Bruto (Texto del Autor)
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono">Entrada original</span>
                                        </div>
                                        <p class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line py-1">
                                            {{ $block->raw_text ?: 'Sin contenido bruto.' }}
                                        </p>
                                    </div>
                                @elseif($activeTab === 'structured')
                                    <!-- Vista Etapa 2: Tratamiento -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between pb-2 border-b border-indigo-100 dark:border-indigo-950/60">
                                            <span class="text-[11px] font-mono uppercase tracking-wider font-bold text-indigo-600 dark:text-indigo-400">
                                                2. Tratamiento Dramático / Acciones
                                            </span>
                                            <button wire:click="openAiTrace('{{ $block->id }}', 'structured')"
                                                    type="button"
                                                    title="Ver trazabilidad de inferencia de IA y pasos seguidos"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 dark:text-indigo-300 dark:border-indigo-800/60 transition shadow-xs">
                                                <span>⚡</span>
                                                <span>Trazabilidad IA</span>
                                            </button>
                                        </div>
                                        <p class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line py-1">
                                            {{ $block->structured_text }}
                                        </p>
                                    </div>
                                @elseif($activeTab === 'screenplay')
                                    <!-- Vista Etapa 3: Guión Literario (Fountain) -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between pb-2 border-b border-amber-100 dark:border-amber-950/60">
                                            <span class="text-[11px] font-mono uppercase tracking-wider font-bold text-amber-600 dark:text-amber-400">
                                                3. Guión Literario (Fountain / Courier)
                                            </span>
                                            <button wire:click="openAiTrace('{{ $block->id }}', 'screenplay')"
                                                    type="button"
                                                    title="Ver trazabilidad de inferencia de IA y pasos seguidos"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 dark:text-amber-300 dark:border-amber-800/60 transition shadow-xs">
                                                <span>⚡</span>
                                                <span>Trazabilidad IA</span>
                                            </button>
                                        </div>
                                        <div class="bg-amber-50/20 dark:bg-slate-950 p-6 rounded-xl border border-amber-200/50 dark:border-amber-500/20 shadow-xs">
                                            <div class="font-screenplay text-sm text-slate-900 dark:text-amber-100 leading-relaxed whitespace-pre-wrap max-w-2xl mx-auto">
                                                {{ $block->screenplay_content }}
                                            </div>
                                        </div>
                                    </div>
                                @elseif($activeTab === 'shots')
                                    <!-- Vista Etapa 4: Desglose Técnico Completo de Planos y Tomas -->
                                    <div class="space-y-5">
                                        <div class="flex items-center justify-between pb-2 border-b border-cyan-100 dark:border-cyan-950/60">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] font-mono uppercase tracking-wider font-bold text-cyan-600 dark:text-cyan-400">
                                                    4. Desglose Técnico de Planos y Tomas ({{ $block->shots->count() }})
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-mono hidden sm:inline">
                                                    Sintaxis <code class="font-mono text-cyan-600 dark:text-cyan-400">--- shot N (tipo) ---</code>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button wire:click="startNewShotForBlock('{{ $block->id }}')"
                                                        type="button"
                                                        class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-cyan-500 hover:bg-cyan-400 text-slate-950 transition flex items-center gap-1 shadow-xs font-bold">
                                                    <span>+</span>
                                                    <span>Añadir Plano</span>
                                                </button>
                                                <button wire:click="openAiTrace('{{ $block->id }}', 'shots')"
                                                        type="button"
                                                        title="Ver trazabilidad del desglose de planos con IA"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200 dark:bg-cyan-950/50 dark:hover:bg-cyan-900/60 dark:text-cyan-300 dark:border-cyan-800/60 transition shadow-xs">
                                                    <span>⚡</span>
                                                    <span>Trazabilidad IA</span>
                                                </button>
                                            </div>
                                        </div>

                                        @if($block->shots->isEmpty())
                                            <div class="text-center py-8 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                                                <span class="text-3xl">📹</span>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">Este bloque todavía no tiene planos técnicos desglosados.</p>
                                                <div class="flex items-center justify-center gap-2 pt-1">
                                                    <button wire:click="breakdownBlockShots('{{ $block->id }}')"
                                                            type="button"
                                                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold transition shadow-sm">
                                                        ⚡ Desglosar Planos con IA
                                                    </button>
                                                    <button wire:click="startNewShotForBlock('{{ $block->id }}')"
                                                            type="button"
                                                            class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-slate-800 dark:text-slate-200 transition">
                                                        + Añadir Plano Manualmente
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="space-y-4">
                                                @foreach($block->shots as $shot)
                                                    <div class="rounded-xl bg-slate-50/70 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 p-4 space-y-3 transition">
                                                        <!-- Cabecera del Plano -->
                                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2.5 border-b border-slate-200 dark:border-slate-800/60">
                                                            <div class="flex items-center gap-2.5">
                                                                <span class="font-mono font-bold text-cyan-700 bg-cyan-50 border border-cyan-200 dark:text-cyan-300 dark:bg-cyan-950/40 dark:border-cyan-800/50 text-xs px-2 py-0.5 rounded-md">
                                                                    --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                                </span>
                                                                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                                                    {{ $shot->takes->count() }} tomas registradas
                                                                </span>
                                                            </div>

                                                            <button wire:click="$set('addingTakeShotId', '{{ $shot->id }}')"
                                                                    type="button"
                                                                    class="text-xs text-cyan-700 hover:text-cyan-600 dark:text-cyan-400 dark:hover:text-cyan-300 font-semibold self-start sm:self-auto flex items-center gap-1">
                                                                <span>+</span>
                                                                <span>Registrar Toma</span>
                                                            </button>
                                                        </div>

                                                        <!-- Descripción Técnica del Plano -->
                                                        <p class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                                                            {{ $shot->description }}
                                                        </p>

                                                        <!-- Formulario Inline para registrar nueva toma si está activo -->
                                                        @if($addingTakeShotId === $shot->id)
                                                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-cyan-300 dark:border-cyan-500/40 space-y-2.5 shadow-sm">
                                                                <h5 class="text-xs font-bold text-cyan-700 dark:text-cyan-400">Registrar Nueva Toma para Plano {{ $shot->shot_number }}</h5>
                                                                <div>
                                                                    <input type="text"
                                                                           wire:model="newTakeEditorialNotes"
                                                                           placeholder="Observaciones editoriales (ej: Foco preciso, ritmo fluido)..."
                                                                           class="w-full px-3 py-1.5 text-xs rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                                                                </div>
                                                                <div class="flex items-center gap-2">
                                                                    <label class="text-xs text-slate-700 dark:text-slate-300 flex items-center gap-2 cursor-pointer">
                                                                        <input type="checkbox" wire:model="newTakeIsGood" class="rounded bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-cyan-500 focus:ring-cyan-500">
                                                                        <span>Marcar como Toma Buena (Aprobada para montaje)</span>
                                                                    </label>
                                                                </div>
                                                                <div class="flex justify-end gap-2 pt-1">
                                                                    <button wire:click="$set('addingTakeShotId', null)" class="px-2.5 py-1 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                                                                    <button wire:click="saveNewTake" class="px-3 py-1 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs rounded-lg shadow-xs">Guardar Toma</button>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <!-- Listado de Tomas del Plano -->
                                                        @if($shot->takes->isNotEmpty())
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 pt-1">
                                                                @foreach($shot->takes as $take)
                                                                    <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border {{ $take->is_good_take ? 'border-emerald-400 dark:border-emerald-500/40 shadow-xs' : 'border-slate-200 dark:border-slate-800/80' }} flex flex-col justify-between gap-2.5">
                                                                        <div>
                                                                            <!-- Cabecera de la toma: Número, Toggle Buena y Eliminar -->
                                                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
                                                                                    TOMA {{ $take->take_number }}
                                                                                </span>

                                                                                <div class="flex items-center gap-1.5">
                                                                                    <button wire:click="toggleGoodTake('{{ $take->id }}')"
                                                                                            type="button"
                                                                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase transition flex items-center gap-1 {{ $take->is_good_take ? 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-300 dark:border-slate-700 hover:text-slate-900 dark:hover:text-slate-200' }}">
                                                                                        <span>{{ $take->is_good_take ? '✓ TOMA BUENA' : '○ Descarte' }}</span>
                                                                                    </button>

                                                                                    <button wire:click="deleteTake('{{ $take->id }}')"
                                                                                            wire:confirm="¿Seguro que deseas eliminar la Toma {{ $take->take_number }} y su archivo de vídeo si existe?"
                                                                                            type="button"
                                                                                            class="text-slate-400 hover:text-rose-500 p-1 text-xs transition"
                                                                                            title="Eliminar Toma">
                                                                                        ✕
                                                                                    </button>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Archivo de Vídeo -->
                                                                            <div class="mb-2">
                                                                                @if($uploadingTakeId === $take->id)
                                                                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-cyan-300 dark:border-cyan-500/30 space-y-2">
                                                                                        <label class="block text-[10px] font-bold text-cyan-700 dark:text-cyan-400 uppercase">
                                                                                            Seleccionar Vídeo (MP4, MOV, WEBM)
                                                                                        </label>
                                                                                        <input type="file"
                                                                                               wire:model="videoUpload"
                                                                                               accept="video/mp4,video/quicktime,video/webm,video/x-m4v"
                                                                                               class="w-full text-[11px] text-slate-600 dark:text-slate-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-cyan-500 file:text-slate-950 file:font-bold">
                                                                                        <div wire:loading wire:target="videoUpload" class="text-cyan-600 dark:text-cyan-400 text-[10px] flex items-center gap-1">
                                                                                            <span>⏳ Subiendo vídeo a memoria...</span>
                                                                                        </div>
                                                                                        <div class="flex justify-end gap-1.5 pt-1">
                                                                                            <button wire:click="$set('uploadingTakeId', null)" type="button" class="px-2 py-0.5 text-slate-500 dark:text-slate-400 text-[10px]">Cancelar</button>
                                                                                            <button wire:click="uploadTakeVideo" wire:loading.attr="disabled" type="button" class="px-2.5 py-0.5 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold rounded text-[10px]">
                                                                                                Guardar Vídeo
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
                                                                                @elseif($take->file_path)
                                                                                    <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs">
                                                                                        <a href="{{ $take->video_url }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 flex items-center gap-1.5 font-mono text-[11px] truncate" title="{{ $take->file_path }}">
                                                                                            <span>🎥</span>
                                                                                            <span class="truncate">{{ basename($take->file_path) }}</span>
                                                                                        </a>
                                                                                        <button wire:click="startUploadVideo('{{ $take->id }}')" type="button" class="text-[10px] text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 underline">
                                                                                            Cambiar
                                                                                        </button>
                                                                                    </div>
                                                                                @else
                                                                                    <button wire:click="startUploadVideo('{{ $take->id }}')"
                                                                                            type="button"
                                                                                            class="w-full py-1.5 px-2 rounded-lg bg-slate-50 hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-900 border border-dashed border-slate-300 hover:border-slate-400 dark:border-slate-700 text-slate-600 dark:text-slate-400 text-[11px] flex items-center justify-center gap-1.5 transition">
                                                                                        <span>📤</span>
                                                                                        <span>Subir Vídeo</span>
                                                                                    </button>
                                                                                @endif
                                                                            </div>

                                                                            <!-- Notas Editoriales -->
                                                                            <div>
                                                                                @if($editingTakeNotesId === $take->id)
                                                                                    <div class="space-y-1.5">
                                                                                        <textarea wire:model="editingTakeNotesText"
                                                                                                  rows="2"
                                                                                                  class="w-full p-2 text-xs rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400"
                                                                                                  placeholder="Anotaciones de dirección y posproducción..."></textarea>
                                                                                        <div class="flex justify-end gap-1.5">
                                                                                            <button wire:click="$set('editingTakeNotesId', null)" type="button" class="px-2 py-0.5 text-slate-500 dark:text-slate-400 text-[10px]">Cancelar</button>
                                                                                            <button wire:click="saveTakeNotes" type="button" class="px-2.5 py-0.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[10px] font-bold">Guardar</button>
                                                                                        </div>
                                                                                    </div>
                                                                                @else
                                                                                    <div class="flex items-start justify-between gap-1 group">
                                                                                        <p class="text-xs text-slate-600 dark:text-slate-300 italic leading-relaxed">
                                                                                            {{ $take->editorial_notes ? '"'.$take->editorial_notes.'"' : 'Sin notas editoriales.' }}
                                                                                        </p>
                                                                                        <button wire:click="startEditTakeNotes('{{ $take->id }}')"
                                                                                                type="button"
                                                                                                class="text-slate-400 hover:text-cyan-500 text-[10px] opacity-0 group-hover:opacity-100 transition"
                                                                                                title="Editar Notas">
                                                                                            ✏️
                                                                                        </button>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 bg-slate-50 dark:bg-slate-900/40 rounded-2xl border border-slate-200 dark:border-slate-800">
                        <span class="text-3xl">📝</span>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Esta escena no tiene bloques de guión todavía.</p>
                        <button wire:click="addBlock" type="button" class="mt-4 px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl text-xs hover:bg-amber-400 transition">
                            + Añadir Primer Bloque
                        </button>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- ================= SECCIÓN DE PLANOS Y TOMAS (SOLO EN SUBVISTA DEDICADA DE PLANOS) ================= -->
        @if($viewMode === 'shots')
            <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📹</span>
                            <span>Desglose Técnico de Planos y Tomas</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Planos detectados según la sintaxis canónica <code class="font-mono text-amber-600 dark:text-amber-400">--- shot N (tipo) ---</code> y control de tomas de rodaje.
                        </p>
                    </div>

                    <button wire:click="$set('showNewShotModal', true)"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold transition flex items-center gap-1.5 shadow-md">
                        <span>+</span>
                        <span>Añadir Plano</span>
                    </button>
                </div>

                <div class="space-y-6">
                    @forelse($activeScene->shots as $shot)
                        <div class="rounded-2xl bg-white dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 p-5 shadow-sm dark:shadow-lg space-y-4 transition-colors">
                            <!-- Cabecera del Plano -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono font-bold text-amber-700 bg-amber-50 border border-amber-200 dark:text-amber-400 dark:bg-slate-950 dark:border-slate-800 text-sm tracking-wider px-2.5 py-1 rounded-lg">
                                        --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                        {{ $shot->takes->count() }} tomas registradas
                                    </span>
                                </div>

                                <button wire:click="$set('addingTakeShotId', '{{ $shot->id }}')"
                                        type="button"
                                        class="text-xs text-amber-700 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300 font-semibold self-start sm:self-auto flex items-center gap-1">
                                    <span>+</span>
                                    <span>Registrar Toma</span>
                                </button>
                            </div>

                            <!-- Descripción Técnica del Plano -->
                            <p class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                                {{ $shot->description }}
                            </p>

                            <!-- Formulario Inline para registrar nueva toma si está activo -->
                            @if($addingTakeShotId === $shot->id)
                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-amber-300 dark:border-amber-500/30 space-y-3">
                                    <h5 class="text-xs font-bold text-amber-700 dark:text-amber-400">Registrar Nueva Toma para Plano {{ $shot->shot_number }}</h5>
                                    <div>
                                        <input type="text"
                                               wire:model="newTakeEditorialNotes"
                                               placeholder="Observaciones editoriales (ej: Corte limpio, mejorar luz)..."
                                               class="w-full px-3 py-1.5 text-xs rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-slate-700 dark:text-slate-300 flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model="newTakeIsGood" class="rounded bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-amber-500 focus:ring-amber-500">
                                            <span>Marcar como Toma Buena (Aprobada para montaje)</span>
                                        </label>
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <button wire:click="$set('addingTakeShotId', null)" class="px-2.5 py-1 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                                        <button wire:click="saveNewTake" class="px-3 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded">Guardar Toma</button>
                                    </div>
                                </div>
                            @endif

                            <!-- Listado de Tomas del Plano -->
                            @if($shot->takes->isNotEmpty())
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                                    @foreach($shot->takes as $take)
                                        <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-950/80 border {{ $take->is_good_take ? 'border-emerald-400 dark:border-emerald-500/40 shadow-xs' : 'border-slate-200 dark:border-slate-800/80' }} flex flex-col justify-between gap-3">
                                            <div>
                                                <!-- Cabecera de la toma: Número, Toggle Buena y Eliminar -->
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
                                                        TOMA {{ $take->take_number }}
                                                    </span>

                                                    <div class="flex items-center gap-1.5">
                                                        <!-- Botón reactivo toggle toma buena -->
                                                        <button wire:click="toggleGoodTake('{{ $take->id }}')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase transition flex items-center gap-1 {{ $take->is_good_take ? 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-300 dark:border-slate-700 hover:text-slate-900 dark:hover:text-slate-200' }}">
                                                            <span>{{ $take->is_good_take ? '✓ TOMA BUENA' : '○ Descarte' }}</span>
                                                        </button>

                                                        <!-- Eliminar toma -->
                                                        <button wire:click="deleteTake('{{ $take->id }}')"
                                                                wire:confirm="¿Seguro que deseas eliminar la Toma {{ $take->take_number }} y su archivo de vídeo si existe?"
                                                                type="button"
                                                                class="text-slate-400 hover:text-rose-500 p-1 text-xs transition"
                                                                title="Eliminar Toma">
                                                            ✕
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Archivo de Vídeo -->
                                                <div class="mb-2">
                                                    @if($uploadingTakeId === $take->id)
                                                        <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-500/30 space-y-2">
                                                            <label class="block text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase">
                                                                Seleccionar Vídeo (MP4, MOV, WEBM)
                                                            </label>
                                                            <input type="file"
                                                                   wire:model="videoUpload"
                                                                   accept="video/mp4,video/quicktime,video/webm,video/x-m4v"
                                                                   class="w-full text-[11px] text-slate-600 dark:text-slate-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-amber-500 file:text-slate-950 file:font-bold">
                                                            <div wire:loading wire:target="videoUpload" class="text-amber-600 dark:text-amber-400 text-[10px] flex items-center gap-1">
                                                                <span>⏳ Subiendo vídeo a memoria...</span>
                                                            </div>
                                                            <div class="flex justify-end gap-1.5 pt-1">
                                                                <button wire:click="$set('uploadingTakeId', null)" type="button" class="px-2 py-0.5 text-slate-500 dark:text-slate-400 text-[10px]">Cancelar</button>
                                                                <button wire:click="uploadTakeVideo" wire:loading.attr="disabled" type="button" class="px-2.5 py-0.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded text-[10px]">
                                                                    Guardar Vídeo
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @elseif($take->file_path)
                                                        <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 text-xs">
                                                            <a href="{{ $take->video_url }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 flex items-center gap-1.5 font-mono text-[11px] truncate" title="{{ $take->file_path }}">
                                                                <span>🎥</span>
                                                                <span class="truncate">{{ basename($take->file_path) }}</span>
                                                            </a>
                                                            <button wire:click="startUploadVideo('{{ $take->id }}')" type="button" class="text-[10px] text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 underline">
                                                                Cambiar
                                                            </button>
                                                        </div>
                                                    @else
                                                        <button wire:click="startUploadVideo('{{ $take->id }}')"
                                                                type="button"
                                                                class="w-full py-1.5 px-2 rounded-lg bg-white hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 border border-dashed border-slate-300 hover:border-slate-400 dark:border-slate-700 dark:hover:border-slate-600 text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 text-[11px] flex items-center justify-center gap-1.5 transition">
                                                            <span>📤</span>
                                                            <span>Subir Archivo de Vídeo</span>
                                                        </button>
                                                    @endif
                                                </div>

                                                <!-- Notas Editoriales -->
                                                <div class="mb-2">
                                                    @if($editingTakeNotesId === $take->id)
                                                        <div class="space-y-1.5">
                                                            <textarea wire:model="editingTakeNotesText"
                                                                      rows="2"
                                                                      class="w-full p-2 text-xs rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400"
                                                                      placeholder="Anotaciones de dirección y posproducción..."></textarea>
                                                            <div class="flex justify-end gap-1.5">
                                                                <button wire:click="$set('editingTakeNotesId', null)" type="button" class="px-2 py-0.5 text-slate-500 dark:text-slate-400 text-[10px]">Cancelar</button>
                                                                <button wire:click="saveTakeNotes" type="button" class="px-2.5 py-0.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[10px] font-bold">Guardar</button>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="flex items-start justify-between gap-1 group">
                                                            <p class="text-xs text-slate-600 dark:text-slate-300 italic leading-relaxed">
                                                                {{ $take->editorial_notes ? '"'.$take->editorial_notes.'"' : 'Sin notas editoriales.' }}
                                                            </p>
                                                            <button wire:click="startEditTakeNotes('{{ $take->id }}')"
                                                                    type="button"
                                                                    class="text-slate-400 hover:text-amber-500 text-[10px] opacity-0 group-hover:opacity-100 transition"
                                                                    title="Editar Notas">
                                                                ✏️
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Metadatos Técnicos (JSONB) -->
                                            @if($take->technical_meta)
                                                <div class="pt-2 border-t border-slate-200 dark:border-slate-800/60 flex flex-wrap items-center gap-2 text-[10px] font-mono text-slate-500 dark:text-slate-400">
                                                    @if(isset($take->technical_meta['resolution']))
                                                        <span class="bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                                                             {{ $take->technical_meta['resolution'] }}
                                                        </span>
                                                    @endif
                                                    @if(isset($take->technical_meta['codec']))
                                                        <span class="bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                                                            {{ $take->technical_meta['codec'] }}
                                                        </span>
                                                    @endif
                                                    @if(isset($take->technical_meta['fps']))
                                                        <span class="bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">
                                                            {{ $take->technical_meta['fps'] }} fps
                                                        </span>
                                                    @endif
                                                    @if(isset($take->technical_meta['duration_seconds']))
                                                        <span class="bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-800 text-amber-700 dark:text-amber-400">
                                                            {{ $take->technical_meta['duration_seconds'] }}s
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-6 text-center">No hay planos desglosados para esta escena aún.</p>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
    @else
        <div class="text-center py-20 bg-slate-50 dark:bg-slate-900/30 rounded-2xl border border-slate-200 dark:border-slate-800">
            <span class="text-4xl">🎬</span>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-3">Comienza creando una escena</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Las escenas ordenan los bloques dramáticos y permiten el desglose técnico de planos.
            </p>
            <button wire:click="$set('showNewSceneModal', true)" class="mt-4 px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl text-xs hover:bg-amber-400 transition">
                + Crear Primera Escena
            </button>
        </div>
    @endif

    <!-- Modal para Nueva Escena -->
    @if($showNewSceneModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Nueva Escena</h3>
                    <button wire:click="$set('showNewSceneModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">✕</button>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Encabezado Cinematográfico (Slugline) *</label>
                        <input type="text"
                               wire:model="newSceneSlugline"
                               placeholder="Ej: INT. BAR CENTRAL - NOCHE 1"
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
                        <button wire:click="createScene" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Crear Escena</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal para Nuevo Plano -->
    @if($showNewShotModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Añadir Plano Técnico</h3>
                    <button wire:click="$set('showNewShotModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">✕</button>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Tipo de Plano *</label>
                        <select wire:model="newShotType" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono">
                            <option value="PM">PM (Plano Medio)</option>
                            <option value="PG">PG (Plano General)</option>
                            <option value="PP">PP (Primer Plano)</option>
                            <option value="PD">PD (Plano Detalle)</option>
                            <option value="PA">PA (Plano Americano)</option>
                            <option value="GPG">GPG (Gran Plano General)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Descripción Técnica del Plano *</label>
                        <textarea wire:model="newShotDescription"
                                  rows="3"
                                  placeholder="Describe la cobertura visual, encuadre o movimiento de cámara..."
                                  class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                        @error('newShotDescription') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Vincular a Bloque de Guión (Opcional)</label>
                        <select wire:model="newShotBlockId" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                            <option value="">-- General de la escena --</option>
                            @foreach($activeScene->scriptBlocks as $blk)
                                <option value="{{ $blk->id }}">Bloque #{{ $blk->block_order }} ({{ substr($blk->raw_text, 0, 40) }}...)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="$set('showNewShotModal', false)" class="px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                        <button wire:click="addShot" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Guardar Plano</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL DE TRAZABILIDAD E INSPECCIÓN DE IA (ESTILO JG_HUB & JG_DASHBOARD) ================= -->
    @if($showAiTraceModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-slate-950/80 backdrop-blur-md transition">
            <div class="w-full lg:w-1/2 max-w-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh] mx-auto">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🔍</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span>Trazabilidad Cognitiva IA</span>
                                <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase rounded bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
                                    {{ $aiTraceData['tool'] ?? 'pipeline_step' }}
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Bloque #{{ $aiTraceData['block_order'] ?? '1' }} · Escena #{{ $aiTraceData['scene_order'] ?? '1' }} ({{ $aiTraceData['scene_slugline'] ?? '' }})
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-mono font-bold bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">
                            {{ strtoupper($aiTraceData['provider'] ?? 'GROQ') }} / {{ $aiTraceData['model'] ?? 'openai/gpt-oss-120b' }}
                        </span>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-mono font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                            200 OK
                        </span>
                        <button wire:click="closeAiTraceModal" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xl px-2 py-1 rounded-lg transition leading-none">
                            &times;
                        </button>
                    </div>
                </div>

                <!-- Meta info banner -->
                <div class="px-6 py-2.5 bg-slate-100/60 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                    <div class="flex items-center gap-4">
                        <span>⏱️ Latencia: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $aiTraceData['duration_ms'] ?? 0 }} ms</strong></span>
                        <span>📅 Invocado: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $aiTraceData['executed_at'] ?? $aiTraceData['timestamp'] ?? now()->toIso8601String() }}</strong></span>
                    </div>
                    <div>
                        <span>🎭 Personajes en biblia: <strong class="text-slate-800 dark:text-slate-200">{{ count($aiTraceData['characters_context'] ?? []) }}</strong></span>
                    </div>
                </div>

                <!-- Navigation Tabs inside Modal -->
                <div class="px-6 pt-3 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center gap-2">
                    <button wire:click="switchAiTraceTab('steps')" type="button"
                            class="px-4 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'steps' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        ⚡ Grafo & Saltos
                    </button>
                    <button wire:click="switchAiTraceTab('prompt')" type="button"
                            class="px-4 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'prompt' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        💬 System Prompt & Entrada
                    </button>
                    <button wire:click="switchAiTraceTab('output')" type="button"
                            class="px-4 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'output' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        📄 Salida Cinematográfica
                    </button>
                    <button wire:click="switchAiTraceTab('json')" type="button"
                            class="px-4 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'json' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        { } JSON Técnico
                    </button>
                </div>

                <!-- Modal Body Content -->
                <div class="p-6 overflow-y-auto flex-1 bg-white dark:bg-slate-900">
                    @if($aiTraceActiveTab === 'steps')
                        <!-- Timeline con diseño idéntico a jg_dashboard -->
                        <div class="relative pl-8 space-y-6 before:content-[''] before:absolute before:left-3.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                            @foreach($aiTraceData['steps'] ?? [] as $step)
                                <div class="relative group">
                                    <span class="absolute -left-8 top-1 flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 border-2 border-indigo-600 text-indigo-700 dark:bg-slate-900 dark:border-indigo-400 dark:text-indigo-400 text-xs font-bold">
                                        {{ $step['number'] ?? 1 }}
                                    </span>
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h4>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-bold border border-emerald-500/30">
                                                {{ $step['status'] ?? 'COMPLETED' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-mono">{{ $step['detail'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif($aiTraceActiveTab === 'prompt')
                        <div class="space-y-4">
                            <div>
                                <span class="block text-xs font-mono uppercase font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                                    ⚙️ System Prompt Inyectado (Biblia de Proyecto + Reglas)
                                </span>
                                <div class="p-4 rounded-xl bg-slate-900 text-slate-100 dark:bg-slate-950 dark:text-slate-100 font-mono text-xs whitespace-pre-wrap leading-relaxed max-h-60 overflow-y-auto border border-slate-700 dark:border-slate-800 shadow-inner">
                                    {{ $aiTraceData['system_prompt'] ?? 'Sin prompt de sistema disponible.' }}
                                </div>
                            </div>
                            <div>
                                <span class="block text-xs font-mono uppercase font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                                    📝 Entrada del Autor / Texto Suministrado
                                </span>
                                <div class="p-4 rounded-xl bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 font-mono text-xs whitespace-pre-wrap leading-relaxed border border-slate-200 dark:border-slate-800 shadow-inner">
                                    {{ $aiTraceData['user_prompt'] ?? $aiTraceData['input_text'] ?? 'Sin entrada registrada.' }}
                                </div>
                            </div>
                        </div>
                    @elseif($aiTraceActiveTab === 'output')
                        <div>
                            <span class="block text-xs font-mono uppercase font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                                🎬 Salida Cinematográfica Generada
                            </span>
                            <div class="p-5 rounded-xl bg-slate-900 text-amber-300 dark:bg-slate-950 dark:text-amber-200 font-mono text-xs border border-slate-700 dark:border-slate-800 whitespace-pre-wrap leading-relaxed shadow-inner">
                                {{ $aiTraceData['output'] ?? $aiTraceData['raw_response'] ?? 'Sin salida registrada.' }}
                            </div>
                        </div>
                    @elseif($aiTraceActiveTab === 'json')
                        <div>
                            <span class="block text-xs font-mono uppercase font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                                { } Payload & Telemetría Completa
                            </span>
                            <pre class="p-4 rounded-xl bg-[#0f172a] text-slate-100 font-mono text-xs overflow-x-auto border border-slate-800 max-h-96 shadow-inner"><code class="language-json text-slate-100 leading-relaxed">{{ json_encode($aiTraceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end">
                    <button wire:click="closeAiTraceModal" type="button" class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-white transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
