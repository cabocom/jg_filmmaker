<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Header del Dashboard -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex flex-wrap items-center gap-2 sm:gap-3">
                <span>Proyectos Cinematográficos</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-700 shrink-0">
                    {{ $projects->count() }} de {{ $user->paid_project_slots ?? 5 }} slots
                </span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Gestiona y desarrolla el pipeline de guión, Biblia de arte, desglose de planos y tomas de rodaje.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            <button wire:click="openImportModal"
                    type="button"
                    class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition-all cursor-pointer shadow-xs">
                <span>📥</span>
                <span>Importar Guión (PDF/TXT)</span>
            </button>

            <button wire:click="$set('showCreateModal', true)"
                    type="button"
                    class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 transition-all cursor-pointer">
                <span>+</span>
                <span>Nuevo Proyecto</span>
            </button>
        </div>
    </div>

    <!-- Mensajes Flash -->
    @if(session()->has('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Grid de Proyectos -->
    @if($projects->isEmpty())
        <div class="mt-12 text-center py-16 px-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/30">
            <span class="text-4xl">🎬</span>
            <h3 class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">No tienes proyectos creados aún</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                Crea tu primera obra cinematográfica o importa un guión en PDF/TXT para comenzar de inmediato.
            </p>
            <div class="mt-6 flex items-center justify-center gap-3">
                <button wire:click="openImportModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    📥 Importar Guión
                </button>
                <button wire:click="$set('showCreateModal', true)"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-amber-500 text-slate-950 font-bold hover:bg-amber-400 transition">
                    + Crear Primer Proyecto
                </button>
            </div>
        </div>
    @else
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($projects as $project)
                <div class="flex flex-col justify-between rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 hover:border-amber-500/50 p-6 shadow-sm dark:shadow-xl hover:shadow-md dark:hover:shadow-2xl dark:hover:shadow-amber-950/20 transition-all group">
                    <div>
                        <!-- Barra superior tarjeta: Estado y UUID -->
                        <div class="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-100 dark:border-slate-800/60">
                            @php
                                $statusColors = [
                                    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                                    'in_progress' => 'bg-amber-500/10 text-amber-700 border-amber-500/30 dark:text-amber-300',
                                    'completed' => 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30 dark:text-emerald-300',
                                    'archived' => 'bg-rose-500/10 text-rose-700 border-rose-500/30 dark:text-rose-300',
                                ];
                            @endphp
                            <span class="text-[11px] font-mono uppercase px-2 py-0.5 rounded-full border {{ $statusColors[$project->status] ?? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                                {{ $project->status === 'in_progress' ? 'En Rodaje / Edición' : ($project->status === 'draft' ? 'Borrador' : ($project->status === 'completed' ? 'Completado' : 'Archivado')) }}
                            </span>
                            <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500 truncate max-w-[120px]" title="{{ $project->id }}">
                                {{ substr($project->id, 0, 8) }}...
                            </span>
                        </div>

                        <!-- Título y Botón Editar -->
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition leading-snug">
                                <a href="{{ route('projects.show', $project->id) }}">
                                    {{ $project->title }}
                                </a>
                            </h2>
                            <button wire:click="openEditModal('{{ $project->id }}')"
                                    type="button"
                                    title="Modificar nombre y detalles del proyecto"
                                    class="p-1 rounded-lg text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                        </div>

                        <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-400 line-clamp-3 leading-relaxed">
                            {{ $project->synopsis ?: 'Sin sinopsis definida aún. Accede al estudio para iniciar el tratamiento narrativo.' }}
                        </p>
                    </div>

                    <!-- Métricas y Acción -->
                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800/60">
                        <div class="grid grid-cols-3 gap-2 text-center mb-4">
                            <div class="bg-slate-50 dark:bg-slate-950/50 p-2 rounded-lg border border-slate-200/80 dark:border-slate-800/40">
                                <span class="block text-xs text-slate-500">Escenas</span>
                                <span class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200">{{ $project->scenes_count }}</span>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950/50 p-2 rounded-lg border border-slate-200/80 dark:border-slate-800/40">
                                <span class="block text-xs text-slate-500">Personajes</span>
                                <span class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200">{{ $project->characters_count }}</span>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-950/50 p-2 rounded-lg border border-slate-200/80 dark:border-slate-800/40">
                                <span class="block text-xs text-slate-500">Locaciones</span>
                                <span class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200">{{ $project->locations_count }}</span>
                            </div>
                        </div>

                        <a href="{{ route('projects.show', $project->id) }}"
                           class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-slate-700 dark:text-slate-200 transition-all border border-slate-200 dark:border-slate-700 hover:border-amber-400 shadow-sm">
                            <span>Entrar al Estudio</span>
                            <span>➜</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Modal para Nuevo Proyecto -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🎬</span>
                        <span>Crear Nuevo Proyecto Cinematográfico</span>
                    </h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                @error('quota')
                    <div class="mt-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="createProject" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="title"
                               placeholder="Ej: El Laberinto de las Sombras"
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm">
                        @error('title') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis Preliminar</label>
                        <textarea wire:model="synopsis"
                                  rows="3"
                                  placeholder="Describe la premisa dramática, el tono y el arco narrativo general..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                        @error('synopsis') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Estado Inicial</label>
                        <select wire:model="status" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                            <option value="draft">Borrador (Desarrollo Temprano)</option>
                            <option value="in_progress">En Progreso / Producción</option>
                            <option value="completed">Completado</option>
                        </select>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showCreateModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            Crear e Iniciar Estudio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal para Modificar Nombre / Detalles del Proyecto -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✏️</span>
                        <span>Modificar Proyecto Cinematográfico</span>
                    </h3>
                    <button wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
                </div>

                <form wire:submit="updateProject" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="editTitle"
                               placeholder="Título del proyecto..."
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm">
                        @error('editTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sinopsis</label>
                        <textarea wire:model="editSynopsis"
                                  rows="3"
                                  placeholder="Sinopsis o notas generales..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-900 dark:text-white text-sm"></textarea>
                        @error('editSynopsis') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Estado</label>
                        <select wire:model="editStatus" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 focus:border-amber-500 text-slate-900 dark:text-white text-sm">
                            <option value="draft">Borrador (Desarrollo Temprano)</option>
                            <option value="in_progress">En Progreso / Producción</option>
                            <option value="completed">Completado</option>
                            <option value="archived">Archivado</option>
                        </select>
                        @error('editStatus') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button"
                                wire:click="$set('showEditModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/30 transition cursor-pointer">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30 transition cursor-pointer">
                                Confirmar e Importar Proyecto
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
