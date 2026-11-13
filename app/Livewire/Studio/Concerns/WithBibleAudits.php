<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\AuditBibleEntityUsageAction;
use App\Actions\Studio\DeleteBibleEntityAction;
use App\Models\Character;
use App\Models\Location;

trait WithBibleAudits
{
    /**
     * Audita deterministamente las apariciones de un personaje en escenas y bloques.
     *
     * @return list<string>
     */
    private function auditCharacterUsages(Character $char): array
    {
        return app(AuditBibleEntityUsageAction::class)->auditCharacterUsages($this->project, $char);
    }

    /**
     * Audita deterministamente las apariciones de una localización en escenas y bloques.
     *
     * @return list<string>
     */
    private function auditLocationUsages(Location $loc): array
    {
        return app(AuditBibleEntityUsageAction::class)->auditLocationUsages($this->project, $loc);
    }

    /**
     * Solicita eliminar un personaje tras auditar deterministamente su uso en todo el guion.
     */
    public function requestDeleteCharacter(string $characterId): void
    {
        $char = $this->project->characters()->findOrFail($characterId);

        $this->deletingType = 'character';
        $this->deletingId = $char->id;
        $this->deletingName = $char->name;
        $this->deletingUsages = $this->auditCharacterUsages($char);
        $this->showDeleteModal = true;
        $this->isOpen = true;
    }

    /**
     * Solicita eliminar una localización tras auditar deterministamente su uso en escenas y bloques.
     */
    public function requestDeleteLocation(string $locationId): void
    {
        $loc = $this->project->locations()->findOrFail($locationId);

        $this->deletingType = 'location';
        $this->deletingId = $loc->id;
        $this->deletingName = $loc->name;
        $this->deletingUsages = $this->auditLocationUsages($loc);
        $this->showDeleteModal = true;
        $this->isOpen = true;
    }

    /**
     * Cancela la solicitud de borrado y cierra el modal de confirmación, manteniendo abierta la biblia.
     */
    public function cancelDelete(): void
    {
        $this->reset(['deletingType', 'deletingId', 'deletingName', 'deletingUsages', 'showDeleteModal']);
        $this->isOpen = true;
    }

    /**
     * Confirma y ejecuta el borrado definitivo tras re-auditar en el servidor que no existan usos activos.
     */
    public function confirmDelete(?DeleteBibleEntityAction $action = null): void
    {
        if ($this->deletingType && $this->deletingId) {
            $result = ($action ?? app(DeleteBibleEntityAction::class))->deleteIfUnused(
                $this->project,
                $this->deletingType,
                $this->deletingId
            );

            if (! $result['success']) {
                session()->flash('bible_error', $result['message']);
                $this->cancelDelete();

                return;
            }

            session()->flash('bible_success', $result['message']);
        }

        $this->cancelDelete();
        $this->dispatch('bible-updated');
    }

    /**
     * Forzado soberano del director (HITL): elimina el elemento de la Biblia de todos modos.
     */
    public function forceDelete(?DeleteBibleEntityAction $action = null): void
    {
        if ($this->deletingType && $this->deletingId) {
            $msg = ($action ?? app(DeleteBibleEntityAction::class))->forceDelete(
                $this->project,
                $this->deletingType,
                $this->deletingId
            );
            session()->flash('bible_success', $msg);
        }

        $this->cancelDelete();
        $this->dispatch('bible-updated');
    }

    /**
     * Cierra el modal de confirmación de borrado y la biblia, y abre el drawer lateral con el detalle del bloque en ScriptEditor.
     */
    public function inspectUsage(string $usage): void
    {
        $blockId = null;

        if (preg_match('/ESC\.\s*(\d+)\s*·\s*BLOQUE\s*(\d+)/iu', $usage, $m)) {
            $sceneOrder = (int) $m[1];
            $blockOrder = (int) $m[2];
            $scene = $this->project->scenes()->where('scene_order', $sceneOrder)->first();
            if ($scene) {
                $block = $scene->scriptBlocks()->where('block_order', $blockOrder)->first();
                $blockId = $block?->id;
            }
        } elseif (preg_match('/ESC\.\s*(\d+)/iu', $usage, $m)) {
            $sceneOrder = (int) $m[1];
            $scene = $this->project->scenes()->where('scene_order', $sceneOrder)->first();
            $blockId = $scene?->scriptBlocks()->orderBy('block_order')->first()?->id;
        }

        if ($blockId) {
            $this->close();
            $this->dispatch('open-block-details', blockId: $blockId);
        }
    }
}
