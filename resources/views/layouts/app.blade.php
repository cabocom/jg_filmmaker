<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Estudio Cinematográfico' }} — jG Filmmaker</title>

    <!-- Google Fonts: Inter & JetBrains Mono (Tipografía idéntica a jg_hub) + Courier Prime para Guión -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Resaltador de Sintaxis para JSON y Código (Highlight.js estilo jg_hub) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/json.min.js"></script>

    <script>
        (function() {
            const saved = localStorage.getItem('jg_theme') || localStorage.getItem('console_theme') || 'light';
            const theme = (saved === 'dark' || saved === 'negro') ? 'dark' : 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body, button, input, select, textarea, h1, h2, h3, h4, h5, h6, p, span, a, table, th, td {
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
            font-size: 13px;
            line-height: 1.6;
        }
        code, kbd, samp, pre, .font-mono {
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
        }
        .font-screenplay {
            font-family: 'Courier Prime', Courier, monospace !important;
        }
        pre {
            background-color: #0f172a !important;
            border: 1px solid #1e293b !important;
            border-radius: 8px !important;
        }
        pre code {
            background-color: transparent !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            font-size: 12px !important;
            line-height: 1.65 !important;
        }
        pre code::before,
        pre code::after {
            display: none !important;
        }
        .hljs {
            background: transparent !important;
        }
    </style>
</head>
<body class="h-full flex flex-col font-mono bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-amber-500/30 selection:text-amber-900 dark:selection:text-amber-200 transition-colors duration-150">
    <!-- Navbar Superior de Estudio -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/90 dark:border-slate-800 dark:bg-slate-950/80 backdrop-blur-md px-4 sm:px-6 py-3 transition-colors">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <!-- Marca y Breadcrumbs -->
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.index') }}" class="flex items-center gap-2 group">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center shadow-lg shadow-amber-950/20 border border-amber-400/30 group-hover:scale-105 transition">
                        <span class="text-lg">🎬</span>
                    </div>
                    <div>
                        <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                            jG Filmmaker
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 font-semibold tracking-wider">STUDIO</span>
                        </span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-none">Pipeline Cinematográfico IA</p>
                    </div>
                </a>

                @if(isset($currentProject))
                    <span class="text-slate-400 dark:text-slate-600">/</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-semibold text-amber-700 dark:text-amber-300 truncate max-w-[200px] sm:max-w-xs">
                            {{ $currentProject->title }}
                        </span>
                        <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
                            {{ $currentProject->status }}
                        </span>
                    </div>
                @endif
            </div>

            <!-- Acciones y Usuario -->
            <div class="flex items-center gap-3">
                <!-- Selector Tema Claro / Oscuro -->
                <button type="button"
                        onclick="toggleTheme()"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-200 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition shadow-sm"
                        title="Alternar Tema Claro / Oscuro">
                    <span class="dark:hidden text-sm">🌙</span>
                    <span class="hidden dark:inline text-sm">☀️</span>
                </button>

                <!-- Botón Drawer de Biblia (si hay proyecto activo) -->
                @if(isset($currentProject))
                    <button type="button"
                            onclick="Livewire.dispatch('toggle-bible-drawer')"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 hover:border-amber-500/40 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700/80 dark:text-slate-200 transition shadow-sm"
                            title="Abrir Biblia Contextual (Cmd+B / Ctrl+B)">
                        <span>📖</span>
                        <span class="hidden sm:inline">Biblia de Proyecto</span>
                        <kbd class="hidden md:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-mono bg-white text-slate-600 rounded border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">
                            ⌘B
                        </kbd>
                    </button>
                @endif

                <!-- Menú Desplegable de Usuario (Estilo jg_hub / jg_dashboard con Cuota de Slots) -->
                <div class="relative pl-2 border-l border-slate-200 dark:border-slate-800" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                    <button @click="userMenuOpen = !userMenuOpen"
                            type="button"
                            class="flex items-center gap-2.5 px-2 py-1 rounded-xl bg-slate-100/80 hover:bg-slate-200 border border-slate-200/80 dark:bg-slate-900/80 dark:hover:bg-slate-800 dark:border-slate-800 transition shadow-xs cursor-pointer text-left">
                        <div class="text-right hidden sm:block">
                            <div class="flex items-center justify-end gap-1.5">
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ auth()->user()->name ?? 'Javier Gálvez' }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-700 border border-amber-500/30 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/40">
                                    {{ strtoupper(auth()->user()->plan_tier ?? 'PRO') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-end gap-1 text-[10px] font-mono text-slate-500 dark:text-slate-400 leading-none">
                                <span>Slots:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ auth()->user()->projects()->count() ?? 1 }}</span>
                                <span>/</span>
                                <span>{{ auth()->user()->paid_project_slots ?? 5 }}</span>
                            </div>
                        </div>

                        <div class="w-8 h-8 rounded-full bg-amber-500/20 border border-amber-500/40 flex items-center justify-center font-bold text-xs text-amber-600 dark:text-amber-300 shadow-inner">
                            {{ strtoupper(substr(auth()->user()->name ?? 'J', 0, 1)) }}
                        </div>

                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': userMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Panel (Mini Menú de Usuario) -->
                    <div x-show="userMenuOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-72 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden z-50 p-2 space-y-2"
                         style="display: none;">

                        <!-- Tarjeta de Identidad -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-950/70 rounded-xl border border-slate-100 dark:border-slate-800/80">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ auth()->user()->name ?? 'Javier Gálvez' }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold font-mono uppercase bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                    {{ strtoupper(auth()->user()->plan_tier ?? 'PRO') }}
                                </span>
                            </div>
                            <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ auth()->user()->email ?? 'filmmaker@cabocom.net' }}</p>
                        </div>

                        <!-- Sección de Slots y Capacidad -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-950/70 rounded-xl border border-slate-100 dark:border-slate-800/80 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-600 dark:text-slate-400">Slots de Proyectos:</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white">
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ auth()->user()->projects()->count() ?? 1 }}</span>
                                    <span class="text-slate-400">/</span>
                                    <span>{{ auth()->user()->paid_project_slots ?? 5 }}</span>
                                </span>
                            </div>
                            <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ min(100, ((auth()->user()->projects()->count() ?? 1) / (auth()->user()->paid_project_slots ?? 5)) * 100) }}%"></div>
                            </div>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-tight">
                                Tienes {{ max(0, (auth()->user()->paid_project_slots ?? 5) - (auth()->user()->projects()->count() ?? 1)) }} slots disponibles en tu suscripción.
                            </p>
                        </div>

                        <!-- Opciones Rápidas -->
                        <div class="space-y-1 pt-1 border-t border-slate-100 dark:border-slate-800/60 text-xs">
                            <a href="{{ route('projects.index') }}"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-medium">
                                <span>📁</span>
                                <span>Mis Proyectos</span>
                            </a>
                            @if(isset($currentProject))
                                <button type="button"
                                        onclick="Livewire.dispatch('toggle-bible-drawer'); userMenuOpen = false;"
                                        class="w-full text-left flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-medium cursor-pointer">
                                    <div class="flex items-center gap-2.5">
                                        <span>📖</span>
                                        <span>Biblia del Proyecto</span>
                                    </div>
                                    <kbd class="px-1.5 py-0.5 text-[9px] font-mono bg-slate-200 dark:bg-slate-800 rounded border border-slate-300 dark:border-slate-700">⌘B</kbd>
                                </button>
                            @endif
                            <button type="button"
                                    onclick="toggleTheme()"
                                    class="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-medium cursor-pointer">
                                <span class="dark:hidden">🌙</span>
                                <span class="hidden dark:inline">☀️</span>
                                <span class="dark:hidden">Modo Oscuro</span>
                                <span class="hidden dark:inline">Modo Claro</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="flex-1 overflow-y-auto">
        {{ $slot }}
    </main>

    <!-- Drawer Lateral Global para Biblia Contextual -->
    @if(isset($currentProject))
        <livewire:studio.context-bible-drawer :project="$currentProject" />
    @endif

    @livewireScripts

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
        document.addEventListener('DOMContentLoaded', applyCodeHighlighting);
        document.addEventListener('livewire:navigated', applyCodeHighlighting);
        document.addEventListener('livewire:initialized', () => {
            if (window.Livewire) {
                Livewire.hook('morph.updated', () => {
                    applyCodeHighlighting();
                });
            }
        });
    </script>
</body>
</html>
