<div @if ($hasBusyAds) wire:poll.4s @endif class="space-y-4">
    {{-- Header / Directory info and Scan action --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 backdrop-blur">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-zinc-100 flex items-center gap-2">
                    <span>📺</span>
                    <span>Gestión de Cuñas Publicitarias y Continuidad</span>
                </h3>
                <p class="text-xs text-zinc-400 mt-1">
                    Carpeta de medios: <code class="px-1.5 py-0.5 rounded bg-zinc-800 text-emerald-400 font-mono">{{ $channelConfig->ads_dir ?? 'ai-today/ads' }}</code>
                </p>
            </div>
            <div>
                <button
                    wire:click="scanAdsFolder"
                    wire:loading.attr="disabled"
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-medium rounded-lg border border-zinc-700 transition disabled:opacity-50"
                >
                    <svg wire:loading.class="animate-spin" wire:target="scanAdsFolder" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>🔄 Escanear Medios</span>
                </button>
            </div>
        </div>

        @if ($statusMessage)
            <div class="mt-3 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/60 text-emerald-300 text-xs flex items-center gap-2">
                <span>✅</span>
                <span>{{ $statusMessage }}</span>
            </div>
        @endif

        @if ($errorMessage)
            <div class="mt-3 p-3 rounded-lg bg-rose-950/40 border border-rose-800/60 text-rose-300 text-xs flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ $errorMessage }}</span>
            </div>
        @endif
    </div>

    {{-- Form to Add Slot --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4 backdrop-blur space-y-3">
        <h4 class="text-sm font-semibold text-zinc-200">Añadir Activo Publicitario / Continuidad</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Título</label>
                <input
                    type="text"
                    wire:model="title"
                    placeholder="Ej: Cuña Patrocinio Alpha"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-zinc-200 focus:outline-none focus:border-zinc-600"
                />
                @error('title') <p class="text-xs text-rose-400 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Rol de Emisión</label>
                <select
                    wire:model="assetRole"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-zinc-200 focus:outline-none focus:border-zinc-600"
                >
                    <option value="ad">Anuncio publicitario (ad)</option>
                    <option value="fallback_l3">Respaldo L3 (fallback_l3)</option>
                    <option value="intro">Cabecera de emisión (intro)</option>
                    <option value="outro">Cierre de emisión (outro)</option>
                </select>
                @error('assetRole') <p class="text-xs text-rose-400 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-zinc-400 mb-1">Ruta relativa en Google Drive (selecciona un archivo escaneado o escribe la ruta)</label>
                <input
                    type="text"
                    list="drive-ad-files-list"
                    wire:model="adMediaPath"
                    placeholder="ej: ai-today/ads/slot1.mp4"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-zinc-200 focus:outline-none focus:border-zinc-600"
                />
                <datalist id="drive-ad-files-list">
                    @foreach ($availableAdFiles as $file)
                        <option value="{{ $file }}"></option>
                    @endforeach
                </datalist>
                @error('adMediaPath') <p class="text-xs text-rose-400 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Duración (segundos)</label>
                <input
                    type="number"
                    wire:model="durationSeconds"
                    min="0"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-zinc-200 focus:outline-none focus:border-zinc-600"
                />
                @error('durationSeconds') <p class="text-xs text-rose-400 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Frecuencia (minutos)</label>
                <input
                    type="number"
                    wire:model="frequencyMinutes"
                    min="0"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-1.5 text-xs text-zinc-200 focus:outline-none focus:border-zinc-600"
                />
                @error('frequencyMinutes') <p class="text-xs text-rose-400 mt-0.5">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <label class="inline-flex items-center gap-2 text-xs text-zinc-300 cursor-pointer">
                <input
                    type="checkbox"
                    wire:model="paidPromotion"
                    class="rounded border-zinc-800 bg-zinc-950 text-indigo-600 focus:ring-0"
                />
                <span>Promoción pagada (Paid Promotion)</span>
            </label>

            <button
                type="button"
                wire:click="saveAdSlot"
                wire:loading.attr="disabled"
                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded-lg transition disabled:opacity-50"
            >
                Guardar Slot
            </button>
        </div>
    </div>

    {{-- Slots List --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur">
        <div class="p-3 border-b border-zinc-800/80 flex items-center justify-between">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Slots Registrados ({{ $adSlots->count() }})</h4>
            @if ($hasBusyAds)
                <span class="inline-flex items-center gap-1.5 text-xs text-amber-400 font-mono">
                    <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>Sincronizando jobs de normalización en curso...</span>
                </span>
            @endif
        </div>

        <div class="divide-y divide-zinc-800/60">
            @forelse ($adSlots as $slot)
                <div class="p-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-zinc-800/20 transition">
                    <div class="space-y-1 min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-sm text-zinc-200">{{ $slot->title ?: '(Sin título)' }}</span>
                            <span class="px-2 py-0.5 text-[10px] rounded-full font-mono uppercase bg-zinc-800 text-zinc-300 border border-zinc-700">
                                {{ $slot->asset_role }}
                            </span>
                            @if ($slot->paid_promotion)
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-mono bg-purple-950/60 text-purple-300 border border-purple-800/60">
                                    Patrocinado
                                </span>
                            @endif
                            <span class="text-xs text-zinc-400">
                                {{ $slot->duration_seconds }}s @if($slot->frequency_minutes > 0) · cada {{ $slot->frequency_minutes }}m @endif
                            </span>
                        </div>

                        <p class="text-xs font-mono text-zinc-400 truncate max-w-xl">
                            {{ $slot->media_path ?: '(Sin ruta de medios)' }}
                        </p>

                        {{-- Semáforo ffprobe y Stage --}}
                        <div class="flex items-center gap-2 pt-1 text-xs">
                            @if ($slot->isBusy())
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded font-mono text-[11px] bg-amber-950/50 text-amber-300 border border-amber-800/60">
                                    <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span>{{ ucfirst($slot->stage) }} (normalizando)</span>
                                </span>
                            @elseif ($slot->stage === 'failed')
                                <span class="px-2 py-0.5 rounded font-mono text-[11px] bg-rose-950/50 text-rose-300 border border-rose-800/60" title="{{ $slot->error_message }}">
                                    🔴 Fallo: {{ \Illuminate\Support\Str::limit($slot->error_message ?: 'Error desconocido', 30) }}
                                </span>
                            @elseif ($slot->broadcast_compliant)
                                <span class="px-2 py-0.5 rounded font-mono text-[11px] bg-emerald-950/50 text-emerald-300 border border-emerald-800/60">
                                    🟢 ffprobe OK (Compliant)
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded font-mono text-[11px] bg-amber-950/50 text-amber-300 border border-amber-800/60" title="{{ json_encode($slot->compliance_reasons_json) }}">
                                    🟡 Requiere normalización
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Actions Controls --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($slot->isBusy())
                            <span class="inline-flex items-center gap-1 text-xs text-amber-400 font-mono pr-2">
                                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>Procesando</span>
                            </span>
                        @endif

                        <button
                            type="button"
                            wire:click="normalizeAdSlot('{{ $slot->id }}')"
                            @disabled($slot->isBusy())
                            title="Normalizar códec y dimensiones según broadcast profile"
                            class="px-2.5 py-1 text-xs font-medium rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            🔧 Normalizar
                        </button>

                        <button
                            type="button"
                            wire:click="toggleAdActive('{{ $slot->id }}')"
                            @disabled($slot->isBusy())
                            class="px-2.5 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-40 disabled:cursor-not-allowed {{ $slot->active ? 'bg-emerald-950/40 border-emerald-800/60 text-emerald-300 hover:bg-emerald-900/40' : 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:bg-zinc-700' }}"
                        >
                            {{ $slot->active ? 'Activo' : 'Pausado' }}
                        </button>

                        <button
                            type="button"
                            wire:click="deleteAdSlot('{{ $slot->id }}')"
                            @disabled($slot->isBusy())
                            wire:confirm="¿Deseas eliminar este slot publicitario definitivamente?"
                            class="p-1 text-zinc-500 hover:text-rose-400 transition disabled:opacity-40 disabled:cursor-not-allowed"
                            title="Eliminar slot"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-xs text-zinc-500">
                    No hay cuñas publicitarias ni activos de continuidad configurados.
                </div>
            @endforelse
        </div>
    </div>
</div>
