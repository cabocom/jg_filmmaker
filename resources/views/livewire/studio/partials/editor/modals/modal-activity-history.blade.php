    @if($showActivityHistoryModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.closeActivityHistoryModal()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="closeActivityHistoryModal"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-2.5 sm:p-4">
                <div class="relative w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-3.5 sm:p-6 space-y-3 sm:space-y-4 max-h-[90vh] flex flex-col"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                        <div class="flex items-center gap-2 sm:gap-2.5">
                            <span class="text-xl">📜</span>
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span>Historial de Actividad del Proyecto</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-300 border border-sky-300 dark:border-sky-800">
                                        {{ $activityLogs->count() }} eventos
                                    </span>
                                </h3>
                                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                                    Trazabilidad cronológica inversa de todas las acciones del usuario.
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeActivityHistoryModal" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Lista con scroll vertical en orden cronológico inverso -->
                    <div class="flex-1 overflow-y-auto pr-1 space-y-2.5 max-h-[60vh] sm:max-h-[64vh]">
                        @forelse($activityLogs as $log)
                            @include('livewire.studio.partials.editor.modals.activity-log-item', ['log' => $log])
                        @empty
                            <div class="p-8 text-center text-slate-400 font-mono text-xs">
                                <span>No hay acciones registradas en el historial todavía.</span>
                            </div>
                        @endforelse
                    </div>

                    <!-- Pie del modal -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-[11px] font-mono text-slate-400">
                            Base para futura función "Evolución de la Historia"
                        </span>
                        <button wire:click="closeActivityHistoryModal"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-slate-200 dark:text-slate-900 transition cursor-pointer">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
