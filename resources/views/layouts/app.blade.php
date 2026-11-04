<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

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
<body class="h-full flex flex-col font-mono bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-amber-500/30 selection:text-amber-900 dark:selection:text-amber-200 transition-colors duration-150 overflow-x-hidden">
    <!-- Navbar Superior de Estudio -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/95 dark:border-slate-800 dark:bg-slate-950/90 backdrop-blur-md px-2.5 sm:px-6 py-2 transition-colors">
        <div class="flex items-center justify-between gap-2 max-w-7xl mx-auto w-full">
            <!-- Marca y Breadcrumbs -->
            <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                <a href="{{ route('projects.index') }}" class="flex items-center gap-2 group shrink-0">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center shadow-md shadow-amber-950/20 border border-amber-400/30 group-hover:scale-105 transition">
                        <span class="text-sm sm:text-base">🎬</span>
                    </div>
                </a>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 leading-tight">
                        <a href="{{ route('projects.index') }}" class="text-xs sm:text-sm font-bold tracking-tight text-slate-900 dark:text-white hover:text-amber-600 transition truncate">
                            jG Filmmaker
                        </a>
                        <span class="text-[8px] sm:text-[9px] font-mono px-1 py-0.2 rounded bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 font-semibold tracking-wider shrink-0">STUDIO</span>
                    </div>
                    <p class="text-[9px] sm:text-[11px] text-slate-500 dark:text-slate-400 leading-tight mt-0.5 truncate">
                        Pipeline Cinematográfico IA hecho por <a href="https://javiergalvez.com" target="_blank" rel="noopener noreferrer" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline">Javier Gálvez</a>
                    </p>
                </div>
            </div>

            <!-- Acciones y Usuario -->
            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                <!-- Selector Tema Claro / Oscuro -->
                <button type="button"
                        onclick="toggleTheme()"
                        class="inline-flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-200 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition shadow-2xs shrink-0"
                        title="Alternar Tema Claro / Oscuro">
                    <span class="dark:hidden text-xs sm:text-sm">🌙</span>
                    <span class="hidden dark:inline text-xs sm:text-sm">☀️</span>
                </button>

                @auth
                    @php
                        $userTokensAvail = auth()->user()->getTokensAvailableToday();
                        $userDailyLimit = auth()->user()->getDailyTokenLimit();
                        $userUsedToday = auth()->user()->getTokensUsedToday();
                        $userPercentLeft = $userDailyLimit > 0 ? max(0, min(100, round(($userTokensAvail / $userDailyLimit) * 100))) : 0;
                    @endphp

                    <!-- Contador de Tokens Diarios IA del Usuario (Entre selector de tema y usuario) -->
                    <div class="inline-flex items-center gap-1 px-1.5 sm:px-2.5 py-0.5 sm:py-1 rounded-xl text-[10px] sm:text-xs font-mono font-medium bg-slate-100/80 hover:bg-slate-200/80 dark:bg-slate-900/80 dark:hover:bg-slate-800/80 border border-slate-200/80 dark:border-slate-800 text-slate-700 dark:text-slate-300 shadow-2xs transition shrink-0 cursor-default"
                         title="Tokens de IA disponibles hoy: {{ number_format($userTokensAvail, 0, ',', '.') }} de {{ number_format($userDailyLimit, 0, ',', '.') }} ({{ $userPercentLeft }}%). Consumidos hoy: {{ number_format($userUsedToday, 0, ',', '.') }}. Se renueva diariamente a las 00:00.">
                        <span class="{{ $userPercentLeft < 15 ? 'text-rose-500 animate-pulse' : ($userPercentLeft < 40 ? 'text-amber-500' : 'text-emerald-500') }}">⚡</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userTokensAvail, 0, ',', '.') }}</span>
                        <span class="hidden lg:inline text-[11px] text-slate-400">/ {{ number_format($userDailyLimit, 0, ',', '.') }}</span>
                    </div>

                    <!-- Menú Desplegable de Usuario (Estilo jg_hub / jg_dashboard con Cuota de Slots) -->
                    <div class="relative pl-1 sm:pl-1.5 border-l border-slate-200 dark:border-slate-800 shrink-0" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                        <button @click="userMenuOpen = !userMenuOpen"
                                type="button"
                                class="flex items-center gap-1 sm:gap-2 px-1 sm:px-1.5 py-0.5 rounded-xl bg-slate-100/80 hover:bg-slate-200 border border-slate-200/80 dark:bg-slate-900/80 dark:hover:bg-slate-800 dark:border-slate-800 transition shadow-2xs cursor-pointer text-left">
                            <div class="text-right hidden sm:block">
                                <div class="flex items-center justify-end gap-1.5">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ auth()->user()->email }}</span>
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold tracking-wider uppercase bg-amber-500/10 text-amber-700 border border-amber-500/30 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/40">
                                        {{ strtoupper(auth()->user()->plan_tier) }}
                                    </span>
                                </div>
                            </div>

                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-amber-500/20 border border-amber-500/40 flex items-center justify-center font-bold text-xs text-amber-600 dark:text-amber-300 shadow-inner shrink-0">
                                {{ strtoupper(substr(auth()->user()->name ?: auth()->user()->email, 0, 1)) }}
                            </div>

                            <svg class="w-3 h-3 text-slate-400 transition-transform shrink-0" :class="{ 'rotate-180': userMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold font-mono uppercase bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                        {{ strtoupper(auth()->user()->plan_tier) }}
                                    </span>
                                </div>
                                <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ auth()->user()->email }}</p>
                            </div>

                            <!-- Sección de Tokens Diarios de IA -->
                            <div class="p-3 bg-slate-50 dark:bg-slate-950/70 rounded-xl border border-slate-100 dark:border-slate-800/80 space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-slate-600 dark:text-slate-400 flex items-center gap-1">
                                        <span>⚡</span> Tokens Diarios:
                                    </span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">
                                        <span class="{{ $userPercentLeft < 15 ? 'text-rose-500' : ($userPercentLeft < 40 ? 'text-amber-500' : 'text-emerald-500') }}">{{ number_format($userTokensAvail, 0, ',', '.') }}</span>
                                        <span class="text-slate-400">/</span>
                                        <span class="text-slate-500 text-[11px]">{{ number_format($userDailyLimit, 0, ',', '.') }}</span>
                                    </span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full {{ $userPercentLeft < 15 ? 'bg-rose-500' : ($userPercentLeft < 40 ? 'bg-amber-500' : 'bg-emerald-500') }} rounded-full transition-all" style="width: {{ $userPercentLeft }}%"></div>
                                </div>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 flex justify-between">
                                    <span>Consumidos hoy: {{ number_format($userUsedToday, 0, ',', '.') }}</span>
                                    <span>Renueva: 00:00</span>
                                </p>
                            </div>

                            <!-- Sección de Slots y Capacidad -->
                            <div class="p-3 bg-slate-50 dark:bg-slate-950/70 rounded-xl border border-slate-100 dark:border-slate-800/80 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-slate-600 dark:text-slate-400">Slots de Proyectos:</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ auth()->user()->projects()->count() }}</span>
                                        <span class="text-slate-400">/</span>
                                        <span>{{ auth()->user()->paid_project_slots }}</span>
                                    </span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ min(100, (auth()->user()->projects()->count() / max(1, auth()->user()->paid_project_slots)) * 100) }}%"></div>
                                </div>
                            </div>

                            <!-- Opciones Rápidas -->
                            <div class="space-y-1 pt-1 border-t border-slate-100 dark:border-slate-800/60 text-xs">
                                <a href="{{ route('projects.index') }}"
                                   class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-medium">
                                    <span>📁</span>
                                    <span>Mis Proyectos</span>
                                </a>

                                @if(auth()->user()->is_admin)
                                    <a href="{{ route('admin.users') }}"
                                       class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-amber-700 dark:text-amber-400 hover:bg-amber-500/10 transition font-medium font-mono">
                                        <span>🛠️</span>
                                        <span>Panel Administración</span>
                                    </a>
                                @endif

                                @if(isset($currentProject))
                                    <button type="button"
                                            onclick="Livewire.dispatch('toggle-bible-drawer'); userMenuOpen = false;"
                                            class="w-full text-left flex items-center justify-between px-3 py-2 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition font-medium cursor-pointer"
                                            title="Biblia del Proyecto">
                                        <div class="flex items-center gap-2.5">
                                            <span>📖</span>
                                            <span>Biblia del Proyecto</span>
                                        </div>
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

                                <form method="POST" action="{{ route('logout') }}" class="pt-1 border-t border-slate-100 dark:border-slate-800/60">
                                    @csrf
                                    <button type="submit"
                                            class="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 transition font-medium cursor-pointer">
                                        <span>🚪</span>
                                        <span>Cerrar Sesión</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}"
                       class="px-3.5 py-1.5 rounded-xl font-bold font-mono text-xs bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-2xs">
                        Acceso Seguro ➔
                    </a>
                @endauth
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
