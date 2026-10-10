<div class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors">
    <!-- ================= BARRA SUPERIOR STICKY: ESCENAS Y PIPELINE ================= -->
    <div class="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800 shadow-sm backdrop-blur-md px-4 sm:px-6 lg:px-8 py-3 transition-colors">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-3">
            <!-- Selector de Escena Activa -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 scrollbar-thin">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 shrink-0">Escenas:</span>
                <div class="flex items-center gap-1.5">
                    @forelse($scenes as $scene)
                        <button wire:click="selectScene('{{ $scene->id }}')"
                                type="button"
                                class="px-2.5 py-1 rounded-xl text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-1.5 border {{ $activeSceneId === $scene->id ? 'bg-amber-500 text-slate-950 font-bold border-amber-400 shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700' }}">
                            <span>#{{ $scene->scene_order }}</span>
                            <span class="truncate max-w-[120px] sm:max-w-[180px]">{{ $scene->slugline }}</span>
                        </button>
                    @empty
                        <span class="text-xs text-slate-400 italic">No hay escenas creadas.</span>
                    @endforelse

                    <button wire:click="$set('showNewSceneModal', true)"
                            type="button"
                            class="px-2 py-1 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-amber-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 transition flex items-center gap-1 shrink-0"
                            title="Añadir Escena">
                        <span>+</span>
                        <span class="hidden sm:inline">Nueva Escena</span>
                    </button>
                </div>
            </div>

            <!-- Menú del Pipeline Ordenado Estrictamente: Idea > Tratamiento > Guión > Planos/Tomas > Montaje -->
            <div class="flex items-center gap-2 shrink-0 self-end md:self-auto">
                <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-950 p-1 border border-slate-200 dark:border-slate-800 text-xs shadow-inner">
                    <!-- 1. Idea (Original / Bruto) -->
                    <button wire:click="setPipelineStage('idea')"
                            type="button"
                            title="Listado de textos originales en bruto"
                            class="px-3 py-1 rounded-lg transition {{ $pipelineStage === 'idea' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        Idea
                    </button>

                    <!-- 2. Tratamiento -->
                    <button wire:click="setPipelineStage('structured')"
                            type="button"
                            title="Listado de textos estructurados a tratamiento"
                            class="px-3 py-1 rounded-lg transition {{ $pipelineStage === 'structured' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        Tratamiento
                    </button>

                    <!-- 3. Guión -->
                    <button wire:click="setPipelineStage('screenplay')"
                            type="button"
                            title="Listado de textos formateados a guión canónico Fountain"
                            class="px-3 py-1 rounded-lg transition {{ $pipelineStage === 'screenplay' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        Guión
                    </button>

                    <!-- 4. Planos / Tomas -->
                    <button wire:click="setPipelineStage('shots')"
                            type="button"
                            title="Desglose técnico de planos de cámara y tomas"
                            class="px-3 py-1 rounded-lg transition {{ $pipelineStage === 'shots' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        Planos / Tomas
                    </button>

                    <!-- 5. Montaje -->
                    <button wire:click="setPipelineStage('timeline')"
                            type="button"
                            title="Línea de tiempo y pre-corte de edición"
                            class="px-3 py-1 rounded-lg transition {{ $pipelineStage === 'timeline' ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        🎬 Montaje
                    </button>
                </div>

                <!-- Botón Añadir Bloque en Barra Superior -->
                <button wire:click="openNewBlockModal"
                        type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-xs transition flex items-center gap-1 shrink-0">
                    <span>+</span>
                    <span>Añadir Bloque</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= CONTENEDOR PRINCIPAL (ALINEADO AL LOGOTIPO CON MAX-W-7XL) ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <!-- Mensajes Flash -->
        @if(session()->has('success'))
            <div class="p-3.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if($activeScene)
            <!-- Encabezado de la Escena Activa (Slugline) -->
            <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition">
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
                    <h2 class="text-lg sm:text-xl font-bold font-mono tracking-tight text-slate-900 dark:text-white uppercase">
                        {{ $activeScene->slugline }}
                    </h2>
                </div>

                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">
                    <span>{{ $activeScene->scriptBlocks->count() }} bloques en escena</span>
                </div>
            </div>

            @if($pipelineStage === 'timeline')
                <!-- Subvista de Montaje / RoughCutTimeline -->
                <livewire:studio.rough-cut-timeline :project="$project" :active-scene-id="$activeSceneId" :key="'timeline-'.$activeSceneId" />
            @else
                <!-- ================= FEED CONTINUO DE BLOQUES DE LA HISTORIA ================= -->
                <div class="py-2 space-y-1">
                    <!-- Botón Gutenberg Inicial (Insertar antes del primer bloque, visible SOLO en hover) -->
                    <div class="relative group my-3 py-2 flex items-center justify-center cursor-pointer">
                        <div class="absolute inset-0 flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none" aria-hidden="true">
                            <div class="w-full border-t-2 border-amber-500 shadow-xs"></div>
                        </div>
                        <button wire:click="openNewBlockModal(0)"
                                type="button"
                                title="Insertar bloque al inicio"
                                class="relative z-10 opacity-0 group-hover:opacity-100 inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-base shadow-lg shadow-amber-500/30 border-2 border-white dark:border-slate-900 transition-all transform hover:scale-125 cursor-pointer">
                            <span class="leading-none pb-0.5">+</span>
                        </button>
                    </div>

                    @forelse($activeScene->scriptBlocks as $block)
                        @php
                            $isSelected = ($selectedBlockId === $block->id);
                        @endphp

                        <!-- Elemento Continuo de Bloque (Sin barra superior de metadatos, pura lectura fluida) -->
                        <div id="block-{{ $block->id }}"
                             wire:click="selectBlock('{{ $block->id }}')"
                             class="group relative rounded-2xl p-5 transition-all cursor-pointer border-l-4 {{ $isSelected ? 'bg-amber-50/50 dark:bg-slate-900/90 border-amber-500 dark:border-amber-400 shadow-xs' : 'border-transparent hover:bg-slate-100/60 dark:hover:bg-slate-900/40 hover:border-slate-300 dark:hover:border-slate-700' }}">

                            <div class="flex items-start justify-between gap-6">
                                <!-- Texto de la Historia según la etapa del menú superior -->
                                <div class="flex-1 min-w-0">
                                    @if($pipelineStage === 'idea')
                                        <!-- Etapa 1: Idea / Original / Texto Bruto -->
                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line">
                                            {{ $block->raw_text ?: 'Sin notas o texto en bruto.' }}
                                        </div>
                                    @elseif($pipelineStage === 'structured')
                                        <!-- Etapa 2: Tratamiento Cinematográfico -->
                                        @if(!empty($block->structured_text))
                                            <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line">
                                                {{ $block->structured_text }}
                                            </div>
                                        @else
                                            <div class="py-1 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                <span>[Tratamiento pendiente de estructurar para este bloque]</span>
                                                <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'structured')" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline cursor-pointer">
                                                    ⚡ Abrir detalles para estructurar ➔
                                                </button>
                                            </div>
                                        @endif
                                    @elseif($pipelineStage === 'screenplay')
                                        <!-- Etapa 3: Guión Literario (Fountain) -->
                                        @if(!empty($block->screenplay_content))
                                            <div class="p-4 bg-amber-50/20 dark:bg-slate-950/60 rounded-xl border border-amber-200/40 dark:border-amber-500/20">
                                                <div class="font-screenplay text-sm text-slate-900 dark:text-amber-100 leading-relaxed whitespace-pre-wrap max-w-2xl mx-auto">
                                                    {{ $block->screenplay_content }}
                                                </div>
                                            </div>
                                        @else
                                            <div class="py-1 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                <span>[Guión literario pendiente de formatear para este bloque]</span>
                                                <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'screenplay')" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline cursor-pointer">
                                                    🎬 Abrir detalles para convertir a guión ➔
                                                </button>
                                            </div>
                                        @endif
                                    @elseif($pipelineStage === 'shots')
                                        <!-- Etapa 4: Planos y Tomas -->
                                        @if($block->shots->isNotEmpty())
                                            <div class="space-y-2.5">
                                                @foreach($block->shots as $shot)
                                                    <div class="p-3 rounded-lg bg-slate-50/80 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-2 py-0.5 rounded">
                                                                --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                            </span>
                                                            <span class="text-slate-700 dark:text-slate-300">{{ $shot->description }}</span>
                                                        </div>
                                                        <span class="text-[11px] text-slate-500 font-mono">{{ $shot->takes->count() }} tomas</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="py-1 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                <span>[Sin planos técnicos desglosados en este bloque]</span>
                                                <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'shots')" class="text-cyan-600 dark:text-cyan-400 font-semibold hover:underline cursor-pointer">
                                                    📹 Abrir detalles para desglosar planos ➔
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                <!-- Botón de Detalles flotando a la derecha a la misma altura que la primera línea de texto -->
                                <div class="shrink-0 pt-0.5">
                                    <button wire:click.stop="openBlockDetails('{{ $block->id }}')"
                                            type="button"
                                            title="Abrir detalles y etapas en panel lateral"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-500 hover:text-amber-700 dark:hover:text-amber-300 transition shadow-2xs cursor-pointer">
                                        <span class="text-sm">📑</span>
                                        <span class="text-xs font-bold">Detalles</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Divisor Gutenberg Intermedio (Círculo naranja y línea SOLO en hover) -->
                        <div class="relative group my-3 py-2 flex items-center justify-center cursor-pointer">
                            <div class="absolute inset-0 flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none" aria-hidden="true">
                                <div class="w-full border-t-2 border-amber-500 shadow-xs"></div>
                            </div>
                            <button wire:click="openNewBlockModal({{ $block->block_order }})"
                                    type="button"
                                    title="Añadir bloque aquí"
                                    class="relative z-10 opacity-0 group-hover:opacity-100 inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-base shadow-lg shadow-amber-500/30 border-2 border-white dark:border-slate-900 transition-all transform hover:scale-125 cursor-pointer">
                                <span class="leading-none pb-0.5">+</span>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-12 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 p-6 space-y-3">
                            <span class="text-3xl">📝</span>
                            <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">No hay bloques en esta escena todavía.</p>
                            <button wire:click="openNewBlockModal" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-sm">
                                + Crear Primer Bloque
                            </button>
                        </div>
                    @endforelse
                </div>
            @endif
        @else
            <div class="text-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-8 space-y-4">
                <span class="text-4xl">🎬</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Selecciona o crea una escena para comenzar</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">Cada escena organiza la acción dramática mediante bloques encadenados a través del pipeline cinematográfico.</p>
                <button wire:click="$set('showNewSceneModal', true)" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">
                    + Crear Escena
                </button>
            </div>
        @endif
    </div>

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
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $detailActiveTab === 'raw' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            1. Idea / Bruto
                        </button>
                        <button wire:click="setDetailTab('structured')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $detailActiveTab === 'structured' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            2. Tratamiento
                        </button>
                        <button wire:click="setDetailTab('screenplay')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $detailActiveTab === 'screenplay' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            3. Guión Literario
                        </button>
                        <button wire:click="setDetailTab('shots')"
                                type="button"
                                class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $detailActiveTab === 'shots' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
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
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 hover:border-amber-400 transition shadow-2xs">
                                            ✏️ Editar Texto
                                        </button>
                                        <button wire:click="openAiTrace('{{ $detailBlock->id }}', '{{ $detailActiveTab === 'raw' ? 'structured' : $detailActiveTab }}')"
                                                type="button"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800/60 transition flex items-center gap-1.5">
                                            <span>⚡</span>
                                            <span>Trazabilidad IA</span>
                                        </button>
                                    </div>

                                    <!-- Botones de Acción de IA Contextual -->
                                    <div>
                                        @if($detailActiveTab === 'raw')
                                            <button wire:click="structureBlock('{{ $detailBlock->id }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="structureBlock('{{ $detailBlock->id }}')"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition flex items-center gap-1.5">
                                                <span>⚡ Estructurar Tratamiento</span>
                                            </button>
                                        @elseif($detailActiveTab === 'structured')
                                            <button wire:click="formatBlockToScreenplay('{{ $detailBlock->id }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="formatBlockToScreenplay('{{ $detailBlock->id }}')"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition flex items-center gap-1.5 font-mono">
                                                <span>🎬 Convertir a Guión</span>
                                            </button>
                                        @elseif($detailActiveTab === 'screenplay')
                                            <button wire:click="breakdownBlockShots('{{ $detailBlock->id }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="breakdownBlockShots('{{ $detailBlock->id }}')"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 text-white shadow-sm transition flex items-center gap-1.5">
                                                <span>📹 Desglosar Planos</span>
                                            </button>
                                        @elseif($detailActiveTab === 'shots')
                                            <div class="flex items-center gap-2">
                                                <button wire:click="breakdownBlockShots('{{ $detailBlock->id }}')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 text-white shadow-sm transition">
                                                    ⚡ Regenerar Planos
                                                </button>
                                                <button wire:click="startNewShotForBlock('{{ $detailBlock->id }}')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                                                    + Añadir Plano
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Vista de Texto según la etapa en el Drawer -->
                                @if($detailActiveTab === 'raw')
                                    <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2">
                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-slate-500">1. Texto Bruto Original:</span>
                                        <p class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line">
                                            {{ $detailBlock->raw_text ?: 'Sin notas brutas.' }}
                                        </p>
                                    </div>
                                @elseif($detailActiveTab === 'structured')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-2">
                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">2. Tratamiento Dramático:</span>
                                        <p class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line">
                                            {{ $detailBlock->structured_text ?: 'Aún no se ha estructurado el tratamiento para este bloque.' }}
                                        </p>
                                    </div>
                                @elseif($detailActiveTab === 'screenplay')
                                    <div class="p-5 rounded-xl bg-amber-50/20 dark:bg-slate-950/70 border border-amber-200/50 dark:border-amber-900/40 space-y-2">
                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">3. Guión Literario (Fountain):</span>
                                        <div class="font-screenplay text-sm text-slate-900 dark:text-amber-100 leading-relaxed whitespace-pre-wrap">
                                            {{ $detailBlock->screenplay_content ?: 'Aún no se ha formateado a guión literario.' }}
                                        </div>
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
             x-data
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
                        <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                            200 OK
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

                <!-- Navigation Tabs inside Modal (Fijo) -->
                <div class="px-6 pt-2 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center gap-2 shrink-0" style="flex-shrink: 0;">
                    <button wire:click="switchAiTraceTab('steps')" type="button"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'steps' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        ⚡ Grafo & Saltos
                    </button>
                    <button wire:click="switchAiTraceTab('prompt')" type="button"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'prompt' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        💬 System Prompt & Entrada
                    </button>
                    <button wire:click="switchAiTraceTab('output')" type="button"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'output' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        📄 Salida Cinematográfica
                    </button>
                    <button wire:click="switchAiTraceTab('json')" type="button"
                            class="px-3.5 py-2 border-b-2 text-xs font-semibold transition {{ $aiTraceActiveTab === 'json' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                        { } JSON Técnico
                    </button>
                </div>

                <!-- Modal Body Content (ÁREA SCROLLEABLE INTERNA CON PROPORCIÓN FIJA) -->
                <div class="p-6 bg-white dark:bg-slate-900" style="flex: 1 1 0%; min-height: 0; overflow-y: auto; overflow-x: hidden;">
                    @if($aiTraceActiveTab === 'steps')
                        <div class="space-y-3.5">
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
                                        <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-mono">{{ $step['detail'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif($aiTraceActiveTab === 'prompt')
                        <div class="space-y-5">
                            <div>
                                <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                                    <span>⚙️</span>
                                    <span>System Prompt Inyectado (Biblia de Proyecto + Reglas)</span>
                                </span>
                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-xs whitespace-pre-wrap leading-relaxed max-h-48 overflow-y-auto border border-slate-300 dark:border-slate-800 shadow-inner">
                                    {{ $aiTraceData['system_prompt'] ?? 'Sin prompt de sistema disponible.' }}
                                </div>
                            </div>
                            <div>
                                <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                                    <span>📝</span>
                                    <span>Entrada del Autor / Texto Suministrado</span>
                                </span>
                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-xs whitespace-pre-wrap leading-relaxed max-h-48 overflow-y-auto border border-slate-300 dark:border-slate-800 shadow-inner">
                                    {{ $aiTraceData['user_prompt'] ?? $aiTraceData['input_text'] ?? 'Sin entrada registrada.' }}
                                </div>
                            </div>
                        </div>
                    @elseif($aiTraceActiveTab === 'output')
                        <div class="space-y-2">
                            <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
                                <span>🎬</span>
                                <span>Salida Cinematográfica Generada</span>
                            </span>
                            <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-mono text-xs border border-slate-300 dark:border-slate-800 whitespace-pre-wrap leading-relaxed overflow-y-auto shadow-inner" style="min-height: 280px; max-height: 380px;">
                                {{ $aiTraceData['output'] ?? $aiTraceData['raw_response'] ?? 'Sin salida registrada.' }}
                            </div>
                        </div>
                    @elseif($aiTraceActiveTab === 'json')
                        <div class="flex flex-col space-y-2">
                            <span class="block text-xs font-mono uppercase font-bold text-slate-600 dark:text-slate-300 shrink-0 flex items-center gap-1.5">
                                <span>{ }</span>
                                <span>Payload & Telemetría Completa (JSON)</span>
                            </span>
                            <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-4 shadow-inner" style="min-height: 280px; max-height: 380px; overflow: auto;">
                                <pre class="m-0 p-0 bg-transparent border-0"><code class="language-json font-mono text-xs leading-relaxed block" style="color: #38bdf8 !important;">{{ json_encode($aiTraceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    @endif
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
