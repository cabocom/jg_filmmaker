<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoryboardService
{
    /**
     * Catálogo canónico de estilos visuales disponibles para el Storyboard.
     *
     * @var array<string, array{name: string, badge: string, description: string, modifier: string}>
     */
    public const STYLES = [
        'grayscale_sketch' => [
            'name' => 'Escala de Grises',
            'badge' => 'Sketch B&W',
            'description' => 'Boceto de producción cinematográfica a carboncillo y grafito, alto contraste, sombras noir y composición dinámica.',
            'modifier' => 'Cinematic production storyboard sketch, charcoal and graphite drawing, black and white tones, dynamic lighting, film noir shading, rough crosshatching, rule of thirds composition, 35mm storyboard aspect ratio 16:9.',
        ],
        'kids_2d_animation' => [
            'name' => 'Animación 2D Infantil',
            'badge' => '2D Infantil',
            'description' => 'Fotograma de animación clásica 2D, líneas claras tipo vector, paleta cálida y saturada, diseño amigable de personajes.',
            'modifier' => 'Vibrant 2D children\'s animated feature film still, clean vector linework, whimsical warm color palette, soft cel shading, expressive character design, storybook aesthetic, high quality 2D animation, 16:9.',
        ],
        'enki_bilal' => [
            'name' => 'Enki Bilal',
            'badge' => 'Enki Bilal',
            'description' => 'Novela gráfica europea de ciencia ficción distópica, textura al pastel graso, tonos apagados ocres/azules y mirada melancólica.',
            'modifier' => 'Graphic novel panel in the distinct art style of Enki Bilal, European bande dessinée, textured chalk and oil pastel, gritty dystopian atmosphere, desaturated muted color palette with chalky blues and ochre, weathered look, expressive melancholy gaze, cinematic sci-fi noir, 16:9.',
        ],
    ];

    /**
     * Retorna los estilos soportados.
     *
     * @return array<string, array{name: string, badge: string, description: string, modifier: string}>
     */
    public function getStyles(): array
    {
        return self::STYLES;
    }

    /**
     * Construye el prompt descriptivo optimizado para Google AI Studio (Imagen 3 / Gemini),
     * incorporando encuadre, escena, acción, estilo y condicionamiento del personaje si existe turn-around.
     */
    public function buildPrompt(Shot $shot, ?string $style = null): string
    {
        $shot->loadMissing(['scene.project.characters', 'scriptBlock']);

        $styleKey = $style ?? $shot->scene?->project?->storyboard_style ?? 'grayscale_sketch';
        $styleData = self::STYLES[$styleKey] ?? self::STYLES['grayscale_sketch'];

        $framing = $shot->shot_type ?: 'Medium Shot';
        $sceneHeader = $shot->scene ? "ESCENA {$shot->scene->scene_order}: {$shot->scene->slugline}" : 'SCENE';
        $description = trim((string) $shot->description) ?: 'Cinematic composition depicting the scene action.';

        // Comprobar si hay un personaje asociado al bloque de guion o mencionado en el plano
        $characterDirective = $this->resolveCharacterConditioning($shot);

        $promptParts = [
            'Cinematic film storyboard panel, aspect ratio 16:9.',
            "Camera Framing: {$framing}.",
            "Scene Setting: {$sceneHeader}.",
            "Action & Composition: {$description}.",
        ];

        if ($characterDirective) {
            $promptParts[] = "Character Consistency: {$characterDirective}";
        }

        $promptParts[] = "Artistic Style: {$styleData['modifier']}";

        return implode(' ', $promptParts);
    }

    /**
     * Genera la imagen de storyboard a través de la API de Google AI Studio (Imagen 3),
     * almacena el artefacto en el disco público y actualiza el plano.
     *
     * @throws RuntimeException
     */
    public function generateStoryboard(Shot $shot, ?string $style = null): string
    {
        $shot->loadMissing(['scene.project']);
        $projectId = $shot->scene?->project_id ?? 'global';

        $styleKey = $style ?? $shot->scene?->project?->storyboard_style ?? 'grayscale_sketch';
        if (! array_key_exists($styleKey, self::STYLES)) {
            $styleKey = 'grayscale_sketch';
        }

        $prompt = $this->buildPrompt($shot, $styleKey);
        $apiKey = config('services.google_ai.api_key');
        $baseUrl = rtrim((string) config('services.google_ai.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $model = config('services.google_ai.model', 'imagen-3.0-generate-002');
        $timeout = (int) config('services.google_ai.timeout', 60);

        $imageBytes = null;

        // Si no hay API key configurada o estamos en entorno de testing sin fake HTTP explícito
        if (empty($apiKey)) {
            if (app()->environment('testing', 'local')) {
                Log::warning('StoryboardService: Sin GOOGLE_AI_API_KEY. Empleando viñeta de reserva para testing/local.');
                $imageBytes = $this->getDummyJpegBytes();
            } else {
                throw new RuntimeException('No se ha configurado la variable GOOGLE_AI_API_KEY para generar viñetas de storyboard.');
            }
        } else {
            $isGenerateContent = str_starts_with($model, 'gemini') || str_contains($model, 'banana');

            if ($isGenerateContent) {
                $endpoint = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::timeout($timeout)->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ]);
            } else {
                $endpoint = "{$baseUrl}/models/{$model}:predict?key={$apiKey}";
                $response = Http::timeout($timeout)->post($endpoint, [
                    'instances' => [
                        ['prompt' => $prompt],
                    ],
                    'parameters' => [
                        'sampleCount' => 1,
                        'aspectRatio' => '16:9',
                        'outputOptions' => [
                            'mimeType' => 'image/jpeg',
                        ],
                    ],
                ]);
            }

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                Log::error("StoryboardService: Error de Google AI Studio ({$response->status()}): {$errorMsg}");

                if ($response->status() === 402) {
                    throw new RuntimeException('Google AI Studio requiere créditos prepago o facturación activa en tu proyecto de Google Cloud (Error 402: Prepayment credits depleted). Revisa https://ai.studio/projects o la facturación de Google Cloud.');
                }

                throw new RuntimeException("Error en Google AI Studio ({$response->status()}): {$errorMsg}");
            }

            $base64 = $response->json('predictions.0.bytesBase64Encoded');
            $mimeType = 'image/jpeg';

            if (empty($base64)) {
                $parts = (array) ($response->json('candidates.0.content.parts') ?? []);
                foreach ($parts as $part) {
                    if (! empty($part['inlineData']['data'])) {
                        $base64 = $part['inlineData']['data'];
                        $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                        break;
                    }
                    if (! empty($part['inline_data']['data'])) {
                        $base64 = $part['inline_data']['data'];
                        $mimeType = $part['inline_data']['mime_type'] ?? 'image/png';
                        break;
                    }
                }
            }

            if (empty($base64)) {
                Log::error('StoryboardService: La API de Google AI no devolvió datos base64 en la respuesta.', [
                    'response' => $response->json(),
                ]);
                throw new RuntimeException('Google AI Studio no devolvió la imagen esperada.');
            }

            $imageBytes = base64_decode($base64);
        }

        if (empty($imageBytes)) {
            throw new RuntimeException('No se pudieron obtener los bytes de la imagen generada.');
        }

        // Eliminar viñeta previa si existía en disco
        if ($shot->storyboard_image_path && Storage::disk('public')->exists($shot->storyboard_image_path)) {
            Storage::disk('public')->delete($shot->storyboard_image_path);
        }

        // Guardar nuevo artefacto con su extensión correspondiente
        $ext = str_contains($mimeType, 'png') ? 'png' : 'jpg';
        $filename = "projects/{$projectId}/storyboards/shot_{$shot->id}_".time().".{$ext}";
        Storage::disk('public')->put($filename, $imageBytes);

        // Actualizar registro en base de datos
        $shot->update([
            'storyboard_image_path' => $filename,
            'storyboard_style' => $styleKey,
            'storyboard_prompt' => $prompt,
        ]);

        return $filename;
    }

    /**
     * Resuelve si el plano tiene un personaje asociado con turn-around / referencia para condicionar el prompt.
     */
    protected function resolveCharacterConditioning(Shot $shot): ?string
    {
        $project = $shot->scene?->project;
        if (! $project) {
            return null;
        }

        /** @var Collection<int, Character> $characters */
        $characters = $project->characters;
        if ($characters->isEmpty()) {
            return null;
        }

        // 1. Buscar si el nombre de algún personaje aparece en la descripción del plano o en el bloque de guion
        $searchContext = $shot->description.' '.($shot->scriptBlock?->screenplay_content ?? '').' '.($shot->scriptBlock?->raw_text ?? '');
        $matchedChar = $characters->first(function (Character $c) use ($searchContext) {
            return stripos($searchContext, $c->name) !== false;
        });

        if (! $matchedChar) {
            // 2. Fallback: si hay algún personaje con imagen de referencia en el proyecto
            $matchedChar = $characters->first(fn (Character $c) => $c->hasReferenceImage());
        }

        if (! $matchedChar) {
            return null;
        }

        $directives = ["Depict character '{$matchedChar->name}'"];
        if ($matchedChar->costume_notes) {
            $directives[] = "wearing {$matchedChar->costume_notes}";
        }
        if ($matchedChar->psychology) {
            $directives[] = "expression reflects {$matchedChar->psychology}";
        }
        if ($matchedChar->hasReferenceImage()) {
            $directives[] = 'strictly respecting the character turn-around model sheet reference with precise facial features, hairstyle and physique';
        }

        return implode(', ', $directives).'.';
    }

    /**
     * Retorna una imagen JPEG mínima válida (1x1 píxel) para pruebas offline sin agotar cuotas.
     */
    protected function getDummyJpegBytes(): string
    {
        return (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
    }
}
