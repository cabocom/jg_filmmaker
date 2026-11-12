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
