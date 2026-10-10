<?php

namespace App\Livewire\Studio;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use Livewire\Attributes\On;
use Livewire\Component;

class ContextBibleDrawer extends Component
{
    public Project $project;

    public bool $isOpen = false;

    public string $activeTab = 'characters'; // 'characters' or 'locations'

    // Formulario para nuevo personaje
    public bool $showNewCharacterForm = false;

    public string $newCharName = '';

    public string $newCharBio = '';

    public string $newCharPsychology = '';

    public string $newCharCostume = '';

    // Formulario para nueva localización
    public bool $showNewLocationForm = false;

    public string $newLocName = '';

    public string $newLocType = 'INT';

    public string $newLocArtNotes = '';

    public string $newLocLightingNotes = '';

    public string $newLocAddress = '';

    // Edición inline de personajes y localizaciones
    public ?string $editingCharacterId = null;

    public string $editingCharCostume = '';

    public string $editingCharBio = '';

    public ?string $editingLocationId = null;

    public string $editingLocArtNotes = '';

    public string $editingLocLightingNotes = '';

    // Modal de advertencia y confirmación de borrado
    public ?string $deletingType = null; // 'character' o 'location'

    public ?string $deletingId = null;

    public ?string $deletingName = null;

    public array $deletingUsages = [];

