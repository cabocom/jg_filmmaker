<div>
    @if($isOpen)
        <!-- Modal Centrado de Biblia de Proyecto (Dimensiones fijas y mismo scroll que trazabilidad) -->
        <div class="fixed inset-0 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-black/90 backdrop-blur-md transition"
             style="position: fixed; inset: 0; z-index: 9999;"
             x-data
             @keydown.escape.window="if (!$wire.showDeleteModal) { $wire.close(); }"
             wire:click.self="close">
            <!-- Contenedor del Modal con PROPORCIONES FIJAS POR LOS CUATRO LADOS Y CENTRADO -->
            <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col mx-auto shrink-0 transition-none"
                 style="width: min(840px, 92vw); min-width: min(840px, 92vw); max-width: min(840px, 92vw); height: min(630px, 86vh); min-height: min(630px, 86vh); max-height: min(630px, 86vh); position: relative; z-index: 10000;">
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
                @if(session()->has('bible_error'))
                    <div class="mx-6 mt-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-500/30 dark:text-rose-300 text-xs flex items-center gap-2 shrink-0">
                        <span>⚠️</span>
                        <span>{{ session('bible_error') }}</span>
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
                                        const voice = this.$refs.newVoiceSelect ? this.$refs.newVoiceSelect.value : $wire.newCharVoiceId;
                                        const url = '{{ route('projects.tts.demo-voice', $project) }}?voice=' + encodeURIComponent(voice);
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
                                    <div x-data="{ isDragging: false }"
                                         @dragover.prevent="isDragging = true"
                                         @dragleave.prevent="isDragging = false"
                                         @drop.prevent="
                                            isDragging = false;
                                            if ($event.dataTransfer && $event.dataTransfer.files.length > 0) {
                                                $refs.newCharFileInput.files = $event.dataTransfer.files;
                                                $refs.newCharFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                            }
                                         "
                                         @click="$refs.newCharFileInput.click()"
                                         :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                                         class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                                        <input x-ref="newCharFileInput"
                                               type="file"
                                               @click.stop
                                               wire:model="newCharReferenceImage"
                                               accept="image/*"
                                               class="hidden">
                                        <span class="text-base">🖼️</span>
                                        <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                            <span class="text-amber-600 dark:text-amber-400 font-semibold">Arrastra y suelta</span> aquí o <span class="underline">haz clic para seleccionar</span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500">PNG, JPG o WEBP (máx. 10MB)</span>
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
                                    <button wire:click="createCharacter" type="button" class="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs">Guardar</button>
                                </div>
                            </div>
                        @endif

                        <!-- Lista de Personajes -->
                        @forelse($characters as $character)
                            @php
                                $customAttrs = is_array($character->custom_attributes) ? $character->custom_attributes : [];
                                $charVoice = $customAttrs['voice_id'] ?? null;
                            @endphp
                            <div wire:key="character-item-{{ $character->id }}"
                                 class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
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
                                                const voice = this.$refs.voiceSelect ? this.$refs.voiceSelect.value : $wire.editingCharVoiceId;
                                                const url = '{{ route('projects.tts.demo-voice', $project) }}?voice=' + encodeURIComponent(voice);
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
                                            <div x-data="{ isDragging: false }"
                                                 @dragover.prevent="isDragging = true"
                                                 @dragleave.prevent="isDragging = false"
                                                 @drop.prevent="
                                                    isDragging = false;
                                                    if ($event.dataTransfer && $event.dataTransfer.files.length > 0) {
                                                        $refs.editCharFileInput.files = $event.dataTransfer.files;
                                                        $refs.editCharFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                                    }
                                                 "
                                                 @click="$refs.editCharFileInput.click()"
                                                 :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                                                 class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                                                <input x-ref="editCharFileInput"
                                                       type="file"
                                                       @click.stop
                                                       wire:model="editingCharReferenceImage"
                                                       accept="image/*"
                                                       class="hidden">
                                                <span class="text-base">🖼️</span>
                                                <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                                    <span class="text-amber-600 dark:text-amber-400 font-semibold">Arrastra y suelta</span> aquí o <span class="underline">haz clic para seleccionar</span>
                                                </div>
                                                <span class="text-[10px] text-slate-400 dark:text-slate-500">PNG, JPG o WEBP (máx. 10MB)</span>
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
                                            <button wire:click="saveCharacter" class="px-2.5 py-0.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded">Guardar Cambios</button>
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
                                <div>
                                    <label class="block text-[10px] text-slate-500 dark:text-slate-400 mb-1 font-medium">Imagen de Referencia de Localización / Set:</label>
                                    <div x-data="{ isDragging: false }"
                                         @dragover.prevent="isDragging = true"
                                         @dragleave.prevent="isDragging = false"
                                         @drop.prevent="
                                            isDragging = false;
                                            if ($event.dataTransfer && $event.dataTransfer.files.length > 0) {
                                                $refs.newLocFileInput.files = $event.dataTransfer.files;
                                                $refs.newLocFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                            }
                                         "
                                         @click="$refs.newLocFileInput.click()"
                                         :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                                         class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                                        <input x-ref="newLocFileInput"
                                               type="file"
                                               @click.stop
                                               wire:model="newLocReferenceImage"
                                               accept="image/*"
                                               class="hidden">
                                        <span class="text-base">📍</span>
                                        <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                            <span class="text-amber-600 dark:text-amber-400 font-semibold">Arrastra y suelta</span> aquí o <span class="underline">haz clic para seleccionar</span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500">PNG, JPG o WEBP (máx. 10MB)</span>
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
                                    <button wire:click="createLocation" type="button" class="px-3 py-1 rounded bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs">Guardar</button>
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
                                            <div x-data="{ isDragging: false }"
                                                 @dragover.prevent="isDragging = true"
                                                 @dragleave.prevent="isDragging = false"
                                                 @drop.prevent="
                                                    isDragging = false;
                                                    if ($event.dataTransfer && $event.dataTransfer.files.length > 0) {
                                                        $refs.editLocFileInput.files = $event.dataTransfer.files;
                                                        $refs.editLocFileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                                    }
                                                 "
                                                 @click="$refs.editLocFileInput.click()"
                                                 :class="isDragging ? 'border-amber-500 bg-amber-500/10' : 'border-slate-300 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60'"
                                                 class="relative border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1">
                                                <input x-ref="editLocFileInput"
                                                       type="file"
                                                       @click.stop
                                                       wire:model="editingLocReferenceImage"
                                                       accept="image/*"
                                                       class="hidden">
                                                <span class="text-base">📍</span>
                                                <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                                    <span class="text-amber-600 dark:text-amber-400 font-semibold">Arrastra y suelta</span> aquí o <span class="underline">haz clic para seleccionar</span>
                                                </div>
                                                <span class="text-[10px] text-slate-400 dark:text-slate-500">PNG, JPG o WEBP (máx. 10MB)</span>
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
                                            <button wire:click="saveLocation" class="px-2.5 py-0.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded">Guardar</button>
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

    @if($showDeleteModal)
        <!-- ================= MODAL SUPERIOR DE CONFIRMACIÓN O BLOQUEO DE BORRADO (Z-INDEX 100000) ================= -->
        <div class="fixed inset-0 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md transition"
             style="position: fixed; inset: 0; z-index: 100000;"
             x-data
             @keydown.escape.stop.window="$wire.cancelDelete()"
             wire:click.self="cancelDelete">
            <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 m-auto"
                 style="position: relative; z-index: 100001;">
                @if(count($deletingUsages) > 0)
                    <!-- CASO 1: BLOQUEO POR USO (NO SE PUEDE BORRAR) -->
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-500 shrink-0 text-lg">
                            ⚠️
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                No se puede eliminar {{ $deletingType === 'character' ? 'el personaje' : 'la localización' }}
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                <strong class="text-amber-600 dark:text-amber-400 font-bold font-mono">"{{ $deletingName }}"</strong> está siendo utilizado actualmente en el guión cinematográfico.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
                            Aparece en {{ count($deletingUsages) }} {{ count($deletingUsages) === 1 ? 'ubicación' : 'ubicaciones' }}:
                        </span>
                        <div class="max-h-48 overflow-y-auto rounded-xl p-2 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 space-y-1.5">
                            @foreach($deletingUsages as $usage)
                                <button wire:click="inspectUsage('{{ $usage }}')"
                                        type="button"
                                        title="Ir a este bloque en el guión y abrir su panel de detalle"
                                        class="w-full flex items-center justify-between gap-2 py-2 px-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800/80 hover:border-amber-500 hover:bg-amber-50/50 dark:hover:bg-slate-800/80 transition cursor-pointer text-left group shadow-2xs">
                                    <div class="flex items-center gap-2 text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                                        <span class="text-amber-500">📌</span>
                                        <span>{{ $usage }}</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 group-hover:text-amber-700 dark:group-hover:text-amber-300">
                                        <span>Ver detalle</span>
                                        <span class="transition-transform group-hover:translate-x-0.5">➔</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">
                            Haz clic en una ubicación para abrir directamente su panel lateral y modificar o retirar el texto.
                        </p>
                    </div>

                    <div class="pt-2 flex items-center justify-between gap-2.5">
                        <button wire:click="cancelDelete"
                                type="button"
                                class="py-2.5 px-4 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 transition cursor-pointer">
                            Cancelar / Revisar
                        </button>
                        <button wire:click="forceDelete"
                                type="button"
                                title="Eliminar este elemento de la Biblia de todos modos ignorando las menciones en texto"
                                class="py-2.5 px-4 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5">
                            <span>🗑️</span>
                            <span>Eliminar de todos modos</span>
                        </button>
                    </div>
                @else
                    <!-- CASO 2: SIN USO (CONFIRMACIÓN DE BORRADO TOTAL) -->
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-500 shrink-0 text-lg">
                            🗑️
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                ¿Eliminar {{ $deletingType === 'character' ? 'personaje' : 'localización' }} permanentemente?
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                <strong class="text-slate-900 dark:text-white font-bold font-mono">"{{ $deletingName }}"</strong> no está siendo utilizado en ninguna escena ni bloque del guión.
                            </p>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 text-xs text-rose-800 dark:text-rose-300">
                        Esta acción eliminará de forma irreversible este elemento de la Biblia de proyecto.
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2.5">
                        <button wire:click="cancelDelete"
                                type="button"
                                class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmDelete"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5">
                            <span>🗑️</span>
                            <span>Eliminar definitivamente</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
