                    <!-- Contenido de Bloques de la Escena (Acordeón expandible) -->
                    @if($isExpanded)
                        @if($sceneStage === 'timeline')
                            <div class="p-3 sm:p-5">
                                <livewire:studio.rough-cut-timeline :project="$project" :active-scene-id="$scene->id" :key="'timeline-'.$scene->id" />
                            </div>
                        @else
                            <div class="p-3 sm:p-5 space-y-0.5">
                                <!-- Divisor Inicial de Bloque: Gutenberg (Invisible en reposo, revelado al pasar el ratón) -->
                                <div x-data="{ isInitPlusHovered: false }"
                                     @mouseenter="isInitPlusHovered = true"
                                     @mouseleave="isInitPlusHovered = false"
                                     class="gutenberg-container relative py-1 my-0 flex items-center justify-center cursor-pointer z-[5] w-full"
                                     style="z-index: 5;">
                                    <div class="gutenberg-divider-line"
                                         :class="isInitPlusHovered ? 'is-visible' : ''"
                                         style="height: 2px; background-color: #f59e0b;"></div>
                                    <button wire:click="openNewBlockModal(0, '{{ $scene->id }}')"
                                            type="button"
                                            title="Insertar bloque al inicio de la escena"
                                            :class="isInitPlusHovered ? 'is-visible' : ''"
                                            class="gutenberg-divider-btn opacity-0 scale-75 pointer-events-none relative z-[6] w-7 h-7 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold flex items-center justify-center shadow-md hover:scale-110 transition-all duration-150 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>

                                @forelse($scene->scriptBlocks as $block)
                                    @php
                                        $isSelected = ($selectedBlockId === $block->id);
                                    @endphp

                                    <!-- Elemento Continuo de Bloque con Drag & Drop Completo de Fila y Efecto Hover Garantizado -->
                                    <div id="block-{{ $block->id }}"
                                         x-data="{ isBlockHovered: false, isDragging: false, isOver: false }"
                                         @mouseenter="isBlockHovered = true"
                                         @mouseleave="isBlockHovered = false"
                                         @dragover.prevent="isOver = true"
                                         @dragleave="isOver = false"
                                         @drop.prevent="
                                             isOver = false;
                                             const srcId = event.dataTransfer.getData('text/plain');
                                             if (srcId && srcId !== '{{ $block->id }}') {
                                                 $wire.reorderBlock(srcId, {{ $block->block_order }}, '{{ $scene->id }}');
                                             }
                                         "
                                         :class="{
                                             'opacity-40 scale-[0.99]': isDragging,
                                             'border-amber-500 ring-2 ring-amber-500/50 bg-amber-50/50 dark:bg-amber-950/30': isOver
                                         }"
                                         wire:click="selectBlock('{{ $block->id }}')"
                                         class="script-block-item group relative rounded-xl px-2.5 py-1.5 transition-all cursor-pointer border-l-2 {{ $isSelected ? 'is-selected border-amber-500 shadow-2xs' : 'border-transparent' }}">

                                        <div class="flex items-start gap-2">
                                            <!-- Tirador Drag & Drop con draggable=true directo y arrastre de fila completa -->
                                            <div draggable="true"
                                                 @dragstart.stop="
                                                     event.dataTransfer.setData('text/plain', '{{ $block->id }}');
                                                     event.dataTransfer.effectAllowed = 'move';
                                                     const blockEl = document.getElementById('block-{{ $block->id }}');
                                                     if (event.dataTransfer.setDragImage && blockEl) {
                                                         event.dataTransfer.setDragImage(blockEl, 20, 20);
                                                     }
                                                     isDragging = true;
                                                 "
                                                 @dragend="isDragging = false"
                                                 title="Arrastrar para mover este bloque de posición"
                                                 :class="(isBlockHovered || {{ $isSelected ? 'true' : 'false' }}) ? 'opacity-100' : 'opacity-0'"
                                                 class="shrink-0 w-5 h-6 mt-0.5 rounded flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-500/10 dark:text-slate-500 dark:hover:text-amber-400 cursor-grab active:cursor-grabbing transition-opacity duration-150 select-none group-hover:opacity-100">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <circle cx="7" cy="4" r="1.5" />
                                                    <circle cx="13" cy="4" r="1.5" />
                                                    <circle cx="7" cy="10" r="1.5" />
                                                    <circle cx="13" cy="10" r="1.5" />
                                                    <circle cx="7" cy="16" r="1.5" />
                                                    <circle cx="13" cy="16" r="1.5" />
                                                </svg>
                                            </div>

                                            <!-- Texto de la Historia según la etapa del menú de la escena individual -->
                                            <div class="flex-1 min-w-0 pr-14 sm:pr-24">
                                                @if($sceneStage === 'idea')
                                                    <!-- Etapa 1: Idea / Original / Texto Bruto -->
                                                    <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($block->raw_text ?: 'Sin notas o texto en bruto.') }}</div>
                                                @elseif($sceneStage === 'structured')
                                                    <!-- Etapa 2: Tratamiento Cinematográfico -->
                                                    @if(!empty($block->structured_text))
                                                        <div class="text-sm text-slate-800 dark:text-slate-200 leading-relaxed font-sans whitespace-pre-line text-left">{{ trim($block->structured_text) }}</div>
                                                    @else
                                                        <div class="py-0.5 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                            <span>[Tratamiento pendiente de estructurar para este bloque]</span>
                                                            <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'structured')" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline cursor-pointer">
                                                                ⚡ Abrir detalles para estructurar ➔
                                                            </button>
                                                        </div>
                                                    @endif
                                                @elseif($sceneStage === 'screenplay')
                                                    <!-- Etapa 3: Guión Literario (Story Architect / Fountain) -->
                                                    @if(!empty($block->screenplay_content))
                                                        <div class="screenplay-rendered-container font-screenplay text-sm max-w-2xl mx-auto w-full py-1">
                                                            {!! \App\Services\ScreenplayFormatterService::formatToHtml($block->screenplay_content) !!}
                                                        </div>
                                                    @else
                                                        <div class="py-0.5 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                            <span>[Guión literario pendiente de formatear para este bloque]</span>
                                                            <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'screenplay')" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline cursor-pointer">
                                                                🎬 Abrir detalles para convertir a guión ➔
                                                            </button>
                                                        </div>
                                                    @endif
                                                @elseif($sceneStage === 'shots')
                                                    <!-- Etapa 4: Planos y Tomas -->
                                                    @if($block->shots->isNotEmpty())
                                                        <div class="space-y-1.5">
                                                            @foreach($block->shots as $shot)
                                                                <div class="p-2 rounded-lg bg-slate-50/80 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                                                                    <div class="flex items-center gap-2">
                                                                        @if($shot->hasStoryboard())
                                                                            <img src="{{ $shot->storyboard_url }}"
                                                                                 alt="Shot {{ $shot->shot_number }}"
                                                                                 class="h-8 w-14 rounded object-cover border border-slate-300 dark:border-slate-700 cursor-pointer shrink-0"
                                                                                 wire:click.stop="openStoryboardPreview('{{ $shot->id }}')">
                                                                        @endif
                                                                        <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-1.5 py-0.5 rounded text-[11px]">
                                                                            --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                                        </span>
                                                                        <span class="text-slate-700 dark:text-slate-300">{{ $shot->description }}</span>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="py-0.5 flex items-center justify-between text-xs italic text-slate-400 dark:text-slate-500">
                                                            <span>[Sin planos técnicos desglosados en este bloque]</span>
                                                            <button wire:click.stop="openBlockDetails('{{ $block->id }}', 'shots')" class="text-cyan-600 dark:text-cyan-400 font-semibold hover:underline cursor-pointer">
                                                                📹 Abrir detalles para desglosar planos ➔
                                                            </button>
                                                        </div>
                                                    @endif
                                                @endif

                                                <!-- Aviso de Agujero de Guión (Persistente hasta nuevo análisis o justificación) -->
                                                @if($showPlotHoleAlerts && $block->hasPlotHole())
                                                    <div class="mt-2.5 p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-2.5 shadow-2xs">
                                                        <div class="flex items-start gap-2.5 min-w-0">
                                                            <span class="text-sm shrink-0">🕳️</span>
                                                            <div class="space-y-0.5">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="font-bold uppercase tracking-wider text-[10px] text-rose-900 dark:text-rose-300">
                                                                        Agujero de Guión Detectado
                                                                    </span>
                                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-rose-200/90 dark:bg-rose-900/80 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-700 uppercase">
                                                                        Detectado en: {{ $block->getPlotHoleStageLabel() }}
                                                                    </span>
                                                                </div>
                                                                <p class="leading-relaxed font-sans text-xs text-rose-800 dark:text-rose-200">
                                                                    {{ $block->plot_hole }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <button wire:click.stop="openJustifyModal('{{ $block->id }}')"
                                                                type="button"
                                                                class="px-2 py-1 rounded text-[11px] font-bold bg-white hover:bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:hover:bg-rose-800 dark:text-rose-100 border border-rose-300 dark:border-rose-700 transition shadow-2xs cursor-pointer flex items-center gap-1 shrink-0"
                                                                title="Justificar por qué no es un agujero de guión y retirarlo">
                                                            <span>✅</span>
                                                            <span>Justificar</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Botones FLOTANTES en la esquina superior derecha (no en línea con el texto) -->
                                            <div class="absolute top-1 right-1 sm:top-1.5 sm:right-2 z-10 flex items-center gap-1 transition-opacity bg-white/90 dark:bg-slate-900/90 backdrop-blur-xs p-0.5 rounded-lg border border-slate-200/60 dark:border-slate-800/60 shadow-2xs">
                                                @if($showPlotHoleAlerts && $block->hasPlotHole())
                                                    <span class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30"
                                                          title="Agujero de guión detectado en este bloque">
                                                        🕳️
                                                    </span>
                                                @endif
                                                @if($block->isStageLocked('structured') || $block->isStageLocked('screenplay'))
                                                    <span class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30"
                                                          title="Este bloque tiene etapas protegidas contra sobreescritura por modificaciones manuales">
                                                        🔒
                                                    </span>
                                                @endif
                                                <button onclick="window.dispatchEvent(new CustomEvent('play-tts', { detail: { scope: 'block', blockId: '{{ $block->id }}' } }))"
                                                        type="button"
                                                        title="Escuchar locución de este bloque (TTS)"
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition shadow-2xs cursor-pointer">
                                                    <span>🔊</span>
                                                </button>
                                                <button wire:click.stop="openBlockDetails('{{ $block->id }}')"
                                                        type="button"
                                                        title="Abrir detalles y etapas en panel lateral"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-500 hover:text-amber-700 dark:hover:text-amber-300 transition shadow-2xs cursor-pointer">
                                                    <span class="text-xs">📑</span>
                                                    <span class="text-[11px] font-bold hidden sm:inline">Detalles</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Divisor Gutenberg Intermedio: Revelado al pasar el ratón -->
                                    <div x-data="{ isPlusHovered: false }"
                                         @mouseenter="isPlusHovered = true"
                                         @mouseleave="isPlusHovered = false"
                                         class="gutenberg-container relative py-1 my-0 flex items-center justify-center cursor-pointer z-[5] w-full"
                                         style="z-index: 5;">
                                        <div class="gutenberg-divider-line"
                                             :class="isPlusHovered ? 'is-visible' : ''"
                                             style="height: 2px; background-color: #f59e0b;"></div>
                                        <button wire:click="openNewBlockModal({{ $block->block_order }}, '{{ $scene->id }}')"
                                                type="button"
                                                title="Añadir bloque aquí"
                                                :class="isPlusHovered ? 'is-visible' : ''"
                                                class="gutenberg-divider-btn opacity-0 scale-75 pointer-events-none relative z-[6] w-7 h-7 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold flex items-center justify-center shadow-md hover:scale-110 transition-all duration-150 cursor-pointer">
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-center py-8 bg-slate-50 dark:bg-slate-950 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 p-4 space-y-2">
                                        <p class="text-xs text-slate-500 font-medium">No hay bloques en esta escena todavía.</p>
                                        <button wire:click="openNewBlockModal(null, '{{ $scene->id }}')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-lg shadow-2xs cursor-pointer">
                                            + Crear Primer Bloque
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>

