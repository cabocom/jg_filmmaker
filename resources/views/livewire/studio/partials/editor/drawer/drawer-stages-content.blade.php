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
                                            @include('livewire.studio.partials.editor.drawer.drawer-shots-stage', ['detailBlock' => $detailBlock])
                                        @endif
                                    </div>
                            </div>
                        @endif
