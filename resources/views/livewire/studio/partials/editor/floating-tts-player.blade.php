    <!-- ================= REPRODUCTOR FLOTANTE DE LOCUCIÓN TTS (GOOGLE-AI / GEMINI-TTS) ================= -->
    <div x-data="ttsPlayer({ projectId: '{{ $project->id }}' })"
         x-on:play-tts.window="startPlayback($event.detail)"
         x-on:stop-tts.window="stopPlayback()"
         x-show="isPlaying || isLoading"
         id="floating-tts-player"
         class="rounded-2xl shadow-2xl p-3 sm:p-4 text-white font-mono"
         :style="(isPlaying || isLoading) ? 'position: fixed !important; bottom: 24px !important; left: 50% !important; transform: translateX(-50%) !important; z-index: 999999 !important; width: min(94vw, 680px) !important; background-color: rgba(15, 23, 42, 0.96) !important; border: 1.5px solid rgba(245, 158, 11, 0.6) !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important; backdrop-filter: blur(12px) !important; display: block !important;' : 'display: none !important;'"
         style="position: fixed !important; bottom: 24px !important; left: 50% !important; transform: translateX(-50%) !important; z-index: 999999 !important; width: min(94vw, 680px) !important; background-color: rgba(15, 23, 42, 0.96) !important; border: 1.5px solid rgba(245, 158, 11, 0.6) !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important; backdrop-filter: blur(12px) !important; display: none;">

        <div class="flex items-center justify-between gap-3">
            <!-- Info del segmento actual -->
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-full bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                    <template x-if="isLoading">
                        <span class="animate-spin text-sm">⏳</span>
                    </template>
                    <template x-if="!isLoading && !isPaused">
                        <span class="animate-pulse text-sm">🔊</span>
                    </template>
                    <template x-if="!isLoading && isPaused">
                        <span class="text-sm">⏸️</span>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider"
                              :class="currentSpeaker === 'NARRADOR' ? 'bg-slate-800 text-slate-300 border border-slate-700' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                              x-text="currentSpeaker">
                        </span>
                        <span class="text-[11px] text-slate-400 font-sans" x-text="progressText"></span>
                    </div>
                    <p class="text-xs text-slate-200 truncate mt-0.5 font-sans italic" x-text="currentText || 'Cargando locución...'"></p>
                </div>
            </div>

            <!-- Controles de Reproducción -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Anterior -->
                <button @click="prevSegment()"
                        :disabled="currentIndex <= 0 || isLoading"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                        title="Segmento anterior">
                    ⏮️
                </button>

                <!-- Play / Pausa -->
                <button @click="togglePlayPause()"
                        :disabled="isLoading"
                        type="button"
                        class="px-2.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition cursor-pointer flex items-center gap-1 shadow-md">
                    <template x-if="isPaused">
                        <span>▶️ Reanudar</span>
                    </template>
                    <template x-if="!isPaused">
                        <span>⏸️ Pausa</span>
                    </template>
                </button>

                <!-- Siguiente -->
                <button @click="nextSegment()"
                        :disabled="currentIndex >= manifest.length - 1 || isLoading"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                        title="Siguiente segmento">
                    ⏭️
                </button>

                <!-- Selector de Velocidad -->
                <button @click="cycleSpeed()"
                        type="button"
                        class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 text-[11px] font-bold border border-slate-700 transition cursor-pointer"
                        title="Cambiar velocidad de reproducción">
                    <span x-text="speed + 'x'"></span>
                </button>

                <!-- Detener / Cerrar -->
                <button @click="stopPlayback()"
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition cursor-pointer ml-1"
                        title="Cerrar y detener reproducción">
                    ✕
                </button>
            </div>
        </div>
    </div>
