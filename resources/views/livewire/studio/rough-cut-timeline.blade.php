<div class="space-y-6">
    <!-- Barra Superior de Control de Montaje -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-xl backdrop-blur-md">
        <div class="flex items-center gap-3">
            <span class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xl">
                🎬
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-white tracking-wide">
                        Línea de Montaje (Rough Cut)
                    </h2>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        {{ $goodTakes->count() }} Tomas Aprobadas
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    Pre-corte secuencial de tomas válidas (<code class="font-mono text-emerald-400">is_good_take = true</code>) listas para posproducción.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <!-- Selector de Filtro de Escena -->
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-400">Filtrar por:</span>
                <select wire:change="setSceneFilter($event.target.value)"
                        class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-200 text-xs focus:ring-1 focus:ring-amber-500">
                    <option value="">Todo el Proyecto (Todas las Escenas)</option>
                    @foreach($scenes as $s)
                        <option value="{{ $s->id }}" {{ $filterSceneId === $s->id ? 'selected' : '' }}>
                            Escena #{{ $s->scene_order }} — {{ $s->slugline }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Botón Exportar Lista de Montaje -->
            <button wire:click="exportRoughCutJson"
                    type="button"
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-lg shadow-amber-500/20">
                <span>⬇️</span>
                <span>Exportar EDL / JSON</span>
            </button>

            <!-- Botón Renderizar Pre-corte (FFmpeg) -->
            <button wire:click="assembleRoughCut"
                    wire:loading.attr="disabled"
                    type="button"
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-lg shadow-indigo-600/20 disabled:opacity-50">
                <span wire:loading.remove wire:target="assembleRoughCut">🎬</span>
                <svg wire:loading wire:target="assembleRoughCut" class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Renderizar Pre-corte (FFmpeg)</span>
            </button>
        </div>
    </div>

    <!-- Mensajes Flash y Enlace de Vídeo Ensamblado -->
    @if(session()->has('success'))
        <div class="p-3.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
            @if($renderedVideoUrl)
                <a href="{{ $renderedVideoUrl }}" target="_blank" class="px-3 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition">
                    Ver Vídeo Ensamblado ➜
                </a>
            @endif
        </div>
    @endif

    @if(session()->has('error'))
        <div class="p-3.5 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Monitor Central de Edición & Datos del Corte -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Pantalla de Reproducción de Vídeo (2 Columnas) -->
        <div class="lg:col-span-2 rounded-2xl bg-black border border-slate-800 shadow-2xl overflow-hidden flex flex-col justify-between">
            <div class="relative aspect-video w-full bg-slate-950 flex items-center justify-center">
                @if($currentTake && $currentTake->video_url)
                    <video src="{{ $currentTake->video_url }}"
                           controls
                           autoplay
                           class="w-full h-full object-contain bg-black">
                        Tu navegador no soporta reproducción de vídeo HTML5.
                    </video>
                @elseif($currentTake)
                    <!-- Claqueta Mockup para toma sin archivo cargado aún -->
                    <div class="text-center p-8 max-w-md">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-3xl shadow-inner">
                            🎞️
                        </div>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-mono font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 mb-2">
                            PLANO {{ $currentTake->shot?->shot_number }} · TOMA {{ $currentTake->take_number }}
                        </span>
                        <h4 class="text-base font-bold text-white mb-1">
                            {{ $currentTake->shot?->scene?->slugline }}
                        </h4>
                        <p class="text-xs text-slate-400 mb-4 line-clamp-2">
                            {{ $currentTake->shot?->description ?: 'Sin descripción técnica.' }}
                        </p>
                        <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 text-left text-xs">
                            <span class="block text-[10px] font-mono text-slate-500 uppercase font-bold mb-1">Nota Editorial:</span>
                            <span class="text-slate-300 italic">
                                {{ $currentTake->editorial_notes ?: 'Toma aprobada para montaje. Archivo de vídeo aún no cargado.' }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="text-center p-12 text-slate-500 text-xs">
                        <span class="text-4xl block mb-2">🎬</span>
                        No hay tomas marcadas como "buenas" en este filtro.
                    </div>
                @endif
            </div>

            <!-- Barra de Información de Tiempo de la Pantalla -->
            <div class="bg-slate-950 px-5 py-3 border-t border-slate-800 flex items-center justify-between text-xs">
                <div class="flex items-center gap-4 text-slate-400 font-mono">
                    <div>
                        <span class="text-slate-600">DURACIÓN TOTAL EST.:</span>
                        <span class="text-amber-400 font-bold ml-1">{{ number_format($totalEstimatedSeconds, 1) }}s</span>
                    </div>
                    @if($currentTake)
                        <div>
                            <span class="text-slate-600">CORTE ACTUAL:</span>
                            <span class="text-white font-bold ml-1">
                                {{ $currentTake->technical_meta['duration_seconds'] ?? 5.0 }}s
                            </span>
                        </div>
                    @endif
                </div>

                @if($currentTake)
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-800">
                            {{ $currentTake->technical_meta['resolution'] ?? '1080p' }}
                        </span>
                        <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-800">
                            {{ $currentTake->technical_meta['fps'] ?? 24 }} fps
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Panel Lateral de Detalles del Corte Seleccionado (1 Columna) -->
        <div class="rounded-2xl bg-slate-900/90 border border-slate-800 p-5 flex flex-col justify-between shadow-xl">
            @if($currentTake)
                <div class="space-y-4">
                    <div>
                        <span class="text-[10px] font-mono uppercase tracking-wider text-slate-500 font-bold block mb-1">
                            Detalles del Corte Activo
                        </span>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>Plano {{ $currentTake->shot?->shot_number }}</span>
                            <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                {{ $currentTake->shot?->shot_type ?? 'PM' }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            Escena #{{ $currentTake->shot?->scene?->scene_order }} — {{ $currentTake->shot?->scene?->slugline }}
                        </p>
                    </div>

                    <!-- Acción / Descripción -->
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                        <span class="block text-[10px] font-mono text-slate-500 uppercase font-bold mb-1">Descripción:</span>
                        <p class="text-slate-200 leading-relaxed">
                            {{ $currentTake->shot?->description ?: 'Sin descripción de plano.' }}
                        </p>
                    </div>

                    <!-- Notas de Dirección y Montaje -->
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-amber-500/20 text-xs">
                        <span class="block text-[10px] font-mono text-amber-400/80 uppercase font-bold mb-1">Notas Editoriales:</span>
                        <p class="text-amber-100/90 italic leading-relaxed">
                            {{ $currentTake->editorial_notes ?: 'Sin anotaciones editoriales para esta toma.' }}
                        </p>
                    </div>

                    <!-- Metadatos Técnicos -->
                    <div class="space-y-1.5 text-xs font-mono">
                        <div class="flex justify-between py-1 border-b border-slate-800/80">
                            <span class="text-slate-500">Toma número:</span>
                            <span class="text-white font-bold">#{{ $currentTake->take_number }} (Toma Buena)</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800/80">
                            <span class="text-slate-500">Archivo:</span>
                            <span class="text-slate-300 truncate max-w-[180px]" title="{{ $currentTake->file_path }}">
                                {{ $currentTake->file_path ? basename($currentTake->file_path) : 'Pendiente de vídeo' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-800/80">
                            <span class="text-slate-500">Códec:</span>
                            <span class="text-slate-300">{{ $currentTake->technical_meta['codec'] ?? 'prores/h264' }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-12 text-slate-500 text-xs">
                    Selecciona una toma en el timeline inferior para ver sus especificaciones.
                </div>
            @endif

            <div class="mt-4 pt-4 border-t border-slate-800 text-center">
                <span class="text-[11px] font-mono text-slate-500">
                    Ecosistema Cinematográfico jg_ia
                </span>
            </div>
        </div>
    </div>

    <!-- Timeline Horizontal Estilo NLE (Non-Linear Editor) -->
    <div class="rounded-2xl bg-slate-900/90 border border-slate-800 p-5 shadow-xl">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">
                    Secuencia de Montaje
                </span>
                <span class="text-[11px] font-mono text-slate-500">
                    ({{ $goodTakes->count() }} clips encadenados)
                </span>
            </div>
            <div class="text-xs font-mono text-slate-500">
                Haz clic en cualquier clip para inspeccionar o reproducir
            </div>
        </div>

        @if($goodTakes->isEmpty())
            <div class="py-8 text-center bg-slate-950/60 rounded-xl border border-slate-800 text-xs text-slate-400">
                No hay tomas marcadas como "Toma Buena" (<code class="font-mono text-amber-400">is_good_take = true</code>) para montar. Marca tomas válidas en la pestaña de Desglose.
            </div>
        @else
            <!-- Pista de Clips Horizontales -->
            <div class="flex items-center gap-3 overflow-x-auto pb-3 pt-1 scrollbar-thin scrollbar-thumb-slate-800">
                @foreach($goodTakes as $index => $take)
                    @php
                        $isSelected = $currentPlayingTakeId === $take->id;
                        $shotType = $take->shot?->shot_type ?? 'PM';
                        $typeColors = [
                            'PG' => 'border-sky-500/40 text-sky-300 bg-sky-950/30',
                            'PM' => 'border-amber-500/40 text-amber-300 bg-amber-950/30',
                            'PP' => 'border-emerald-500/40 text-emerald-300 bg-emerald-950/30',
                            'PD' => 'border-purple-500/40 text-purple-300 bg-purple-950/30',
                        ];
                        $badgeStyle = $typeColors[$shotType] ?? 'border-slate-700 text-slate-300 bg-slate-800';
                    @endphp

                    <button wire:click="selectTakeForPlayback('{{ $take->id }}')"
                            type="button"
                            class="flex-shrink-0 w-48 text-left rounded-xl p-3 border transition cursor-pointer relative group {{ $isSelected ? 'bg-amber-500/10 border-amber-500 shadow-lg shadow-amber-500/10 ring-1 ring-amber-500/50' : 'bg-slate-950/80 border-slate-800 hover:border-slate-700' }}">
                        <!-- Índice de corte y tipo de plano -->
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-mono font-bold {{ $isSelected ? 'text-amber-400' : 'text-slate-500' }}">
                                #{{ $index + 1 }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border {{ $badgeStyle }}">
                                {{ $shotType }}
                            </span>
                        </div>

                        <!-- Información del Plano y Escena -->
                        <h5 class="text-xs font-bold text-white truncate">
                            Plano {{ $take->shot?->shot_number }} · Toma {{ $take->take_number }}
                        </h5>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">
                            Esc. {{ $take->shot?->scene?->scene_order }} — {{ $take->shot?->scene?->slugline }}
                        </p>

                        <!-- Duración e Indicador de Vídeo -->
                        <div class="mt-3 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[10px] font-mono">
                            <span class="text-slate-400">
                                ⏱️ {{ $take->technical_meta['duration_seconds'] ?? 5.0 }}s
                            </span>
                            @if($take->file_path)
                                <span class="text-emerald-400 flex items-center gap-1 font-semibold" title="Vídeo disponible">
                                    <span>●</span> VÍDEO
                                </span>
                            @else
                                <span class="text-slate-600 flex items-center gap-1" title="Vídeo no cargado">
                                    <span>○</span> PEND
                                </span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
