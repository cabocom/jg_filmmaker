<div class="space-y-5">
    @if (session()->has('success'))
        <div class="rounded-lg bg-emerald-900 border border-emerald-500 p-4 text-emerald-50 text-sm flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button type="button" wire:click="$refresh" class="text-emerald-200 hover:text-white cursor-pointer">×</button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-lg bg-rose-900 border border-rose-500 p-4 text-rose-50 text-sm flex items-center justify-between">
            <span>{{ session('error') }}</span>
            <button type="button" wire:click="$refresh" class="text-rose-200 hover:text-white cursor-pointer">×</button>
        </div>
    @endif

    @if ($showForm)
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-sm text-slate-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-slate-100">
                    {{ $editingStyleId ? 'Editar Estilo Visual' : 'Nuevo Estilo Visual' }}
                </h3>
                <button type="button" wire:click="cancelForm" class="text-xs text-slate-400 hover:text-slate-200 cursor-pointer">
                    ✕ Cerrar
                </button>
            </div>

            <form wire:submit="saveStyle" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Nombre del Estilo</label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Ej: Storyboard Clásico"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        required
                    />
                    @error('name') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Prompt Base (Inglés recomendado para generación)</label>
                    <textarea
                        wire:model="promptBase"
                        rows="3"
                        placeholder="Describe el estilo visual base, técnica, iluminación, paleta y restricciones..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                        required
                    ></textarea>
                    @error('promptBase') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Características / Notas Editoriales (opcional)</label>
                    <textarea
                        wire:model="characteristics"
                        rows="2"
                        placeholder="Resumen breve de las características del estilo para el equipo..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                    ></textarea>
                    @error('characteristics') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Imagen de Referencia (opcional, máx. 4MB)</label>
                    <input
                        type="file"
                        wire:model="referenceImage"
                        accept="image/*"
                        class="block w-full text-xs text-slate-300 file:mr-3 file:rounded-lg file:border file:border-slate-700 file:bg-slate-800 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-200 hover:file:bg-slate-700"
                    />
                    @error('referenceImage') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror

                    <div wire:loading wire:target="referenceImage" class="text-xs text-indigo-400 mt-1.5">
                        Subiendo imagen de referencia...
                    </div>

                    @if ($referenceImage)
                        <div class="mt-3 flex items-center gap-3">
                            <img
                                src="{{ $referenceImage->temporaryUrl() }}"
                                alt="Vista previa"
                                class="h-20 w-32 rounded-lg border border-slate-700 object-cover bg-slate-950"
                            />
                            <span class="text-xs text-slate-400">Nueva imagen seleccionada</span>
                        </div>
                    @elseif ($editingStyle?->reference_image_path)
                        <div class="mt-3 flex items-center gap-3">
                            <img
                                src="{{ route('tv.styles.reference', $editingStyle) }}"
                                alt="{{ $editingStyle->name }}"
                                class="h-20 w-32 rounded-lg border border-slate-700 object-cover bg-slate-950"
                            />
                            <button
                                type="button"
                                wire:click="removeReferenceImage('{{ $editingStyle->id }}')"
                                class="rounded-lg border border-rose-800 bg-rose-950/60 px-2.5 py-1 text-xs text-rose-300 hover:bg-rose-900 cursor-pointer"
                            >
                                Quitar imagen
                            </button>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" wire:model="isDefault" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                        <span>Establecer como estilo por defecto</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" wire:model="isActive" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0" />
                        <span>Activo (visible en selectores)</span>
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="cancelForm"
                        class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-slate-200 hover:bg-slate-700 cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50 cursor-pointer"
                    >
                        {{ $editingStyleId ? 'Guardar Cambios' : 'Crear Estilo' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden shadow-sm text-slate-100">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-200">🎨 Estilos Visuales (AI Today)</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">
                    Define los estilos artísticos disponibles para las escaletas IA y sus proyectos vinculados.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400">{{ count($styles) }} estilos</span>
                <button
                    type="button"
                    wire:click="startCreate"
                    class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 text-xs font-medium text-white transition-colors cursor-pointer"
                >
                    + Nuevo Estilo
                </button>
            </div>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($styles as $style)
                <div class="rounded-xl border {{ $style->is_default ? 'border-indigo-500/60' : 'border-slate-800' }} bg-slate-950/70 p-4 flex flex-col justify-between gap-4">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-sm font-semibold text-slate-100 truncate">{{ $style->name }}</h4>
                                    @if ($style->is_default)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-950 text-indigo-300 border border-indigo-700">
                                            Por defecto
                                        </span>
                                    @endif
                                    @if (! $style->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-400 border border-slate-700">
                                            Inactivo
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5">{{ $style->slug }}</div>
                            </div>
                        </div>

                        @if ($style->reference_image_path)
                            <div class="relative overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
                                <img
                                    src="{{ route('tv.styles.reference', $style) }}"
                                    alt="{{ $style->name }}"
                                    class="w-full h-36 object-cover"
                                />
                                <button
                                    type="button"
                                    wire:click="removeReferenceImage('{{ $style->id }}')"
                                    class="absolute top-2 right-2 rounded bg-slate-950/85 hover:bg-rose-900 border border-slate-700 px-2 py-0.5 text-[10px] text-rose-300 hover:text-white cursor-pointer"
                                    title="Eliminar imagen de referencia"
                                >
                                    Quitar imagen
                                </button>
                            </div>
                        @endif

                        <div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">Prompt Base</div>
                            <p class="text-xs font-mono text-slate-300 bg-slate-900 border border-slate-800 rounded-lg p-2.5 break-words">
                                {{ $style->prompt_base }}
                            </p>
                        </div>

                        @if ($style->characteristics)
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">Características</div>
                                <p class="text-xs text-slate-300">{{ $style->characteristics }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
                        @if (! $style->is_default)
                            <button
                                type="button"
                                wire:click="setDefault('{{ $style->id }}')"
                                class="rounded px-2.5 py-1 text-xs font-medium bg-slate-800 hover:bg-indigo-900 text-indigo-300 hover:text-white border border-slate-700 transition-colors cursor-pointer"
                            >
                                Por defecto
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="startEdit('{{ $style->id }}')"
                            class="rounded px-2.5 py-1 text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 transition-colors cursor-pointer"
                        >
                            Editar
                        </button>
                        <button
                            type="button"
                            wire:click="deleteStyle('{{ $style->id }}')"
                            wire:confirm="¿Seguro que deseas eliminar este estilo visual?"
                            class="rounded px-2.5 py-1 text-xs font-medium bg-slate-800 hover:bg-rose-900 text-rose-300 hover:text-rose-100 border border-slate-700 transition-colors cursor-pointer"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
