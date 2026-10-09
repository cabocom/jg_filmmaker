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
        $this->reset(['editingCharacterId', 'editingLocationId', 'showNewCharacterForm', 'showNewLocationForm']);
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
