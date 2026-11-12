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

    <!-- ================= MODAL CONFIRMAR COSTE EN TOKENS ANTES DE AUDITAR AGUJEROS DE GUIÓN ================= -->
    @if($showPlotHoleConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.cancelAnalyzePlotHoles()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="cancelAnalyzePlotHoles"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🕳️</span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Auditoría de Agujeros de Guión
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Confirmación previa de coste en tokens de IA
                                </p>
                            </div>
                        </div>
                        <button wire:click="cancelAnalyzePlotHoles" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Mensaje explicativo -->
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        El script analizará los <strong class="font-bold text-slate-900 dark:text-white">{{ $estimatedBlocksToAudit }} bloque(s)</strong> con contenido de tu proyecto a través de las 3 etapas (Idea, Tratamiento y Guion), contrastando coherencia narrativa, personajes y localizaciones con la Biblia de Proyecto.
                    </p>

                    <!-- Tarjeta de Desglose de Tokens -->
                    @php
                        $currentUser = auth()->user() ?? $project->user;
                        $userTokensAvail = $currentUser ? $currentUser->getTokensAvailableToday() : 0;
                        $hasEnough = $userTokensAvail >= $estimatedPlotHoleTokens;
                        $remainingAfter = max(0, $userTokensAvail - $estimatedPlotHoleTokens);
                    @endphp
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Bloques a auditar:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $estimatedBlocksToAudit }} bloques</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Coste estimado en tokens:</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">~{{ number_format($estimatedPlotHoleTokens, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-800/80 pt-2 flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Saldo disponible hoy:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userTokensAvail, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="flex items-center justify-between {{ $hasEnough ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            <span class="font-medium">Saldo tras ejecución:</span>
                            <span class="font-bold">~{{ number_format($remainingAfter, 0, ',', '.') }} tokens</span>
                        </div>
                    </div>

                    @if(! $hasEnough)
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2">
                            <span>⚠️</span>
                            <span>No dispones de suficientes tokens diarios para auditar todos los bloques. Tu saldo se renovará automáticamente a las 00:00.</span>
                        </div>
                    @endif

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button wire:click="cancelAnalyzePlotHoles"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmAnalyzePlotHoles"
                                wire:loading.attr="disabled"
                                @if(! $hasEnough) disabled @endif
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold {{ $hasEnough ? 'bg-amber-500 hover:bg-amber-400 text-slate-950' : 'bg-slate-300 text-slate-500 cursor-not-allowed' }} shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span wire:loading.remove wire:target="confirmAnalyzePlotHoles">🕳️ Confirmar y Auditar</span>
                            <span wire:loading wire:target="confirmAnalyzePlotHoles" class="flex items-center gap-1.5">
                                <span class="animate-spin text-xs">⏳</span>
                                <span>Auditando con IA...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

    <!-- ================= MODAL CONFIRMAR COSTE EN TOKENS PARA ACCIÓN DE BLOQUE INDIVIDUAL ================= -->
    @if($showBlockActionConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto"
             x-data
             @keydown.escape.window="$wire.cancelBlockAction()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity cursor-pointer"
                 wire:click="cancelBlockAction"></div>

            <!-- Modal Dialog -->
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-4 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                     @click.stop>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            @if($confirmingBlockAction === 'structure')
                                <span class="text-xl">⚡</span>
                            @elseif($confirmingBlockAction === 'screenplay')
                                <span class="text-xl">🎬</span>
                            @else
                                <span class="text-xl">📹</span>
                            @endif
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Confirmar Acción de IA en Bloque
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Confirmación previa de coste en tokens de IA
                                </p>
                            </div>
                        </div>
                        <button wire:click="cancelBlockAction" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg cursor-pointer">
                            ✕
                        </button>
                    </div>

                    <!-- Mensaje explicativo -->
                    @php
                        $actionLabel = match($confirmingBlockAction) {
                            'structure' => 'Estructurar a Tratamiento',
                            'screenplay' => 'Convertir a Guión Literario',
                            'shots' => 'Desglosar en Planos Técnicos',
                            default => 'Procesar con IA',
                        };
                    @endphp
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Se ejecutará la operación <strong class="font-bold text-slate-900 dark:text-white">"{{ $actionLabel }}"</strong> sobre el <strong class="text-amber-600 dark:text-amber-400 font-mono">Bloque #{{ $confirmingBlockOrder }}</strong> mediante los servicios de IA de la plataforma.
                    </p>

                    <!-- Tarjeta de Desglose de Tokens -->
                    @php
                        $currentUser = auth()->user() ?? $project->user;
                        $userTokensAvail = $currentUser ? $currentUser->getTokensAvailableToday() : 0;
                        $hasEnoughBlock = $userTokensAvail >= $estimatedBlockActionTokens;
                        $remainingAfterBlock = max(0, $userTokensAvail - $estimatedBlockActionTokens);
                    @endphp
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Operación:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $actionLabel }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Coste estimado en tokens:</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">~{{ number_format($estimatedBlockActionTokens, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-800/80 pt-2 flex items-center justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Saldo disponible hoy:</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userTokensAvail, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="flex items-center justify-between {{ $hasEnoughBlock ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            <span class="font-medium">Saldo tras ejecución:</span>
                            <span class="font-bold">~{{ number_format($remainingAfterBlock, 0, ',', '.') }} tokens</span>
                        </div>
                    </div>

                    @if(! $hasEnoughBlock)
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2">
                            <span>⚠️</span>
                            <span>No dispones de suficientes tokens diarios para ejecutar esta acción. Tu saldo se renovará automáticamente a las 00:00.</span>
                        </div>
                    @endif

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <button wire:click="cancelBlockAction"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button wire:click="confirmBlockAction"
                                wire:loading.attr="disabled"
                                @if(! $hasEnoughBlock) disabled @endif
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold {{ $hasEnoughBlock ? 'bg-amber-500 hover:bg-amber-400 text-slate-950' : 'bg-slate-300 text-slate-500 cursor-not-allowed' }} shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span wire:loading.remove wire:target="confirmBlockAction">Confirmar y Ejecutar</span>
                            <span wire:loading wire:target="confirmBlockAction" class="flex items-center gap-1.5">
                                <span class="animate-spin text-xs">⏳</span>
                                <span>Ejecutando con IA...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
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
                            @php
                                $actionIcon = match($log->action) {
                                    'create_block', 'create_scene' => '➕',
                                    'update_block', 'update_scene' => '✏️',
                                    'delete_block', 'delete_scene' => '🗑️',
                                    'reorder_block', 'reorder_scene' => '↕️',
                                    'structure_block_ai' => '🤖',
                                    'format_screenplay_ai' => '🎬',
                                    'toggle_stage_lock' => '🔒',
                                    'justify_plot_hole' => '✅',
                                    'clear_plot_holes' => '🧹',
                                    'analyze_plot_holes' => '🕳️',
                                    default => '📌',
                                };

                                $badgeClass = match($log->action) {
                                    'create_block', 'create_scene' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                    'update_block', 'update_scene' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                                    'delete_block', 'delete_scene' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                    'reorder_block', 'reorder_scene' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                                    'structure_block_ai', 'format_screenplay_ai' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                    'toggle_stage_lock' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                                    'justify_plot_hole' => 'bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300 border-teal-200 dark:border-teal-800',
                                    'analyze_plot_holes' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300 border-orange-200 dark:border-orange-800',
                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                                };
                            @endphp

                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-xs transition hover:border-slate-300 dark:hover:border-slate-700 space-y-1.5"
                                 x-data="{ showSnapshot: false }">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm">{{ $actionIcon }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase border {{ $badgeClass }}">
                                            {{ str_replace('_', ' ', $log->action) }}
                                        </span>
                                        <span class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                                            {{ $log->user?->name ?? 'Usuario' }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] font-mono text-slate-400" title="{{ $log->created_at->format('d/m/Y H:i:s') }}">
                                        {{ $log->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                <p class="text-slate-800 dark:text-slate-200 font-sans leading-relaxed">
                                    {{ $log->description }}
                                </p>

                                @if($log->action === 'analyze_plot_holes')
                                    @php
                                        $detectedHoles = $log->snapshot['detected_holes'] ?? [];
                                        $totalDetected = $log->snapshot['total_detected'] ?? 0;
                                    @endphp
                                    @if(!empty($detectedHoles))
                                        <div class="mt-2 p-3 rounded-xl bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 space-y-2">
                                            <div class="flex items-center justify-between text-[11px] font-mono font-bold text-rose-700 dark:text-rose-400">
                                                <span>🕳️ Fallos detectados en este análisis ({{ count($detectedHoles) }}):</span>
                                                @if(isset($log->snapshot['total_blocks_audited']))
                                                    <span class="text-[10px] text-slate-500 font-normal">({{ $log->snapshot['total_blocks_audited'] }} bloques auditados)</span>
                                                @endif
                                            </div>
                                            <div class="space-y-1.5">
                                                @foreach($detectedHoles as $hole)
                                                    <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-rose-200/80 dark:border-rose-900/50 text-xs space-y-1 shadow-2xs">
                                                        <div class="flex items-center justify-between text-[11px] font-mono">
                                                            <span class="font-bold text-slate-900 dark:text-white">
                                                                Escena #{{ $hole['scene_order'] ?? '?' }} @if(!empty($hole['scene_slugline'])) ({{ $hole['scene_slugline'] }}) @endif · Bloque #{{ $hole['block_order'] ?? '?' }}
                                                            </span>
                                                            <span class="px-1.5 py-0.2 rounded bg-rose-100 dark:bg-rose-900/70 text-rose-800 dark:text-rose-200 uppercase font-bold text-[9px]">
                                                                {{ $hole['stage_label'] ?? $hole['stage'] ?? '' }}
                                                            </span>
                                                        </div>
                                                        <p class="text-slate-800 dark:text-slate-200 font-sans text-xs leading-relaxed">
                                                            <strong class="font-bold text-rose-700 dark:text-rose-400">Inconsistencia:</strong> {{ $hole['reason'] ?? '' }}
                                                        </p>
                                                        @if(!empty($hole['block_preview']))
                                                            <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400 italic pt-0.5 truncate">
                                                                Fragmento: "{{ $hole['block_preview'] }}"
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif($totalDetected > 0)
                                        <div class="mt-2 p-2 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/40 text-xs text-rose-700 dark:text-rose-300 font-mono">
                                            <span>🕳️ Inconsistencias detectadas: {{ $totalDetected }}</span>
                                        </div>
                                    @endif
                                @endif

                                @if(!empty($log->snapshot))
                                    <div class="pt-1.5" x-data="{ showSnapshot: false, viewMode: 'visual' }">
                                        <button type="button"
                                                @click="showSnapshot = !showSnapshot"
                                                class="text-[11px] font-mono text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 flex items-center gap-1.5 cursor-pointer font-medium">
                                            <span x-text="showSnapshot ? '▼ Ocultar snapshot' : '▶ Ver snapshot guardado'"></span>
                                        </button>

                                        <div x-show="showSnapshot"
                                             x-collapse
                                             style="display: none;"
                                             class="mt-2 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2.5">
                                            
                                            <!-- Barra de Selección: Vista Estructurada / Código JSON -->
                                            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                    Datos del evento
                                                </span>
                                                <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-slate-800 text-[10px] font-mono">
                                                    <button type="button"
                                                            @click="viewMode = 'visual'"
                                                            :class="viewMode === 'visual' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                                            class="px-2 py-0.5 rounded-md cursor-pointer transition">
                                                        Visual
                                                    </button>
                                                    <button type="button"
                                                            @click="viewMode = 'json'"
                                                            :class="viewMode === 'json' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                                            class="px-2 py-0.5 rounded-md cursor-pointer transition">
                                                        JSON
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- 1. VISTA VISUAL ESTRUCTURADA -->
                                            <div x-show="viewMode === 'visual'" class="space-y-2.5 text-xs">
                                                <!-- Caso A: Auditoría de Agujeros de Guión -->
                                                @if(isset($log->snapshot['total_detected']))
                                                    <div class="space-y-2">
                                                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[11px] font-mono">
                                                            <span class="text-slate-600 dark:text-slate-300">Total detectados:</span>
                                                            <span class="font-bold {{ ($log->snapshot['total_detected'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                                                {{ $log->snapshot['total_detected'] ?? 0 }}
                                                            </span>
                                                        </div>

                                                        @if(isset($log->snapshot['by_stage']))
                                                            <div class="flex items-center gap-3 text-[10px] font-mono text-slate-500 dark:text-slate-400 px-1">
                                                                <span>Idea: <strong class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['by_stage']['raw'] ?? 0 }}</strong></span>
                                                                <span>·</span>
                                                                <span>Tratamiento: <strong class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['by_stage']['structured'] ?? 0 }}</strong></span>
                                                                <span>·</span>
                                                                <span>Guión: <strong class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['by_stage']['screenplay'] ?? 0 }}</strong></span>
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['detected_holes']))
                                                            <div class="space-y-1.5 pt-1">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-rose-600 dark:text-rose-400 block">Inconsistencias Reportadas:</span>
                                                                @foreach($log->snapshot['detected_holes'] as $hole)
                                                                    <div class="p-2.5 rounded-lg bg-rose-50/80 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-[11px] space-y-1">
                                                                        <div class="flex items-center justify-between font-mono text-[10px] text-rose-800 dark:text-rose-300">
                                                                            <span>Escena #{{ $hole['scene_order'] ?? '?' }} · Bloque #{{ $hole['block_order'] ?? '?' }}</span>
                                                                            <span class="px-1.5 py-0.2 rounded bg-rose-200/80 dark:bg-rose-900 text-rose-900 dark:text-rose-200 font-bold uppercase text-[9px]">{{ $hole['stage_label'] ?? $hole['stage'] ?? '' }}</span>
                                                                        </div>
                                                                        <p class="text-slate-900 dark:text-slate-100 font-sans leading-relaxed">
                                                                            {{ $hole['reason'] ?? '' }}
                                                                        </p>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Caso B: Bloque de Guión (Creación, Edición, Borrado, IA) -->
                                                @if(isset($log->snapshot['raw_text']) || isset($log->snapshot['structured_text']) || isset($log->snapshot['screenplay_content']))
                                                    <div class="space-y-2">
                                                        @if(isset($log->snapshot['scene_order']) || isset($log->snapshot['block_order']))
                                                            <div class="flex items-center justify-between flex-wrap gap-1 p-1.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-[10px] text-slate-600 dark:text-slate-300">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="font-bold text-slate-900 dark:text-white">Escena #{{ $log->snapshot['scene_order'] ?? '?' }}</span>
                                                                    @if(!empty($log->snapshot['scene_slugline']))
                                                                        <span class="text-slate-500 truncate max-w-xs">({{ $log->snapshot['scene_slugline'] }})</span>
                                                                    @endif
                                                                    <span>·</span>
                                                                    <span class="font-bold text-slate-900 dark:text-white">Bloque #{{ $log->snapshot['block_order'] ?? '?' }}</span>
                                                                </div>
                                                                @if(!empty($log->snapshot['stage']))
                                                                    <span class="px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-800 uppercase font-bold text-[9px] text-slate-700 dark:text-slate-300">{{ $log->snapshot['stage'] }}</span>
                                                                @endif
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['raw_text']))
                                                            <div class="space-y-0.5">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-amber-700 dark:text-amber-400">Idea (Bruto):</span>
                                                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-sans text-xs whitespace-pre-wrap leading-relaxed">
                                                                    {{ $log->snapshot['raw_text'] }}
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['structured_text']))
                                                            <div class="space-y-0.5">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-sky-700 dark:text-sky-400">Tratamiento Narrativo:</span>
                                                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-sans text-xs whitespace-pre-wrap leading-relaxed">
                                                                    {{ $log->snapshot['structured_text'] }}
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if(!empty($log->snapshot['screenplay_content']))
                                                            <div class="space-y-0.5">
                                                                <span class="text-[10px] font-mono font-bold uppercase text-purple-700 dark:text-purple-400">Guion Literario:</span>
                                                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-mono text-[11px] whitespace-pre-wrap leading-relaxed">
                                                                    {{ $log->snapshot['screenplay_content'] }}
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Caso C: Escena (Creación, Edición, Borrado) -->
                                                @if(isset($log->snapshot['slugline']))
                                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono text-xs">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-slate-500">Encabezado:</span>
                                                            <span class="font-bold text-slate-900 dark:text-white">{{ $log->snapshot['slugline'] }}</span>
                                                        </div>
                                                        @if(isset($log->snapshot['time_of_day']))
                                                            <div class="flex items-center gap-2 text-[11px]">
                                                                <span class="text-slate-500">Momento del día:</span>
                                                                <span class="text-slate-800 dark:text-slate-200">{{ $log->snapshot['time_of_day'] }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <!-- Caso D: Justificación de Agujero de Guión -->
                                                @if(isset($log->snapshot['justification']))
                                                    <div class="p-2.5 rounded-lg bg-teal-50/80 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800 space-y-1.5 text-xs">
                                                        <div class="font-mono text-[10px] text-teal-800 dark:text-teal-300 font-bold uppercase">Explicación del Autor:</div>
                                                        <p class="text-slate-900 dark:text-slate-100 font-sans italic leading-relaxed">"{{ $log->snapshot['justification'] }}"</p>
                                                        @if(isset($log->snapshot['hole_reason']))
                                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono pt-1 border-t border-teal-200 dark:border-teal-800/60">
                                                                Inconsistencia previa: {{ $log->snapshot['hole_reason'] }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- 2. VISTA CÓDIGO TÉCNICO (JSON) -->
                                            <div x-show="viewMode === 'json'"
                                                 style="display: none;"
                                                 class="rounded-xl border border-slate-300 dark:border-slate-700 p-3 overflow-x-auto max-h-56 bg-slate-100 dark:bg-slate-900">
                                                <pre style="color: #000000 !important; background: transparent !important; white-space: pre-wrap; word-break: break-all; margin: 0; padding: 0; font-family: monospace; font-size: 11px; line-height: 1.45; font-weight: 500;">{{ json_encode($log->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
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
