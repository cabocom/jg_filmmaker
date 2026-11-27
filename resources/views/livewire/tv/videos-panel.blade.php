<div x-data="{ previewUrl: null, previewTitle: '', showIncompatibleModal: false, openPreview(url, title) { this.previewUrl = url; this.previewTitle = title; }, closePreview() { if (this.$refs.previewPlayer) { this.$refs.previewPlayer.pause(); this.$refs.previewPlayer.removeAttribute('src'); this.$refs.previewPlayer.load(); } this.previewUrl = null; this.previewTitle = ''; } }" @keydown.escape.window="if (previewUrl) closePreview(); if (showIncompatibleModal) showIncompatibleModal = false;" class="space-y-4">
    @if ($hasBusyVideos)
        <div wire:poll.4s class="hidden"></div>
    @endif
    <!-- Header / Google Drive Directory & Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 p-4 bg-slate-900 border border-slate-800 rounded-lg text-slate-100">
        <div>
            <h3 class="text-base font-semibold flex items-center gap-2">
                <span>📁</span> Biblioteca de Vídeos en Google Drive
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
                Ruta configurada: <span class="font-mono text-cyan-400 bg-slate-800 px-1.5 py-0.5 rounded">{{ $channelConfig->videos_dir ?? 'ai-today/videos' }}</span>
                &middot; Perfil: <span class="font-mono text-emerald-400 bg-slate-800 px-1.5 py-0.5 rounded">{{ $channelConfig->broadcast_profile ?: '720p30' }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($nonCompliantVideos->isNotEmpty())
                <button
                    type="button"
                    x-on:click="showIncompatibleModal = true"
                    class="text-orange-400 border border-orange-500/60 bg-slate-800 hover:bg-slate-700 px-2.5 py-2 rounded-md text-xs font-medium inline-flex items-center gap-1.5 cursor-pointer"
                    title="Ficheros incompatibles detectados"
                >
                    <span>⚠️</span>
                    <span>{{ $nonCompliantVideos->count() }}</span>
                </button>
            @endif
            <button
                type="button"
                wire:click="scanDriveNow"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-md text-orange-400 border border-orange-500/60 bg-slate-800 hover:bg-slate-700 transition disabled:opacity-50 cursor-pointer"
            >
                <span wire:loading.remove wire:target="scanDriveNow">🔍 Escanear disco</span>
                <span wire:loading wire:target="scanDriveNow" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-3.5 w-3.5 text-orange-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Escaneando disco...
                </span>
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if ($statusMessage)
        <div class="p-3 bg-emerald-900 border border-emerald-500 text-emerald-50 rounded-md text-xs flex items-center justify-between">
            <span>{{ $statusMessage }}</span>
            <button type="button" wire:click="$set('statusMessage', null)" class="text-emerald-200 hover:text-white text-sm font-bold cursor-pointer">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-3 bg-rose-900 border border-rose-500 text-rose-50 rounded-md text-xs flex items-center justify-between">
            <span>{{ $errorMessage }}</span>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-rose-200 hover:text-white text-sm font-bold cursor-pointer">&times;</button>
        </div>
    @endif

    <!-- Filter Pills & Search -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-medium text-slate-400 mr-1">Filtro:</span>
            @foreach (['all' => 'Todos', 'bio' => 'Biografías', 'pill' => 'Píldoras', 'news' => 'Noticias', 'debate' => 'Debates', 'music' => 'Música', 'non_compliant' => 'No conformes'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $key }}')"
                    class="px-2.5 py-1 text-xs rounded-full font-medium transition {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2 sm:w-80">
            <div class="relative flex-1">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por título o ruta..."
                    class="w-full pl-3 pr-7 py-1.5 text-xs bg-slate-950 border border-slate-700 rounded-md text-slate-100 placeholder-slate-500 focus:outline-none focus:border-cyan-500"
                >
                @if ($search !== '')
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 text-sm font-bold"
                        title="Limpiar búsqueda"
                    >&times;</button>
                @endif
            </div>
            <span class="text-xs text-slate-500 whitespace-nowrap">{{ $videos->total() }} vídeos</span>
        </div>
    </div>

    <!-- Video Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-lg overflow-hidden">
        @if ($videos->isEmpty())
            <div class="p-8 text-center text-slate-400 text-sm">
                No se encontraron vídeos para el filtro o búsqueda seleccionados.
            </div>
        @else
            @php
                $sortIcon = fn (string $col): string => $sortField === $col ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕';
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            <th class="py-2.5 px-3">
                                <button type="button" wire:click="sortBy('type')" class="inline-flex items-center gap-1 cursor-pointer hover:text-white transition {{ $sortField === 'type' ? 'text-cyan-400' : '' }}">
                                    <span>Tipo</span>
                                    <span class="text-[10px]">{{ $sortIcon('type') }}</span>
                                </button>
                            </th>
                            <th class="py-2.5 px-3">
                                <button type="button" wire:click="sortBy('title')" class="inline-flex items-center gap-1 cursor-pointer hover:text-white transition {{ $sortField === 'title' ? 'text-cyan-400' : '' }}">
                                    <span>Título / Ruta</span>
                                    <span class="text-[10px]">{{ $sortIcon('title') }}</span>
                                </button>
                            </th>
                            <th class="py-2.5 px-3">
                                <button type="button" wire:click="sortBy('stage')" class="inline-flex items-center gap-1 cursor-pointer hover:text-white transition {{ $sortField === 'stage' ? 'text-cyan-400' : '' }}">
                                    <span>Estado</span>
                                    <span class="text-[10px]">{{ $sortIcon('stage') }}</span>
                                </button>
                            </th>
                            <th class="py-2.5 px-3 whitespace-nowrap">
                                <button type="button" wire:click="sortBy('duration_seconds')" class="inline-flex items-center gap-1 cursor-pointer hover:text-white transition {{ $sortField === 'duration_seconds' ? 'text-cyan-400' : '' }}">
                                    <span>Duración</span>
                                    <span class="text-[10px]">{{ $sortIcon('duration_seconds') }}</span>
                                </button>
                            </th>
                            <th class="py-2.5 px-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-xs">
                        @foreach ($videos as $video)
                            @php
                                $isBusy = $video->isBusy();
                            @endphp
                            <tr wire:key="tv-video-{{ $video->id }}" class="{{ $isBusy ? 'bg-slate-800/40' : 'hover:bg-slate-800/20' }}">
                                <td class="py-3 px-3 align-top whitespace-nowrap">
                                    <select wire:change="updateVideoType('{{ $video->id }}', $event.target.value)" @disabled($isBusy) class="bg-slate-800 border border-slate-700 text-slate-100 rounded px-2 py-1 text-[11px] font-medium cursor-pointer disabled:opacity-50">
                                        <option value="bio" @selected($video->type === 'bio')>Biografía</option>
                                        <option value="pill" @selected($video->type === 'pill')>Píldora</option>
                                        <option value="news" @selected($video->type === 'news')>Noticia</option>
                                        <option value="debate" @selected($video->type === 'debate')>Debate</option>
                                        <option value="music" @selected($video->type === 'music')>Música</option>
                                    </select>
                                </td>

                                <td class="py-3 px-3 align-top max-w-md">
                                    <div class="font-semibold text-slate-100 truncate" title="{{ $video->title }}">
                                        {{ $video->title ?: 'Sin título' }}
                                    </div>
                                    @if ($video->relative_media_path)
                                        <div class="mt-0.5 font-mono text-[11px] text-slate-400 truncate" title="{{ $video->relative_media_path }}">
                                            {{ $video->relative_media_path }}
                                        </div>
                                    @endif
                                    @if ($video->error_message)
                                        <div class="mt-0.5 text-rose-400 font-mono text-[11px] truncate" title="{{ $video->error_message }}">
                                            ⚠ {{ $video->error_message }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-3 px-3 align-top whitespace-nowrap">
                                    <span title="{{ ucfirst($video->stage) }}">
                                        @if ($isBusy)
                                            <svg class="animate-spin h-4 w-4 text-amber-400 inline" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        @elseif ($video->stage === 'failed')
                                            <span class="text-sm">❌</span>
                                        @elseif ($video->stage === 'ready')
                                            <span class="text-sm">✅</span>
                                        @else
                                            <span class="text-sm">⏳</span>
                                        @endif
                                    </span>
                                </td>

                                <td class="py-3 px-3 align-top whitespace-nowrap font-mono text-slate-300">
                                    {{ $video->duration_seconds ? gmdate('H:i:s', (int) $video->duration_seconds) : '—' }}
                                </td>

                                <td class="py-3 px-3 align-top whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            x-on:click="openPreview('{{ route('tv.videos.stream', ['video' => $video->id]) }}', @js($video->title ?: $video->relative_media_path ?: 'Vídeo'))"
                                            @disabled($isBusy || empty($video->relative_media_path))
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded bg-indigo-600 hover:bg-indigo-500 text-white transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                            title="Previsualizar vídeo MP4"
                                        >
                                            ▶ Ver
                                        </button>

                                        @if (! $video->broadcast_compliant || $video->stage === 'failed')
                                            <button
                                                type="button"
                                                wire:click="normalizeVideo('{{ $video->id }}')"
                                                @disabled($isBusy || empty($video->relative_media_path))
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded bg-amber-600 hover:bg-amber-500 text-white transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                                title="Normalizar contenedor y códecs con ffmpeg en jg_filmmaker"
                                            >
                                                🔧 Normalizar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Paginación con selector de elementos por página -->
            <div class="px-4 py-3 bg-slate-950/60 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <span>Mostrar</span>
                        <select wire:model.live="perPage" class="bg-slate-900 border border-slate-700 text-slate-100 rounded px-2 py-1 text-xs font-semibold outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>por página</span>
                    </div>
                    <span class="text-slate-700 hidden sm:inline">|</span>
                    <span class="text-slate-400">
                        @if ($videos->total() > 0)
                            Mostrando <span class="font-semibold text-white">{{ $videos->firstItem() }}</span> a <span class="font-semibold text-white">{{ $videos->lastItem() }}</span> de <span class="font-semibold text-white">{{ $videos->total() }}</span> vídeos
                        @else
                            <span class="font-semibold text-white">0</span> vídeos
                        @endif
                    </span>
                </div>

                @if ($videos->hasPages())
                    <div class="w-full sm:w-auto flex justify-center sm:justify-end">
                        {{ $videos->links('livewire.admin.pagination-links') }}
                    </div>
                @endif
            </div>
        @endif
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
            <!-- Modal Header -->
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

            <!-- Modal Body -->
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

    <!-- Modal Ficheros Incompatibles -->
    <div
        x-show="showIncompatibleModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
        x-on:click.self="showIncompatibleModal = false"
    >
        <div class="relative w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800 bg-slate-950/50">
                <h4 class="text-sm font-semibold text-orange-400 flex items-center gap-2">
                    <span>⚠️</span>
                    <span>Ficheros incompatibles detectados ({{ $nonCompliantVideos->count() }})</span>
                </h4>
                <button
                    type="button"
                    x-on:click="showIncompatibleModal = false"
                    class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition cursor-pointer"
                    title="Cerrar modal"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-4 overflow-y-auto space-y-3">
                @if ($nonCompliantVideos->isEmpty())
                    <p class="text-xs text-slate-400 text-center py-4">No se detectaron vídeos incompatibles.</p>
                @else
                    @foreach ($nonCompliantVideos as $ncVideo)
                        <div class="p-3 bg-slate-950 border border-slate-800 rounded-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-slate-200 truncate" title="{{ $ncVideo->title }}">
                                    {{ $ncVideo->title ?: 'Sin título' }}
                                </div>
                                @if ($ncVideo->relative_media_path)
                                    <div class="text-[11px] font-mono text-slate-400 truncate mt-0.5" title="{{ $ncVideo->relative_media_path }}">
                                        {{ $ncVideo->relative_media_path }}
                                    </div>
                                @endif
                                <div class="text-[11px] text-rose-400 mt-1">
                                    {{ !empty($ncVideo->compliance_reasons_json) ? implode(', ', $ncVideo->compliance_reasons_json) : 'Requiere normalización broadcast' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if ($ncVideo->relative_media_path)
                                    <button
                                        type="button"
                                        x-on:click="openPreview('{{ route('tv.videos.stream', ['video' => $ncVideo->id]) }}', @js($ncVideo->title ?: $ncVideo->relative_media_path)); showIncompatibleModal = false"
                                        class="px-2.5 py-1 text-xs font-medium rounded bg-indigo-600 hover:bg-indigo-500 text-white transition cursor-pointer"
                                        title="Previsualizar vídeo"
                                    >
                                        ▶ Ver
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    x-on:click="$wire.set('search', @js($ncVideo->title ?: $ncVideo->relative_media_path)); showIncompatibleModal = false"
                                    class="px-2.5 py-1 text-xs font-medium rounded bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition cursor-pointer"
                                    title="Filtrar en tabla"
                                >
                                    🔍 Filtrar
                                </button>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
