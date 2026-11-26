@php
    $pageName = $paginator->getPageName();
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navegación de páginas" class="inline-flex items-center gap-1">
        {{-- Botón Anterior --}}
        @if ($paginator->onFirstPage())
            <span class="px-2 py-1 text-xs font-medium text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-800 rounded cursor-not-allowed select-none">
                ‹ Ant
            </span>
        @else
            <button 
                type="button" 
                wire:click="previousPage('{{ $pageName }}')" 
                class="px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#1a2030] hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded transition-colors cursor-pointer shadow-2xs"
                title="Página anterior"
            >
                ‹ Ant
            </button>
        @endif

        {{-- Enlaces Numéricos --}}
        @foreach ($elements as $element)
            {{-- Separador de puntos suspensivos --}}
            @if (is_string($element))
                <span class="px-2 py-1 text-xs text-gray-400 dark:text-gray-600 select-none">...</span>
            @endif

            {{-- Array de páginas --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="px-2.5 py-1 text-xs font-bold text-white bg-indigo-600 border border-indigo-600 rounded shadow-2xs select-none">
                            {{ $page }}
                        </span>
                    @else
                        <button 
                            type="button" 
                            wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" 
                            class="px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#1a2030] hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded transition-colors cursor-pointer shadow-2xs"
                            title="Ir a página {{ $page }}"
                        >
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Botón Siguiente --}}
        @if ($paginator->hasMorePages())
            <button 
                type="button" 
                wire:click="nextPage('{{ $pageName }}')" 
                class="px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#1a2030] hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded transition-colors cursor-pointer shadow-2xs"
                title="Página siguiente"
            >
                Sig ›
            </button>
        @else
            <span class="px-2 py-1 text-xs font-medium text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-800 rounded cursor-not-allowed select-none">
                Sig ›
            </span>
        @endif
    </nav>
@endif

