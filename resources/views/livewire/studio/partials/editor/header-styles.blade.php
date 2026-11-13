    <style>
        /* Estilos directos e infalibles para hover de bloques */
        .script-block-item {
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .script-block-item:not(.is-selected):hover {
            background-color: #f1f5f9 !important; /* Tailwind slate-100 */
        }
        .dark .script-block-item:not(.is-selected):hover {
            background-color: #1e293b !important; /* Tailwind slate-800 */
        }
        .script-block-item.is-selected {
            background-color: rgba(245, 158, 11, 0.1) !important;
            border-left-color: #f59e0b !important;
        }

        /* Divisores Gutenberg: línea horizontal continua ámbar garantizada */
        .gutenberg-container {
            position: relative;
            z-index: 5 !important;
        }
        .gutenberg-divider-line {
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 2px !important;
            background-color: #f59e0b !important; /* amber-500 */
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            z-index: 5 !important;
        }
        .gutenberg-container:hover .gutenberg-divider-line,
        .gutenberg-divider-line.is-visible {
            opacity: 1 !important;
        }
        .gutenberg-container:hover .gutenberg-divider-btn,
        .gutenberg-divider-btn.is-visible {
            opacity: 1 !important;
            transform: scale(1) !important;
            pointer-events: auto !important;
            z-index: 6 !important;
        }
        [x-cloak] {
            display: none !important;
        }
    </style>

