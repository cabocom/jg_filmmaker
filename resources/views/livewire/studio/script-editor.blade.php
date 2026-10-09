<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Barra Superior de Navegación de Escenas -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 shadow-xl backdrop-blur-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Selector de Escena Activa -->
        <div class="flex items-center gap-3 overflow-x-auto pb-1 md:pb-0 scrollbar-thin">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 shrink-0">Escenas:</span>
            <div class="flex items-center gap-2">
                @forelse($scenes as $scene)
                    <button wire:click="selectScene('{{ $scene->id }}')"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border {{ $activeSceneId === $scene->id ? 'bg-amber-500 text-slate-950 font-bold border-amber-400 shadow-md shadow-amber-950/40' : 'bg-slate-950 text-slate-300 hover:bg-slate-800 border-slate-800' }}">
                        <span>#{{ $scene->scene_order }}</span>
                        <span class="truncate max-w-[150px] sm:max-w-[200px]">{{ $scene->slugline }}</span>
                    </button>
                @empty
                    <span class="text-xs text-slate-500 italic">No hay escenas creadas todavía.</span>
                @endforelse

                <button wire:click="$set('showNewSceneModal', true)"
                        type="button"
                        class="px-2.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-amber-400 border border-slate-700 transition flex items-center gap-1 shrink-0"
                        title="Añadir Escena">
                    <span>+</span>
                    <span>Nueva Escena</span>
                </button>
            </div>
        </div>

        <!-- Filtros de Vista del Pipeline y Acciones -->
        <div class="flex items-center gap-2 shrink-0 self-end md:self-auto">
            <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-slate-800 text-xs">
                <button wire:click="$set('viewMode', 'all')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'all' ? 'bg-slate-800 text-amber-300 font-semibold' : 'text-slate-400 hover:text-white' }}">
                    Pipeline
                </button>
                <button wire:click="$set('viewMode', 'screenplay')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'screenplay' ? 'bg-slate-800 text-amber-300 font-semibold' : 'text-slate-400 hover:text-white' }}">
                    Guión
                </button>
                <button wire:click="$set('viewMode', 'structured')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'structured' ? 'bg-slate-800 text-amber-300 font-semibold' : 'text-slate-400 hover:text-white' }}">
                    Tratamiento
                </button>
                <button wire:click="$set('viewMode', 'shots')"
                        type="button"
                        class="px-2.5 py-1 rounded-lg transition {{ $viewMode === 'shots' ? 'bg-slate-800 text-amber-300 font-semibold' : 'text-slate-400 hover:text-white' }}">
                    Planos / Tomas
                </button>
            </div>

            <button wire:click="addBlock"
                    type="button"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 transition flex items-center gap-1.5">
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
        <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-5 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 text-xs font-mono text-amber-400 mb-1">
                    <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 font-bold">ESCENA {{ $activeScene->scene_order }}</span>
                    <span>•</span>
                    <span class="text-slate-400">{{ $activeScene->time_of_day }}</span>
                    @if($activeScene->location)
                        <span>•</span>
                        <span class="text-slate-300 font-bold">{{ $activeScene->location->name }} ({{ $activeScene->location->type }})</span>
                    @endif
                </div>
                <h2 class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-white uppercase">
                    {{ $activeScene->slugline }}
                </h2>
            </div>

            <!-- Accesos rápidos de escena -->
            <div class="flex items-center gap-2">
                <button wire:click="$set('showNewShotModal', true)"
                        type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition flex items-center gap-1.5">
                    <span>📹</span>
                    <span>Desglosar Plano</span>
                </button>
            </div>
        </div>

        <!-- ================= SECCIÓN DE BLOQUES Y PIPELINE ================= -->
        @if($viewMode !== 'shots')
            <div class="space-y-6">
                @forelse($activeScene->scriptBlocks as $block)
                    @php
                        $userLocks = $block->user_locks ?? [];
                        $hasLocks = !empty($userLocks['locked_fields']) || !empty($userLocks['pinned_terms']) || !empty($userLocks['manual_diffs']);
                    @endphp

                    <div class="rounded-2xl bg-slate-900/90 border {{ $hasLocks ? 'border-amber-500/40 shadow-amber-950/20' : 'border-slate-800' }} shadow-xl overflow-hidden transition">
                        <!-- Cabecera del Bloque: Orden, Etapa, Indicador de Candado -->
                        <div class="bg-slate-950/70 border-b border-slate-800 px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono font-bold text-slate-300">
                                    BLOQUE #{{ $block->block_order }}
                                </span>

                                <!-- Selector de Etapa -->
                                <div class="flex items-center gap-1">
                                    @foreach(['raw' => 'Bruto', 'structured' => 'Tratamiento', 'screenplay' => 'Guión'] as $stageKey => $stageLabel)
                                        <button wire:click="setBlockStage('{{ $block->id }}', '{{ $stageKey }}')"
                                                type="button"
                                                class="px-2 py-0.5 rounded text-[10px] font-mono transition {{ $block->stage === $stageKey ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40 font-bold' : 'text-slate-500 hover:text-slate-300' }}">
                                            {{ $stageLabel }}
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Indicador de Candado User Locks -->
                                @if($hasLocks)
                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[11px] font-medium"
                                         title="Cambios manuales o términos bloqueados contra sobreescritura de IA">
                                        <span>🔒</span>
                                        <span>Candados Activos</span>
                                        @if(!empty($userLocks['pinned_terms']))
                                            <span class="text-[10px] text-amber-300/80 font-mono">
                                                ({{ count($userLocks['pinned_terms']) }} términos)
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Acciones del Bloque -->
                            <div class="flex items-center gap-2">
                                @if($editingBlockId === $block->id)
                                    <button wire:click="saveBlock"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-sm">
                                        ✓ Guardar Bloque
                                    </button>
                                    <button wire:click="$set('editingBlockId', null)"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-400 hover:text-white text-xs">
                                        Cancelar
                                    </button>
                                @else
                                    <button wire:click="startEditBlock('{{ $block->id }}')"
                                            type="button"
                                            class="px-2.5 py-1 rounded-lg text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition">
                                        ✏️ Editar
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Detalle de User Locks si existen -->
                        @if($hasLocks && !empty($userLocks['pinned_terms']))
                            <div class="px-5 py-2 bg-amber-950/20 border-b border-amber-500/20 text-xs text-amber-300/90 flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-[11px] uppercase tracking-wider">Términos Fijos:</span>
                                @foreach($userLocks['pinned_terms'] as $original => $fixed)
                                    <span class="px-2 py-0.5 rounded bg-amber-500/20 border border-amber-500/30 font-mono text-[11px]">
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
                                            <label class="block text-xs font-semibold text-slate-300 mb-1">
                                                1. Texto Bruto / Estímulo Libre:
                                            </label>
                                            <textarea wire:model="editRawText"
                                                      rows="4"
                                                      class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white font-sans"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-300 mb-1">
                                                2. Texto Estructurado / Tratamiento Cinematográfico:
                                            </label>
                                            <textarea wire:model="editStructuredText"
                                                      rows="4"
                                                      class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white font-sans"></textarea>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-amber-400 mb-1">
                                            3. Guión Literario (Formato Estándar Fountain / Diálogos):
                                        </label>
                                        <textarea wire:model="editScreenplayContent"
                                                  rows="6"
                                                  class="w-full px-4 py-3 text-xs sm:text-sm rounded-xl bg-slate-950 border border-amber-500/40 text-amber-100 font-screenplay leading-relaxed"></textarea>
                                    </div>
                                </div>
                            @else
                                <!-- Modo Visualización -->
                                @if($viewMode === 'all')
                                    <!-- Pipeline en Paralelo (Bruto -> Estructurado -> Guión) -->
                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                        <!-- Fase 1: Bruto -->
                                        <div class="p-4 rounded-xl bg-slate-950/50 border border-slate-800">
                                            <span class="block text-[10px] font-mono uppercase text-slate-500 font-bold mb-2">1. Bruto (Autor)</span>
                                            <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line">
                                                {{ $block->raw_text ?: 'Sin contenido bruto.' }}
                                            </p>
                                        </div>

                                        <!-- Fase 2: Estructurado -->
                                        <div class="p-4 rounded-xl bg-slate-950/50 border border-slate-800">
                                            <span class="block text-[10px] font-mono uppercase text-slate-500 font-bold mb-2">2. Tratamiento / Acciones</span>
                                            <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line">
                                                {{ $block->structured_text ?: 'Sin procesar a tratamiento estructurado todavía.' }}
                                            </p>
                                        </div>

                                        <!-- Fase 3: Guión Literario -->
                                        <div class="p-4 rounded-xl bg-slate-950 border border-amber-500/20">
                                            <span class="block text-[10px] font-mono uppercase text-amber-500/80 font-bold mb-2">3. Guión Literario</span>
                                            <div class="font-screenplay text-xs text-amber-100 leading-relaxed whitespace-pre-wrap">
                                                {{ $block->screenplay_content ?: 'Guión pendiente de formateo.' }}
                                            </div>
                                        </div>
                                    </div>
                                @elseif($viewMode === 'raw')
                                    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                                        <p class="text-sm text-slate-200 leading-relaxed whitespace-pre-line">
                                            {{ $block->raw_text }}
                                        </p>
                                    </div>
                                @elseif($viewMode === 'structured')
                                    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                                        <p class="text-sm text-slate-200 leading-relaxed whitespace-pre-line">
                                            {{ $block->structured_text ?: 'No hay tratamiento generado para este bloque aún.' }}
                                        </p>
                                    </div>
                                @elseif($viewMode === 'screenplay')
                                    <!-- Renderizado Tipográfico Cinematográfico Courier Prime -->
                                    <div class="p-6 rounded-xl bg-slate-950 border border-slate-800/80 shadow-inner">
                                        <div class="font-screenplay text-sm text-slate-100 leading-relaxed whitespace-pre-wrap max-w-2xl mx-auto">
                                            {{ $block->screenplay_content ?: $block->raw_text }}
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 bg-slate-900/40 rounded-2xl border border-slate-800">
                        <span class="text-3xl">📝</span>
                        <p class="text-sm text-slate-400 mt-2">Esta escena no tiene bloques de guión todavía.</p>
                        <button wire:click="addBlock" type="button" class="mt-4 px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl text-xs hover:bg-amber-400 transition">
                            + Añadir Primer Bloque
                        </button>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- ================= SECCIÓN DE PLANOS Y TOMAS (VISOR DE DESGLOSE) ================= -->
        @if($viewMode === 'shots' || $viewMode === 'all')
            <div class="mt-8 pt-6 border-t border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>📹</span>
                            <span>Desglose Técnico de Planos y Tomas</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Planos detectados según la sintaxis canónica <code class="font-mono text-amber-400">--- shot N (tipo) ---</code> y control de tomas de rodaje.
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
                        <div class="rounded-2xl bg-slate-900/70 border border-slate-800 p-5 shadow-lg space-y-4">
                            <!-- Cabecera del Plano -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-slate-800/80">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono font-bold text-amber-400 text-sm tracking-wider bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-800">
                                        --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                    </span>
                                    <span class="text-xs text-slate-400 font-medium">
                                        {{ $shot->takes->count() }} tomas registradas
                                    </span>
                                </div>

                                <button wire:click="$set('addingTakeShotId', '{{ $shot->id }}')"
                                        type="button"
                                        class="text-xs text-amber-400 hover:text-amber-300 font-semibold self-start sm:self-auto flex items-center gap-1">
                                    <span>+</span>
                                    <span>Registrar Toma</span>
                                </button>
                            </div>

                            <!-- Descripción Técnica del Plano -->
                            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                                {{ $shot->description }}
                            </p>

                            <!-- Formulario Inline para registrar nueva toma si está activo -->
                            @if($addingTakeShotId === $shot->id)
                                <div class="p-4 rounded-xl bg-slate-950 border border-amber-500/30 space-y-3">
                                    <h5 class="text-xs font-bold text-amber-400">Registrar Nueva Toma para Plano {{ $shot->shot_number }}</h5>
                                    <div>
                                        <input type="text"
                                               wire:model="newTakeEditorialNotes"
                                               placeholder="Observaciones editoriales (ej: Corte limpio, mejorar luz)..."
                                               class="w-full px-3 py-1.5 text-xs rounded-lg bg-slate-900 border border-slate-700 text-white">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-slate-300 flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model="newTakeIsGood" class="rounded bg-slate-800 border-slate-700 text-amber-500 focus:ring-amber-500">
                                            <span>Marcar como Toma Buena (Aprobada para montaje)</span>
                                        </label>
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <button wire:click="$set('addingTakeShotId', null)" class="px-2.5 py-1 text-xs text-slate-400">Cancelar</button>
                                        <button wire:click="saveNewTake" class="px-3 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded">Guardar Toma</button>
                                    </div>
                                </div>
                            @endif

                            <!-- Listado de Tomas del Plano -->
                            @if($shot->takes->isNotEmpty())
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                                    @foreach($shot->takes as $take)
                                        <div class="p-3.5 rounded-xl bg-slate-950/80 border {{ $take->is_good_take ? 'border-emerald-500/40 shadow-emerald-950/20' : 'border-slate-800/80' }} flex flex-col justify-between gap-3">
                                            <div>
                                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                                    <span class="font-mono text-xs font-bold text-slate-200">
                                                        TOMA {{ $take->take_number }}
                                                    </span>

                                                    <!-- Botón reactivo toggle toma buena -->
                                                    <button wire:click="toggleGoodTake('{{ $take->id }}')"
                                                            type="button"
                                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase transition flex items-center gap-1 {{ $take->is_good_take ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:text-slate-200' }}">
                                                        <span>{{ $take->is_good_take ? '✓ TOMA BUENA' : '○ Descarte' }}</span>
                                                    </button>
                                                </div>

                                                @if($take->editorial_notes)
                                                    <p class="text-xs text-slate-300 italic mb-2">
                                                        "{{ $take->editorial_notes }}"
                                                    </p>
                                                @endif
                                            </div>

                                            <!-- Metadatos Técnicos (JSONB) -->
                                            @if($take->technical_meta)
                                                <div class="pt-2 border-t border-slate-800/60 flex flex-wrap items-center gap-2 text-[10px] font-mono text-slate-400">
                                                    @if(isset($take->technical_meta['resolution']))
                                                        <span class="bg-slate-900 px-1.5 py-0.5 rounded border border-slate-800 text-slate-300">
                                                            {{ $take->technical_meta['resolution'] }}
                                                        </span>
                                                    @endif
                                                    @if(isset($take->technical_meta['codec']))
                                                        <span class="bg-slate-900 px-1.5 py-0.5 rounded border border-slate-800 text-slate-300">
                                                            {{ $take->technical_meta['codec'] }}
                                                        </span>
                                                    @endif
                                                    @if(isset($take->technical_meta['fps']))
                                                        <span class="bg-slate-900 px-1.5 py-0.5 rounded border border-slate-800 text-slate-300">
                                                            {{ $take->technical_meta['fps'] }} fps
                                                        </span>
                                                    @endif
                                                    @if(isset($take->technical_meta['duration_seconds']))
                                                        <span class="bg-slate-900 px-1.5 py-0.5 rounded border border-slate-800 text-amber-400">
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
                        <p class="text-xs text-slate-500 py-6 text-center">No hay planos desglosados para esta escena aún.</p>
                    @endforelse
                </div>
            </div>
        @endif
    @else
        <div class="text-center py-20 bg-slate-900/30 rounded-2xl border border-slate-800">
            <span class="text-4xl">🎬</span>
            <h3 class="text-lg font-bold text-white mt-3">Comienza creando una escena</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Las escenas ordenan los bloques dramáticos y permiten el desglose técnico de planos.
            </p>
            <button wire:click="$set('showNewSceneModal', true)" class="mt-4 px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl text-xs hover:bg-amber-400 transition">
                + Crear Primera Escena
            </button>
        </div>
    @endif

    <!-- Modal para Nueva Escena -->
    @if($showNewSceneModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Nueva Escena</h3>
                    <button wire:click="$set('showNewSceneModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Encabezado Cinematográfico (Slugline) *</label>
                        <input type="text"
                               wire:model="newSceneSlugline"
                               placeholder="Ej: INT. BAR CENTRAL - NOCHE 1"
                               class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white font-mono uppercase">
                        @error('newSceneSlugline') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Momento del Día *</label>
                        <select wire:model="newSceneTimeOfDay" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white">
                            <option value="DÍA">DÍA</option>
                            <option value="NOCHE">NOCHE</option>
                            <option value="ATARDECER">ATARDECER</option>
                            <option value="AMANECER">AMANECER</option>
                            <option value="CONTINUO">CONTINUO</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Localización de la Biblia (Opcional)</label>
                        <select wire:model="newSceneLocationId" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white">
                            <option value="">-- Sin asignar --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-800">
                        <button wire:click="$set('showNewSceneModal', false)" class="px-3 py-1.5 text-xs text-slate-400">Cancelar</button>
                        <button wire:click="createScene" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Crear Escena</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal para Nuevo Plano -->
    @if($showNewShotModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Añadir Plano Técnico</h3>
                    <button wire:click="$set('showNewShotModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Tipo de Plano *</label>
                        <select wire:model="newShotType" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white font-mono">
                            <option value="PM">PM (Plano Medio)</option>
                            <option value="PG">PG (Plano General)</option>
                            <option value="PP">PP (Primer Plano)</option>
                            <option value="PD">PD (Plano Detalle)</option>
                            <option value="PA">PA (Plano Americano)</option>
                            <option value="GPG">GPG (Gran Plano General)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Descripción Técnica del Plano *</label>
                        <textarea wire:model="newShotDescription"
                                  rows="3"
                                  placeholder="Describe la cobertura visual, encuadre o movimiento de cámara..."
                                  class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white"></textarea>
                        @error('newShotDescription') <span class="text-rose-400 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Vincular a Bloque de Guión (Opcional)</label>
                        <select wire:model="newShotBlockId" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-950 border border-slate-700 text-white">
                            <option value="">-- General de la escena --</option>
                            @foreach($activeScene->scriptBlocks as $blk)
                                <option value="{{ $blk->id }}">Bloque #{{ $blk->block_order }} ({{ substr($blk->raw_text, 0, 40) }}...)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-800">
                        <button wire:click="$set('showNewShotModal', false)" class="px-3 py-1.5 text-xs text-slate-400">Cancelar</button>
                        <button wire:click="addShot" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Guardar Plano</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
