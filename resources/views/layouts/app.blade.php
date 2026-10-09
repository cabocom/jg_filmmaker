<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Estudio Cinematográfico' }} — jG Filmmaker</title>

    <!-- Google Fonts: Inter & Courier Prime for Screenplay standard -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        .font-screenplay {
            font-family: 'Courier Prime', Courier, monospace;
        }
        .font-mono-code {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans bg-slate-950 text-slate-200 antialiased selection:bg-amber-500/30 selection:text-amber-200">
    <!-- Navbar Superior de Estudio -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-800 bg-slate-950/80 backdrop-blur-md px-4 sm:px-6 py-3">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <!-- Marca y Breadcrumbs -->
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.index') }}" class="flex items-center gap-2 group">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center shadow-lg shadow-amber-950/40 border border-amber-400/30 group-hover:scale-105 transition">
                        <span class="text-lg">🎬</span>
                    </div>
                    <div>
                        <span class="text-base font-bold tracking-tight text-white flex items-center gap-1.5">
                            jG Filmmaker
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold tracking-wider">STUDIO</span>
                        </span>
                        <p class="text-[11px] text-slate-400 leading-none">Pipeline Cinematográfico IA</p>
                    </div>
                </a>

                @if(isset($currentProject))
                    <span class="text-slate-600">/</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-semibold text-amber-300 truncate max-w-[200px] sm:max-w-xs">
                            {{ $currentProject->title }}
                        </span>
                        <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                            {{ $currentProject->status }}
                        </span>
                    </div>
                @endif
            </div>

            <!-- Acciones y Usuario -->
            <div class="flex items-center gap-3">
                <!-- Botón Drawer de Biblia (si hay proyecto activo) -->
                @if(isset($currentProject))
                    <button type="button"
                            onclick="Livewire.dispatch('toggle-bible-drawer')"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-900 hover:bg-slate-800 border border-slate-700/80 hover:border-amber-500/40 text-slate-200 transition shadow-sm"
                            title="Abrir Biblia Contextual (Cmd+B / Ctrl+B)">
                        <span>📖</span>
                        <span class="hidden sm:inline">Biblia de Proyecto</span>
                        <kbd class="hidden md:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-mono bg-slate-800 text-slate-400 rounded border border-slate-700">
                            ⌘B
                        </kbd>
                    </button>
                @endif

                <!-- Cuota de Proyectos -->
                <div class="hidden lg:flex items-center gap-2 px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-xs">
                    <span class="text-slate-400">Slots:</span>
                    <span class="font-mono font-medium text-emerald-400">{{ auth()->user()->projects()->count() ?? 1 }}</span>
                    <span class="text-slate-600">/</span>
                    <span class="font-mono text-slate-300">{{ auth()->user()->paid_project_slots ?? 5 }}</span>
                </div>

                <!-- Usuario y Badge PRO -->
                <div class="flex items-center gap-2.5 pl-2 border-l border-slate-800">
                    <div class="text-right hidden sm:block">
                        <div class="flex items-center justify-end gap-1.5">
                            <span class="text-xs font-semibold text-slate-200">{{ auth()->user()->name ?? 'Cineasta' }}</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold tracking-wider uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                {{ strtoupper(auth()->user()->plan_tier ?? 'PRO') }}
                            </span>
                        </div>
                        <p class="text-[10px] font-mono text-slate-400 leading-none">{{ auth()->user()->email ?? 'filmmaker@cabocom.net' }}</p>
                    </div>

                    <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-amber-400 shadow-inner">
                        {{ strtoupper(substr(auth()->user()->name ?? 'F', 0, 1)) }}
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
        // Atajo global de teclado Cmd+B / Ctrl+B para el Drawer
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'b') {
                e.preventDefault();
                Livewire.dispatch('toggle-bible-drawer');
            }
        });
    </script>
</body>
</html>
