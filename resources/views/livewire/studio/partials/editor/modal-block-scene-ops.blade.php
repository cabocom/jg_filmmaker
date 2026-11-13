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

