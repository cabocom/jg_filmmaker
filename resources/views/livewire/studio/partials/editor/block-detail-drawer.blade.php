    <!-- ================= MODAL LATERAL DERECHO AL 60% (DETALLES Y EDICIÓN DE BLOQUE) ================= -->
    @if($detailBlockId)
        @php
            $detailBlock = \App\Models\ScriptBlock::with('scene.location', 'shots.takes')->find($detailBlockId);
            $detailLocks = $detailBlock?->user_locks ?? [];
        @endphp

        @if($detailBlock)
            <!-- Contenedor general del Drawer -->
            <div class="fixed inset-0 z-50 overflow-hidden"
                 x-data>

                <!-- Backdrop suave exterior: NO muy oscurecido (sin cierre accidental al hacer clic fuera) -->
                <div class="fixed inset-0 bg-slate-950/25 backdrop-blur-[2px] transition-opacity"></div>

                <!-- Panel lateral deslizante alineado a la derecha (adaptativo 100% en móvil, 60% en escritorio) -->
                <div class="fixed inset-y-0 right-0 z-50 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col h-full w-full sm:w-[540px] md:w-[620px] lg:w-[60vw] max-w-full">

                    <!-- Header del Drawer -->
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

                    <!-- Cuerpo scrolleable del Drawer -->
                    <div class="flex-1 overflow-y-auto p-3.5 sm:p-6 space-y-4 sm:space-y-6">
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

                        <!-- Contenido y Edición según la pestaña seleccionada en el Drawer -->
                        @if($isEditingInDrawer)
                            <!-- Modo Edición dentro del Drawer -->
                            <div class="space-y-4">
                                @if($detailActiveTab === 'raw')
                                    <div>
                                        <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Texto Bruto / Idea
                                        </label>
                                        <textarea wire:model="editRawText" rows="12" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-sans leading-relaxed focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-inner"></textarea>
                                    </div>
                                @elseif($detailActiveTab === 'structured')
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Tratamiento Cinematográfico
                                        </label>
                                        <textarea wire:model="editStructuredText" rows="12" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-indigo-300 dark:border-indigo-700 text-slate-900 dark:text-white font-sans leading-relaxed focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition shadow-inner"></textarea>
                                    </div>
                                @elseif($detailActiveTab === 'screenplay')
                                    <div>
                                        <label class="block text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider font-mono mb-1.5">
                                            Edición: Guión Literario (Fountain)
                                        </label>
                                        <textarea wire:model="editScreenplayContent" rows="14" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-amber-300 dark:border-amber-500/50 text-slate-900 dark:text-amber-100 font-mono leading-relaxed focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition shadow-inner"></textarea>
                                    </div>
                                @elseif($detailActiveTab === 'shots')
                                    <div x-data="{
                                        insertCut(type) {
                                            const el = this.$refs.shotsArea;
                                            const start = el.selectionStart;
                                            const end = el.selectionEnd;
                                            const text = el.value;
                                            const tag = '\n\n--- shot (' + type + ') ---\n';
                                            el.value = text.substring(0, start) + tag + text.substring(end);
                                            el.selectionStart = el.selectionEnd = start + tag.length;
                                            el.dispatchEvent(new Event('input'));
                                            el.focus();
                                        }
                                    }">
                                        <div class="flex flex-wrap items-center justify-between gap-1.5 mb-1.5">
                                            <label class="block text-xs font-bold text-cyan-700 dark:text-cyan-400 uppercase tracking-wider font-mono">
                                                Edición: Delimitadores de Planos Técnicos
                                            </label>
                                            <div class="flex items-center gap-1">
                                                <span class="text-[10px] text-slate-400 font-mono hidden sm:inline">Insertar plano:</span>
                                                <button type="button" @click="insertCut('PG')" title="Plano General (PG): Muestra el escenario completo y sitúa al sujeto en su entorno" class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-100 hover:bg-cyan-200 text-cyan-800 dark:bg-cyan-950 dark:hover:bg-cyan-900 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-800 cursor-pointer">+ PG</button>
                                                <button type="button" @click="insertCut('PM')" title="Plano Medio (PM): Corta al sujeto a la altura de la cintura; ideal para interacciones y diálogos" class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-100 hover:bg-cyan-200 text-cyan-800 dark:bg-cyan-950 dark:hover:bg-cyan-900 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-800 cursor-pointer">+ PM</button>
                                                <button type="button" @click="insertCut('PP')" title="Primer Plano (PP): Encuadre a la altura de hombros o rostro; enfocado en emociones y miradas" class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-100 hover:bg-cyan-200 text-cyan-800 dark:bg-cyan-950 dark:hover:bg-cyan-900 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-800 cursor-pointer">+ PP</button>
                                                <button type="button" @click="insertCut('PD')" title="Plano Detalle (PD): Centrado en un objeto concreto o elemento específico relevante" class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-100 hover:bg-cyan-200 text-cyan-800 dark:bg-cyan-950 dark:hover:bg-cyan-900 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-800 cursor-pointer">+ PD</button>
                                                <button type="button" @click="insertCut('PA')" title="Plano Americano (PA): Corta al sujeto a la altura de las rodillas (plano 3/4)" class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-100 hover:bg-cyan-200 text-cyan-800 dark:bg-cyan-950 dark:hover:bg-cyan-900 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-800 cursor-pointer">+ PA</button>
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-2">
                                            💡 Sitúa el cursor en el texto donde desees cambiar de plano y pulsa un botón (+PG, +PM, +PP...). No incluyas números en las etiquetas: la numeración correlativa (1, 2, 3...) se calcula automáticamente al guardar.
                                        </p>
                                        <textarea x-ref="shotsArea" wire:model="editScreenplayContent" rows="14" class="w-full px-4 py-3 text-sm rounded-xl bg-white dark:bg-slate-950 border border-cyan-300 dark:border-cyan-700 text-slate-900 dark:text-cyan-100 font-mono leading-relaxed focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition shadow-inner"></textarea>
                                    </div>
                                @endif

                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                                    <button wire:click="cancelEditingInDrawer" type="button" class="px-3 py-1.5 rounded-lg text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                                        Cancelar
                                    </button>
                                    <button wire:click="saveBlockFromDrawer" type="button" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                                        ✓ Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- Modo Visualización en el Drawer con Acciones de IA y Trazabilidad -->
                            <div class="space-y-5">
                                <!-- Barra de Acciones del Drawer según la etapa -->
                                <div class="flex flex-wrap items-center justify-between gap-2.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                    <div class="flex items-center gap-2">
                                        <button wire:click="startEditingInDrawer"
                                                type="button"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 hover:border-amber-400 transition shadow-2xs cursor-pointer">
                                            @if($detailActiveTab === 'shots')
                                                ✏️ Editar Planos
                                            @else
                                                ✏️ Editar Texto
                                            @endif
                                        </button>
                                        @if($detailActiveTab !== 'raw')
                                            <button wire:click="openAiTrace('{{ $detailBlock->id }}', '{{ $detailActiveTab }}')"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800/60 transition flex items-center gap-1.5 cursor-pointer">
                                                <span>⚡</span>
                                                <span>Trazabilidad IA</span>
                                            </button>
                                        @endif
                                    </div>

                                    <!-- Botones de Acción de IA Contextual -->
                                    <div>
                                        @if($detailActiveTab === 'raw')
                                            @php
                                                $isStructuredLocked = $detailBlock->isStageLocked('structured');
                                            @endphp
                                            @if($isStructuredLocked)
                                                <button type="button"
                                                        disabled
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-300 dark:border-slate-700 cursor-not-allowed flex items-center gap-1.5 opacity-60"
                                                        title="El tratamiento o guión tiene modificaciones manuales protegidas. Desbloquéalo para permitir regenerar desde el bruto.">
                                                    <span>🔒</span>
                                                    <span>Tratamiento Protegido</span>
                                                </button>
                                            @else
                                                <button wire:key="btn-action-structure-{{ $detailBlock->id }}"
                                                        wire:click="promptBlockAction('{{ $detailBlock->id }}', 'structure')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="promptBlockAction('{{ $detailBlock->id }}', 'structure')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                                    <span wire:loading.remove wire:target="promptBlockAction('{{ $detailBlock->id }}', 'structure')">⚡ Estructurar Tratamiento</span>
                                                    <span wire:loading wire:target="promptBlockAction('{{ $detailBlock->id }}', 'structure')" class="flex items-center gap-1.5">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                        <span>Calculando tokens...</span>
                                                    </span>
                                                </button>
                                            @endif
                                        @elseif($detailActiveTab === 'structured')
                                            @php
                                                $isScreenplayLocked = $detailBlock->isStageLocked('screenplay');
                                            @endphp
                                            @if($isScreenplayLocked)
                                                <button type="button"
                                                        disabled
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-300 dark:border-slate-700 cursor-not-allowed flex items-center gap-1.5 font-mono opacity-60"
                                                        title="El guión literario tiene modificaciones manuales protegidas. Desbloquéalo en la pestaña 'Guión' para permitir regenerar.">
                                                    <span>🔒</span>
                                                    <span>Guión Protegido</span>
                                                </button>
                                            @else
                                                <button wire:key="btn-action-screenplay-{{ $detailBlock->id }}"
                                                        wire:click="promptBlockAction('{{ $detailBlock->id }}', 'screenplay')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="promptBlockAction('{{ $detailBlock->id }}', 'screenplay')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 shadow-sm transition flex items-center gap-1.5 font-mono cursor-pointer">
                                                    <span wire:loading.remove wire:target="promptBlockAction('{{ $detailBlock->id }}', 'screenplay')">🎬 Convertir a Guión</span>
                                                    <span wire:loading wire:target="promptBlockAction('{{ $detailBlock->id }}', 'screenplay')" class="flex items-center gap-1.5">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                        <span>Calculando tokens...</span>
                                                    </span>
                                                </button>
                                            @endif
                                        @elseif($detailActiveTab === 'screenplay')
                                            <button wire:key="btn-action-shots-{{ $detailBlock->id }}"
                                                    wire:click="promptBlockAction('{{ $detailBlock->id }}', 'shots')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="promptBlockAction('{{ $detailBlock->id }}', 'shots')"
                                                    type="button"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                                <span wire:loading.remove wire:target="promptBlockAction('{{ $detailBlock->id }}', 'shots')">📹 Desglosar Planos</span>
                                                <span wire:loading wire:target="promptBlockAction('{{ $detailBlock->id }}', 'shots')" class="flex items-center gap-1.5">
                                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                    <span>Calculando tokens...</span>
                                                </span>
                                            </button>
                                        @elseif($detailActiveTab === 'shots')
                                            <div class="flex items-center gap-2">
                                                <button wire:click="promptBlockAction('{{ $detailBlock->id }}', 'shots')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="promptBlockAction('{{ $detailBlock->id }}', 'shots')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed text-white shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                                    <span wire:loading.remove wire:target="promptBlockAction('{{ $detailBlock->id }}', 'shots')">⚡ Regenerar Planos</span>
                                                    <span wire:loading wire:target="promptBlockAction('{{ $detailBlock->id }}', 'shots')" class="flex items-center gap-1.5">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                        <span>Calculando tokens...</span>
                                                    </span>
                                                </button>
                                                <button wire:click="startNewShotForBlock('{{ $detailBlock->id }}')"
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 cursor-pointer">
                                                    + Añadir Plano
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Vista de Texto según la etapa en el Drawer (Estilo homogéneo unificado) -->
                                @if($detailActiveTab === 'raw')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-2 shadow-2xs">
                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">1. Texto Bruto Original:</span>
                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($detailBlock->raw_text ?: 'Sin notas brutas.') }}</div>
                                    </div>
                                @elseif($detailActiveTab === 'structured')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-3 shadow-2xs">
                                        <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-indigo-100 dark:border-indigo-900/40">
                                            <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">2. Tratamiento Dramático:</span>
                                            @if($detailBlock->hasManualOverride('structured') || $detailBlock->isStageLocked('structured'))
                                                <div class="flex items-center gap-2">
                                                    @if($detailBlock->isStageLocked('structured'))
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                                            <span>🔒</span>
                                                            <span>Modificado manualmente · Protegido</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'structured')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 transition cursor-pointer"
                                                                title="Desbloquear para permitir que la IA regenere esta etapa">
                                                            🔓 Desbloquear
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                                            <span>🔓</span>
                                                            <span>Modificado manualmente · Desbloqueado</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'structured')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition cursor-pointer"
                                                                title="Bloquear para proteger contra regeneraciones de la IA">
                                                            🔒 Proteger
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        @if($detailBlock->hasManualOverride('structured'))
                                            <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                                <span class="text-sm">⚠️</span>
                                                <div class="leading-snug">
                                                    <strong class="font-bold">Modificación manual detectada:</strong>
                                                    @if($detailBlock->isStageLocked('structured'))
                                                        Este texto está <strong>protegido (🔒)</strong>. Si vuelves a regenerar desde el texto bruto, se conservarán tus cambios y no se sobreescribirán.
                                                    @else
                                                        Este texto está <strong>desbloqueado (🔓)</strong>. Si ejecutas la estructuración desde el texto bruto, <strong>perderás tus cambios manuales</strong>.
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($detailBlock->structured_text ?: 'Aún no se ha estructurado el tratamiento para este bloque.') }}</div>
                                    </div>
                                @elseif($detailActiveTab === 'screenplay')
                                    <div class="p-5 rounded-xl bg-indigo-50/20 dark:bg-slate-950/70 border border-indigo-200/50 dark:border-indigo-900/40 space-y-3 shadow-2xs">
                                        <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-indigo-100 dark:border-indigo-900/40">
                                            <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">3. Guión Literario (Story Architect):</span>
                                            @if($detailBlock->hasManualOverride('screenplay') || $detailBlock->isStageLocked('screenplay'))
                                                <div class="flex items-center gap-2">
                                                    @if($detailBlock->isStageLocked('screenplay'))
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                                            <span>🔒</span>
                                                            <span>Modificado manualmente · Protegido</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'screenplay')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 transition cursor-pointer"
                                                                title="Desbloquear para permitir que la IA regenere esta etapa">
                                                            🔓 Desbloquear
                                                        </button>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                                            <span>🔓</span>
                                                            <span>Modificado manualmente · Desbloqueado</span>
                                                        </span>
                                                        <button wire:click="toggleStageLock('{{ $detailBlock->id }}', 'screenplay')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition cursor-pointer"
                                                                title="Bloquear para proteger contra regeneraciones de la IA">
                                                            🔒 Proteger
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        @if($detailBlock->hasManualOverride('screenplay'))
                                            <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                                <span class="text-sm">⚠️</span>
                                                <div class="leading-snug">
                                                    <strong class="font-bold">Modificación manual detectada:</strong>
                                                    @if($detailBlock->isStageLocked('screenplay'))
                                                        Este guión está <strong>protegido (🔒)</strong>. Si regeneras desde el tratamiento o el bruto, tus cambios de diálogo/acción se preservarán y no se sobreescribirán.
                                                    @else
                                                        Este guión está <strong>desbloqueado (🔓)</strong>. Si ejecutas la conversión a guión desde el tratamiento, <strong>perderás estos cambios manuales</strong>.
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        @if(!empty($detailBlock->screenplay_content))
                                            <div class="screenplay-rendered-container font-screenplay text-sm max-w-2xl mx-auto w-full py-1">
                                                {!! \App\Services\ScreenplayFormatterService::formatToHtml($detailBlock->screenplay_content) !!}
                                            </div>
                                        @else
                                            <div class="text-sm text-slate-400 dark:text-slate-500 italic font-screenplay">Aún no se ha formateado a guión literario.</div>
                                        @endif
                                    </div>
                                @elseif($detailActiveTab === 'shots')
                                    <div class="space-y-4">
                                        <!-- Barra de Estilo de Storyboard & Condicionamiento -->
                                        <div class="p-3 rounded-xl bg-slate-100/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-2">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                                                        🎨 Estilo Visual Storyboard (Google AI):
                                                    </span>
                                                    @php
                                                        $refCharCount = $project->characters->filter(fn($c) => $c->hasReferenceImage())->count();
                                                    @endphp
                                                    @if($refCharCount > 0)
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20" title="La IA condicionará la imagen según la ficha de modelo de personajes">
                                                            <span>👤</span> {{ $refCharCount }} modelo{{ $refCharCount > 1 ? 's' : '' }} turn-around
                                                        </span>
                                                    @endif
                                                </div>
                                                <!-- Selector de Estilos -->
                                                <div class="flex items-center gap-1 bg-white dark:bg-slate-950 p-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[11px] font-medium">
                                                    @foreach(\App\Services\StoryboardService::STYLES as $sKey => $sVal)
                                                        <button wire:click="setStoryboardStyle('{{ $sKey }}')"
                                                                type="button"
                                                                class="px-2.5 py-1 rounded transition cursor-pointer {{ $storyboardStyle === $sKey ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                                                            {{ $sVal['name'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">
                                                {{ \App\Services\StoryboardService::STYLES[$storyboardStyle]['description'] ?? '' }}
                                            </p>
                                        </div>

                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 block pt-1">
                                            4. Desglose Técnico de Planos y Viñetas:
                                        </span>
                                        @forelse($detailBlock->shots as $shot)
                                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                                                    <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-2 py-0.5 rounded text-xs">
                                                        --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                    </span>
                                                    <div class="flex items-center gap-1.5">
                                                        <button wire:click="startEditShot('{{ $shot->id }}')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition cursor-pointer"
                                                                title="Editar este plano individualmente">
                                                            ✏️ Editar
                                                        </button>
                                                        <button wire:click="deleteShot('{{ $shot->id }}')"
                                                                wire:confirm="¿Seguro que deseas eliminar el Plano #{{ $shot->shot_number }}?"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50 transition cursor-pointer"
                                                                title="Eliminar este plano">
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </div>

                                                @if($editingShotId === $shot->id)
                                                    <!-- Formulario de edición rápida del plano individual -->
                                                    <div class="p-3.5 rounded-xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/30 space-y-3">
                                                        <div class="flex items-center gap-2">
                                                            <label class="text-[11px] font-bold font-mono text-slate-700 dark:text-slate-300 shrink-0">Escala de plano:</label>
                                                            <select wire:model="editingShotType" class="px-2.5 py-1 text-xs font-mono font-bold rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500">
                                                                <option value="PG">PG (Plano General)</option>
                                                                <option value="PE">PE (Plano Entero)</option>
                                                                <option value="PA">PA (Plano Americano)</option>
                                                                <option value="PM">PM (Plano Medio)</option>
                                                                <option value="PMC">PMC (Plano Medio Corto)</option>
                                                                <option value="PP">PP (Primer Plano)</option>
                                                                <option value="PPP">PPP (Primerísimo Primer Plano)</option>
                                                                <option value="PD">PD (Plano Detalle)</option>
                                                                <option value="ZEN">ZEN (Cenital)</option>
                                                                <option value="PIC">PIC (Picado)</option>
                                                                <option value="CTRP">CTRP (Contrapicado)</option>
                                                                <option value="NAD">NAD (Nadir)</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[11px] font-bold font-mono text-slate-700 dark:text-slate-300 mb-1">Descripción y encuadre:</label>
                                                            <textarea wire:model="editingShotDescription" rows="3" class="w-full px-3 py-2 text-xs rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500 font-sans"></textarea>
                                                            @error('editingShotDescription') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                                        </div>
                                                        <div class="flex items-center justify-end gap-2 pt-1">
                                                            <button wire:click="cancelEditShot" type="button" class="px-2.5 py-1 rounded text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 cursor-pointer">Cancelar</button>
                                                            <button wire:click="saveEditShot" type="button" class="px-3 py-1 rounded text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition cursor-pointer">✓ Guardar Plano</button>
                                                        </div>
                                                    </div>
                                                @else

                                                <!-- Viñeta de Storyboard -->
                                                @if($shot->hasStoryboard())
                                                    <div class="relative group rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-950 aspect-video shadow-md">
                                                        <img src="{{ $shot->storyboard_url }}"
                                                             alt="Storyboard shot {{ $shot->shot_number }}"
                                                             class="w-full h-full object-cover transition duration-300 group-hover:scale-102">
                                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-transparent to-transparent flex items-end justify-between p-3 opacity-90 group-hover:opacity-100 transition">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/70 text-amber-300 border border-amber-500/30 backdrop-blur-xs font-semibold">
                                                                    🎨 {{ \App\Services\StoryboardService::STYLES[$shot->storyboard_style]['name'] ?? 'Storyboard' }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button wire:click="openStoryboardPreview('{{ $shot->id }}')"
                                                                        type="button"
                                                                        title="Ampliar viñeta"
                                                                        class="px-2.5 py-1 rounded-lg bg-black/70 hover:bg-black/90 text-white text-xs border border-white/20 backdrop-blur-xs cursor-pointer flex items-center gap-1">
                                                                    <span>🔍</span> <span>Ampliar</span>
                                                                </button>
                                                                <button wire:click="openStoryboardModal('{{ $shot->id }}')"
                                                                        type="button"
                                                                        title="Componer y regenerar viñeta con IA"
                                                                        class="px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold flex items-center gap-1 cursor-pointer shadow-xs transition">
                                                                    <span>🔄 Regenerar</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="flex items-center justify-between p-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-900/60">
                                                         <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                             <span>🎨</span>
                                                             <span>Sin viñeta de storyboard generada</span>
                                                         </div>
                                                         <button wire:click="openStoryboardModal('{{ $shot->id }}')"
                                                                 type="button"
                                                                 class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs transition">
                                                             <span>✨ Generar Storyboard (IA)</span>
                                                         </button>
                                                    </div>
                                                @endif

                                                <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">{{ $shot->description }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-center py-6 text-xs text-slate-500">No hay planos desglosados en este bloque.</div>
                                        @endforelse

                                        <div class="pt-2">
                                            <button wire:click="startNewShotForBlock('{{ $detailBlock->id }}')"
                                                    type="button"
                                                    class="w-full py-2.5 px-3 rounded-xl border border-dashed border-cyan-400 dark:border-cyan-700 bg-cyan-50/50 hover:bg-cyan-50 dark:bg-cyan-950/20 dark:hover:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 font-mono text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer">
                                                <span>+</span>
                                                <span>Añadir Plano Manual</span>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif
