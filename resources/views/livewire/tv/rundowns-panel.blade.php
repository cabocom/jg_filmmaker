<div x-data="{ progressModal: null, showCreateForm: false, previewUrl: null, previewTitle: '', openPreview(url, title) { this.previewUrl = url; this.previewTitle = title; }, closePreview() { if (this.$refs.previewPlayer) { this.$refs.previewPlayer.pause(); this.$refs.previewPlayer.removeAttribute('src'); this.$refs.previewPlayer.load(); } this.previewUrl = null; this.previewTitle = ''; } }" @keydown.escape.window="progressModal = null; showCreateForm = false; if (previewUrl) closePreview()" class="space-y-5">
    @if ($hasBusyRundowns)
        <div wire:poll.4s class="hidden"></div>
    @endif
    @if (session()->has('success'))
        <div class="rounded-lg bg-emerald-900 border border-emerald-500 p-4 text-emerald-50 text-sm flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button type="button" wire:click="$refresh" class="text-emerald-200 hover:text-white">×</button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-lg bg-rose-900 border border-rose-500 p-4 text-rose-50 text-sm flex items-center justify-between">
            <span>{{ session('error') }}</span>
            <button type="button" wire:click="$refresh" class="text-rose-200 hover:text-white">×</button>
        </div>
    @endif

    <div x-show="showCreateForm" x-cloak class="rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-sm text-slate-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                <span>Nueva Escaleta de Emisión</span>
            </h3>
            <button type="button" @click="showCreateForm = false" class="text-xs text-slate-400 hover:text-slate-200 cursor-pointer">✕ Cerrar</button>
        </div>

        <form wire:submit="createRundown" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Origen / Fuente</label>
                    <select wire:model.live="sourceKind" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                        <option value="wikipedia">Wikipedia / URL</option>
                        <option value="manual">Manual / Prompt</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Tipo de Escaleta</label>
                    <select wire:model="rundownType" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                        <option value="pill">Píldora (pill)</option>
                        <option value="news">Noticia (news)</option>
                        <option value="debate">Debate (debate)</option>
                        <option value="music">Música (music)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Idioma</label>
                    <select wire:model="language" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                        <option value="es">Español (es)</option>
                        <option value="en">English (en)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Título</label>
                    <input type="text" wire:model="title" placeholder="Ej: Lanzamiento Claude 3.5 Sonnet" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none" required />
                    @error('title') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">
                        @if ($sourceKind === 'wikipedia')
                            URL o Término de Wikipedia
                        @else
                            Tema / Referencia URL (opcional)
                        @endif
                    </label>
                    <input type="text" wire:model="topicOrUrl" placeholder="{{ $sourceKind === 'wikipedia' ? 'https://es.wikipedia.org/wiki/... o tema' : 'https://... o referencia' }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none" @if ($sourceKind === 'wikipedia') required @endif />
                    @error('topicOrUrl') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Proveedor TTS</label>
                    <select wire:model="ttsProvider" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                        <option value="gemini">Gemini TTS</option>
                        <option value="kokoro">Kokoro TTS</option>
                        <option value="elevenlabs">ElevenLabs</option>
                        <option value="edge">Edge TTS</option>
                    </select>
                    @error('ttsProvider') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Voz TTS</label>
                    <input type="text" wire:model="ttsVoice" list="create-tts-voices" placeholder="Ej: Kore, Orus, Puck, Aoede" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none" required />
                    <datalist id="create-tts-voices">
                        <option value="Kore">
                        <option value="Orus">
                        <option value="Puck">
                        <option value="Aoede">
                    </datalist>
                    @error('ttsVoice') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Estilo Visual</label>
                    <select wire:model="visualStyleId" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                        <option value="">Sin estilo específico</option>
                        @foreach ($visualStyles as $style)
                            <option value="{{ $style->id }}">{{ $style->name }}@if ($style->is_default) (Por defecto)@endif</option>
                        @endforeach
                    </select>
                    @error('visualStyleId') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            @if ($sourceKind === 'manual')
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Guion / Contenido inicial</label>
                    <textarea wire:model="scriptText" rows="3" placeholder="Escribe o pega aquí el guion..." class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"></textarea>
                    @error('scriptText') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-6 pt-1">
                <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                    <input type="checkbox" wire:model="publishToPodcast" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                    <span>Publicar en Podcast</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                    <input type="checkbox" wire:model="generateShort" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                    <span>Generar Short</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                    <input type="checkbox" wire:model="evergreenFallback" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                    <span>Evergreen Fallback</span>
                </label>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="createRundown">✨ Generar Escaleta</span>
                    <span wire:loading wire:target="createRundown" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span>Generando...</span>
                    </span>
                </button>
            </div>
        </form>
    </div>

    @if ($editingRundownId)
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-lg text-slate-100">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-sm font-semibold text-indigo-400">Editar Borrador de Escaleta</h4>
                <button type="button" wire:click="$set('editingRundownId', null)" class="text-slate-400 hover:text-slate-200 text-sm">✕ Cerrar</button>
            </div>

            <form wire:submit="saveDraftEdits" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Título</label>
                        <input type="text" wire:model="editTitle" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none" required />
                        @error('editTitle') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tipo</label>
                        <select wire:model="editType" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                            <option value="pill">Píldora (pill)</option>
                            <option value="news">Noticia (news)</option>
                            <option value="debate">Debate (debate)</option>
                            <option value="music">Música (music)</option>
                        </select>
                        @error('editType') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Proveedor TTS</label>
                        <select wire:model="editTtsProvider" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                            <option value="gemini">Gemini TTS</option>
                            <option value="kokoro">Kokoro TTS</option>
                            <option value="elevenlabs">ElevenLabs</option>
                            <option value="edge">Edge TTS</option>
                        </select>
                        @error('editTtsProvider') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Voz TTS</label>
                        <input type="text" wire:model="editTtsVoice" list="edit-tts-voices" placeholder="Ej: Kore, Orus, Puck, Aoede" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none" required />
                        <datalist id="edit-tts-voices">
                            <option value="Kore">
                            <option value="Orus">
                            <option value="Puck">
                            <option value="Aoede">
                        </datalist>
                        @error('editTtsVoice') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Estilo Visual</label>
                        <select wire:model="editVisualStyleId" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                            <option value="">Sin estilo específico</option>
                            @foreach ($visualStyles as $style)
                                <option value="{{ $style->id }}">{{ $style->name }}@if ($style->is_default) (Por defecto)@endif</option>
                            @endforeach
                        </select>
                        @error('editVisualStyleId') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Texto del Guion</label>
                    <textarea wire:model="editScriptText" rows="6" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 font-mono text-xs focus:border-indigo-500 focus:outline-none"></textarea>
                    @error('editScriptText') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" wire:model="editPublishToPodcast" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                        <span>Publicar en Podcast</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" wire:model="editGenerateShort" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                        <span>Generar Short</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" wire:model="editEvergreenFallback" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                        <span>Evergreen Fallback</span>
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('editingRundownId', null)" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-slate-200 hover:bg-slate-700">
                        Cancelar
                    </button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                        Guardar Borrador
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden shadow-sm text-slate-100">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-200">Escaletas Registradas (AI Today)</h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400">{{ count($rundowns) }} escaletas</span>
                <button
                    type="button"
                    @click="showCreateForm = !showCreateForm"
                    class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 text-xs font-medium text-white transition-colors cursor-pointer"
                >
                    <span x-show="!showCreateForm">+ Crear nuevo</span>
                    <span x-show="showCreateForm" x-cloak>Ocultar formulario</span>
                </button>
            </div>
        </div>

        @if ($rundowns->isEmpty())
            <div class="p-8 text-center text-sm text-slate-400">
                No hay escaletas registradas actualmente. Crea una arriba usando Wikipedia o de forma manual.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-300 border-b border-slate-800 uppercase font-medium">
                        <tr>
                            <th class="px-4 py-3">Título / Origen</th>
                            <th class="px-4 py-3">Tipo / Idioma</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Duración</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($rundowns as $rundown)
                            @php
                                $durationSec = (int) round((float) ($rundown->tvVideo?->duration_seconds ?: data_get($rundown->broadcastJob?->artifacts, 'master_audio.total_duration_seconds') ?: $rundown->duration_seconds ?: 0));
                                $canPreview = $rundown->tvVideo && (! empty($rundown->tvVideo->relative_media_path) || ! empty($rundown->tvVideo->file_path) || ! empty($rundown->tvVideo->mp4_url));
                            @endphp
                            <tr class="hover:bg-slate-800/30 transition-colors {{ $rundown->isBusy() ? 'opacity-90 bg-indigo-950/20' : '' }}">
                                <td class="px-4 py-3 max-w-xs">
                                    <div class="font-medium text-slate-100 flex items-center gap-2">
                                        @if ($rundown->isBusy())
                                            <svg class="h-4 w-4 animate-spin text-amber-400 shrink-0" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                        @endif
                                        <span class="truncate" title="{{ $rundown->title }}">{{ $rundown->title }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate mt-0.5" title="{{ $rundown->topic_or_url }}">
                                        {{ $rundown->source_kind }}: {{ $rundown->topic_or_url ?: 'N/A' }}
                                    </div>
                                    @if ($rundown->error_message)
                                        <div class="text-[11px] text-rose-400 mt-1 truncate" title="{{ $rundown->error_message }}">
                                            Error: {{ $rundown->error_message }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-200 border border-slate-700">
                                            {{ $rundown->type }}
                                        </span>
                                        <span class="text-slate-400 text-[11px] uppercase">
                                            {{ $rundown->language }}
                                        </span>
                                        @if ($rundown->visualStyle)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-950 text-indigo-300 border border-indigo-700/70">
                                                🎨 {{ $rundown->visualStyle->name }}
                                            </span>
                                        @endif
                                        @if ($rundown->publish_to_podcast)
                                            <span title="Exportar a Podcast MP3">🎙️</span>
                                        @endif
                                        @if ($rundown->generate_short)
                                            <span title="Generar Short 9:16">📱</span>
                                        @endif
                                        @if ($rundown->evergreen_fallback)
                                            <span title="Evergreen Fallback">🌲</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="progressModal = @js([
                                                'title' => (string) $rundown->title,
                                                'stage' => (string) $rundown->stage,
                                                'source_kind' => (string) $rundown->source_kind,
                                                'tts_provider' => (string) ($rundown->tts_provider ?: 'gemini'),
                                                'tts_voice' => (string) ($rundown->tts_voice ?: 'Kore'),
                                                'has_script' => ! empty($rundown->script_text),
                                                'filmmaker_job_id' => (string) ($rundown->filmmaker_job_id ?? ''),
                                                'tv_video_id' => (string) ($rundown->tv_video_id ?? ''),
                                                'error_message' => (string) ($rundown->error_message ?? ''),
                                            ])"
                                            class="inline-flex items-center gap-1.5 cursor-pointer hover:opacity-80 transition-opacity"
                                            title="Ver progreso y pasos"
                                        >
                                            @if ($rundown->stage === 'ready')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-950 text-emerald-300 border border-emerald-700">
                                                    Listo
                                                </span>
                                            @elseif ($rundown->stage === 'failed')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-rose-950 text-rose-300 border border-rose-700">
                                                    Fallido
                                                </span>
                                            @elseif ($rundown->isBusy())
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-950 text-amber-300 border border-amber-700">
                                                    <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                    {{ ucfirst($rundown->stage) }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-200 border border-slate-700">
                                                    {{ ucfirst($rundown->stage) }}
                                                </span>
                                            @endif
                                            <span class="text-[10px] text-slate-400 hover:text-indigo-400">📊</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-slate-300">
                                    {{ $durationSec > 0 ? gmdate('H:i:s', $durationSec) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($canPreview)
                                            <button
                                                type="button"
                                                x-on:click="openPreview('{{ route('tv.videos.stream', ['video' => $rundown->tvVideo->id]) }}', @js($rundown->title ?: 'Vídeo'))"
                                                class="rounded px-2 py-1 text-xs bg-indigo-600 hover:bg-indigo-500 text-white transition-colors cursor-pointer"
                                                title="Ver vídeo MP4"
                                                aria-label="Ver vídeo MP4"
                                            >
                                                ▶
                                            </button>
                                        @endif

                                        @if ($rundown->project_id)
                                            <a
                                                href="{{ route('projects.show', $rundown->project_id) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="rounded px-2 py-1 text-xs bg-amber-600 hover:bg-amber-500 text-white transition-colors"
                                                title="Abrir proyecto Videos IA en el Estudio Filmmaker"
                                                aria-label="Abrir proyecto Videos IA en el Estudio Filmmaker"
                                            >
                                                🎬
                                            </a>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="startEditing('{{ $rundown->id }}')"
                                            @disabled($rundown->isBusy())
                                            class="rounded px-2 py-1 text-xs bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                            title="Editar borrador"
                                            aria-label="Editar borrador"
                                        >
                                            ✏️
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="triggerVideoRender('{{ $rundown->id }}')"
                                            @disabled($rundown->isBusy() || empty($rundown->script_text))
                                            class="rounded bg-emerald-600 hover:bg-emerald-500 px-2 py-1 text-xs text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                            title="{{ empty($rundown->script_text) ? 'Requiere guion para renderizar' : 'Generar vídeo en Filmmaker' }}"
                                            aria-label="Generar vídeo en Filmmaker"
                                        >
                                            🚀
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="regenerateRundown('{{ $rundown->id }}')"
                                            wire:confirm="¿Regenerar desde cero? Se borrarán el vídeo, planos y audio anteriores de esta escaleta."
                                            @disabled($rundown->isBusy())
                                            class="rounded bg-purple-700 hover:bg-purple-600 px-2 py-1 text-xs text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                            title="Regenerar desde cero (machacar todo)"
                                            aria-label="Regenerar desde cero"
                                        >
                                            🔁
                                        </button>

                                        @if ($rundown->stage === 'failed')
                                            <button
                                                type="button"
                                                wire:click="retryRundown('{{ $rundown->id }}')"
                                                class="rounded bg-amber-600 hover:bg-amber-500 px-2 py-1 text-xs text-white transition-colors cursor-pointer"
                                                title="Reintentar escaleta fallida"
                                                aria-label="Reintentar escaleta fallida"
                                            >
                                                🔄
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="deleteRundown('{{ $rundown->id }}')"
                                            wire:confirm="¿Seguro que deseas eliminar esta escaleta?"
                                            @disabled($rundown->isBusy())
                                            class="rounded px-2 py-1 text-xs bg-slate-800 hover:bg-rose-900 text-rose-300 hover:text-rose-100 border border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                            title="Eliminar escaleta"
                                            aria-label="Eliminar escaleta"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Modal de Progreso Paso a Paso -->
    <div
        x-show="progressModal !== null"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
        @click.self="progressModal = null"
    >
        <div class="w-full max-w-lg rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-2xl text-slate-100 space-y-5">
            <div class="flex items-start justify-between gap-4 border-b border-slate-800 pb-3">
                <div class="min-w-0">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-indigo-400">Pipeline de Producción</span>
                    <h3 class="text-base font-semibold text-slate-100 truncate mt-0.5" x-text="progressModal?.title"></h3>
                </div>
                <button type="button" @click="progressModal = null" class="text-slate-400 hover:text-white text-lg leading-none shrink-0">✕</button>
            </div>

            <!-- Metadatos de la escaleta -->
            <div class="grid grid-cols-2 gap-3 text-xs bg-slate-950 p-3 rounded-lg border border-slate-800">
                <div>
                    <span class="text-slate-400">Origen:</span>
                    <span class="text-slate-200 font-medium capitalize ml-1" x-text="progressModal?.source_kind"></span>
                </div>
                <div>
                    <span class="text-slate-400">TTS / Voz:</span>
                    <span class="text-slate-200 font-medium ml-1" x-text="(progressModal?.tts_provider || 'gemini') + ' / ' + (progressModal?.tts_voice || 'Kore')"></span>
                </div>
                <div class="col-span-2" x-show="progressModal?.filmmaker_job_id">
                    <span class="text-slate-400">Job ID:</span>
                    <span class="text-indigo-300 font-mono ml-1 text-[11px]" x-text="progressModal?.filmmaker_job_id"></span>
                </div>
                <div class="col-span-2" x-show="progressModal?.tv_video_id">
                    <span class="text-slate-400">Video ID:</span>
                    <span class="text-emerald-300 font-mono ml-1 text-[11px]" x-text="progressModal?.tv_video_id"></span>
                </div>
            </div>

            <!-- Pasos del Pipeline -->
            <div class="space-y-3">
                <!-- Paso 1 -->
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        <template x-if="progressModal?.stage === 'generating'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-950 border border-amber-600 text-amber-300">
                                <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                        </template>
                        <template x-if="progressModal?.stage === 'failed' && !progressModal?.has_script">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-950 border border-rose-600 text-rose-300 text-xs font-bold">✕</span>
                        </template>
                        <template x-if="progressModal?.stage !== 'generating' && (progressModal?.stage !== 'failed' || progressModal?.has_script)">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs font-bold">✓</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">1. Síntesis de contenido (Wikipedia / Manual)</div>
                        <div class="text-[11px] text-slate-400">Extracción y resumen del contenido inicial para la escaleta.</div>
                    </div>
                </div>

                <!-- Paso 2 -->
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        <template x-if="progressModal?.stage === 'generating'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 border border-slate-700 text-slate-500 text-xs">2</span>
                        </template>
                        <template x-if="progressModal?.stage === 'draft'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-950 border border-indigo-600 text-indigo-300 text-xs font-bold">●</span>
                        </template>
                        <template x-if="['queued', 'rendering', 'ready'].includes(progressModal?.stage) || (progressModal?.stage === 'failed' && progressModal?.has_script)">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs font-bold">✓</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">2. Borrador listo (revisión de guion y TTS)</div>
                        <div class="text-[11px] text-slate-400">Edición editorial del guion, proveedor de voz y parámetros.</div>
                    </div>
                </div>

                <!-- Paso 3 -->
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        <template x-if="['generating', 'draft'].includes(progressModal?.stage)">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 border border-slate-700 text-slate-500 text-xs">3</span>
                        </template>
                        <template x-if="progressModal?.stage === 'queued'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-950 border border-amber-600 text-amber-300">
                                <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                        </template>
                        <template x-if="['rendering', 'ready'].includes(progressModal?.stage) || (progressModal?.stage === 'failed' && progressModal?.filmmaker_job_id)">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs font-bold">✓</span>
                        </template>
                        <template x-if="progressModal?.stage === 'failed' && progressModal?.has_script && !progressModal?.filmmaker_job_id">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-950 border border-rose-600 text-rose-300 text-xs font-bold">✕</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">3. Enviado a cola de render (jg_filmmaker)</div>
                        <div class="text-[11px] text-slate-400">Despacho firmado con HMAC y encolado en el microservicio.</div>
                    </div>
                </div>

                <!-- Paso 4 -->
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        <template x-if="['generating', 'draft', 'queued'].includes(progressModal?.stage)">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 border border-slate-700 text-slate-500 text-xs">4</span>
                        </template>
                        <template x-if="progressModal?.stage === 'rendering'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-950 border border-amber-600 text-amber-300">
                                <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </span>
                        </template>
                        <template x-if="progressModal?.stage === 'ready'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs font-bold">✓</span>
                        </template>
                        <template x-if="progressModal?.stage === 'failed' && progressModal?.filmmaker_job_id">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-950 border border-rose-600 text-rose-300 text-xs font-bold">✕</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">4. Renderizando vídeo y audio</div>
                        <div class="text-[11px] text-slate-400">Generación de síntesis TTS, clips visuales y composición audiovisual.</div>
                    </div>
                </div>

                <!-- Paso 5 -->
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        <template x-if="progressModal?.stage === 'ready'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 text-xs font-bold">✓</span>
                        </template>
                        <template x-if="progressModal?.stage !== 'ready'">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 border border-slate-700 text-slate-500 text-xs">5</span>
                        </template>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">5. Disponible en Biblioteca de Vídeos</div>
                        <div class="text-[11px] text-slate-400">Registro sincronizado en TvVideo y disponible para emisión/podcast.</div>
                    </div>
                </div>
            </div>

            <!-- Bloque de Error si stage === 'failed' -->
            <div x-show="progressModal?.stage === 'failed' && progressModal?.error_message" class="rounded-lg bg-rose-950/60 border border-rose-700/60 p-3 text-xs text-rose-200">
                <div class="font-semibold text-rose-300 mb-1 flex items-center gap-1.5">
                    <span>⚠️ Detalle del Error:</span>
                </div>
                <p class="font-mono text-[11px] break-words" x-text="progressModal?.error_message"></p>
            </div>

            <!-- Botón de Cerrar -->
            <div class="pt-2 flex justify-end">
                <button
                    type="button"
                    @click="progressModal = null"
                    class="rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 px-4 py-2 text-xs font-medium text-slate-200 transition-colors"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Previsualización de Vídeo -->
    <div
        x-show="previewUrl !== null"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
        x-on:click.self="closePreview()"
    >
        <div class="relative w-full max-w-4xl bg-slate-900 border border-slate-800 rounded-xl shadow-2xl overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800 bg-slate-950/50">
                <h4 class="text-sm font-semibold text-slate-100 truncate flex items-center gap-2">
                    <span>🎬</span>
                    <span x-text="previewTitle || 'Previsualización de vídeo'"></span>
                </h4>
                <button
                    type="button"
                    x-on:click="closePreview()"
                    class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition cursor-pointer"
                    title="Cerrar modal"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-4 bg-slate-950 flex justify-center items-center">
                <video
                    x-ref="previewPlayer"
                    :src="previewUrl"
                    controls
                    preload="metadata"
                    class="w-full max-h-[70vh] rounded bg-black"
                ></video>
            </div>
        </div>
    </div>
</div>
