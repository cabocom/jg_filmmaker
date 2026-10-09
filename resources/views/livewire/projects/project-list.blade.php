<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Header del Dashboard -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                <span>Proyectos Cinematográficos</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                    {{ $projects->count() }} de {{ $user->paid_project_slots ?? 5 }} slots
                </span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Gestiona y desarrolla el pipeline de guión, Biblia de arte, desglose de planos y tomas de rodaje.
            </p>
        </div>

        <div>
            <button wire:click="$set('showCreateModal', true)"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold shadow-lg shadow-amber-950/50 hover:shadow-amber-500/20 transition-all cursor-pointer">
                <span>+</span>
                <span>Nuevo Proyecto</span>
            </button>
        </div>
    </div>

    <!-- Mensajes Flash -->
    @if(session()->has('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-300 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Grid de Proyectos -->
    @if($projects->isEmpty())
        <div class="mt-12 text-center py-16 px-4 rounded-2xl border-2 border-dashed border-slate-800 bg-slate-900/30">
            <span class="text-4xl">🎬</span>
            <h3 class="mt-3 text-lg font-semibold text-white">No tienes proyectos creados aún</h3>
            <p class="mt-1 text-sm text-slate-400 max-w-sm mx-auto">
                Crea tu primera obra cinematográfica para comenzar a estructurar el guión con asistencia cognitiva.
            </p>
            <button wire:click="$set('showCreateModal', true)"
                    type="button"
                    class="mt-6 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-amber-500 text-slate-950 font-bold hover:bg-amber-400 transition">
                + Crear Primer Proyecto
            </button>
        </div>
    @else
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($projects as $project)
                <div class="flex flex-col justify-between rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-amber-500/50 p-6 shadow-xl hover:shadow-2xl hover:shadow-amber-950/20 transition-all group">
                    <div>
                        <!-- Barra superior tarjeta: Estado y UUID -->
                        <div class="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-800/60">
                            @php
                                $statusColors = [
                                    'draft' => 'bg-slate-800 text-slate-300 border-slate-700',
                                    'in_progress' => 'bg-amber-500/10 text-amber-300 border-amber-500/30',
                                    'completed' => 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30',
                                ];
                            @endphp
                            <span class="text-[11px] font-mono uppercase px-2 py-0.5 rounded-full border {{ $statusColors[$project->status] ?? 'bg-slate-800 text-slate-300' }}">
                                {{ $project->status === 'in_progress' ? 'En Rodaje / Edición' : ($project->status === 'draft' ? 'Borrador' : 'Completado') }}
                            </span>
                            <span class="text-[10px] font-mono text-slate-500 truncate max-w-[120px]" title="{{ $project->id }}">
                                {{ substr($project->id, 0, 8) }}...
                            </span>
                        </div>

                        <!-- Título y Sinopsis -->
                        <h2 class="text-xl font-bold text-white group-hover:text-amber-400 transition leading-snug">
                            <a href="{{ route('projects.show', $project->id) }}">
                                {{ $project->title }}
                            </a>
                        </h2>

                        <p class="mt-2 text-xs sm:text-sm text-slate-400 line-clamp-3 leading-relaxed">
                            {{ $project->synopsis ?: 'Sin sinopsis definida aún. Accede al estudio para iniciar el tratamiento narrativo.' }}
                        </p>
                    </div>

                    <!-- Métricas y Acción -->
                    <div class="mt-6 pt-4 border-t border-slate-800/60">
                        <div class="grid grid-cols-3 gap-2 text-center mb-4">
                            <div class="bg-slate-950/50 p-2 rounded-lg border border-slate-800/40">
                                <span class="block text-xs text-slate-500">Escenas</span>
                                <span class="font-mono font-bold text-sm text-slate-200">{{ $project->scenes_count }}</span>
                            </div>
                            <div class="bg-slate-950/50 p-2 rounded-lg border border-slate-800/40">
                                <span class="block text-xs text-slate-500">Personajes</span>
                                <span class="font-mono font-bold text-sm text-slate-200">{{ $project->characters_count }}</span>
                            </div>
                            <div class="bg-slate-950/50 p-2 rounded-lg border border-slate-800/40">
                                <span class="block text-xs text-slate-500">Locaciones</span>
                                <span class="font-mono font-bold text-sm text-slate-200">{{ $project->locations_count }}</span>
                            </div>
                        </div>

                        <a href="{{ route('projects.show', $project->id) }}"
                           class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-slate-800 hover:bg-amber-500 hover:text-slate-950 text-slate-200 transition-all border border-slate-700 hover:border-amber-400 shadow-sm">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>🎬</span>
                        <span>Crear Nuevo Proyecto Cinematográfico</span>
                    </h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-white text-lg">✕</button>
                </div>

                @error('quota')
                    <div class="mt-4 p-3 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-300 text-xs">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="createProject" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Título de la Obra *</label>
                        <input type="text"
                               wire:model="title"
                               placeholder="Ej: El Laberinto de las Sombras"
                               class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-white text-sm">
                        @error('title') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Sinopsis Preliminar</label>
                        <textarea wire:model="synopsis"
                                  rows="3"
                                  placeholder="Describe la premisa dramática, el tono y el arco narrativo general..."
                                  class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-white text-sm"></textarea>
                        @error('synopsis') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Estado Inicial</label>
                        <select wire:model="status" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 focus:border-amber-500 text-white text-sm">
                            <option value="draft">Borrador (Desarrollo Temprano)</option>
                            <option value="in_progress">En Progreso / Producción</option>
                            <option value="completed">Completado</option>
                        </select>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                        <button type="button"
                                wire:click="$set('showCreateModal', false)"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-950/40 transition">
                            Crear e Iniciar Estudio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
