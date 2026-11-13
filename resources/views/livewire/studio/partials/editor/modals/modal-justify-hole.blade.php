    <!-- ================= MODAL PARA JUSTIFICAR Y DESCARTAR AGUJERO DE GUIÓN ================= -->
    @if($showJustifyModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.closeJustifyModal()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="closeJustifyModal"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative w-full max-w-xl max-h-[92vh] flex flex-col overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-6 space-y-4 sm:space-y-5"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">✅</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Justificar Agujero de Guión
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Explica el motivo por el cual no es una inconsistencia para retirarlo y archivarlo en Agujeros justificados.
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeJustifyModal" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Motivo detectado por la IA -->
                    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-800 dark:text-rose-200 space-y-1">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-rose-900 dark:text-rose-300 block">
                            Inconsistencia reportada por la IA:
                        </span>
                        <p class="font-sans leading-relaxed">
                            {{ $justifyingHoleReason }}
                        </p>
                    </div>

                    <!-- Campo de justificación del usuario -->
                    <div class="space-y-1.5">
                        <label for="justificationText" class="block text-xs font-bold font-mono text-slate-800 dark:text-slate-200 uppercase">
                            Motivo por el que no es un agujero de guión (Obligatorio):
                        </label>
                        <textarea id="justificationText"
                                  wire:model="justificationText"
                                  rows="4"
                                  placeholder="Ejemplo: Pedro no está en el bar porque sigue en el coche esperando a Javier, como se describe en la acción previa..."
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-xs leading-relaxed focus:ring-2 focus:ring-emerald-500 focus:outline-hidden"></textarea>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button wire:click="closeJustifyModal"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="savePlotHoleJustification"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span>✅</span>
                            <span>Confirmar Justificación</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

