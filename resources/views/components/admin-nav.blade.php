<div class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 lg:px-8 py-3 mb-6 transition-colors">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="text-xl">🛠️</span>
            <div>
                <h1 class="text-base font-bold font-mono text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span>Panel de Administración</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                        ADMIN
                    </span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Gobernanza de usuarios, telemetría de costes y seguridad
                </p>
            </div>
        </div>

        <nav class="flex items-center gap-2 font-mono text-xs overflow-x-auto pb-1 sm:pb-0">
            <a href="{{ route('admin.users') }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5 {{ request()->routeIs('admin.users*') ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                <span>👥</span>
                <span>Usuarios</span>
            </a>

            <a href="{{ route('admin.projects') }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5 {{ request()->routeIs('admin.projects*') ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                <span>🎬</span>
                <span>Proyectos</span>
            </a>

            <a href="{{ route('admin.pricing') }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5 {{ request()->routeIs('admin.pricing') ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                <span>⚡</span>
                <span>Tarifas Tokens</span>
            </a>

            <a href="{{ route('admin.security') }}"
               class="px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5 {{ request()->routeIs('admin.security') ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                <span>🛡️</span>
                <span>Alertas Seguridad</span>
            </a>

            <div class="h-4 w-px bg-slate-300 dark:bg-slate-700 mx-1"></div>

            <a href="{{ route('projects.index') }}"
               class="px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1.5">
                <span>🎬</span>
                <span>Estudio</span>
            </a>
        </nav>
    </div>
</div>
