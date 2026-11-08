<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $project->title }} - Guión Cinematográfico</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 25mm;
            size: a4 portrait;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11pt;
            line-height: 1.25;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* Portada Canónica de Guion (Title Page) */
        .title-page {
            page-break-after: always;
            padding-top: 140px;
            text-align: center;
        }

        .title-page h1 {
            font-size: 22pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 25px;
            text-decoration: underline;
        }

        .title-page .byline {
            font-size: 11pt;
            margin-bottom: 12px;
            font-style: italic;
        }

        .title-page .author {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 35px;
        }

        .title-page .synopsis-box {
            margin: 40px auto;
            max-width: 480px;
            text-align: justify;
            font-size: 10pt;
            line-height: 1.4;
            padding: 12px;
            border-left: 2px solid #333;
        }

        .title-page .meta {
            margin-top: 120px;
            font-size: 9pt;
            color: #555;
            line-height: 1.6;
        }

        /* Contenido del Guion */
        .script-content {
            margin-top: 0;
        }

        .scene-container {
            margin-bottom: 22px;
        }

        .scene-header {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 24px;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
            border-bottom: 1px dashed #bbb;
            padding-bottom: 4px;
        }

        .action-paragraph {
            margin-bottom: 12px;
            text-align: justify;
            line-height: 1.3;
        }

        .dialogue-block {
            margin-top: 10px;
            margin-bottom: 14px;
        }

        .character-cue {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 20%;
            margin-right: 20%;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .parenthetical-cue {
            text-align: center;
            font-style: italic;
            margin-left: 25%;
            margin-right: 25%;
            margin-bottom: 2px;
            font-size: 10pt;
        }

        .speech-text {
            margin-left: 14%;
            margin-right: 14%;
            text-align: left;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .page-footer {
            position: fixed;
            bottom: -15mm;
            right: 0;
            font-size: 9pt;
            color: #777;
        }

        .footer-counter:before {
            content: "Pág. " counter(page);
        }
    </style>
</head>
<body>

    <!-- ================= PORTADA DEL GUIÓN ================= -->
    <div class="title-page">
        <h1>{{ $project->title }}</h1>

        <div class="byline">Guión original escrito por</div>
        <div class="author">{{ $project->user->name ?? $project->user->email ?? 'Autor' }}</div>

        @if(!empty(trim((string)$project->synopsis)))
            <div class="synopsis-box">
                <strong>SINOPSIS:</strong><br>
                {{ $project->synopsis }}
            </div>
        @endif

        <div class="meta">
            <div>Obra registrada en <em>Filmmaker Studio</em></div>
            <div>Fecha de emisión: {{ $exportedAt }}</div>
            <div>Total de escenas: {{ count($scenes) }}</div>
        </div>
    </div>

    <!-- ================= CUERPO DEL GUIÓN ================= -->
    <div class="script-content">
        @forelse($scenes as $scene)
            <div class="scene-container">
                <div class="scene-header">
                    ESCENA {{ $scene['order'] }} — {{ $scene['slugline'] }}
                </div>

                @foreach($scene['blocks'] as $block)
                    @foreach($block['elements'] as $element)
                        @if($element['type'] === 'scene_heading')
                            <div class="scene-header" style="margin-top: 14px; font-size: 10.5pt; border-bottom: none;">
                                {{ $element['text'] }}
                            </div>
                        @elseif($element['type'] === 'character')
                            <div class="character-cue">
                                {{ $element['text'] }}
                            </div>
                        @elseif($element['type'] === 'parenthetical')
                            <div class="parenthetical-cue">
                                {{ $element['text'] }}
                            </div>
                        @elseif($element['type'] === 'dialogue')
                            <div class="speech-text">
                                {{ $element['text'] }}
                            </div>
                        @else
                            <div class="action-paragraph">
                                {{ $element['text'] }}
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        @empty
            <div class="action-paragraph" style="text-align: center; margin-top: 40px; font-style: italic;">
                Este proyecto aún no contiene escenas ni bloques de texto.
            </div>
        @endforelse
    </div>

</body>
</html>
