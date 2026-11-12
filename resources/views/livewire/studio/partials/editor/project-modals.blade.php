    <!-- ================= MODAL: MODIFICAR NOMBRE Y DETALLES DEL PROYECTO ================= -->
    @if($showEditTitleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✏️</span>
                        <span>Modificar Nombre de la Obra</span>
                    </h3>
                    <button wire:click="$set('showEditTitleModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                <form wire:submit="updateProjectTitle" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="projectTitle"
                               placeholder="Ej: El Laberinto de las Sombras"
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                        @error('projectTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis</label>
                        <textarea wire:model="projectSynopsis"
                                  rows="3"
                                  placeholder="Sinopsis o premisa dramática..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                        @error('projectSynopsis') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showEditTitleModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            Guardar Título
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ================= MODAL: RENDERIZAR EN LOTE ("RENDERIZAR TODO DE GOLPE") ================= -->
    @if($showBatchModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⚡</span>
                        <span>Renderizar / Procesar en Lote</span>
                    </h3>
                    <button wire:click="$set('showBatchModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                <div class="mt-4 space-y-4 text-xs font-sans">
                    <p class="text-slate-600 dark:text-slate-300">
                        Ejecuta de forma secuencial y automatizada el pipeline cinematográfico. Se respetarán estrictamente todos los bloques protegidos con candado (🔒).
                    </p>

                    <!-- Selector de Alcance -->
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Alcance del Procesamiento</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                    wire:click="$set('batchScope', 'scene')"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $batchScope === 'scene' ? 'border-amber-500 bg-amber-500/10 text-amber-900 dark:text-amber-200 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <span class="block font-bold">🎬 Escena Activa</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400">Solo la escena seleccionada</span>
                            </button>
                            <button type="button"
                                    wire:click="$set('batchScope', 'project')"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer {{ $batchScope === 'project' ? 'border-amber-500 bg-amber-500/10 text-amber-900 dark:text-amber-200 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <span class="block font-bold">🌐 Todo el Proyecto</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400">Todas las escenas y bloques</span>
                            </button>
                        </div>
                    </div>

                    <!-- Selector de Etapa -->
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Etapa a Renderizar</label>
                        <div class="space-y-1.5">
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="all" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">🚀 Pipeline Completo (Recomendado)</span>
                                    <span class="block text-[11px] text-slate-500">Estructura brutos ➔ Formatea a guion ➔ Desglosa planos técnicos</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="structured" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">📝 Brutos ➔ Tratamiento</span>
                                    <span class="block text-[11px] text-slate-500">Estructura todos los textos brutos a prosa dramática</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="screenplay" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">📜 Tratamientos ➔ Guion Literario</span>
                                    <span class="block text-[11px] text-slate-500">Convierte los tratamientos a formato canónico Fountain</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2.5 p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                                <input type="radio" wire:model="batchStage" value="shots" class="text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="block font-bold text-slate-900 dark:text-white">🎥 Guiones ➔ Planos Técnicos</span>
                                    <span class="block text-[11px] text-slate-500">Desglosa los guiones en planos para claqueta y rodaje</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Tarjeta de Desglose de Tokens de Procesamiento en Lote -->
                    @php
                        $currentUser = auth()->user() ?? $project->user;
                        $userTokensAvail = $currentUser ? $currentUser->getTokensAvailableToday() : 0;
                        $hasEnoughBatch = $userTokensAvail >= $estimatedBatchTokens;
                        $remainingAfterBatch = max(0, $userTokensAvail - $estimatedBatchTokens);
                    @endphp
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2.5 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Bloques en ámbito:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $estimatedBatchBlocks }} bloques</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Coste estimado en tokens:</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">~{{ number_format($estimatedBatchTokens, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-800/80 pt-2 flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Saldo disponible hoy:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userTokensAvail, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="flex items-center justify-between {{ $hasEnoughBatch ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            <span class="font-medium">Saldo tras ejecución:</span>
                            <span class="font-bold">~{{ number_format($remainingAfterBatch, 0, ',', '.') }} tokens</span>
                        </div>
                    </div>

                    @if(! $hasEnoughBatch)
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2">
                            <span>⚠️</span>
                            <span>No dispones de suficientes tokens diarios para ejecutar este renderizado en lote. Tu cupo se renovará a las 00:00.</span>
                        </div>
                    @endif

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showBatchModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button"
                                wire:click="executeBatchProcessing"
                                wire:loading.attr="disabled"
                                @if(! $hasEnoughBatch) disabled @endif
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold {{ $hasEnoughBatch ? 'bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-amber-500/30' : 'bg-slate-300 dark:bg-slate-800 text-slate-500 cursor-not-allowed' }} font-bold shadow-lg transition cursor-pointer">
                            <span wire:loading.remove wire:target="executeBatchProcessing">⚡ Iniciar Renderizado en Lote</span>
                            <span wire:loading wire:target="executeBatchProcessing" class="flex items-center gap-1.5">
                                <span class="animate-spin text-sm">⏳</span>
                                <span>Procesando pipeline...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
