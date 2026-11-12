    <!-- ================= MODAL DE COMPOSICIÓN Y GENERACIÓN DE STORYBOARD (IA) ================= -->
    @if($showStoryboardModal && $storyboardShotId)
        @php
            $modalShot = $this->projectShot($storyboardShotId);
            $selectedModalChar = $storyboardCharacterId ? \App\Models\Character::find($storyboardCharacterId) : null;
            $selectedModalLoc = $storyboardLocationId ? \App\Models\Location::find($storyboardLocationId) : null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm transition overflow-y-auto"
             wire:click.self="closeStoryboardModal">
            <div class="relative max-w-3xl w-full bg-slate-900 border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden my-6 flex flex-col max-h-[92vh]">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 bg-slate-950/90 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-lg">✨</span>
                        <div>
                            <h3 class="text-sm font-bold font-mono text-slate-100">
                                Composición de Viñeta: Shot #{{ $modalShot?->shot_number }} ({{ $modalShot?->shot_type }})
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                Google AI Studio &bull; Coherencia multimodal y prompt refinado
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeStoryboardModal"
                            type="button"
                            class="text-slate-400 hover:text-white text-base font-bold p-1 cursor-pointer transition">
                        ✕
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-5 overflow-y-auto flex-1 text-xs text-slate-300">
                    <!-- Resumen del plano -->
                    <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 space-y-1">
                        <div class="text-[10px] font-mono uppercase tracking-wider text-amber-400/90 font-bold">
                            Escena {{ $modalShot?->scene?->scene_order }}: {{ $modalShot?->scene?->slugline }}
                        </div>
                        <div class="text-xs text-slate-200">
                            {{ $modalShot?->description }}
                        </div>
                    </div>

                    <!-- Referencias Multimodales (Personaje + Localización) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Personaje -->
                        <div class="p-3.5 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-2.5">
                            <label class="block text-[11px] font-bold text-slate-200">
                                👤 Personaje de Referencia:
                            </label>
                            <select wire:model.live="storyboardCharacterId"
                                    class="w-full text-xs rounded-lg bg-slate-900 border border-slate-700 text-slate-200 p-2 focus:ring-1 focus:ring-amber-500">
                                <option value="">Sin personaje específico / General</option>
                                @foreach($this->project->characters as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name }} {{ $c->hasReferenceImage() ? '📷 [Con foto turn-around]' : '' }}
                                    </option>
                                @endforeach
                            </select>

                            @if($selectedModalChar)
                                @if($selectedModalChar->hasReferenceImage())
                                    <div class="flex items-center gap-2.5 p-2 rounded-lg bg-emerald-950/40 border border-emerald-500/30">
                                        <img src="{{ $selectedModalChar->reference_image_url }}" alt="{{ $selectedModalChar->name }}" class="w-11 h-11 rounded-md object-cover border border-emerald-500/50 shrink-0">
                                        <div class="min-w-0">
                                            <div class="text-[11px] font-bold text-emerald-300 truncate">{{ $selectedModalChar->name }}</div>
                                            <div class="text-[10px] text-emerald-200/70">Turn-around activo (se enviará como referencia visual).</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="p-2 rounded-lg bg-slate-900/60 border border-slate-800 text-[10px] text-slate-400">
                                        ⚠️ {{ $selectedModalChar->name }} no tiene foto en la Biblia (condicionamiento textual).
                                    </div>
                                @endif
                            @endif
                        </div>

                        <!-- Localización -->
                        <div class="p-3.5 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-2.5">
                            <label class="block text-[11px] font-bold text-slate-200">
                                📍 Localización / Escenografía:
                            </label>
                            <select wire:model.live="storyboardLocationId"
                                    class="w-full text-xs rounded-lg bg-slate-900 border border-slate-700 text-slate-200 p-2 focus:ring-1 focus:ring-amber-500">
                                <option value="">Sin localización específica / General</option>
                                @foreach($this->project->locations as $loc)
                                    <option value="{{ $loc->id }}">
                                        {{ $loc->name }} ({{ $loc->type }}) {{ $loc->hasReferenceImage() ? '📷 [Con foto]' : '' }}
                                    </option>
                                @endforeach
                            </select>

                            @if($selectedModalLoc)
                                @if($selectedModalLoc->hasReferenceImage())
                                    <div class="flex items-center gap-2.5 p-2 rounded-lg bg-emerald-950/40 border border-emerald-500/30">
                                        <img src="{{ $selectedModalLoc->reference_image_url }}" alt="{{ $selectedModalLoc->name }}" class="w-11 h-11 rounded-md object-cover border border-emerald-500/50 shrink-0">
                                        <div class="min-w-0">
                                            <div class="text-[11px] font-bold text-emerald-300 truncate">{{ $selectedModalLoc->name }}</div>
                                            <div class="text-[10px] text-emerald-200/70">Foto de localización activa (referencia visual de entorno).</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="p-2 rounded-lg bg-slate-900/60 border border-slate-800 text-[10px] text-slate-400">
                                        ⚠️ {{ $selectedModalLoc->name }} no tiene foto en la Biblia (condicionamiento textual).
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- Atrezzo / Objetos de Referencia (Hasta 3 imágenes) -->
                    <div class="p-3.5 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="text-[11px] font-bold text-slate-200 flex items-center gap-1.5">
                                <span>📦 Atrezzo / Objetos del Plano</span>
                                <span class="font-normal text-slate-400">(máximo 3 imágenes)</span>
                            </label>
                            <span class="text-[10px] font-mono {{ count($storyboardPropUploads) >= 3 ? 'text-amber-400 font-bold' : 'text-slate-400' }}">
                                {{ count($storyboardPropUploads) }} / 3
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400">
                            Adjunta fotos de objetos o accesorios clave que aparezcan en este plano (p. ej. ambientador, taza, pistola, móvil) para que la IA los reproduzca fielmente.
                        </p>

                        <!-- Lista de fotos de atrezzo cargadas -->
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            @foreach($storyboardPropUploads as $idx => $propFile)
                                <div class="relative group w-16 h-16 rounded-lg overflow-hidden border border-amber-500/50 bg-slate-950 shrink-0 shadow-md">
                                    <img src="{{ $propFile->temporaryUrl() }}" alt="Atrezzo {{ $idx + 1 }}" class="w-full h-full object-cover">
                                    <button type="button"
                                            wire:click="removeStoryboardProp({{ $idx }})"
                                            title="Eliminar objeto"
                                            class="absolute top-1 right-1 bg-rose-600/90 hover:bg-rose-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-[10px] leading-none cursor-pointer shadow">
                                        ✕
                                    </button>
                                </div>
                            @endforeach

                            @if(count($storyboardPropUploads) < 3)
                                <label class="w-16 h-16 rounded-lg border border-dashed border-slate-600 hover:border-amber-400/80 bg-slate-900/60 hover:bg-slate-900 flex flex-col items-center justify-center text-slate-400 hover:text-amber-300 cursor-pointer transition shrink-0">
                                    <span class="text-base leading-none">+</span>
                                    <span class="text-[9px] mt-1 font-mono font-medium">Subir</span>
                                    <input type="file"
                                           wire:model="storyboardPropUploads"
                                           accept="image/*"
                                           multiple
                                           class="hidden">
                                </label>
                            @endif

                            <div wire:loading wire:target="storyboardPropUploads" class="text-[11px] text-amber-400 flex items-center gap-1 font-mono">
                                ⏳ Procesando imagen...
                            </div>
                        </div>
                    </div>

                    <!-- Estilo Visual -->
                    <div class="space-y-2">
                        <label class="block text-[11px] font-bold text-slate-200">
                            🎨 Estilo Visual:
                        </label>
                        <div class="flex flex-wrap gap-2">
                            @foreach(\App\Services\StoryboardService::STYLES as $sKey => $sInfo)
                                <button type="button"
                                        wire:click="$set('storyboardModalStyle', '{{ $sKey }}')"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer {{ $storyboardModalStyle === $sKey ? 'bg-amber-500 text-slate-950 border-amber-400 shadow-xs' : 'bg-slate-800/80 hover:bg-slate-800 text-slate-300 border-slate-700' }}">
                                    {{ $sInfo['name'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Prompt Editable -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[11px] font-bold text-slate-200 flex items-center gap-1">
                                <span>✏️ Prompt para el Motor de IA (Google AI Studio)</span>
                                <span class="font-normal text-slate-400">(editable con referencias @)</span>
                            </label>
                            <button type="button"
                                    wire:click="resetStoryboardModalPrompt"
                                    title="Regenerar prompt combinando la selección actual"
                                    class="text-[11px] text-amber-400 hover:text-amber-300 underline flex items-center gap-1 cursor-pointer">
                                <span>🔄 Restablecer prompt automático</span>
                            </button>
                        </div>

                        <!-- Barra interactiva de etiquetas @ estilo Flow / Midjourney -->
                        <div class="flex items-center flex-wrap gap-1.5 p-2 rounded-lg bg-slate-950/80 border border-slate-800">
                            <span class="text-[10px] text-slate-400 font-mono mr-1">Etiquetas @:</span>
                            @if($selectedModalChar)
                                @php $cTag = '@' . preg_replace('/\s+/', '_', trim($selectedModalChar->name)); @endphp
                                <button type="button"
                                        onclick="insertTagToStoryboardPrompt('{{ $cTag }}')"
                                        title="Insertar etiqueta del personaje en la posición del cursor"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 cursor-pointer transition">
                                    {{ $cTag }}
                                </button>
                            @endif

                            @if($selectedModalLoc)
                                @php $lTag = '@' . preg_replace('/\s+/', '_', trim($selectedModalLoc->name)); @endphp
                                <button type="button"
                                        onclick="insertTagToStoryboardPrompt('{{ $lTag }}')"
                                        title="Insertar etiqueta de la localización en la posición del cursor"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 cursor-pointer transition">
                                    {{ $lTag }}
                                </button>
                            @endif

                            @foreach($storyboardPropUploads as $pIdx => $pFile)
                                @php $pTag = '@Atrezzo' . ($pIdx + 1); @endphp
                                <button type="button"
                                        onclick="insertTagToStoryboardPrompt('{{ $pTag }}')"
                                        title="Insertar etiqueta del objeto de atrezzo {{ $pIdx + 1 }}"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 cursor-pointer transition">
                                    {{ $pTag }}
                                </button>
                            @endforeach

                            <span class="text-[10px] text-slate-500 italic ml-auto hidden sm:inline">
                                💡 Haz clic para insertar la etiqueta @ en el texto
                            </span>
                        </div>

                        <textarea id="storyboard_modal_prompt"
                                  wire:model.live.debounce.300ms="storyboardModalPrompt"
                                  rows="4"
                                  class="w-full rounded-xl bg-slate-950 border border-slate-700 p-3 font-mono text-xs text-slate-200 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 placeholder-slate-500 leading-relaxed"
                                  placeholder="Escribe o edita el prompt detallado usando etiquetas @personaje, @localizacion o @Atrezzo..."></textarea>
                        @error('storyboardModalPrompt')
                            <p class="text-rose-400 text-[10px]">{{ $message }}</p>
                        @enderror
                    </div>
                    <!-- Desglose de Tokens de Storyboard -->
                    @php
                        $currentUser = auth()->user() ?? $project->user;
                        $userTokensAvail = $currentUser ? $currentUser->getTokensAvailableToday() : 0;
                        $storyboardCost = 350;
                        $hasEnoughStoryboard = $userTokensAvail >= $storyboardCost;
                        $remainingAfterStoryboard = max(0, $userTokensAvail - $storyboardCost);
                    @endphp
                    <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Generación de viñeta visual:</span>
                            <span class="font-bold text-amber-400">~{{ $storyboardCost }} tokens</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Saldo disponible hoy:</span>
                            <span class="font-bold text-slate-200">{{ number_format($userTokensAvail, 0, ',', '.') }} tokens</span>
                        </div>
                        <div class="border-t border-slate-800 pt-1.5 flex items-center justify-between {{ $hasEnoughStoryboard ? 'text-emerald-400' : 'text-rose-400' }}">
                            <span>Saldo tras generación:</span>
                            <span class="font-bold">~{{ number_format($remainingAfterStoryboard, 0, ',', '.') }} tokens</span>
                        </div>
                    </div>

                    @if(! $hasEnoughStoryboard)
                        <div class="p-2.5 rounded-xl bg-rose-950/40 border border-rose-800/80 text-xs text-rose-300 flex items-start gap-2">
                            <span>⚠️</span>
                            <span>Saldo insuficiente para generar la viñeta con IA. Se renovará a las 00:00.</span>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-slate-950/90 border-t border-slate-800 flex items-center justify-between">
                    <button type="button"
                            wire:click="closeStoryboardModal"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white bg-slate-800/80 hover:bg-slate-800 border border-slate-700 transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="confirmGenerateStoryboard"
                            wire:loading.attr="disabled"
                            @if(! $hasEnoughStoryboard) disabled @endif
                            class="px-5 py-2 rounded-xl {{ $hasEnoughStoryboard ? 'bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-md' : 'bg-slate-800 text-slate-500 cursor-not-allowed' }} text-xs font-bold flex items-center gap-2 cursor-pointer transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirmGenerateStoryboard">✨ Generar Viñeta con IA</span>
                        <span wire:loading wire:target="confirmGenerateStoryboard" class="flex items-center gap-1.5">
                            ⏳ Enviando a Google AI Studio...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ================= MODAL LIGHTBOX DE STORYBOARD ================= -->
    @if(!empty($previewStoryboardUrl))
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm transition"
             wire:click.self="closeStoryboardPreview">
            <div class="relative max-w-4xl w-full bg-slate-900 border border-slate-700 rounded-2xl overflow-hidden shadow-2xl">
                <div class="flex items-center justify-between px-4 py-3 bg-slate-950/90 border-b border-slate-800">
                    <span class="text-xs font-mono font-bold text-slate-200">
                        🎨 {{ $previewStoryboardTitle ?? 'Storyboard' }}
                    </span>
                    <button wire:click="closeStoryboardPreview"
                            type="button"
                            class="text-slate-400 hover:text-white text-sm font-bold p-1 cursor-pointer">
                        ✕
                    </button>
                </div>
                <div class="p-2 flex items-center justify-center bg-black aspect-video max-h-[80vh]">
                    <img src="{{ $previewStoryboardUrl }}"
                         alt="Previsualización de Storyboard"
                         class="w-full h-full object-contain">
                </div>
            </div>
        </div>
    @endif

    <script>
        function insertTagToStoryboardPrompt(tag) {
            const el = document.getElementById('storyboard_modal_prompt');
            if (!el) return;
            const start = el.selectionStart || 0;
            const end = el.selectionEnd || 0;
            const val = el.value || '';
            const before = val.substring(0, start);
            const after = val.substring(end);
            const spaceBefore = (start > 0 && !/\s$/.test(before)) ? ' ' : '';
            const spaceAfter = (!/^\s/.test(after)) ? ' ' : '';
            el.value = before + spaceBefore + tag + spaceAfter + after;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            const newPos = start + spaceBefore.length + tag.length + spaceAfter.length;
            el.setSelectionRange(newPos, newPos);
            el.focus();
        }

        document.addEventListener('livewire:initialized', () => {
            Livewire.on('scroll-to-selected-block', ({ blockId }) => {
                if (!blockId) return;
                setTimeout(() => {
                    const el = document.getElementById('block-' + blockId);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }, 60);
            });
        });
    </script>
