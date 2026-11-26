<div class="space-y-5">
    <div class="p-4 bg-white dark:bg-[#121622] border border-gray-200 dark:border-gray-800 rounded-lg shadow-2xs">
        <h3 class="text-xs font-bold text-gray-800 dark:text-white uppercase tracking-wider mb-4">
            Configuración de Emisión RTMP y Perfiles de Voz
        </h3>

        <form wire:submit.prevent="saveChannelConfig" class="space-y-4">
            {{-- Almacenamiento y Perfil de Emisión Broadcast --}}
            <div class="p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-md border border-gray-200 dark:border-gray-800 space-y-3">
                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 block">
                    Almacenamiento y Perfil de Emisión Broadcast (Google Drive / jg_filmmaker)
                </span>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Perfil de Emisión
                        </label>
                        <select
                            wire:model="broadcastProfile"
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        >
                            <option value="720p30">720p30 (1280x720 @ 30fps) - Por defecto</option>
                            <option value="1080p30">1080p30 (1920x1080 @ 30fps)</option>
                        </select>
                        @error('broadcastProfile') <span class="text-[10px] text-red-600 dark:text-red-400 block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Directorio de Vídeos (Google Drive)
                        </label>
                        <input
                            type="text"
                            wire:model="videosDir"
                            placeholder="ai-today/videos"
                            class="w-full px-2.5 py-1.5 text-xs font-mono rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                        @error('videosDir') <span class="text-[10px] text-red-600 dark:text-red-400 block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Directorio de Publicidad (Google Drive)
                        </label>
                        <input
                            type="text"
                            wire:model="adsDir"
                            placeholder="ai-today/ads"
                            class="w-full px-2.5 py-1.5 text-xs font-mono rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                        @error('adsDir') <span class="text-[10px] text-red-600 dark:text-red-400 block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Credenciales de YouTube Live --}}
            <div class="p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-md border border-gray-200 dark:border-gray-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">YouTube Live Stream</span>
                    @if ($channelConfig->youtube_connected)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            ✓ Conectado
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400">
                            Sin Conexión
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">YouTube Channel ID</label>
                        <input
                            type="text"
                            wire:model="youtubeChannelId"
                            placeholder="UC..."
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                        @error('youtubeChannelId') <span class="text-[10px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
                            YouTube Stream Key (Cifrada con APP_KEY)
                        </label>
                        <input
                            type="password"
                            wire:model="youtubeStreamKey"
                            placeholder="Dejar vacío para conservar clave actual"
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                        @if ($channelConfig->stream_key_fingerprint)
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 block">
                                Huella actual: <code class="font-mono">{{ $channelConfig->stream_key_fingerprint }}</code>
                            </span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
                        Credenciales OAuth JSON de YouTube (Cifradas con APP_KEY)
                    </label>
                    <textarea
                        wire:model="youtubeOauthJson"
                        rows="2"
                        placeholder="Dejar vacío para no modificar credenciales OAuth"
                        class="w-full px-2.5 py-1.5 text-xs font-mono rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                    ></textarea>
                </div>
            </div>

            {{-- Perfiles de Voz TTS --}}
            <div class="p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-md border border-gray-200 dark:border-gray-800 space-y-3">
                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 block">Perfiles de Voz TTS</span>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Narrador Principal</label>
                        <input
                            type="text"
                            wire:model="voiceNarrator"
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Voz Orus (Debate 1)</label>
                        <input
                            type="text"
                            wire:model="voiceOrus"
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Voz Puck (Debate 2)</label>
                        <input
                            type="text"
                            wire:model="voicePuck"
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Voz Aoede (Debate 3)</label>
                        <input
                            type="text"
                            wire:model="voiceAoede"
                            class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-indigo-500 focus:outline-hidden"
                        />
                    </div>
                </div>
            </div>

            {{-- Feed Podcast Público --}}
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-1">
                <div class="flex items-center gap-1.5">
                    <span>🎙️ Feed RSS del Podcast AI Today:</span>
                    <a
                        href="/podcast/ai-today/feed.xml"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-mono underline"
                    >
                        /podcast/ai-today/feed.xml
                    </a>
                </div>
                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-md shadow-2xs text-white bg-indigo-600 hover:bg-indigo-700 cursor-pointer transition-colors"
                >
                    Guardar Configuración de Emisión
                </button>
            </div>
        </form>
    </div>
</div>
