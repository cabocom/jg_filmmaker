                        <!-- Alerta de Agujero de Guión si está marcado -->
                        @if($detailBlock->hasPlotHole())
                            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-3 shadow-xs">
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="text-xl shrink-0">🕳️</span>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold tracking-wide uppercase text-[11px] block text-rose-900 dark:text-rose-300">
                                                Agujero de Guión Detectado
                                            </span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-200/90 dark:bg-rose-900/80 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-700 uppercase">
                                                Detectado en: {{ $detailBlock->getPlotHoleStageLabel() }}
                                            </span>
                                        </div>
                                        <p class="leading-relaxed font-sans text-rose-800 dark:text-rose-200">
                                            {{ $detailBlock->plot_hole }}
                                        </p>
                                    </div>
                                </div>
                                <button wire:click="openJustifyModal('{{ $detailBlock->id }}')"
                                        type="button"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-white hover:bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:hover:bg-rose-800 dark:text-rose-100 border border-rose-300 dark:border-rose-700 transition shadow-2xs cursor-pointer flex items-center gap-1.5 shrink-0"
                                        title="Justificar por qué no es un agujero de guión y retirarlo">
                                    <span>✅</span>
                                    <span>Justificar</span>
                                </button>
                            </div>
                        @endif

                        <!-- Sección de Agujeros Justificados -->
                        @if($detailBlock->hasJustifiedPlotHoles())
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-3 shadow-2xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                        <span>✅</span>
                                        <span>Agujeros justificados ({{ count($detailBlock->getJustifiedPlotHoles()) }})</span>
                                    </span>
                                </div>
                                <div class="space-y-2.5">
                                    @foreach($detailBlock->getJustifiedPlotHoles() as $jHole)
                                        <div class="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs space-y-1.5">
                                            <div class="text-slate-500 dark:text-slate-400 font-mono text-[10px] flex items-center justify-between gap-2 flex-wrap">
                                                <div class="flex items-center gap-1.5">
                                                    <span>🕳️ Motivo IA original:</span>
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-semibold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                        {{ $jHole['stage_label'] ?? 'Idea (Bruto)' }}
                                                    </span>
                                                </div>
                                                <span>{{ \Carbon\Carbon::parse($jHole['justified_at'])->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <p class="text-slate-600 dark:text-slate-400 italic">"{{ $jHole['hole_reason'] }}"</p>
                                            <div class="pt-1.5 border-t border-slate-100 dark:border-slate-800 text-emerald-800 dark:text-emerald-300">
                                                <span class="font-bold uppercase text-[10px] block">Motivo por el que no es un agujero:</span>
                                                <p class="text-slate-900 dark:text-slate-100 font-sans mt-0.5">{{ $jHole['justification'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Barra de Términos Fijos y Candados -->
                        <div class="p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-900 dark:text-amber-300 space-y-2">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <span class="font-bold text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🔒</span>
                                    <span>Términos Fijos:</span>
                                </span>
                                @if(!empty($detailLocks['pinned_terms']))
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @foreach($detailLocks['pinned_terms'] as $orig => $rep)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-[11px] font-mono">
                                                <span>{{ $orig }} ➜ {{ $rep }}</span>
                                                <button wire:click="removePinnedTerm('{{ $detailBlock->id }}', '{{ $orig }}')" class="text-rose-500 hover:text-rose-700 ml-1 cursor-pointer">✕</button>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 italic">Ningún término fijado en este bloque.</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 pt-1 border-t border-amber-200/50 dark:border-amber-500/10 flex-wrap">
                                <input type="text" wire:model="newPinnedTerm" placeholder="Término original (ej: Javi)" maxlength="80" class="text-xs px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white flex-1 min-w-[120px] focus:ring-1 focus:ring-amber-500" />
                                <span class="text-amber-700 dark:text-amber-400">➜</span>
                                <input type="text" wire:model="newPinnedReplacement" placeholder="Fijar como (ej: Javier)" maxlength="80" class="text-xs px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white flex-1 min-w-[120px] focus:ring-1 focus:ring-amber-500" />
                                <button type="button" wire:click="addPinnedTerm('{{ $detailBlock->id }}')" class="px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-2xs transition cursor-pointer">Fijar</button>
                            </div>
                        </div>

