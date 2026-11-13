    <!-- ================= MODAL CONFIRMAR COSTE EN TOKENS ANTES DE EJECUTAR PIPELINE COMPLETO DE ESCENA ================= -->
    @if($showScenePipelineConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.cancelRunScenePipeline()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="cancelRunScenePipeline"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">⚡</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Pipeline IA Completo de Escena
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Confirmación previa de coste en tokens de IA
                                </p>
                            </div>
                        </div>
                        <button wire:click="cancelRunScenePipeline" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Mensaje explicativo -->
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Se procesarán los <strong class="font-bold text-slate-900 dark:text-white">{{ $estimatedSceneBlocks }} bloque(s)</strong> de la <strong class="text-amber-600 dark:text-amber-400 font-mono">Escena #{{ $scenePipelineSceneOrder }} ({{ $scenePipelineSceneSlugline }})</strong> a través de todo el flujo cinematográfico con IA: <span class="font-bold">Bruto ➔ Tratamiento ➔ Guión Literario ➔ Desglose de Planos Técnicos</span>.
                    </p>

                    <!-- Tarjeta de Desglose de Tokens -->
                    @php
                        $currentUser = auth()->user() ?? $project->user;
                        $userTokensAvail = $currentUser ? $currentUser->getTokensAvailableToday() : 0;
                        $hasEnoughScene = $userTokensAvail >= $estimatedSceneTokens;
                        $remainingAfterScene = max(0, $userTokensAvail - $estimatedSceneTokens);
                    @endphp
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Bloques de la escena:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $estimatedSceneBlocks }} bloques</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Coste estimado en tokens:</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">~{{ number_format($estimatedSceneTokens, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-800/80 pt-2 flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Saldo disponible hoy:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userTokensAvail, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="flex items-center justify-between {{ $hasEnoughScene ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            <span class="font-medium">Saldo tras ejecución:</span>
                            <span class="font-bold">~{{ number_format($remainingAfterScene, 0, ',', '.') }} tokens</span>
                        </div>
                    </div>

                    @if(! $hasEnoughScene)
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2">
                            <span>⚠️</span>
                            <span>No dispones de suficientes tokens diarios para procesar esta escena. Tu saldo se renovará automáticamente a las 00:00.</span>
                        </div>
                    @endif

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button wire:click="cancelRunScenePipeline"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmRunScenePipeline"
                                wire:loading.attr="disabled"
                                @if(! $hasEnoughScene) disabled @endif
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold {{ $hasEnoughScene ? 'bg-amber-500 hover:bg-amber-400 text-slate-950' : 'bg-slate-300 text-slate-500 cursor-not-allowed' }} shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span wire:loading.remove wire:target="confirmRunScenePipeline">⚡ Confirmar y Ejecutar Pipeline</span>
                            <span wire:loading wire:target="confirmRunScenePipeline" class="flex items-center gap-1.5">
                                <span class="animate-spin text-xs">⏳</span>
                                <span>Procesando escena con IA...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

