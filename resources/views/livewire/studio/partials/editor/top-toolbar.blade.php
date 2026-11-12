    <!-- ================= BARRA SUPERIOR STICKY: TÍTULO DEL PROYECTO Y NAVEGACIÓN STORY ARCHITECT ================= -->
    <div class="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 border-b border-slate-200 dark:border-slate-800 shadow-xs backdrop-blur-md px-3 sm:px-6 lg:px-8 py-2 transition-colors">
        <div class="max-w-7xl mx-auto flex items-center gap-2 flex-wrap py-1">
            <!-- Identificación del Proyecto y Acciones Rápidas -->
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                <div class="flex items-center gap-1 shrink-0">
                    <span class="text-xs sm:text-sm font-bold font-mono uppercase tracking-wider text-slate-900 dark:text-white truncate max-w-[200px] sm:max-w-xs" title="{{ $project->title }}">
                        {{ $project->title }}
                    </span>

                    <!-- Lápiz para cambiar nombre -->
                    <button wire:click="openEditTitleModal"
                            type="button"
                            title="Modificar nombre del proyecto"
                            class="p-1 rounded-md text-slate-400 hover:text-amber-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>

                    <!-- Iconos al lado del lápiz: Exportar PDF, Exportar MP3, Lectura TTS y Renderizar en Lote -->
                    <div class="flex items-center gap-1 ml-0.5 pl-1.5 border-l border-slate-200 dark:border-slate-700">
                        <!-- 1. Icono: Exportar Guión Completo a PDF -->
                        <button type="button"
                                wire:click="exportPdf"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1 px-1.5 py-1 rounded-lg text-xs font-semibold text-emerald-600 hover:text-emerald-500 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/60 transition cursor-pointer disabled:opacity-50"
                                title="Exportar guión completo a PDF">
                            <span wire:loading.remove wire:target="exportPdf" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="font-bold">PDF</span>
                            </span>
                            <span wire:loading wire:target="exportPdf" class="animate-spin text-xs">⏳</span>
                        </button>

                        <!-- 2. Icono: Descargar Locución Completa a MP3 -->
                        @if($this->isAudioComplete)
                            <button type="button"
                                    wire:click="exportMp3"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1 px-1.5 py-1 rounded-lg text-xs font-semibold text-violet-600 hover:text-violet-500 hover:bg-violet-50 dark:text-violet-400 dark:hover:bg-violet-950/60 transition cursor-pointer disabled:opacity-50"
                                    title="Descargar locución completa del guión en MP3">
                                <span wire:loading.remove wire:target="exportMp3" class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span class="font-bold">MP3</span>
                                </span>
                                <span wire:loading wire:target="exportMp3" class="animate-spin text-xs">⏳</span>
                            </button>
                        @else
                            <button type="button"
                                    disabled
                                    class="inline-flex items-center gap-1 px-1.5 py-1 rounded-lg text-xs font-semibold text-slate-400 dark:text-slate-600 cursor-not-allowed opacity-50"
                                    title="No puede descargarse porque faltan audios por generar, primero tienes que escucharlo.">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span class="font-bold">MP3</span>
                            </button>
                        @endif

                        <!-- 3. Icono: Lectura de Guión Automatizada (TTS) -->
                        <button type="button"
                                onclick="window.dispatchEvent(new CustomEvent('play-tts', { detail: { scope: 'project' } }))"
                                class="p-1.5 rounded-lg text-indigo-600 hover:text-indigo-500 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/60 transition cursor-pointer"
                                title="Lectura dramática de todo el guión con voces neurales (TTS)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                        </button>

                        <!-- 4. Icono: Renderizado / Procesamiento en Lote -->
                        <button type="button"
                                wire:click="openBatchModal"
                                class="p-1.5 rounded-lg text-amber-500 hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/60 transition cursor-pointer"
                                title="Renderizar en lote: estructurar brutos, generar guiones y desglosar planos">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Contador de Escenas -->
                <span class="px-2 py-0.5 rounded-lg text-xs font-bold font-mono bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                    {{ $scenes->count() }} {{ $scenes->count() === 1 ? 'escena' : 'escenas' }}
                </span>
            </div>

            <!-- Menú Desplegable Contraído de Etapas (Pipeline, Idea, Tratamiento, Guion, Planos/Tomas) -->
            @php
                $stageLabels = [
                    'pipeline' => ['icon' => '🎬', 'label' => 'Pipeline'],
                    'idea' => ['icon' => '💡', 'label' => 'Idea'],
                    'structured' => ['icon' => '📝', 'label' => 'Tratamiento'],
                    'screenplay' => ['icon' => '📜', 'label' => 'Guion'],
                    'shots' => ['icon' => '🎥', 'label' => 'Planos'],
                ];
                $currStage = $stageLabels[$activeView] ?? ['icon' => '🎬', 'label' => 'Pipeline'];
            @endphp
            <div class="relative shrink-0" x-data="{ stageMenuOpen: false }" @click.outside="stageMenuOpen = false">
                <button @click="stageMenuOpen = !stageMenuOpen"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition shadow-xs cursor-pointer shrink-0">
                    <span>{{ $currStage['icon'] }}</span>
                    <span>{{ $currStage['label'] }}</span>
                    <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': stageMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="stageMenuOpen"
                     x-transition
                     class="absolute left-0 top-full mt-1.5 w-44 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-1 z-50 space-y-0.5 font-mono text-xs"
                     style="display: none;">
                    @foreach($stageLabels as $key => $data)
                        <button wire:click="setActiveView('{{ $key }}')"
                                @click="stageMenuOpen = false"
                                type="button"
                                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-left {{ $activeView === $key ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition cursor-pointer">
                            <div class="flex items-center gap-2">
                                <span>{{ $data['icon'] }}</span>
                                <span>{{ $data['label'] }}</span>
                            </div>
                            @if($activeView === $key)
                                <span class="text-amber-600 dark:text-amber-400 text-xs">✓</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Botón de la Biblia de Proyecto -->
            <button type="button"
                    onclick="Livewire.dispatch('toggle-bible-drawer')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 hover:border-amber-500/40 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer shrink-0"
                    title="Biblia de Proyecto (Personajes y Localizaciones)">
                <span>📖</span>
                <span>Biblia</span>
            </button>

            <!-- Botón del Filtro de Agujeros de Guión con Menú Desplegable -->
            @php
                $totalPlotHoles = $scenes->flatMap->scriptBlocks->filter(fn($b) => filled($b->plot_hole))->count();
            @endphp
            <div class="relative inline-flex items-center shrink-0" x-data="{ holeMenuOpen: false }" @click.outside="holeMenuOpen = false">
                <div class="inline-flex items-center rounded-lg shadow-2xs border {{ $totalPlotHoles > 0 ? 'border-rose-300 dark:border-rose-800' : 'border-slate-200 dark:border-slate-700' }} overflow-hidden">
                    <!-- Acción principal: Analizar previa confirmación de tokens -->
                    <button wire:click="promptAnalyzePlotHoles"
                            wire:loading.attr="disabled"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono font-medium {{ $totalPlotHoles > 0 ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 hover:bg-rose-100' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200' }} transition cursor-pointer shrink-0 disabled:opacity-50"
                            title="Pasar filtro de agujeros de guión con IA (informa del coste en tokens antes de ejecutar)">
                        <span wire:loading.remove wire:target="promptAnalyzePlotHoles, analyzePlotHoles">🕳️</span>
                        <span wire:loading wire:target="promptAnalyzePlotHoles, analyzePlotHoles" class="animate-spin text-xs">⏳</span>
                        <span>Agujeros</span>
                        @if($totalPlotHoles > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $showPlotHoleAlerts ? 'bg-rose-500 text-white animate-pulse' : 'bg-slate-400 text-white' }}"
                                  title="{{ $showPlotHoleAlerts ? 'Avisos activados' : 'Avisos desactivados' }}">
                                {{ $totalPlotHoles }}
                            </span>
                        @endif
                    </button>

                    <!-- Botón del Desplegable (Flecha) -->
                    <button @click="holeMenuOpen = !holeMenuOpen"
                            type="button"
                            class="px-1.5 py-1 text-xs border-l {{ $totalPlotHoles > 0 ? 'border-rose-200 dark:border-rose-800/80 bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 hover:bg-rose-100' : 'border-slate-200 dark:border-slate-700 bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300' }} transition cursor-pointer"
                            title="Opciones de agujeros de guión">
                        <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': holeMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Dropdown Panel de Agujeros de Guión -->
                <div x-show="holeMenuOpen"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-0 top-full mt-1.5 w-48 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-1 z-50 space-y-0.5 font-mono text-xs">
                    
                    <!-- 1. Analizar con IA -->
                    <button wire:click="promptAnalyzePlotHoles"
                            @click="holeMenuOpen = false"
                            type="button"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                        <span>🔍</span>
                        <span>Analizar con IA</span>
                    </button>

                    <!-- 2. Ver u Ocultar Avisos -->
                    @if($showPlotHoleAlerts)
                        <button wire:click="togglePlotHoleAlerts"
                                @click="holeMenuOpen = false"
                                type="button"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                            <span>🙈</span>
                            <span>Ocultar avisos</span>
                        </button>
                    @else
                        <button wire:click="togglePlotHoleAlerts"
                                @click="holeMenuOpen = false"
                                type="button"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-amber-600 dark:text-amber-400 font-bold hover:bg-amber-500/10 transition cursor-pointer">
                            <span>👁️</span>
                            <span>Mostrar avisos</span>
                        </button>
                    @endif

                    <div class="border-t border-slate-100 dark:border-slate-800 my-0.5"></div>

                    <!-- 3. Borrar marcas -->
                    <button wire:click="clearPlotHoles"
                            @click="holeMenuOpen = false"
                            type="button"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-left text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition cursor-pointer">
                        <span>🗑️</span>
                        <span>Borrar avisos</span>
                    </button>
                </div>
            </div>

            <!-- Botón de Historial y Trazabilidad del Proyecto -->
            <button type="button"
                    wire:click="openActivityHistoryModal"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 hover:border-sky-500/40 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer shrink-0"
                    title="Historial de acciones y trazabilidad en orden cronológico inverso">
                <span>📜</span>
                <span>Historial</span>
            </button>


        </div>
    </div>

    <!-- Mensajes Flash Globales -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        @if(session()->has('success'))
            <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session()->has('error'))
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif
        @if(session()->has('warning'))
            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>🕳️</span>
                    <span>{{ session('warning') }}</span>
                </div>
            </div>
        @endif
        @if(session()->has('info'))
            <div class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-800 dark:text-indigo-300 text-xs flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span>ℹ️</span>
                    <span>{{ session('info') }}</span>
                </div>
            </div>
        @endif
    </div>
