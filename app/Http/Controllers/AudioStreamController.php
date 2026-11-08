<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ScriptBlock;
use App\Services\TextToSpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudioStreamController extends Controller
{
    /**
     * Retorna el manifiesto secuencial de segmentos a reproducir para un bloque, una escena o todo el proyecto.
     */
    public function manifest(Request $request, Project $project, TextToSpeechService $tts): JsonResponse
    {
        Gate::authorize('view', $project);

        $sceneId = $request->query('scene_id');
        $blockId = $request->query('block_id');

        $manifest = $tts->buildManifestForScope($project, $sceneId, $blockId);

        return response()->json([
            'status' => 'ok',
            'project_id' => $project->id,
            'count' => count($manifest),
            'manifest' => $manifest,
        ]);
    }

    /**
     * Devuelve el flujo de audio WAV correspondiente a un segmento específico de un bloque.
     */
    public function streamAudio(Request $request, Project $project, TextToSpeechService $tts): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        $blockId = (string) $request->query('block_id');
        $segmentIndex = (int) $request->query('segment_index', 0);
        $speed = max(0.5, min(2.0, (float) $request->query('speed', 1.0)));

        $block = ScriptBlock::where('id', $blockId)
            ->whereHas('scene', fn ($q) => $q->where('project_id', $project->id))
            ->firstOrFail();

        $characters = $project->characters()->get();
        $segments = $tts->parseBlockToSegments($block, $characters, $project);

        if (! isset($segments[$segmentIndex])) {
            abort(404, 'Segmento de audio no encontrado para el bloque especificado.');
        }

        $segment = $segments[$segmentIndex];
        $audioPath = $tts->synthesize($segment['text'], $segment['voice'], $speed);

        return response()->file($audioPath, [
            'Content-Type' => 'audio/wav',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Devuelve el flujo de audio WAV correspondiente a una muestra o demo de voz para la biblia.
     */
    public function demoVoice(Request $request, Project $project, TextToSpeechService $tts): BinaryFileResponse|JsonResponse
    {
        Gate::authorize('view', $project);

        $defaultVoice = $tts->getDefaultVoice();
        $voice = (string) $request->query('voice', $defaultVoice);
        $voiceInfo = collect($tts->getAvailableVoices())->firstWhere('id', $voice);
        $shortName = $voiceInfo['id'] ?? $voice;

        $text = (string) $request->query('text', "Hola, soy la voz {$shortName}.");

        try {
            $audioPath = $tts->synthesize($text, $voice, 1.0);

            return response()->file($audioPath, [
                'Content-Type' => 'audio/wav',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo generar la muestra de voz en este momento: '.$e->getMessage(),
            ], 503);
        }
    }
}
