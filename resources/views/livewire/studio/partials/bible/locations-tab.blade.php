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
            <div>
                <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-1 font-medium">Imagen de Referencia de Localización / Set:</label>
                <div x-data="{
                         isDragging: false,
                         isConverting: false,
                         async processFile(file) {
                             if (!file) return;
                             await window.handleHeicAndUpload(file, (val) => { this.isConverting = val; }, (readyFile) => {
                                 const dt = new DataTransfer();
                                 dt.items.add(readyFile);
                                 $refs.newLocFileInput.files = dt.files;
                                 $refs.newLocFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                             });
                         }
                     }"
                     @dragover.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="
                        isDragging = false;
                        if ($event.dataTransfer && $event.dataTransfer.files.length > 0) {
                            processFile($event.dataTransfer.files[0]);
                        }
                     "
                     @click="$refs.newLocPickerInput.click()"
                     :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                     class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                    <input x-ref="newLocPickerInput"
                           type="file"
                           @click.stop
                           @change="if ($event.target.files.length > 0) { processFile($event.target.files[0]); $event.target.value = ''; }"
                           accept="image/*,.heic,.heif"
                           class="hidden">
                    <input x-ref="newLocFileInput"
                           type="file"
                           @click.stop
                           wire:model="newLocReferenceImage"
                           accept="image/*"
                           class="hidden">
                    <div x-show="!isConverting" class="flex flex-col items-center gap-1">
                        <span class="text-base">📍</span>
                        <div class="text-[11px] text-slate-600 dark:text-slate-300">
                            <span class="text-amber-600 dark:text-amber-400 font-semibold">Arrastra y suelta</span> aquí o <span class="underline">haz clic para seleccionar</span>
                        </div>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500">PNG, JPG, WEBP o HEIC de Mac (máx. 10MB)</span>
                    </div>
                    <div x-show="isConverting" class="flex items-center gap-2 text-amber-600 dark:text-amber-400 text-xs py-1" style="display: none;">
                        <span class="animate-spin text-sm">🔄</span>
                        <span class="font-medium">Convirtiendo formato HEIC (Apple) a JPG...</span>
                    </div>
                </div>
                <div wire:loading wire:target="newLocReferenceImage" class="text-[10px] text-amber-500 mt-1 flex items-center gap-1">
                    <span class="animate-spin">⏳</span> Cargando imagen...
                </div>
                @error('newLocReferenceImage') <span class="text-rose-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                @if ($newLocReferenceImage)
                    <div class="mt-2 flex items-center gap-2 p-1.5 rounded bg-amber-500/10 border border-amber-500/30">
                        <img src="{{ $newLocReferenceImage->temporaryUrl() }}" class="h-16 w-auto rounded border border-amber-400/60 shadow-xs object-contain bg-slate-900">
                        <div class="text-[11px] text-amber-800 dark:text-amber-300">
                            <span class="font-bold">Imagen seleccionada</span>
                        </div>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button wire:click="$set('showNewLocationForm', false)" type="button" class="px-2.5 py-1 rounded text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white">Cancelar</button>
                <button wire:click="createLocation"
                        wire:loading.attr="disabled"
                        wire:target="createLocation, newLocReferenceImage"
                        type="button"
                        class="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold text-xs inline-flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <span wire:loading.remove wire:target="createLocation">Guardar</span>
                    <span wire:loading wire:target="createLocation" class="inline-flex items-center gap-1">
                        <span class="animate-spin text-xs">⏳</span> Guardando...
                    </span>
                </button>
            </div>
        </div>
    @endif

    <!-- Lista de Localizaciones -->
    @forelse($locations as $location)
        <div wire:key="location-item-{{ $location->id }}"
             class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 space-y-2">
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
                    <div class="flex items-center gap-2">
                        <button wire:click="startEditLocation('{{ $location->id }}')"
                                type="button"
                                class="px-2 py-1 rounded-lg text-xs font-medium text-slate-700 hover:text-amber-700 bg-slate-100 hover:bg-amber-50 dark:text-slate-300 dark:hover:text-amber-400 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition cursor-pointer flex items-center gap-1">
                            <span>✏️</span> <span>Editar</span>
                        </button>
                        <button wire:click.prevent="requestDeleteLocation('{{ $location->id }}')"
                                type="button"
                                title="Eliminar localización de la biblia"
                                class="px-2 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:text-white bg-rose-50 hover:bg-rose-600 dark:text-rose-400 dark:hover:text-white dark:bg-rose-950/40 dark:hover:bg-rose-600 border border-rose-200 dark:border-rose-800/80 transition cursor-pointer flex items-center gap-1 shadow-xs">
                            <span>🗑️</span> <span>Eliminar</span>
                        </button>
                    </div>
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
                    <div>
                        <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-1 font-medium">Imagen de Referencia de Localización / Set:</label>
                        @if ($location->hasReferenceImage() && ! $editingLocReferenceImage)
                            <div class="flex items-center gap-3 mb-2 p-1.5 rounded bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <img src="{{ $location->reference_image_url }}" class="h-12 w-auto rounded object-contain bg-slate-950">
                                <button type="button"
                                        wire:click="deleteLocationReferenceImage('{{ $location->id }}')"
                                        class="text-[10px] text-rose-500 hover:text-rose-400 font-semibold flex items-center gap-1 cursor-pointer">
                                    <span>🗑️ Eliminar imagen actual</span>
                                </button>
                            </div>
                        @endif
                        <div x-data="{
                                 isDragging: false,
                                 isConverting: false,
                                 async processFile(file) {
                                     if (!file) return;
                                     await window.handleHeicAndUpload(file, (val) => { this.isConverting = val; }, (readyFile) => {
                                         const dt = new DataTransfer();
                                         dt.items.add(readyFile);
                                         $refs.editLocFileInput.files = dt.files;
                                         $refs.editLocFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                     });
                                 }
                             }"
                             @dragover.prevent="isDragging = true"
                             @dragleave.prevent="isDragging = false"
                             @drop.prevent="
                                isDragging = false;
                                if ($event.dataTransfer && $event.dataTransfer.files.length > 0) {
                                    processFile($event.dataTransfer.files[0]);
                                }
                             "
                             @click="$refs.editLocPickerInput.click()"
                             :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                             class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                            <input x-ref="editLocPickerInput"
                                   type="file"
                                   @click.stop
                                   @change="if ($event.target.files.length > 0) { processFile($event.target.files[0]); $event.target.value = ''; }"
                                   accept="image/*,.heic,.heif"
                                   class="hidden">
                            <input x-ref="editLocFileInput"
                                   type="file"
                                   @click.stop
                                   wire:model="editingLocReferenceImage"
                                   accept="image/*"
                                   class="hidden">
                            <div x-show="!isConverting" class="flex flex-col items-center gap-1">
                                <span class="text-base">📍</span>
                                <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold">Arrastra y suelta</span> aquí o <span class="underline">haz clic para seleccionar</span>
                                </div>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500">PNG, JPG, WEBP o HEIC de Mac (máx. 10MB)</span>
                            </div>
                            <div x-show="isConverting" class="flex items-center gap-2 text-amber-600 dark:text-amber-400 text-xs py-1" style="display: none;">
                                <span class="animate-spin text-sm">🔄</span>
                                <span class="font-medium">Convirtiendo formato HEIC (Apple) a JPG...</span>
                            </div>
                        </div>
                        <div wire:loading wire:target="editingLocReferenceImage" class="text-[10px] text-amber-500 mt-1 flex items-center gap-1">
                            <span class="animate-spin">⏳</span> Subiendo imagen...
                        </div>
                        @error('editingLocReferenceImage') <span class="text-rose-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                        @if ($editingLocReferenceImage)
                            <div class="mt-2 flex items-center gap-2 p-1.5 rounded bg-amber-500/10 border border-amber-500/30">
                                <img src="{{ $editingLocReferenceImage->temporaryUrl() }}" class="h-16 w-auto rounded border border-amber-400/60 shadow-xs object-contain bg-slate-900">
                                <div class="text-[11px] text-amber-800 dark:text-amber-300">
                                    <span class="font-bold">Nueva imagen lista</span>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400">Pulsa "Guardar" para confirmar.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button wire:click="$set('editingLocationId', null)" class="px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                        <button wire:click="saveLocation"
                                wire:loading.attr="disabled"
                                wire:target="saveLocation, editingLocReferenceImage"
                                class="px-3 py-1 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold text-xs rounded transition inline-flex items-center gap-1.5 cursor-pointer shadow-xs">
                            <span wire:loading.remove wire:target="saveLocation">Guardar</span>
                            <span wire:loading wire:target="saveLocation" class="inline-flex items-center gap-1">
                                <span class="animate-spin text-xs">⏳</span> Guardando...
                            </span>
                        </button>
                    </div>
                </div>
            @else
                <!-- Modo Lectura -->
                @if($location->hasReferenceImage())
                    <div class="my-2 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-950 aspect-video max-h-36">
                        <img src="{{ $location->reference_image_url }}" alt="{{ $location->name }}" class="w-full h-full object-cover">
                    </div>
                @endif

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
