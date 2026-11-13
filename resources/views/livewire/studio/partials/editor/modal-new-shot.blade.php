    <!-- ================= MODAL NUEVO PLANO TÉCNICO ================= -->
    @if($showNewShotModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
             x-data
             @keydown.escape.window="$wire.set('showNewShotModal', false)"
             wire:click.self="$set('showNewShotModal', false)">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 w-full max-w-md space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Añadir Plano Técnico</h3>
                    <button wire:click="$set('showNewShotModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-xs font-bold">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Escala de Plano *</label>
                        <select wire:model="newShotType" class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-mono">
                            <option value="PG">PG - Plano General</option>
                            <option value="PE">PE - Plano Entero</option>
                            <option value="PA">PA - Plano Americano</option>
                            <option value="PM">PM - Plano Medio</option>
                            <option value="PMC">PMC - Plano Medio Corto</option>
                            <option value="PP">PP - Primer Plano</option>
                            <option value="PPP">PPP - Primerísimo Primer Plano</option>
                            <option value="PD">PD - Plano Detalle</option>
                            <option value="INSERT">INSERT - Inserto</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-700 dark:text-slate-300 mb-1">Descripción Técnica del Plano *</label>
                        <textarea wire:model="newShotDescription"
                                  rows="3"
                                  placeholder="Describe la cobertura visual o movimiento de cámara..."
                                  class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                        @error('newShotDescription') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                        <button wire:click="$set('showNewShotModal', false)" class="px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400">Cancelar</button>
                        <button wire:click="addShot" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md">Guardar Plano</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

