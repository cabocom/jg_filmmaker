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

