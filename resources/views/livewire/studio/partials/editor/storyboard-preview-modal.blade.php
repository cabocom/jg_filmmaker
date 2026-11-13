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
