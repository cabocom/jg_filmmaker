    <script>
        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';
            if (newTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
            }
            localStorage.setItem('jg_theme', newTheme);
            localStorage.setItem('console_theme', newTheme);
        }

        // Resaltado de código con Highlight.js sincronizado con Livewire (estilo jg_hub)
        function applyCodeHighlighting() {
            if (typeof hljs !== 'undefined') {
                document.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                });
            }
        }
        function applyAntiDoubleClick() {
            document.querySelectorAll('button[type="submit"], button[wire\\:click]').forEach((btn) => {
                if (!btn.hasAttribute('wire:loading.attr')) {
                    btn.setAttribute('wire:loading.attr', 'disabled');
                }
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            applyCodeHighlighting();
            applyAntiDoubleClick();
        });
        document.addEventListener('livewire:navigated', () => {
            applyCodeHighlighting();
            applyAntiDoubleClick();
        });
        document.addEventListener('livewire:initialized', () => {
            if (window.Livewire) {
                Livewire.hook('morph.updated', () => {
                    applyCodeHighlighting();
                    applyAntiDoubleClick();
                });
            }
        });

        // Guardarraíl global contra el doble clic de la impaciencia en botones y formularios
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
                if (btn.disabled) return;
                btn.disabled = true;
                btn.classList.add('opacity-50', 'pointer-events-none');
                setTimeout(() => {
                    if (document.body.contains(btn)) {
                        btn.disabled = false;
                        btn.classList.remove('opacity-50', 'pointer-events-none');
                    }
                }, 1200);
            });
        }, true);

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn || btn.disabled || btn.type === 'submit') return;
            const hasWireAction = Array.from(btn.attributes).some((attr) => attr.name.startsWith('wire:click'));
            if (!hasWireAction) return;
            btn.disabled = true;
            btn.classList.add('opacity-60', 'pointer-events-none');
            setTimeout(() => {
                if (document.body.contains(btn)) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60', 'pointer-events-none');
                }
            }, 700);
        }, true);
    </script>
</body>
</html>
