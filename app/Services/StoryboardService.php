<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Location;
use App\Models\Shot;
use App\Services\JgAgente\AgentClientService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoryboardService
{
    public function __construct(
        protected ?AgentClientService $agentClient = null
    ) {
        $this->agentClient = $agentClient ?? app(AgentClientService::class);
    }

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
     * incorporando encuadre, escena, acción, estilo, localización, personaje y atrezzo con soporte para etiquetas @.
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
        $location = $this->resolveLocationModel($shot, $locationId);
        $locTag = $location ? '@'.preg_replace('/\s+/', '_', trim($location->name)) : '@localizacion';
        $locationDirective = $this->resolveLocationConditioning($shot, $locationId);

        // Comprobar si hay un personaje asociado al bloque de guion o mencionado en el plano
        $character = $this->resolveCharacterModel($shot, $characterId);
        $charTag = $character ? '@'.preg_replace('/\s+/', '_', trim($character->name)) : '@personaje';
        $characterDirective = $this->resolveCharacterConditioning($shot, $characterId);

        // Si la descripción menciona el nombre del personaje, referenciarlo con @
        if ($character && $character->name) {
            $description = (string) preg_replace('/\b'.preg_quote($character->name, '/').'\b/iu', $charTag, $description);
        }

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
            $propTags = array_map(fn (int $i) => "@Atrezzo{$i}", range(1, $propCount));
            $promptParts[] = 'Props & Objects: Incorporate and accurately depict '.implode(', ', $propTags).' from the provided visual reference images into the shot.';
        }

        $promptParts[] = "Artistic Style: {$styleData['modifier']}";

        return implode(' ', $promptParts);
    }

    /**
     * Genera la imagen de storyboard a través de jg_agente (jg_plugin_filmmaker),
     * admitiendo acondicionamiento multimodal (personaje, localización y hasta 3 objetos de atrezzo con etiquetas @),
     * almacena el artefacto en el disco público y actualiza el plano sin alterar su definición dramática.
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

        $character = $this->resolveCharacterModel($shot, $characterId);
        $charImgData = ($character && $character->hasReferenceImage())
            ? $this->getStorageImageData($character->reference_image_path)
            : null;

        $location = $this->resolveLocationModel($shot, $locationId);
        $locImgData = ($location && $location->hasReferenceImage())
            ? $this->getStorageImageData($location->reference_image_path)
            : null;

        $propsData = [];
        foreach ($propImagesSlice as $propItem) {
            $propData = $this->getUploadedOrImageData($propItem);
            if ($propData) {
                $propsData[] = $propData['data'];
            }
        }

        $payload = [
            'prompt' => $prompt,
            'style' => $styleKey,
            'character_name' => $character?->name,
            'character_image_base64' => $charImgData ? $charImgData['data'] : null,
            'location_name' => $location?->name,
            'location_image_base64' => $locImgData ? $locImgData['data'] : null,
            'prop_images_base64' => $propsData,
            'aspect_ratio' => '16:9',
        ];

        // Invocar la herramienta desacoplada en jg_agente / jg_plugin_filmmaker
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

        // Eliminar viñeta previa si existía en disco
        if ($shot->storyboard_image_path && Storage::disk('public')->exists($shot->storyboard_image_path)) {
            Storage::disk('public')->delete($shot->storyboard_image_path);
        }

        // Guardar nuevo artefacto organizado por escena: /projects/{id}/storyboards/scene_{id}/shot_{id}_{time}.ext
        $sceneId = $shot->scene_id ?? 'scene_default';
        $ext = str_contains($mimeType, 'png') ? 'png' : 'jpg';
        $filename = "projects/{$projectId}/storyboards/scene_{$sceneId}/shot_{$shot->id}_".time().".{$ext}";
        Storage::disk('public')->put($filename, $imageBytes);

        // Actualizar registro en base de datos (preservando intactos el shot_type y la descripción)
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
