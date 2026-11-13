<!-- ================= MODAL LATERAL DERECHO AL 60% (DETALLES Y EDICIÓN DE BLOQUE) ================= -->
@if($detailBlockId)
    @php
        $detailBlock = \App\Models\ScriptBlock::with('scene.location', 'shots.takes')->find($detailBlockId);
        $detailLocks = $detailBlock?->user_locks ?? [];
    @endphp

    @if($detailBlock)
        <!-- Contenedor general del Drawer -->
        <div class="fixed inset-0 z-50 overflow-hidden" x-data>
            <!-- Backdrop suave exterior: NO muy oscurecido -->
            <div class="fixed inset-0 bg-slate-950/25 backdrop-blur-[2px] transition-opacity"></div>

            <!-- Panel lateral deslizante alineado a la derecha -->
            <div class="fixed inset-y-0 right-0 z-50 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col h-full w-full sm:w-[540px] md:w-[620px] lg:w-[60vw] max-w-full">
                @include('livewire.studio.partials.editor.drawer.drawer-header', ['detailBlock' => $detailBlock])

                <!-- Cuerpo scrolleable del Drawer -->
                <div class="flex-1 overflow-y-auto p-3.5 sm:p-6 space-y-4 sm:space-y-6">
                    @include('livewire.studio.partials.editor.drawer.drawer-alerts-locks', ['detailBlock' => $detailBlock, 'detailLocks' => $detailLocks])
                    @include('livewire.studio.partials.editor.drawer.drawer-stages-content', ['detailBlock' => $detailBlock, 'detailLocks' => $detailLocks])
                </div>
            </div>
        </div>
    @endif
@endif
