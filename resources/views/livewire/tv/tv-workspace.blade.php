<div class="w-full {{ $embed ? 'p-3' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5' }}">
    {{-- Cabecera Fija del Workspace TV --}}
    <div class="mb-5 p-4 bg-white dark:bg-[#121622] border border-gray-200 dark:border-gray-800 rounded-lg shadow-2xs">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                        <span>📺</span>
                        <span>Canal TV AI Today :: Continuidad y Emisión 24h</span>
                    </h2>
                    <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 rounded">Nativo Filmmaker</span>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                    Control de parrilla 24 horas, escaleta de render jg_filmmaker, cortes de continuidad y emisión RTMP.
                </p>
            </div>

            {{-- Indicador Real de Cola jg_filmmaker --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mr-1">Cola Filmmaker:</span>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 font-mono" title="Vídeos en espera de inicio">
                    Pending: {{ $queueStats['pending'] ?? 0 }}
                </span>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 font-mono" title="En cola en jg_filmmaker">
                    Queued: {{ $queueStats['queued'] ?? 0 }}
                </span>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-mono" title="En renderizado activo">
                    Rendering: {{ $queueStats['rendering'] ?? 0 }}
                </span>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-mono" title="Listos para emisión">
                    Ready: {{ $queueStats['ready'] ?? 0 }}
                </span>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 font-mono" title="Errores de render">
                    Failed: {{ $queueStats['failed'] ?? 0 }}
                </span>
            </div>
        </div>

        {{-- Sub-navegación interna de TV --}}
        <div class="flex space-x-2 border-b border-gray-100 dark:border-gray-800 mt-4 pt-2">
            <button
                type="button"
                wire:click="switchSection('schedule')"
                class="px-3 py-1.5 text-xs font-medium border-b-2 cursor-pointer transition-colors flex items-center gap-1.5 {{ $activeSection === 'schedule' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                <span>📅 Parrilla 24h</span>
            </button>
            <button
                type="button"
                wire:click="switchSection('rundowns')"
                class="px-3 py-1.5 text-xs font-medium border-b-2 cursor-pointer transition-colors flex items-center gap-1.5 {{ $activeSection === 'rundowns' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                <span>📋 Escaletas IA</span>
            </button>
            <button
                type="button"
                wire:click="switchSection('videos')"
                class="px-3 py-1.5 text-xs font-medium border-b-2 cursor-pointer transition-colors flex items-center gap-1.5 {{ $activeSection === 'videos' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                <span>🎬 Vídeos y Emisión</span>
            </button>
            <button
                type="button"
                wire:click="switchSection('ads')"
                class="px-3 py-1.5 text-xs font-medium border-b-2 cursor-pointer transition-colors flex items-center gap-1.5 {{ $activeSection === 'ads' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                <span>📢 Publicidad y Continuidad</span>
            </button>
            <button
                type="button"
                wire:click="switchSection('config')"
                class="px-3 py-1.5 text-xs font-medium border-b-2 cursor-pointer transition-colors flex items-center gap-1.5 {{ $activeSection === 'config' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                <span>⚙️ Emisión y Configuración</span>
            </button>
        </div>
    </div>

    {{-- Notificaciones Flash --}}
    @if ($statusMessage)
        <div class="mb-4 p-3 rounded-md bg-emerald-900 border border-emerald-500 text-emerald-50 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" wire:click="$set('statusMessage', null)" class="text-emerald-200 hover:text-white cursor-pointer">✕</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 p-3 rounded-md bg-rose-900 border border-rose-500 text-rose-50 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ $errorMessage }}</span>
            </div>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-rose-200 hover:text-white cursor-pointer">✕</button>
        </div>
    @endif

    {{-- Secciones Internas Modulares --}}
    @if ($activeSection === 'schedule')
        @include('livewire.tv.partials.schedule')
    @elseif ($activeSection === 'rundowns')
        <livewire:tv.tv-rundowns-panel wire:key="tv-rundowns-panel" />
    @elseif ($activeSection === 'videos')
        <livewire:tv.tv-videos-panel wire:key="tv-videos-panel" />
    @elseif ($activeSection === 'ads')
        <livewire:tv.tv-ads-panel wire:key="tv-ads-panel" />
    @elseif ($activeSection === 'config')
        @include('livewire.tv.partials.config')
    @endif
</div>
