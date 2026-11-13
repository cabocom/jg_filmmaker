    <!-- Modal para Importar Guión y Crear Proyecto -->
    @if($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-2xl w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📥</span>
                        <span>Importar Guión (Crear Nuevo Proyecto)</span>
                    </h3>
                    <button wire:click="$set('showImportModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                @error('quota')
                    <div class="mt-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
                        {{ $message }}
                    </div>
                @enderror

                @if($importStep === 'upload')
                    <form wire:submit="processImportFile" class="mt-4 space-y-4">
                        <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-8 text-center bg-slate-50/50 dark:bg-slate-950/50 hover:border-amber-500/70 transition">
                            <span class="text-4xl block mb-2">📄</span>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1 cursor-pointer">
                                <span>Selecciona un archivo de guión (.txt o .pdf)</span>
                                <input type="file"
                                       wire:model="importFile"
                                       accept=".txt,.pdf"
                                       class="sr-only">
                            </label>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Detección automática de encabezados de escena (INT./EXT.), personajes y diálogos. Máx. 10 MB.
                            </p>

                            @if($importFile)
                                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs font-mono">
                                    <span>📎 {{ $importFile->getClientOriginalName() }}</span>
                                    <span>({{ number_format($importFile->getSize() / 1024, 1) }} KB)</span>
                                </div>
                            @endif

                            @error('importFile')
                                <span class="text-rose-500 dark:text-rose-400 text-xs mt-2 block font-medium">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <div>
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">Asistencia Cognitiva jg_plugin_filmmaker</span>
                                <span class="block text-[11px] text-slate-500">Refinar detección de escenas y personajes con IA si el guion no tiene formato estricto.</span>
                            </div>
                            <input type="checkbox" wire:model="useAiForImport" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 h-4 w-4">
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="button"
                                    wire:click="$set('showImportModal', false)"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                                <span wire:loading.remove wire:target="processImportFile">Analizar Guión</span>
                                <span wire:loading wire:target="processImportFile">Extrayendo texto...</span>
                            </button>
                        </div>
                    </form>
                @elseif($importStep === 'preview')
                    <div class="mt-4 space-y-4">
                        <!-- Resumen del Análisis -->
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block">Escenas detectadas</span>
                                <span class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">{{ $parsedImportData['scenes_count'] ?? 0 }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-blue-500/10 border border-blue-500/30">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block">Personajes detectados</span>
                                <span class="text-xl font-bold font-mono text-blue-600 dark:text-blue-400">{{ count($parsedImportData['characters'] ?? []) }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                                <span class="text-xs text-slate-500 dark:text-slate-400 block">Locaciones detectadas</span>
                                <span class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ count($parsedImportData['locations'] ?? []) }}</span>
                            </div>
                        </div>

                        <!-- Formulario de Configuración del Proyecto a Crear -->
                        <div class="space-y-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título del Nuevo Proyecto *</label>
                                <input type="text"
                                       wire:model="importProjectTitle"
                                       placeholder="Título de la obra..."
                                       class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                                @error('importProjectTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis Opcional</label>
                                <textarea wire:model="importProjectSynopsis"
                                          rows="2"
                                          placeholder="Sinopsis de la obra..."
                                          class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                            </div>
                        </div>

                        <!-- Previsualización de Escenas -->
                        <div>
                            <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Escenas que se crearán</span>
                            <div class="max-h-48 overflow-y-auto space-y-1.5 p-2 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs">
                                @forelse($parsedImportData['scenes'] ?? [] as $sc)
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">#{{ $sc['scene_order'] }} {{ $sc['slugline'] }}</span>
                                        <span class="text-[11px] text-slate-500">{{ count($sc['blocks'] ?? []) }} bloques</span>
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-slate-400">No se detectaron escenas</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Personajes Detectados -->
                        @if(! empty($parsedImportData['characters']))
                            <div>
                                <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Personajes detectados para la Biblia</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($parsedImportData['characters'] as $char)
                                        <span class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20 text-xs font-mono font-bold">
                                            {{ $char }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="pt-4 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
                            <button type="button"
                                    wire:click="$set('importStep', 'upload')"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                                ⬅️ Volver a subir
                            </button>
                            <button type="button"
                                    wire:click="confirmAndCreateProjectFromImport"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold shadow-lg shadow-emerald-600/30 transition cursor-pointer">
                                Confirmar e Importar Proyecto
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
