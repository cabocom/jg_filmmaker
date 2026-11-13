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

    <!-- Conversor automático de imágenes Apple HEIC/HEIF a JPEG -->
    <script src="{{ asset('vendor/heic2any/heic2any.min.js') }}" onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/heic2any/0.0.4/heic2any.min.js';"></script>
    <script>
        window.handleHeicAndUpload = async function(file, onConvertingChange, onReady) {
            if (!file) return;

            const isHeic = file.name.toLowerCase().endsWith('.heic') ||
                           file.name.toLowerCase().endsWith('.heif') ||
                           file.type === 'image/heic' ||
                           file.type === 'image/heif';

            if (isHeic && typeof heic2any === 'function') {
                if (onConvertingChange) onConvertingChange(true);
                try {
                    const conversionResult = await heic2any({
                        blob: file,
                        toType: 'image/jpeg',
                        quality: 0.92,
                    });
                    const blob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
                    const newName = file.name.replace(/\.(heic|heif)$/i, '.jpg');
                    file = new File([blob], newName, { type: 'image/jpeg' });
                } catch (error) {
                    console.error('Error al convertir HEIC a JPG:', error);
                    alert('No se pudo convertir la imagen HEIC automáticamente. Por favor súbela en formato JPG o PNG.');
                    if (onConvertingChange) onConvertingChange(false);
                    return;
                } finally {
                    if (onConvertingChange) onConvertingChange(false);
                }
            }

            if (onReady) {
                onReady(file);
            }
        };
    </script>

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
    @include('layouts.partials.header')

    <!-- Contenido Principal -->
    <main class="flex-1 overflow-y-auto">
        {{ $slot }}
    </main>

    <!-- Drawer Lateral Global para Biblia Contextual -->
    @if(isset($currentProject))
        <livewire:studio.context-bible-drawer :project="$currentProject" />
    @endif

    @livewireScripts

    @include('layouts.partials.scripts')
</body>
</html>
