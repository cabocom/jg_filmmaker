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
                <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5">Notas de Vestuario:</label>
                <textarea wire:model="newCharCostume"
                          rows="2"
                          placeholder="Vestuario característico (ej: Gabardina gris desgastada, botas de cuero)..."
                          class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <div>
                <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5 font-medium">Importancia en la historia:</label>
                <select wire:model="newCharImportance"
                        class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                    <option value="">Automática (calculada según apariciones)</option>
                    <option value="protagonista">Protagonista</option>
                    <option value="secundario">Secundario</option>
                    <option value="reparto">Reparto / Menor</option>
                </select>
            </div>
            <div x-data="{
                isPlaying: false,
                audio: null,
                error: false,
                playDemo() {
                    if (this.isPlaying && this.audio) {
                        this.audio.pause();
                        this.audio = null;
                        this.isPlaying = false;
                        return;
                    }
                    this.error = false;
                    const selectEl = this.$el.querySelector('select');
                    const voice = selectEl ? selectEl.value : $wire.newCharVoiceId;
                    const url = '{{ route('projects.tts.demo-voice', $project, false) }}?voice=' + encodeURIComponent(voice);
                    this.audio = new Audio(url);
                    this.isPlaying = true;
                    this.audio.onended = () => { this.isPlaying = false; this.audio = null; };
                    this.audio.onerror = () => { this.isPlaying = false; this.audio = null; this.error = true; };
                    this.audio.play().catch(() => { this.isPlaying = false; this.audio = null; this.error = true; });
                }
            }" class="space-y-1">
                <div class="flex items-center justify-between">
                    <label class="block text-[10px] text-slate-500 dark:text-slate-400 font-medium">Voz para lectura dramática (TTS):</label>
                    <button type="button"
                            @click="playDemo()"
                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold transition cursor-pointer shadow-2xs"
                            :class="isPlaying ? 'bg-amber-500 text-slate-950 hover:bg-amber-400 ring-2 ring-amber-400 animate-pulse' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80 hover:bg-indigo-100 dark:hover:bg-indigo-900/60'"
                            title="Escuchar demo de esta voz">
                        <span x-show="!isPlaying" class="flex items-center gap-1">
                            <span>🔊</span> <span>Probar voz</span>
                        </span>
                        <span x-show="isPlaying" class="flex items-center gap-1 text-slate-950">
                            <span>⏹️</span> <span>Detener</span>
                        </span>
                        <span x-show="error" class="text-rose-500 text-[10px] font-normal" style="display: none;">⚠️ Error</span>
                    </button>
                </div>
                <select x-ref="newVoiceSelect"
                        wire:model.live="newCharVoiceId"
                        class="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white">
                    @foreach($availableVoices as $v)
                        <option value="{{ $v['id'] }}">{{ $v['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-1 font-medium">Imagen de Referencia / Turn-Around (Storyboard):</label>
                <div x-data="{
                         isDragging: false,
                         isConverting: false,
                         async processFile(file) {
                             if (!file) return;
                             await window.handleHeicAndUpload(file, (val) => { this.isConverting = val; }, (readyFile) => {
                                 const dt = new DataTransfer();
                                 dt.items.add(readyFile);
                                 $refs.newCharFileInput.files = dt.files;
                                 $refs.newCharFileInput.dispatchEvent(new Event('change', { bubbles: true }));
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
                     @click="$refs.newCharPickerInput.click()"
                     :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                     class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                    <input x-ref="newCharPickerInput"
                           type="file"
                           @click.stop
                           @change="if ($event.target.files.length > 0) { processFile($event.target.files[0]); $event.target.value = ''; }"
                           accept="image/*,.heic,.heif"
                           class="hidden">
                    <input x-ref="newCharFileInput"
                           type="file"
                           @click.stop
                           wire:model="newCharReferenceImage"
                           accept="image/*"
                           class="hidden">
                    <div x-show="!isConverting" class="flex flex-col items-center gap-1">
                        <span class="text-base">🖼️</span>
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
                <div wire:loading wire:target="newCharReferenceImage" class="text-[10px] text-amber-500 mt-1 flex items-center gap-1">
                    <span class="animate-spin">⏳</span> Cargando imagen...
                </div>
                @error('newCharReferenceImage') <span class="text-rose-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                @if ($newCharReferenceImage)
                    <div class="mt-2 flex items-center gap-2 p-1.5 rounded bg-amber-500/10 border border-amber-500/30">
                        <img src="{{ $newCharReferenceImage->temporaryUrl() }}" class="h-16 w-auto rounded border border-amber-400/60 shadow-xs object-contain bg-slate-900">
                        <div class="text-[11px] text-amber-800 dark:text-amber-300">
                            <span class="font-bold">Imagen seleccionada</span>
                        </div>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button wire:click="$set('showNewCharacterForm', false)" type="button" class="px-2.5 py-1 rounded text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white">Cancelar</button>
                <button wire:click="createCharacter"
                        wire:loading.attr="disabled"
                        wire:target="createCharacter, newCharReferenceImage"
                        type="button"
                        class="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold text-xs inline-flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <span wire:loading.remove wire:target="createCharacter">Guardar</span>
                    <span wire:loading wire:target="createCharacter" class="inline-flex items-center gap-1">
                        <span class="animate-spin text-xs">⏳</span> Guardando...
                    </span>
                </button>
            </div>
        </div>
    @endif

    <!-- Lista de Personajes -->
    @forelse($characters as $character)
        @php
            $customAttrs = is_array($character->custom_attributes) ? $character->custom_attributes : [];
            $charVoice = $customAttrs['voice_id'] ?? null;
            $importanceInfo = $this->resolveCharacterImportance($character, $characterInterventions ?? []);
            $importanceLevel = $importanceInfo['level'];
            $interventionsCount = $importanceInfo['count'];
        @endphp
        <div wire:key="character-item-{{ $character->id }}"
             class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    @if($importanceLevel === 'protagonista')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-500 text-slate-950 shadow-2xs"
                              title="{{ $interventionsCount }} intervenciones detectadas en el guión">
                            Protagonista
                        </span>
                    @elseif($importanceLevel === 'secundario')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/40"
                              title="{{ $interventionsCount }} intervenciones detectadas en el guión">
                            Secundario
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700"
                              title="{{ $interventionsCount }} intervenciones detectadas en el guión">
                            Reparto
                        </span>
                    @endif
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        {{ $character->name }}
                    </h3>
                    @if($charVoice)
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 flex items-center gap-1 font-medium">
                            <span>🎙️</span> <span>{{ $charVoice }}</span>
                        </span>
                    @endif
                </div>
                @if($editingCharacterId !== $character->id)
                    <div class="flex items-center gap-2">
                        <button wire:click="startEditCharacter('{{ $character->id }}')"
                                type="button"
                                class="px-2 py-1 rounded-lg text-xs font-medium text-slate-700 hover:text-amber-700 bg-slate-100 hover:bg-amber-50 dark:text-slate-300 dark:hover:text-amber-400 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition cursor-pointer flex items-center gap-1">
                            <span>✏️</span> <span>Editar</span>
                        </button>
                        <button wire:click.prevent="requestDeleteCharacter('{{ $character->id }}')"
                                type="button"
                                title="Eliminar personaje de la biblia"
                                class="px-2 py-1 rounded-lg text-xs font-semibold text-rose-600 hover:text-white bg-rose-50 hover:bg-rose-600 dark:text-rose-400 dark:hover:text-white dark:bg-rose-950/40 dark:hover:bg-rose-600 border border-rose-200 dark:border-rose-800/80 transition cursor-pointer flex items-center gap-1 shadow-xs">
                            <span>🗑️</span> <span>Eliminar</span>
                        </button>
                    </div>
                @endif
            </div>

            @if($character->psychology && $character->psychology !== 'Interviene activamente en el desarrollo de la escena.')
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
                        <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5 font-medium">Notas de Vestuario:</label>
                        <textarea wire:model="editingCharCostume" rows="2" placeholder="Vestuario característico (ej: Gabardina gris desgastada, botas de cuero)..." class="w-full px-2.5 py-1.5 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-0.5 font-medium">Importancia en la historia:</label>
                        <select wire:model="editingCharImportance"
                                class="w-full px-2.5 py-1 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                            <option value="">Automática (según intervenciones: {{ $interventionsCount }} diálogos)</option>
                            <option value="protagonista">Protagonista</option>
                            <option value="secundario">Secundario</option>
                            <option value="reparto">Reparto / Menor</option>
                        </select>
                    </div>
                    <div x-data="{
                        isPlaying: false,
                        audio: null,
                        error: false,
                        playDemo() {
                            if (this.isPlaying && this.audio) {
                                this.audio.pause();
                                this.audio = null;
                                this.isPlaying = false;
                                return;
                            }
                            this.error = false;
                            const selectEl = this.$el.querySelector('select');
                            const voice = selectEl ? selectEl.value : $wire.editingCharVoiceId;
                            const url = '{{ route('projects.tts.demo-voice', $project, false) }}?voice=' + encodeURIComponent(voice);
                            this.audio = new Audio(url);
                            this.isPlaying = true;
                            this.audio.onended = () => { this.isPlaying = false; this.audio = null; };
                            this.audio.onerror = () => { this.isPlaying = false; this.audio = null; this.error = true; };
                            this.audio.play().catch(() => { this.isPlaying = false; this.audio = null; this.error = true; });
                        }
                    }" class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block text-[10px] text-slate-500 dark:text-slate-400 font-medium">Voz para lectura dramática (TTS):</label>
                            <button type="button"
                                    @click="playDemo()"
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold transition cursor-pointer shadow-2xs"
                                    :class="isPlaying ? 'bg-amber-500 text-slate-950 hover:bg-amber-400 ring-2 ring-amber-400 animate-pulse' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80 hover:bg-indigo-100 dark:hover:bg-indigo-900/60'"
                                    title="Escuchar demo de esta voz">
                                <span x-show="!isPlaying" class="flex items-center gap-1">
                                    <span>🔊</span> <span>Probar voz</span>
                                </span>
                                <span x-show="isPlaying" class="flex items-center gap-1 text-slate-950">
                                    <span>⏹️</span> <span>Detener</span>
                                </span>
                                <span x-show="error" class="text-rose-500 text-[10px] font-normal" style="display: none;">⚠️ Error</span>
                            </button>
                        </div>
                        <select x-ref="voiceSelect"
                                wire:model.live="editingCharVoiceId"
                                class="w-full px-2.5 py-1 text-xs rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                            @foreach($availableVoices as $v)
                                <option value="{{ $v['id'] }}">{{ $v['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-1 font-medium">Imagen de Referencia / Turn-Around:</label>
                        @if ($character->hasReferenceImage() && ! $editingCharReferenceImage)
                            <div class="flex items-center gap-3 mb-2 p-1.5 rounded bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <img src="{{ $character->reference_image_url }}" class="h-12 w-auto rounded object-contain bg-slate-950">
                                <button type="button"
                                        wire:click="deleteCharacterReferenceImage('{{ $character->id }}')"
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
                                         $refs.editCharFileInput.files = dt.files;
                                         $refs.editCharFileInput.dispatchEvent(new Event('change', { bubbles: true }));
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
                             @click="$refs.editCharPickerInput.click()"
                             :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                             class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                            <input x-ref="editCharPickerInput"
                                   type="file"
                                   @click.stop
                                   @change="if ($event.target.files.length > 0) { processFile($event.target.files[0]); $event.target.value = ''; }"
                                   accept="image/*,.heic,.heif"
                                   class="hidden">
                            <input x-ref="editCharFileInput"
                                   type="file"
                                   @click.stop
                                   wire:model="editingCharReferenceImage"
                                   accept="image/*"
                                   class="hidden">
                            <div x-show="!isConverting" class="flex flex-col items-center gap-1">
                                <span class="text-base">🖼️</span>
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
                        <div wire:loading wire:target="editingCharReferenceImage" class="text-[10px] text-amber-500 mt-1 flex items-center gap-1">
                            <span class="animate-spin">⏳</span> Subiendo imagen...
                        </div>
                        @error('editingCharReferenceImage') <span class="text-rose-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                        @if ($editingCharReferenceImage)
                            <div class="mt-2 flex items-center gap-2 p-1.5 rounded bg-amber-500/10 border border-amber-500/30">
                                <img src="{{ $editingCharReferenceImage->temporaryUrl() }}" class="h-16 w-auto rounded border border-amber-400/60 shadow-xs object-contain bg-slate-900">
                                <div class="text-[11px] text-amber-800 dark:text-amber-300">
                                    <span class="font-bold">Nueva imagen lista</span>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400">Pulsa "Guardar Cambios" para confirmar.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button wire:click="$set('editingCharacterId', null)" class="px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                        <button wire:click="saveCharacter"
                                wire:loading.attr="disabled"
                                wire:target="saveCharacter, editingCharReferenceImage"
                                class="px-3 py-1 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-bold text-xs rounded transition inline-flex items-center gap-1.5 cursor-pointer shadow-xs">
                            <span wire:loading.remove wire:target="saveCharacter">Guardar Cambios</span>
                            <span wire:loading wire:target="saveCharacter" class="inline-flex items-center gap-1">
                                <span class="animate-spin text-xs">⏳</span> Guardando...
                            </span>
                        </button>
                    </div>
                </div>
            @else
                <!-- Modo Lectura -->
                @if($character->hasReferenceImage())
                    <div class="mt-2 flex items-center gap-3 p-2 bg-slate-100/90 dark:bg-slate-900/90 rounded-lg border border-slate-200 dark:border-slate-800">
                        <a href="{{ $character->reference_image_url }}" target="_blank" title="Ver hoja de referencia completa" class="shrink-0 group relative block">
                            <img src="{{ $character->reference_image_url }}" alt="{{ $character->name }}" class="h-14 w-auto max-w-[120px] rounded object-cover border border-slate-300 dark:border-slate-700 group-hover:opacity-90">
                            <span class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/40 rounded text-white text-[10px] font-bold">🔍</span>
                        </a>
                        <div class="text-[11px] text-slate-600 dark:text-slate-400 space-y-0.5">
                            <span class="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-1">
                                <span>🎨</span> Hoja de Diseño / Turn-Around
                            </span>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Condicionará la apariencia física del personaje en la generación de storyboards.</p>
                        </div>
                    </div>
                @endif

                @if($character->bio)
                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ $character->bio }}
                    </p>
                @endif

                @if($character->costume_notes)
                    <div class="text-[11px] bg-slate-100 dark:bg-slate-900/80 px-2.5 py-1.5 rounded border border-slate-200 dark:border-slate-800/80 text-slate-700 dark:text-slate-300">
                        <span class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1 mb-0.5">
                            <span>👔</span> Vestuario:
                        </span>
                        <p class="whitespace-pre-line leading-relaxed">{{ $character->costume_notes }}</p>
                    </div>
                @endif
            @endif
        </div>
    @empty
        <p class="text-xs text-slate-400 text-center py-6">No hay personajes registrados en la biblia.</p>
    @endforelse
@endif
