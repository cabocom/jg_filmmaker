<div>
    @if ($isOpen)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
            wire:click.self="close"
            x-on:keydown.escape.window="$wire.close()"
        >
            <div class="w-full max-w-5xl max-h-[90vh] rounded-xl border border-slate-800 bg-slate-900 shadow-2xl text-slate-100 flex flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-slate-800 bg-slate-950/50">
                    <div class="min-w-0">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-indigo-400">Edición Incremental por Shot</span>
                        <h3 class="text-base font-semibold text-slate-100 truncate mt-0.5">
                            {{ $rundownTitle ?: 'Escaleta' }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        wire:click="close"
                        class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition cursor-pointer"
                        title="Cerrar modal"
                        aria-label="Cerrar modal"
                    >
                        ✕
                    </button>
                </div>

                <div class="p-5 overflow-y-auto space-y-4 flex-1">
                    @if ($feedbackMessage)
                        <div class="rounded-lg bg-emerald-900/80 border border-emerald-500 p-3 text-emerald-50 text-xs flex items-center justify-between">
                            <span>{{ $feedbackMessage }}</span>
                        </div>
                    @endif

                    @if ($errorMessage)
                        <div class="rounded-lg bg-rose-900/80 border border-rose-500 p-3 text-rose-50 text-xs flex items-center justify-between">
                            <span>{{ $errorMessage }}</span>
                        </div>
                    @endif

                    @if ($shots === [])
                        <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-6 text-center text-sm text-slate-400">
                            No se encontraron shots editables para esta escaleta.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($shots as $shot)
                                <div
                                    wire:key="rundown-shot-{{ $shot['shot_id'] }}"
                                    class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 space-y-3"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="inline-flex items-center rounded-md bg-indigo-950/80 border border-indigo-700/60 px-2.5 py-1 text-xs font-semibold text-indigo-200">
                                            #{{ $shot['shot_number'] }} ({{ $shot['shot_type'] !== '' ? $shot['shot_type'] : 'shot' }})
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                                        <div class="md:col-span-3">
                                            @if ($shot['has_image'] && $shot['storyboard_url'])
                                                <img
                                                    wire:key="shot-thumb-{{ $shot['shot_id'] }}-{{ md5((string) $shot['storyboard_url']) }}"
                                                    src="{{ $shot['storyboard_url'] }}"
                                                    alt="Viñeta #{{ $shot['shot_number'] }}"
                                                    class="w-full aspect-video object-cover rounded-lg border border-slate-800 bg-slate-950"
                                                />
                                            @else
                                                <div class="w-full aspect-video rounded-lg border border-dashed border-slate-700 bg-slate-950 flex items-center justify-center text-xs text-slate-500">
                                                    Sin viñeta
                                                </div>
                                            @endif
                                        </div>

                                        <div class="md:col-span-5 space-y-2">
                                            <label class="block text-xs font-medium text-slate-300">
                                                Descripción visual (Prompt de viñeta)
                                            </label>
                                            <textarea
                                                wire:model="descriptions.{{ $shot['shot_id'] }}"
                                                rows="3"
                                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                                            ></textarea>
                                            <div>
                                                <button
                                                    type="button"
                                                    wire:click="regenerateShot('{{ $shot['shot_id'] }}')"
                                                    wire:loading.attr="disabled"
                                                    class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                                >
                                                    Regenerar viñeta
                                                </button>
                                            </div>
                                        </div>

                                        <div class="md:col-span-4 space-y-2">
                                            <label class="block text-xs font-medium text-slate-300">
                                                Texto oral (Locución del shot)
                                            </label>
                                            <textarea
                                                wire:model="oralTexts.{{ $shot['shot_id'] }}"
                                                rows="4"
                                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="px-5 py-4 border-t border-slate-800 bg-slate-950/50 flex flex-wrap items-center justify-end gap-3">
                    <button
                        type="button"
                        wire:click="close"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 px-4 py-2 text-xs font-medium text-slate-200 disabled:opacity-40 transition-colors cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="reassembleOnly"
                        wire:loading.attr="disabled"
                        @disabled($shots === [])
                        class="rounded-lg bg-amber-600 hover:bg-amber-500 px-4 py-2 text-xs font-medium text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                    >
                        Re-ensamblar vídeo (solo cambios visuales)
                    </button>
                    <button
                        type="button"
                        wire:click="saveTextsAndReassemble"
                        wire:loading.attr="disabled"
                        @disabled($shots === [])
                        class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 text-xs font-medium text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                    >
                        Guardar textos y re-ensamblar vídeo
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
