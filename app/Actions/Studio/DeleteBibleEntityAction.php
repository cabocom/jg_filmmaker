<?php

declare(strict_types=1);

namespace App\Actions\Studio;

use App\Models\Project;

final readonly class DeleteBibleEntityAction
{
    public function __construct(
        private AuditBibleEntityUsageAction $auditAction
    ) {}

    /**
     * Intenta eliminar una entidad de la biblia (personaje o localización) verificando que no tenga usos activos.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteIfUnused(Project $project, string $type, string $id): array
    {
        return match ($type) {
            'character' => $this->deleteCharacterIfUnused($project, $id),
            'location' => $this->deleteLocationIfUnused($project, $id),
            default => ['success' => false, 'message' => 'Tipo de elemento desconocido.'],
        };
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function deleteCharacterIfUnused(Project $project, string $id): array
    {
        $char = $project->characters()->findOrFail($id);
        if (! empty($this->auditAction->auditCharacterUsages($project, $char))) {
            return ['success' => false, 'message' => 'No se puede eliminar: el elemento está siendo utilizado en el guión.'];
        }

        $name = $char->name;
        $char->delete();

        return ['success' => true, 'message' => "Personaje \"{$name}\" eliminado correctamente de la Biblia."];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function deleteLocationIfUnused(Project $project, string $id): array
    {
        $loc = $project->locations()->findOrFail($id);
        if (! empty($this->auditAction->auditLocationUsages($project, $loc))) {
            return ['success' => false, 'message' => 'No se puede eliminar: el elemento está siendo utilizado en el guión.'];
        }

        $name = $loc->name;
        $loc->delete();

        return ['success' => true, 'message' => "Localización \"{$name}\" eliminada correctamente de la Biblia."];
    }

    /**
     * Forzado soberano de eliminación (HITL).
     */
    public function forceDelete(Project $project, string $type, string $id): string
    {
        if ($type === 'character') {
            $char = $project->characters()->findOrFail($id);
            $name = $char->name;
            $char->delete();

            return "Personaje \"{$name}\" eliminado de la Biblia.";
        }

        if ($type === 'location') {
            $loc = $project->locations()->findOrFail($id);
            $name = $loc->name;
            $loc->delete();

            return "Localización \"{$name}\" eliminada de la Biblia.";
        }

        return 'Elemento eliminado.';
    }
}
