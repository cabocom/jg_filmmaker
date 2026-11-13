                    <div class="px-3.5 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/80 flex items-center justify-between gap-2 sm:gap-4 shrink-0">
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <span class="text-lg sm:text-xl shrink-0">📑</span>
                            <div class="min-w-0">
                                <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5 sm:gap-2">
                                    <span>BLOQUE #{{ $detailBlock->block_order }}</span>
                                    <span class="px-1.5 sm:px-2 py-0.5 rounded text-[9px] sm:text-[10px] font-mono bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 uppercase font-bold">
                                        Etapa: {{ $detailActiveTab }}
                                    </span>
                                </h3>
                                <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                    Escena #{{ $detailBlock->scene?->scene_order }} · {{ $detailBlock->scene?->slugline }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                            <button onclick="window.dispatchEvent(new CustomEvent('play-tts', { detail: { scope: 'block', blockId: '{{ $detailBlock->id }}' } }))"
                                    type="button"
                                    title="Escuchar locución de este bloque"
                                    class="px-2 sm:px-2.5 py-1.5 rounded-lg text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-800 transition flex items-center gap-1 cursor-pointer">
                                <span>🔊</span>
                                <span class="hidden sm:inline">Escuchar</span>
                            </button>
                            <button wire:click="openDeleteBlockModal('{{ $detailBlock->id }}')"
                                    type="button"
                                    title="Eliminar este bloque permanentemente..."
                                    class="px-2 sm:px-2.5 py-1.5 rounded-lg text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 border border-rose-200 dark:border-rose-800 transition flex items-center gap-1 cursor-pointer">
                                <span>🗑️</span>
                                <span class="hidden sm:inline">Eliminar</span>
                            </button>
                            <button wire:click="closeBlockDetails"
                                    type="button"
                                    title="Cerrar panel lateral"
                                    class="px-2 sm:px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition flex items-center gap-1 cursor-pointer">
                                <span>✕</span>
                                <span class="hidden sm:inline">Cerrar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Pestañas internas para navegar por todas las etapas del bloque -->
                    <div class="px-3 sm:px-6 pt-1 sm:pt-2 border-b border-slate-200 dark:border-slate-800 bg-slate-100/50 dark:bg-slate-950 flex items-center gap-1 sm:gap-2 overflow-x-auto shrink-0">
                        <button wire:click="setDetailTab('raw')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer {{ $detailActiveTab === 'raw' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            1. Idea / Bruto
                        </button>
                        <button wire:click="setDetailTab('structured')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer flex items-center gap-1 {{ $detailActiveTab === 'structured' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            <span>2. Tratamiento</span>
                            @if($detailBlock->isStageLocked('structured'))
                                <span class="text-xs" title="Tratamiento protegido contra sobreescritura (🔒)">🔒</span>
                            @endif
                        </button>
                        <button wire:click="setDetailTab('screenplay')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer flex items-center gap-1 {{ $detailActiveTab === 'screenplay' ? 'border-amber-500 text-amber-600 dark:text-amber-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            <span>3. Guión</span>
                            @if($detailBlock->isStageLocked('screenplay'))
                                <span class="text-xs" title="Guión literario protegido contra sobreescritura (🔒)">🔒</span>
                            @endif
                        </button>
                        <button wire:click="setDetailTab('shots')"
                                type="button"
                                class="px-2.5 sm:px-3.5 py-2 border-b-2 text-xs font-semibold whitespace-nowrap transition cursor-pointer {{ $detailActiveTab === 'shots' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300' }}">
                            4. Planos ({{ $detailBlock->shots->count() }})
                        </button>
                    </div>

