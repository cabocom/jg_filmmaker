                                    <div class="space-y-4">
                                        <!-- Barra de Estilo de Storyboard & Condicionamiento -->
                                        <div class="p-3 rounded-xl bg-slate-100/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-2">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                                                        🎨 Estilo Visual Storyboard (Google AI):
                                                    </span>
                                                    @php
                                                        $refCharCount = $project->characters->filter(fn($c) => $c->hasReferenceImage())->count();
                                                    @endphp
                                                    @if($refCharCount > 0)
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20" title="La IA condicionará la imagen según la ficha de modelo de personajes">
                                                            <span>👤</span> {{ $refCharCount }} modelo{{ $refCharCount > 1 ? 's' : '' }} turn-around
                                                        </span>
                                                    @endif
                                                </div>
                                                <!-- Selector de Estilos -->
                                                <div class="flex items-center gap-1 bg-white dark:bg-slate-950 p-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[11px] font-medium">
                                                    @foreach(\App\Services\StoryboardService::STYLES as $sKey => $sVal)
                                                        <button wire:click="setStoryboardStyle('{{ $sKey }}')"
                                                                type="button"
                                                                class="px-2.5 py-1 rounded transition cursor-pointer {{ $storyboardStyle === $sKey ? 'bg-amber-500 text-slate-950 font-bold shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                                                            {{ $sVal['name'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">
                                                {{ \App\Services\StoryboardService::STYLES[$storyboardStyle]['description'] ?? '' }}
                                            </p>
                                        </div>

                                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 block pt-1">
                                            4. Desglose Técnico de Planos y Viñetas:
                                        </span>
                                        @forelse($detailBlock->shots as $shot)
                                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                                                    <span class="font-mono font-bold text-cyan-700 bg-cyan-50 dark:text-cyan-300 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800/50 px-2 py-0.5 rounded text-xs">
                                                        --- shot {{ $shot->shot_number }} ({{ $shot->shot_type }}) ---
                                                    </span>
                                                    <div class="flex items-center gap-1.5">
                                                        <button wire:click="startEditShot('{{ $shot->id }}')"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 transition cursor-pointer"
                                                                title="Editar este plano individualmente">
                                                            ✏️ Editar
                                                        </button>
                                                        <button wire:click="deleteShot('{{ $shot->id }}')"
                                                                wire:confirm="¿Seguro que deseas eliminar el Plano #{{ $shot->shot_number }}?"
                                                                type="button"
                                                                class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50 transition cursor-pointer"
                                                                title="Eliminar este plano">
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </div>

                                                @if($editingShotId === $shot->id)
                                                    <!-- Formulario de edición rápida del plano individual -->
                                                    <div class="p-3.5 rounded-xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-500/30 space-y-3">
                                                        <div class="flex items-center gap-2">
                                                            <label class="text-[11px] font-bold font-mono text-slate-700 dark:text-slate-300 shrink-0">Escala de plano:</label>
                                                            <select wire:model="editingShotType" class="px-2.5 py-1 text-xs font-mono font-bold rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500">
                                                                <option value="PG">PG (Plano General)</option>
                                                                <option value="PE">PE (Plano Entero)</option>
                                                                <option value="PA">PA (Plano Americano)</option>
                                                                <option value="PM">PM (Plano Medio)</option>
                                                                <option value="PMC">PMC (Plano Medio Corto)</option>
                                                                <option value="PP">PP (Primer Plano)</option>
                                                                <option value="PPP">PPP (Primerísimo Primer Plano)</option>
                                                                <option value="PD">PD (Plano Detalle)</option>
                                                                <option value="ZEN">ZEN (Cenital)</option>
                                                                <option value="PIC">PIC (Picado)</option>
                                                                <option value="CTRP">CTRP (Contrapicado)</option>
                                                                <option value="NAD">NAD (Nadir)</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[11px] font-bold font-mono text-slate-700 dark:text-slate-300 mb-1">Descripción y encuadre:</label>
                                                            <textarea wire:model="editingShotDescription" rows="3" class="w-full px-3 py-2 text-xs rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-amber-500 font-sans"></textarea>
                                                            @error('editingShotDescription') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                                        </div>
                                                        <div class="flex items-center justify-end gap-2 pt-1">
                                                            <button wire:click="cancelEditShot" type="button" class="px-2.5 py-1 rounded text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 cursor-pointer">Cancelar</button>
                                                            <button wire:click="saveEditShot" type="button" class="px-3 py-1 rounded text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition cursor-pointer">✓ Guardar Plano</button>
                                                        </div>
                                                    </div>
                                                @else

                                                <!-- Viñeta de Storyboard -->
                                                @if($shot->hasStoryboard())
                                                    <div class="relative group rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-950 aspect-video shadow-md">
                                                        <img src="{{ $shot->storyboard_url }}"
                                                             alt="Storyboard shot {{ $shot->shot_number }}"
                                                             class="w-full h-full object-cover transition duration-300 group-hover:scale-102">
                                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-transparent to-transparent flex items-end justify-between p-3 opacity-90 group-hover:opacity-100 transition">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/70 text-amber-300 border border-amber-500/30 backdrop-blur-xs font-semibold">
                                                                    🎨 {{ \App\Services\StoryboardService::STYLES[$shot->storyboard_style]['name'] ?? 'Storyboard' }}
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button wire:click="openStoryboardPreview('{{ $shot->id }}')"
                                                                        type="button"
                                                                        title="Ampliar viñeta"
                                                                        class="px-2.5 py-1 rounded-lg bg-black/70 hover:bg-black/90 text-white text-xs border border-white/20 backdrop-blur-xs cursor-pointer flex items-center gap-1">
                                                                    <span>🔍</span> <span>Ampliar</span>
                                                                </button>
                                                                <button wire:click="openStoryboardModal('{{ $shot->id }}')"
                                                                        type="button"
                                                                        title="Componer y regenerar viñeta con IA"
                                                                        class="px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold flex items-center gap-1 cursor-pointer shadow-xs transition">
                                                                    <span>🔄 Regenerar</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="flex items-center justify-between p-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-900/60">
                                                         <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                             <span>🎨</span>
                                                             <span>Sin viñeta de storyboard generada</span>
                                                         </div>
                                                         <button wire:click="openStoryboardModal('{{ $shot->id }}')"
                                                                 type="button"
                                                                 class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs transition">
                                                             <span>✨ Generar Storyboard (IA)</span>
                                                         </button>
                                                    </div>
                                                @endif

                                                <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">{{ $shot->description }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-center py-6 text-xs text-slate-500">No hay planos desglosados en este bloque.</div>
                                        @endforelse

                                        <div class="pt-2">
                                            <button wire:click="startNewShotForBlock('{{ $detailBlock->id }}')"
                                                    type="button"
                                                    class="w-full py-2.5 px-3 rounded-xl border border-dashed border-cyan-400 dark:border-cyan-700 bg-cyan-50/50 hover:bg-cyan-50 dark:bg-cyan-950/20 dark:hover:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 font-mono text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer">
                                                <span>+</span>
                                                <span>Añadir Plano Manual</span>
                                            </button>
                                        </div>
