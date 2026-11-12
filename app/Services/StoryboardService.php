<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Location;
use App\Models\Shot;
use App\Services\JgAgente\AgentClientService;
use App\Services\Storyboard\StoryboardImageProcessor;
use App\Services\Storyboard\StoryboardPromptBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoryboardService
{
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

    protected StoryboardPromptBuilder $promptBuilder;

    protected StoryboardImageProcessor $imageProcessor;

    public function __construct(
        protected ?AgentClientService $agentClient = null,
        ?StoryboardPromptBuilder $promptBuilder = null,
        ?StoryboardImageProcessor $imageProcessor = null
    ) {
        $this->agentClient = $agentClient ?? app(AgentClientService::class);
        $this->promptBuilder = $promptBuilder ?? new StoryboardPromptBuilder(self::STYLES);
        $this->imageProcessor = $imageProcessor ?? new StoryboardImageProcessor;
    }

    /**
     * @return array<string, array{name: string, badge: string, description: string, modifier: string}>
     */
    public function getStyles(): array
    {
        return self::STYLES;
    }

    public function buildPrompt(
        Shot $shot,
        ?string $style = null,
        ?string $characterId = null,
        ?string $locationId = null,
        int $propCount = 0
    ): string {
        return $this->promptBuilder->buildPrompt($shot, $style, $characterId, $locationId, $propCount);
    }

    /**
     * @param  array<int, mixed>  $propImages
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

        $payload = $this->prepareAgentPayload($shot, $prompt, $styleKey, $characterId, $locationId, $propImagesSlice);
        $result = $this->agentClient->callTool('generate_storyboard', $payload);

        if (! ($result['success'] ?? false)) {
            $errorMsg = $result['error'] ?? 'Error desconocido al generar la viñeta con el agente.';
            Log::error("StoryboardService: Fallo en generate_storyboard: {$errorMsg}");
            throw new RuntimeException("Error en generación de storyboard: {$errorMsg}");
        }

        $base64 = $result['metadata']['image_base64'] ?? ($result['image_base64'] ?? null);
        $mimeType = $result['metadata']['mime_type'] ?? ($result['mime_type'] ?? 'image/png');

        if (empty($base64)) {
            Log::error('StoryboardService: El agente no devolvió datos base64 en la respuesta.');
            throw new RuntimeException('Google AI Studio no devolvió la imagen esperada a través del agente.');
        }

        $imageBytes = base64_decode($base64);
        if (empty($imageBytes)) {
            throw new RuntimeException('No se pudieron decodificar los bytes de la imagen generada.');
        }

        return $this->persistStoryboardImage($shot, $projectId, $imageBytes, $mimeType, $styleKey, $prompt);
    }

    protected function prepareAgentPayload(
        Shot $shot,
        string $prompt,
        string $styleKey,
        ?string $characterId,
        ?string $locationId,
        array $propImagesSlice
    ): array {
        $character = $this->resolveCharacterModel($shot, $characterId);
        $charImgData = ($character && $character->hasReferenceImage())
            ? $this->imageProcessor->getStorageImageData($character->reference_image_path)
            : null;

        $location = $this->resolveLocationModel($shot, $locationId);
        $locImgData = ($location && $location->hasReferenceImage())
            ? $this->imageProcessor->getStorageImageData($location->reference_image_path)
            : null;

        $propsData = [];
        foreach ($propImagesSlice as $propItem) {
            $propData = $this->imageProcessor->getUploadedOrImageData($propItem);
            if ($propData) {
                $propsData[] = $propData['data'];
            }
        }

        return [
            'prompt' => $prompt,
            'style' => $styleKey,
            'character_name' => $character?->name,
            'character_image_base64' => $charImgData ? $charImgData['data'] : null,
            'location_name' => $location?->name,
            'location_image_base64' => $locImgData ? $locImgData['data'] : null,
            'prop_images_base64' => $propsData,
            'aspect_ratio' => '16:9',
        ];
    }

    protected function persistStoryboardImage(
        Shot $shot,
        string $projectId,
        string $imageBytes,
        string $mimeType,
        string $styleKey,
        string $prompt
    ): string {
        if ($shot->storyboard_image_path && Storage::disk('public')->exists($shot->storyboard_image_path)) {
            Storage::disk('public')->delete($shot->storyboard_image_path);
        }

        $sceneId = $shot->scene_id ?? 'scene_default';
        $ext = str_contains($mimeType, 'png') ? 'png' : 'jpg';
        $filename = "projects/{$projectId}/storyboards/scene_{$sceneId}/shot_{$shot->id}_".time().".{$ext}";
        Storage::disk('public')->put($filename, $imageBytes);

        $shot->update([
            'storyboard_image_path' => $filename,
            'storyboard_style' => $styleKey,
            'storyboard_prompt' => $prompt,
        ]);

        return $filename;
    }

    public function resolveCharacterModel(Shot $shot, ?string $characterId = null): ?Character
    {
        return $this->promptBuilder->resolveCharacterModel($shot, $characterId);
    }

    public function resolveLocationModel(Shot $shot, ?string $locationId = null): ?Location
    {
        return $this->promptBuilder->resolveLocationModel($shot, $locationId);
    }

    public function resolveCharacterConditioning(Shot $shot, ?string $characterId = null): ?string
    {
        return $this->promptBuilder->resolveCharacterConditioning($shot, $characterId);
    }

    public function resolveLocationConditioning(Shot $shot, ?string $locationId = null): ?string
    {
        return $this->promptBuilder->resolveLocationConditioning($shot, $locationId);
    }

    public function getStorageImageData(?string $path): ?array
    {
        return $this->imageProcessor->getStorageImageData($path);
    }

    public function getUploadedOrImageData(mixed $file): ?array
    {
        return $this->imageProcessor->getUploadedOrImageData($file);
    }

    public function getDummyJpegBytes(): string
    {
        return $this->imageProcessor->getDummyJpegBytes();
    }
}
