<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Location;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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
     * incorporando encuadre, escena, acción, estilo, localización, personaje y atrezzo.
     */
    public function buildPrompt(
        Shot $shot,
        ?string $style = null,
        ?string $characterId = null,
        ?string $locationId = null,
        int $propCount = 0
    ): string {
        $shot->loadMissing(['scene.project.characters', 'scene.project.locations', 'scene.location', 'scriptBlock']);

        $styleKey = $style ?? $shot->scene?->project?->storyboard_style ?? 'grayscale_sketch';
        $styleData = self::STYLES[$styleKey] ?? self::STYLES['grayscale_sketch'];

        $framing = $shot->shot_type ?: 'Medium Shot';
        $sceneHeader = $shot->scene ? "ESCENA {$shot->scene->scene_order}: {$shot->scene->slugline}" : 'SCENE';
        $description = trim((string) $shot->description) ?: 'Cinematic composition depicting the scene action.';

        // Comprobar si hay una localización asociada a la escena o en la Biblia
        $locationDirective = $this->resolveLocationConditioning($shot, $locationId);

        // Comprobar si hay un personaje asociado al bloque de guion o mencionado en el plano
        $characterDirective = $this->resolveCharacterConditioning($shot, $characterId);

        $promptParts = [
            'Cinematic film storyboard panel, aspect ratio 16:9.',
            "Camera Framing: {$framing}.",
            "Scene Setting: {$sceneHeader}.",
        ];

        if ($locationDirective) {
            $promptParts[] = "Location & Scenography: {$locationDirective}";
        }

        $promptParts[] = "Action & Composition: {$description}.";

        if ($characterDirective) {
            $promptParts[] = "Character Consistency: {$characterDirective}";
        }

        if ($propCount > 0) {
            $promptParts[] = "Props & Objects: Incorporate and accurately depict the {$propCount} referenced prop item(s) from the provided visual reference images into the shot.";
        }

        $promptParts[] = "Artistic Style: {$styleData['modifier']}";

        return implode(' ', $promptParts);
    }

    /**
     * Genera la imagen de storyboard a través de la API de Google AI Studio,
     * admitiendo acondicionamiento multimodal (personaje, localización y hasta 3 objetos de atrezzo),
     * almacena el artefacto en el disco público y actualiza el plano.
     *
     * @param  array<int, mixed>  $propImages
     *
     * @throws RuntimeException
     */
    public function generateStoryboard(
        Shot $shot,
        ?string $style = null,
        ?string $customPrompt = null,
        ?string $characterId = null,
        ?string $locationId = null,
        array $propImages = []
    ): string {
        $shot->loadMissing(['scene.project.characters', 'scene.project.locations']);
        $projectId = $shot->scene?->project_id ?? 'global';

        $styleKey = $style ?? $shot->scene?->project?->storyboard_style ?? 'grayscale_sketch';
        if (! array_key_exists($styleKey, self::STYLES)) {
            $styleKey = 'grayscale_sketch';
        }

        $propImagesSlice = array_slice($propImages, 0, 3);
        $prompt = ! empty(trim((string) $customPrompt))
            ? trim($customPrompt)
            : $this->buildPrompt($shot, $styleKey, $characterId, $locationId, count($propImagesSlice));

        $apiKey = config('services.google_ai.api_key');
        $baseUrl = rtrim((string) config('services.google_ai.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $model = config('services.google_ai.model', 'gemini-2.5-flash-image');
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
                $parts = [];

                // 1. Imagen de referencia del personaje si existe
                $character = $this->resolveCharacterModel($shot, $characterId);
                if ($character && $character->hasReferenceImage()) {
                    $charImgData = $this->getStorageImageData($character->reference_image_path);
                    if ($charImgData) {
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $charImgData['mime_type'],
                                'data' => $charImgData['data'],
                            ],
                        ];
                    }
                }

                // 2. Imagen de referencia de la localización si existe
                $location = $this->resolveLocationModel($shot, $locationId);
                if ($location && $location->hasReferenceImage()) {
                    $locImgData = $this->getStorageImageData($location->reference_image_path);
                    if ($locImgData) {
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $locImgData['mime_type'],
                                'data' => $locImgData['data'],
                            ],
                        ];
                    }
                }

                // 3. Imágenes de referencia de atrezzo / props (hasta 3)
                foreach ($propImagesSlice as $propItem) {
                    $propData = $this->getUploadedOrImageData($propItem);
                    if ($propData) {
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $propData['mime_type'],
                                'data' => $propData['data'],
                            ],
                        ];
                    }
                }

                // 4. Prompt descriptivo
                $parts[] = ['text' => $prompt];

                $endpoint = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::timeout($timeout)->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => $parts,
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
     * Resuelve el modelo de personaje asignado o detectado para el plano.
     */
    public function resolveCharacterModel(Shot $shot, ?string $characterId = null): ?Character
    {
        if ($characterId === 'none') {
            return null;
        }

        if (! empty($characterId)) {
            return Character::find($characterId);
        }

        $project = $shot->scene?->project;
        if (! $project) {
            return null;
        }

        /** @var Collection<int, Character> $characters */
        $characters = $project->characters;
        if ($characters->isEmpty()) {
            return null;
        }

        $searchContext = $shot->description.' '.($shot->scriptBlock?->screenplay_content ?? '').' '.($shot->scriptBlock?->raw_text ?? '');
        $matched = $characters->first(function (Character $c) use ($searchContext) {
            return stripos($searchContext, $c->name) !== false;
        });

        return $matched ?: $characters->first(fn (Character $c) => $c->hasReferenceImage());
    }

    /**
     * Resuelve el modelo de localización asignada o detectada para la escena del plano.
     */
    public function resolveLocationModel(Shot $shot, ?string $locationId = null): ?Location
    {
        if ($locationId === 'none') {
            return null;
        }

        if (! empty($locationId)) {
            return Location::find($locationId);
        }

        $scene = $shot->scene;
        if (! $scene) {
            return null;
        }

        $location = $scene->location;
        if (! $location && $scene->project) {
            /** @var Collection<int, Location> $locations */
            $locations = $scene->project->locations;

            return $locations->first(function (Location $loc) use ($scene) {
                return stripos((string) $scene->slugline, (string) $loc->name) !== false;
            });
        }

        return $location;
    }

    /**
     * Resuelve si el plano tiene un personaje asociado con turn-around / referencia para condicionar el prompt.
     */
    public function resolveCharacterConditioning(Shot $shot, ?string $characterId = null): ?string
    {
        $matchedChar = $this->resolveCharacterModel($shot, $characterId);

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
     * Resuelve si la escena tiene una localización asociada o descrita en la Biblia de producción.
     */
    public function resolveLocationConditioning(Shot $shot, ?string $locationId = null): ?string
    {
        $location = $this->resolveLocationModel($shot, $locationId);

        if (! $location) {
            return null;
        }

        $details = [];
        $locName = trim((string) $location->name);
        $locType = trim((string) ($location->type ?? ''));
        if ($locName !== '') {
            $details[] = "Environment: {$locName}".($locType !== '' ? " ({$locType})" : '');
        }
        if ($location->art_department_notes) {
            $details[] = "Set design & architectural dressing: {$location->art_department_notes}";
        }
        if ($location->lighting_notes) {
            $details[] = "Lighting setup & mood: {$location->lighting_notes}";
        }
        if ($location->address_reference) {
            $details[] = "Atmosphere reference: {$location->address_reference}";
        }
        if ($location->hasReferenceImage()) {
            $details[] = 'incorporating the architectural geometry, spatial layout and visual cues from the location reference image';
        }

        return ! empty($details) ? implode('. ', $details).'.' : null;
    }

    /**
     * Extrae datos en base64 de una imagen alojada en el disco público.
     *
     * @return array{mime_type: string, data: string}|null
     */
    protected function getStorageImageData(?string $path): ?array
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($path);
        if (empty($bytes)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

        return [
            'mime_type' => $mime,
            'data' => base64_encode($bytes),
        ];
    }

    /**
     * Extrae datos en base64 de un archivo subido o ruta de imagen.
     *
     * @return array{mime_type: string, data: string}|null
     */
    protected function getUploadedOrImageData(mixed $file): ?array
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath();
            if ($path && file_exists($path)) {
                $bytes = file_get_contents($path);
                if (! empty($bytes)) {
                    return [
                        'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                        'data' => base64_encode($bytes),
                    ];
                }
            }
        }

        if (is_string($file)) {
            if (Storage::disk('public')->exists($file)) {
                return $this->getStorageImageData($file);
            }
            if (file_exists($file)) {
                $bytes = file_get_contents($file);
                if (! empty($bytes)) {
                    $mime = mime_content_type($file) ?: 'image/jpeg';

                    return [
                        'mime_type' => $mime,
                        'data' => base64_encode($bytes),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Retorna una imagen JPEG mínima válida (1x1 píxel) para pruebas offline sin agotar cuotas.
     */
    protected function getDummyJpegBytes(): string
    {
        return (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
    }
}
