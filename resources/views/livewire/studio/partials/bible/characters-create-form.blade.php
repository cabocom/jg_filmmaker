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

