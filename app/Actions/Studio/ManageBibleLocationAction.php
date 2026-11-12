<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Location;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class ManageBibleLocationAction
{
    /**
     * Guarda la edición de una localización existente.
     *
     * @param  array{art_department_notes?: ?string, lighting_notes?: ?string}  $data
     */
    public function update(Location $location, array $data, ?UploadedFile $referenceImage = null): void
    {
        $updateData = [
            'art_department_notes' => $data['art_department_notes'] ?? $location->art_department_notes,
            'lighting_notes' => $data['lighting_notes'] ?? $location->lighting_notes,
        ];

        if ($referenceImage) {
            $this->deleteReferenceImage($location);
            $updateData['reference_image_path'] = $referenceImage->store("projects/{$location->project_id}/locations", 'public');
        }

        $location->update($updateData);
    }

    /**
     * Registra una nueva localización en el proyecto.
     *
     * @param  array{name: string, type: string, art_department_notes?: ?string, lighting_notes?: ?string, address_reference?: ?string}  $data
     */
    public function create(Project $project, array $data, ?UploadedFile $referenceImage = null): Location
    {
        $location = $project->locations()->create([
            'name' => strtoupper(trim($data['name'])),
            'type' => $data['type'],
            'art_department_notes' => $data['art_department_notes'] ?: null,
            'lighting_notes' => $data['lighting_notes'] ?: null,
            'address_reference' => $data['address_reference'] ?: null,
        ]);

        if ($referenceImage) {
            $path = $referenceImage->store("projects/{$project->id}/locations", 'public');
            $location->update(['reference_image_path' => $path]);
        }

        return $location;
    }

    /**
     * Elimina el archivo de imagen de referencia del almacenamiento público.
     */
    public function deleteReferenceImage(Location $location): void
    {
        if ($location->reference_image_path && Storage::disk('public')->exists($location->reference_image_path)) {
            Storage::disk('public')->delete($location->reference_image_path);
        }

        $location->update(['reference_image_path' => null]);
    }
}
