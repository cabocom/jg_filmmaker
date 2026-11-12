@if($showDeleteModal)
    <!-- ================= MODAL SUPERIOR DE CONFIRMACIÓN O BLOQUEO DE BORRADO (Z-INDEX 100000) ================= -->
    <div class="fixed inset-0 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md transition"
         style="position: fixed; inset: 0; z-index: 100000;"
         x-data
         @keydown.escape.stop.window="$wire.cancelDelete()"
         wire:click.self="cancelDelete">
        <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 m-auto"
             style="position: relative; z-index: 100001;">
            @if(count($deletingUsages) > 0)
                <!-- CASO 1: BLOQUEO POR USO (NO SE PUEDE BORRAR) -->
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-500 shrink-0 text-lg">
                        ⚠️
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            No se puede eliminar {{ $deletingType === 'character' ? 'el personaje' : 'la localización' }}
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            <strong class="text-amber-600 dark:text-amber-400 font-bold font-mono">"{{ $deletingName }}"</strong> está siendo utilizado actualmente en el guión cinematográfico.
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
                        Aparece en {{ count($deletingUsages) }} {{ count($deletingUsages) === 1 ? 'ubicación' : 'ubicaciones' }}:
                    </span>
                    <div class="max-h-48 overflow-y-auto rounded-xl p-2 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 space-y-1.5">
                        @foreach($deletingUsages as $usage)
                            <button wire:click="inspectUsage('{{ $usage }}')"
                                    type="button"
                                    title="Ir a este bloque en el guión y abrir su panel de detalle"
                                    class="w-full flex items-center justify-between gap-2 py-2 px-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800/80 hover:border-amber-500 hover:bg-amber-50/50 dark:hover:bg-slate-800/80 transition cursor-pointer text-left group shadow-2xs">
                                <div class="flex items-center gap-2 text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                                    <span class="text-amber-500">📌</span>
                                    <span>{{ $usage }}</span>
                                </div>
                                <div class="flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 group-hover:text-amber-700 dark:group-hover:text-amber-300">
                                    <span>Ver detalle</span>
                                    <span class="transition-transform group-hover:translate-x-0.5">➔</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">
                        Haz clic en una ubicación para abrir directamente su panel lateral y modificar o retirar el texto.
                    </p>
                </div>

                <div class="pt-2 flex items-center justify-between gap-2.5">
                    <button wire:click="cancelDelete"
                            type="button"
                            class="py-2.5 px-4 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 transition cursor-pointer">
                        Cancelar / Revisar
                    </button>
                    <button wire:click="forceDelete"
                            type="button"
                            title="Eliminar este elemento de la Biblia de todos modos ignorando las menciones en texto"
                            class="py-2.5 px-4 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5">
                        <span>🗑️</span>
                        <span>Eliminar de todos modos</span>
                    </button>
                </div>
            @else
                <!-- CASO 2: SIN USO (CONFIRMACIÓN DE BORRADO TOTAL) -->
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-500 shrink-0 text-lg">
                        🗑️
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            ¿Eliminar {{ $deletingType === 'character' ? 'personaje' : 'localización' }} permanentemente?
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            <strong class="text-slate-900 dark:text-white font-bold font-mono">"{{ $deletingName }}"</strong> no está siendo utilizado en ninguna escena ni bloque del guión.
                        </p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 text-xs text-rose-800 dark:text-rose-300">
                    Esta acción eliminará de forma irreversible este elemento de la Biblia de proyecto.
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5">
                    <button wire:click="cancelDelete"
                            type="button"
                            class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition cursor-pointer">
                        Cancelar
                    </button>
                    <button wire:click="confirmDelete"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5">
                        <span>🗑️</span>
                        <span>Eliminar definitivamente</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
