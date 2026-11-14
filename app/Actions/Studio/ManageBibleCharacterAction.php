<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Character;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class ManageBibleCharacterAction
{
    /**
     * Guarda la edición de un personaje existente.
     *
     * @param  array{bio?: string, costume_notes?: string, voice_id?: ?string, importance?: ?string}  $data
     */
    public function update(Character $character, array $data, ?UploadedFile $referenceImage = null): void
    {
        $custom = is_array($character->custom_attributes) ? $character->custom_attributes : [];
        $custom['voice_id'] = $data['voice_id'] ?? ($custom['voice_id'] ?? null);

        if (! empty($data['importance'])) {
            $custom['importance'] = $data['importance'];
        } else {
            unset($custom['importance']);
        }

        $updateData = [
            'costume_notes' => $data['costume_notes'] ?? $character->costume_notes,
            'bio' => $data['bio'] ?? $character->bio,
            'custom_attributes' => $custom,
        ];

        if ($referenceImage) {
            $this->deleteReferenceImage($character);
            $updateData['reference_image_path'] = $referenceImage->store("media/projects/{$character->project_id}/characters", 'local');
        }

        $character->update($updateData);
    }

    /**
     * Registra un nuevo personaje en el proyecto.
     *
     * @param  array{name: string, bio?: ?string, psychology?: ?string, costume_notes?: ?string, voice_id?: ?string, importance?: ?string}  $data
     */
    public function create(Project $project, array $data, ?UploadedFile $referenceImage = null): Character
    {
        $custom = [];
        if (! empty($data['voice_id'])) {
            $custom['voice_id'] = $data['voice_id'];
        }
        if (! empty($data['importance'])) {
            $custom['importance'] = $data['importance'];
        }

        $character = $project->characters()->create([
            'name' => trim($data['name']),
            'bio' => $data['bio'] ?: null,
            'psychology' => $data['psychology'] ?: null,
            'costume_notes' => $data['costume_notes'] ?: null,
            'custom_attributes' => $custom,
        ]);

        if ($referenceImage) {
            $path = $referenceImage->store("media/projects/{$project->id}/characters", 'local');
            $character->update(['reference_image_path' => $path]);
        }

        return $character;
    }

    /**
     * Elimina el archivo de imagen de referencia del almacenamiento local o público legado.
     */
    public function deleteReferenceImage(Character $character): void
    {
        if ($character->reference_image_path) {
            if (Storage::disk('local')->exists($character->reference_image_path)) {
                Storage::disk('local')->delete($character->reference_image_path);
            } elseif (Storage::disk('public')->exists($character->reference_image_path)) {
                Storage::disk('public')->delete($character->reference_image_path);
            }
        }

        $character->update(['reference_image_path' => null]);
    }
}
