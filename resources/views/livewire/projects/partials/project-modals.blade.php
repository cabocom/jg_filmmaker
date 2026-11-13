    <!-- Modal para Nuevo Proyecto -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🎬</span>
                        <span>Crear Nuevo Proyecto Cinematográfico</span>
                    </h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                @error('quota')
                    <div class="mt-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="createProject" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="title"
                               placeholder="Ej: El Laberinto de las Sombras"
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm">
                        @error('title') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis Preliminar</label>
                        <textarea wire:model="synopsis"
                                  rows="3"
                                  placeholder="Describe la premisa dramática, el tono y el arco narrativo general..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                        @error('synopsis') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Estado Inicial</label>
                        <select wire:model="status" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                            <option value="draft">Borrador (Desarrollo Temprano)</option>
                            <option value="in_progress">En Progreso / Producción</option>
                            <option value="completed">Completado</option>
                        </select>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showCreateModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            Crear e Iniciar Estudio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal para Modificar Nombre / Detalles del Proyecto -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✏️</span>
                        <span>Modificar Proyecto Cinematográfico</span>
                    </h3>
                    <button wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                <form wire:submit="updateProject" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="editTitle"
                               placeholder="Título del proyecto..."
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm">
                        @error('editTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis</label>
                        <textarea wire:model="editSynopsis"
                                  rows="3"
                                  placeholder="Sinopsis o notas generales..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                        @error('editSynopsis') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Estado</label>
                        <select wire:model="editStatus" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                            <option value="draft">Borrador (Desarrollo Temprano)</option>
                            <option value="in_progress">En Progreso / Producción</option>
                            <option value="completed">Completado</option>
                            <option value="archived">Archivado</option>
                        </select>
                        @error('editStatus') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showEditModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

