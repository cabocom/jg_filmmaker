<div>
    @if($isOpen)
        <!-- Modal Centrado de Biblia de Proyecto (Dimensiones fijas y mismo scroll que trazabilidad) -->
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-black/90 backdrop-blur-md transition"
             x-data
             @keydown.escape.window="$wire.close()"
             wire:click.self="close">
            <!-- Contenedor del Modal con PROPORCIONES FIJAS POR LOS CUATRO LADOS Y CENTRADO -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col mx-auto shrink-0 transition-none"
                 style="width: min(840px, 92vw); min-width: min(840px, 92vw); max-width: min(840px, 92vw); height: min(630px, 86vh); min-height: min(630px, 86vh); max-height: min(630px, 86vh);">
                <!-- Header del Modal (Fijo) -->
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 flex items-center justify-between gap-4 shrink-0" style="flex-shrink: 0;">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📖</span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span>Biblia Contextual del Proyecto</span>
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Memoria narrativa que alimenta a la IA durante el pipeline cinematográfico.
                            </p>
                        </div>
                    </div>
                    <!-- Botón explícito para cerrar con icono y texto -->
                    <button wire:click="close"
                            type="button"
                            title="Cerrar modal (Esc)"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer shadow-xs ml-1">
                        <span>✕</span>
                        <span class="hidden sm:inline">Cerrar</span>
                    </button>
                </div>

                <!-- Notificaciones Flash -->
                @if(session()->has('bible_success'))
                    <div class="mx-6 mt-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-500/30 dark:text-emerald-300 text-xs flex items-center gap-2 shrink-0">
                        <span>✓</span>
                        <span>{{ session('bible_success') }}</span>
                    </div>
                @endif

                <!-- Pestañas: Personajes vs Localizaciones (Fijo) -->
                <div class="flex border-b border-slate-200 dark:border-slate-800 px-6 pt-2 gap-2 bg-white dark:bg-slate-900 shrink-0" style="flex-shrink: 0;">
                    <button wire:click="$set('activeTab', 'characters')"
                            type="button"
                            class="pb-2.5 px-3.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5 {{ $activeTab === 'characters' ? 'border-amber-500 text-amber-700 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' }}">
                        <span>👥 Personajes</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $characters->count() }}</span>
                    </button>
                    <button wire:click="$set('activeTab', 'locations')"
                            type="button"
                            class="pb-2.5 px-3.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5 {{ $activeTab === 'locations' ? 'border-amber-500 text-amber-700 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' }}">
                        <span>📍 Localizaciones</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $locations->count() }}</span>
                    </button>
                </div>

                <!-- Cuerpo del Modal con scroll interno idéntico a trazabilidad -->
                <div class="p-6 bg-white dark:bg-slate-900 space-y-4" style="flex: 1 1 0%; min-height: 0; overflow-y: auto; overflow-x: hidden;">
                    <!-- ================= TAB: PERSONAJES ================= -->
                    @if($activeTab === 'characters')
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Personajes registrados en la historia</span>
                            <button wire:click="$toggle('showNewCharacterForm')"
                                    type="button"
                                    class="text-xs text-amber-700 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300 font-semibold flex items-center gap-1">
                                <span>+</span>
                                <span>{{ $showNewCharacterForm ? 'Cerrar Formulario' : 'Nuevo Personaje' }}</span>
                            </button>
                        </div>

                        <!-- Formulario para agregar personaje -->
                        @if($showNewCharacterForm)
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-amber-300 dark:border-amber-500/30 space-y-3 mb-4">
                                <h4 class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Nuevo Personaje</h4>
                                <div>
                                    <input type="text"
                                           wire:model="newCharName"
                                           placeholder="Nombre (ej: Javier)"
                                           class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                    @error('newCharName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <textarea wire:model="newCharBio"
                                              rows="2"
                                              placeholder="Biografía y trasfondo narrativo..."
                                              class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white"></textarea>
                                </div>
                                <div>
                                    <input type="text"
                                           wire:model="newCharPsychology"
                                           placeholder="Perfil psicológico (ej: Observador, escéptico)..."
                                           class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                </div>
                                <div>
                                    <input type="text"
                                           wire:model="newCharCostume"
                                           placeholder="Vestuario característico (ej: Gabardina gris)..."
                                           class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button wire:click="$set('showNewCharacterForm', false)" type="button" class="px-2.5 py-1 rounded text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white">Cancelar</button>
                                    <button wire:click="createCharacter" type="button" class="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs">Guardar</button>
                                </div>
                            </div>
                        @endif

                        <!-- Lista de Personajes -->
                        @forelse($characters as $character)
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        {{ $character->name }}
                                    </h3>
                                    @if($editingCharacterId !== $character->id)
                                        <button wire:click="startEditCharacter('{{ $character->id }}')"
                                                class="text-[11px] text-slate-500 hover:text-amber-600 dark:text-slate-400 dark:hover:text-amber-400 transition">
                                            ✏️ Editar
                                        </button>
                                    @endif
                                </div>

                                @if($character->psychology)
                                    <p class="text-xs text-amber-800 dark:text-amber-300/80 italic">
                                        {{ $character->psychology }}
                                    </p>
                                @endif

                                @if($editingCharacterId === $character->id)
                                    <!-- Modo Edición Inline -->
                                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                        <div>
                                            <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">Biografía:</label>
                                            <textarea wire:model="editingCharBio" rows="2" class="w-full px-2.5 py-1.5 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">Notas de Vestuario:</label>
                                            <input type="text" wire:model="editingCharCostume" class="w-full px-2.5 py-1 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                                        </div>
                                        <div class="flex justify-end gap-2 pt-1">
                                            <button wire:click="$set('editingCharacterId', null)" class="px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                                            <button wire:click="saveCharacter" class="px-2.5 py-0.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded">Guardar Cambios</button>
                                        </div>
                                    </div>
                                @else
                                    <!-- Modo Lectura -->
                                    @if($character->bio)
                                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                            {{ $character->bio }}
                                        </p>
                                    @endif

                                    @if($character->costume_notes)
                                        <div class="text-[11px] bg-slate-100 dark:bg-slate-900/80 px-2.5 py-1 rounded border border-slate-200 dark:border-slate-800/80 text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                            <span>👔</span>
                                            <span>{{ $character->costume_notes }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 text-center py-6">No hay personajes registrados en la biblia.</p>
                        @endforelse
                    @endif

                    <!-- ================= TAB: LOCALIZACIONES ================= -->
                    @if($activeTab === 'locations')
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Localizaciones registradas</span>
                            <button wire:click="$toggle('showNewLocationForm')"
                                    type="button"
                                    class="text-xs text-amber-700 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300 font-semibold flex items-center gap-1">
                                <span>+</span>
                                <span>{{ $showNewLocationForm ? 'Cerrar Formulario' : 'Nueva Localización' }}</span>
                            </button>
                        </div>

                        <!-- Formulario para agregar localización -->
                        @if($showNewLocationForm)
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-amber-300 dark:border-amber-500/30 space-y-3 mb-4">
                                <h4 class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Nueva Localización</h4>
                                <div>
                                    <input type="text"
                                           wire:model="newLocName"
                                           placeholder="Nombre (ej: BAR CENTRAL)"
                                           class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                    @error('newLocName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <select wire:model="newLocType" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                        <option value="INT">INT (Interior)</option>
                                        <option value="EXT">EXT (Exterior)</option>
                                        <option value="INT/EXT">INT/EXT</option>
                                    </select>
                                    <input type="text"
                                           wire:model="newLocAddress"
                                           placeholder="Referencia dirección..."
                                           class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                </div>
                                <div>
                                    <textarea wire:model="newLocArtNotes"
                                              rows="2"
                                              placeholder="Notas de Departamento de Arte..."
                                              class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white"></textarea>
                                </div>
                                <div>
                                    <input type="text"
                                           wire:model="newLocLightingNotes"
                                           placeholder="Notas de Iluminación (ej: Luz cenital fría)..."
                                           class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button wire:click="$set('showNewLocationForm', false)" type="button" class="px-2.5 py-1 rounded text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white">Cancelar</button>
                                    <button wire:click="createLocation" type="button" class="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs">Guardar</button>
                                </div>
                            </div>
                        @endif

                        <!-- Lista de Localizaciones -->
                        @forelse($locations as $location)
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-200 text-amber-800 border border-slate-300 dark:bg-slate-800 dark:text-amber-400 dark:border-slate-700">
                                            {{ $location->type }}
                                        </span>
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">
                                            {{ $location->name }}
                                        </h3>
                                    </div>
                                    @if($editingLocationId !== $location->id)
                                        <button wire:click="startEditLocation('{{ $location->id }}')"
                                                class="text-[11px] text-slate-500 hover:text-amber-600 dark:text-slate-400 dark:hover:text-amber-400 transition">
                                            ✏️ Editar
                                        </button>
                                    @endif
                                </div>

                                @if($editingLocationId === $location->id)
                                    <!-- Modo Edición Inline -->
                                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                        <div>
                                            <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">Notas de Arte:</label>
                                            <textarea wire:model="editingLocArtNotes" rows="2" class="w-full px-2.5 py-1.5 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">Notas de Iluminación:</label>
                                            <input type="text" wire:model="editingLocLightingNotes" class="w-full px-2.5 py-1 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                                        </div>
                                        <div class="flex justify-end gap-2 pt-1">
                                            <button wire:click="$set('editingLocationId', null)" class="px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                                            <button wire:click="saveLocation" class="px-2.5 py-0.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded">Guardar</button>
                                        </div>
                                    </div>
                                @else
                                    <!-- Modo Lectura -->
                                    @if($location->art_department_notes)
                                        <div class="text-xs text-slate-600 dark:text-slate-400">
                                            <span class="text-slate-500 font-semibold">Arte:</span> {{ $location->art_department_notes }}
                                        </div>
                                    @endif

                                    @if($location->lighting_notes)
                                        <div class="text-[11px] bg-slate-100 dark:bg-slate-900/80 px-2.5 py-1 rounded border border-slate-200 dark:border-slate-800/80 text-amber-800 dark:text-amber-300/80 flex items-center gap-1.5">
                                            <span>💡</span>
                                            <span>{{ $location->lighting_notes }}</span>
                                        </div>
                                    @endif

                                    @if($location->address_reference)
                                        <p class="text-[11px] text-slate-500">📍 {{ $location->address_reference }}</p>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 text-center py-6">No hay localizaciones registradas en la biblia.</p>
                        @endforelse
                    @endif
                </div>

                <!-- Footer del Modal (Fijo) -->
                <div class="px-6 py-3.5 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0" style="flex-shrink: 0;">
                    <span class="text-xs text-slate-400 font-mono hidden sm:inline">Pulsa <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 text-[10px]">Esc</kbd> para salir</span>
                    <button wire:click="close"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-slate-200 dark:text-slate-900 transition shadow-sm cursor-pointer ml-auto">
                        Cerrar (Esc)
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
