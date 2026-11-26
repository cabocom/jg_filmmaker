<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Cabecera de la Consola TV -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📺</span>
                <span>Consola de Televisión IA</span>
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Canal activo: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $channelConfig->name }}</span>
                · Perfil: <span class="font-mono text-amber-600 dark:text-amber-400">{{ $broadcastProfile }}</span>
            </p>
        </div>

        <!-- Pestañas de Navegación -->
        <nav class="flex flex-wrap gap-1.5" aria-label="Secciones TV">
            @foreach([
                'rundowns' => 'Escaletas IA',
                'videos' => 'Vídeos',
                'schedule' => 'Parrilla 24h',
                'ads' => 'Publicidad',
                'config' => 'Configuración',
            ] as $key => $label)
                <button type="button"
                        wire:click="switchSection('{{ $key }}')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {{ $activeSection === $key
                            ? 'bg-amber-500 text-slate-950 shadow-sm'
                            : 'bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-700 dark:text-emerald-300">
            {{ $statusMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs text-rose-700 dark:text-rose-300">
            {{ $errorMessage }}
        </div>
    @endif

    <!-- Sección: Escaletas IA -->
    @if($activeSection === 'rundowns')
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Formulario de Creación -->
            <form wire:submit="createRundown" class="lg:col-span-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Nueva Escaleta IA</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Fuente</label>
                        <select wire:model.live="newSourceKind" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs">
                            <option value="wikipedia">Wikipedia URL / Tema</option>
                            <option value="manual">Guion Manual</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Formato</label>
                        <select wire:model="newType" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs">
                            <option value="pill">Píldora (pill)</option>
                            <option value="program">Programa (program)</option>
                            <option value="short">Short (short)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Título (opcional en Wikipedia)</label>
                    <input type="text" wire:model="newTitle" placeholder="Ej. Historia de la Inteligencia Artificial"
                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs" />
                    @error('newTitle') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">URL de Wikipedia o Tema</label>
                    <input type="text" wire:model="newTopicOrUrl" placeholder="https://es.wikipedia.org/wiki/..."
                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs" />
                    @error('newTopicOrUrl') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Guion / Texto base</label>
                    <textarea wire:model="newScriptText" rows="4" placeholder="Texto de locución para modo manual o borrador..."
                              class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs"></textarea>
                    @error('newScriptText') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Voz TTS</label>
                        <input type="text" wire:model="newTtsVoice"
                               class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Crédito Visual</label>
                        <input type="text" wire:model="newVisualCredit" placeholder="Fuente: Wikipedia · CC BY-SA 4.0"
                               class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-400">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="newPublishToPodcast" class="rounded border-slate-300 dark:border-slate-700" />
                        <span>Exportar Podcast MP3</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="newGenerateShort" class="rounded border-slate-300 dark:border-slate-700" />
                        <span>Generar Short 9:16</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs py-2.5 transition cursor-pointer">
                    + Crear Escaleta y Proyecto Estudio
                </button>
            </form>

            <!-- Listado de Escaletas -->
            <div class="lg:col-span-7 space-y-3">
                @forelse($rundowns as $rundown)
                    <div wire:key="rundown-{{ $rundown->id }}"
                         class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $rundown->title }}</h3>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 font-semibold uppercase">{{ $rundown->status }}</span>
                                    <span>{{ $rundown->format }}</span>
                                    <span>· {{ $rundown->source_type }}</span>
                                    @if($rundown->visual_credit)
                                        <span>· {{ $rundown->visual_credit }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($rundown->script_text)
                            <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">{{ $rundown->script_text }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            @if($rundown->project_id)
                                <a href="{{ route('projects.show', $rundown->project_id) }}"
                                   @if($embed) target="_top" @endif
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 dark:bg-slate-800 text-white hover:bg-slate-700 text-xs font-semibold transition">
                                    🎬 Abrir en Estudio
                                </a>
                            @endif

                            <button type="button"
                                    wire:click="dispatchProduction('{{ $rundown->id }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition cursor-pointer">
                                🚀 Generar Vídeo Completo
                            </button>

                            <button type="button"
                                    wire:click="deleteRundown('{{ $rundown->id }}')"
                                    class="ml-auto inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 text-xs font-semibold transition cursor-pointer">
                                Eliminar
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                        No hay escaletas creadas todavía. Crea una nueva escaleta desde el formulario.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Sección: Vídeos -->
    @if($activeSection === 'videos')
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-3">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Vídeos Broadcast</h2>
            @forelse($videos as $video)
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 text-xs">
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $video->title }}</p>
                        <p class="text-slate-500 dark:text-slate-400">{{ $video->resolution_profile }} · {{ $video->status }}</p>
                    </div>
                    @if($video->file_path)
                        <span class="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">{{ $video->file_path }}</span>
                    @endif
                </div>
            @empty
                <p class="text-xs text-slate-500 dark:text-slate-400">No hay vídeos renderizados todavía.</p>
            @endforelse
        </div>
    @endif

    <!-- Sección: Parrilla 24h -->
    @if($activeSection === 'schedule')
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-3">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Parrilla 24h</h2>
            @forelse($schedules as $schedule)
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 text-xs">
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $schedule->title ?: $schedule->air_date?->format('Y-m-d') }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 uppercase text-[11px]">{{ $schedule->status }}</span>
                </div>
            @empty
                <p class="text-xs text-slate-500 dark:text-slate-400">No hay parrillas diarias configuradas.</p>
            @endforelse
        </div>
    @endif

    <!-- Sección: Publicidad -->
    @if($activeSection === 'ads')
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-3">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Bloques de Publicidad</h2>
            @forelse($adSlots as $ad)
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 text-xs">
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $ad->name }}</p>
                        <p class="text-slate-500 dark:text-slate-400">{{ $ad->sponsor_name }} · {{ $ad->placement }}</p>
                    </div>
                    <span class="font-mono text-[11px]">{{ $ad->duration_seconds }}s</span>
                </div>
            @empty
                <p class="text-xs text-slate-500 dark:text-slate-400">No hay espacios publicitarios registrados.</p>
            @endforelse
        </div>
    @endif

    <!-- Sección: Configuración -->
    @if($activeSection === 'config')
        <form wire:submit="saveChannelConfig" class="max-w-xl rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Configuración del Canal</h2>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Perfil Broadcast</label>
                <select wire:model="broadcastProfile" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs">
                    <option value="720p30">720p30</option>
                    <option value="1080p30">1080p30</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Directorio de Vídeos</label>
                <input type="text" wire:model="videosDir" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs" />
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Directorio de Publicidad</label>
                <input type="text" wire:model="adsDir" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs" />
            </div>

            <button type="submit"
                    class="rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs px-4 py-2 transition cursor-pointer">
                Guardar Configuración
            </button>
        </form>
    @endif
</div>
