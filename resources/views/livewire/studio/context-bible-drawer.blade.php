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
                    @include('livewire.studio.partials.bible.characters-tab')
                    @include('livewire.studio.partials.bible.locations-tab')
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

    @include('livewire.studio.partials.bible.delete-modal')
</div>