    public bool $showDeleteModal = false;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[On('toggle-bible-drawer')]
    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    #[On('open-bible-drawer')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->showDeleteModal = false;
        $this->reset(['editingCharacterId', 'editingLocationId', 'showNewCharacterForm', 'showNewLocationForm', 'deletingType', 'deletingId', 'deletingName', 'deletingUsages']);
    }

    public function startEditCharacter(string $characterId): void
    {
        $char = Character::findOrFail($characterId);
        $this->editingCharacterId = $char->id;
        $this->editingCharCostume = $char->costume_notes ?? '';
        $this->editingCharBio = $char->bio ?? '';
    }

    public function saveCharacter(): void
    {
        if (! $this->editingCharacterId) {
            return;
        }

        $char = Character::findOrFail($this->editingCharacterId);
        $char->update([
            'costume_notes' => $this->editingCharCostume,
            'bio' => $this->editingCharBio,
        ]);

        $this->editingCharacterId = null;
        session()->flash('bible_success', "Personaje \"{$char->name}\" actualizado.");
    }

    public function createCharacter(): void
    {
        $this->validate([
            'newCharName' => 'required|min:2|max:80',
        ]);

        $this->project->characters()->create([
            'name' => trim($this->newCharName),
            'bio' => $this->newCharBio ?: null,
            'psychology' => $this->newCharPsychology ?: null,
            'costume_notes' => $this->newCharCostume ?: null,
        ]);

        $this->reset(['newCharName', 'newCharBio', 'newCharPsychology', 'newCharCostume', 'showNewCharacterForm']);
        session()->flash('bible_success', 'Nuevo personaje incorporado a la Biblia del proyecto.');
    }

    public function startEditLocation(string $locationId): void
    {
        $loc = Location::findOrFail($locationId);
        $this->editingLocationId = $loc->id;
        $this->editingLocArtNotes = $loc->art_department_notes ?? '';
        $this->editingLocLightingNotes = $loc->lighting_notes ?? '';
    }

    public function saveLocation(): void
    {
        if (! $this->editingLocationId) {
            return;
        }

        $loc = Location::findOrFail($this->editingLocationId);
        $loc->update([
            'art_department_notes' => $this->editingLocArtNotes,
            'lighting_notes' => $this->editingLocLightingNotes,
        ]);

        $this->editingLocationId = null;
        session()->flash('bible_success', "Localización \"{$loc->name}\" actualizada.");
    }

    public function createLocation(): void
    {
        $this->validate([
            'newLocName' => 'required|min:2|max:100',
            'newLocType' => 'required|in:INT,EXT,INT/EXT',
        ]);

        $this->project->locations()->create([
            'name' => strtoupper(trim($this->newLocName)),
            'type' => $this->newLocType,
            'art_department_notes' => $this->newLocArtNotes ?: null,
            'lighting_notes' => $this->newLocLightingNotes ?: null,
            'address_reference' => $this->newLocAddress ?: null,
        ]);

        $this->reset(['newLocName', 'newLocType', 'newLocArtNotes', 'newLocLightingNotes', 'newLocAddress', 'showNewLocationForm']);
        session()->flash('bible_success', 'Nueva localización incorporada a la Biblia del proyecto.');
    }

    /**
     * Solicita eliminar un personaje tras auditar deterministamente su uso en todo el guion.
     */
    public function requestDeleteCharacter(string $characterId): void
    {
        $char = $this->project->characters()->findOrFail($characterId);
        $charName = trim($char->name);

        $usages = [];
        $scenes = $this->project->scenes()->with('scriptBlocks')->orderBy('scene_order')->get();

        foreach ($scenes as $scene) {
            foreach ($scene->scriptBlocks as $block) {
                $texts = [
                    $block->screenplay_content ?? '',
                    $block->structured_text ?? '',
                    $block->raw_text ?? '',
                ];

                $found = false;
                foreach ($texts as $text) {
                    if (! empty($text) && preg_match('/\b'.preg_quote($charName, '/').'\b/iu', $text)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $usages[] = "ESC. {$scene->scene_order} · BLOQUE {$block->block_order}";
                }
            }
        }

        $this->deletingType = 'character';
        $this->deletingId = $char->id;
        $this->deletingName = $char->name;
        $this->deletingUsages = array_values(array_unique($usages));
        $this->showDeleteModal = true;
        $this->isOpen = true;
    }

    /**
     * Solicita eliminar una localización tras auditar deterministamente su uso en escenas y bloques.
     */
    public function requestDeleteLocation(string $locationId): void
    {
        $loc = $this->project->locations()->findOrFail($locationId);
        $locName = trim($loc->name);

        $usages = [];
        $scenes = $this->project->scenes()->with('scriptBlocks')->orderBy('scene_order')->get();

        foreach ($scenes as $scene) {
            if ($scene->location_id === $loc->id) {
                $usages[] = "ESC. {$scene->scene_order} (Asignada a la escena)";
            } elseif (! empty($scene->slugline) && preg_match('/\b'.preg_quote($locName, '/').'\b/iu', $scene->slugline)) {
                $usages[] = "ESC. {$scene->scene_order} (Encabezado de escena)";
            }

            foreach ($scene->scriptBlocks as $block) {
                $texts = [
                    $block->screenplay_content ?? '',
                    $block->structured_text ?? '',
                    $block->raw_text ?? '',
                ];

                $found = false;
                foreach ($texts as $text) {
                    if (! empty($text) && preg_match('/\b'.preg_quote($locName, '/').'\b/iu', $text)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $usages[] = "ESC. {$scene->scene_order} · BLOQUE {$block->block_order}";
                }
            }
        }

        $this->deletingType = 'location';
        $this->deletingId = $loc->id;
        $this->deletingName = $loc->name;
        $this->deletingUsages = array_values(array_unique($usages));
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
     * Confirma y ejecuta el borrado definitivo si el elemento no está en uso.
     */
    public function confirmDelete(): void
    {
        if (! empty($this->deletingUsages)) {
            session()->flash('bible_error', 'No se puede eliminar: el elemento está siendo utilizado en el guión.');
            $this->cancelDelete();

            return;
        }

        if ($this->deletingType === 'character' && $this->deletingId) {
            $char = $this->project->characters()->findOrFail($this->deletingId);
            $name = $char->name;
            $char->delete();
            session()->flash('bible_success', "Personaje \"{$name}\" eliminado correctamente de la Biblia.");
        } elseif ($this->deletingType === 'location' && $this->deletingId) {
            $loc = $this->project->locations()->findOrFail($this->deletingId);
            $name = $loc->name;
            $loc->delete();
            session()->flash('bible_success', "Localización \"{$name}\" eliminada correctamente de la Biblia.");
        }

        $this->cancelDelete();
        $this->dispatch('bible-updated');
    }

    public function render()
    {
        $characters = $this->project->characters()->latest()->get();
        $locations = $this->project->locations()->latest()->get();

        return view('livewire.studio.context-bible-drawer', [
            'characters' => $characters,
            'locations' => $locations,
        ]);
    }
}
