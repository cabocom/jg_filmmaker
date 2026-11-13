<?php

namespace App\Livewire\Studio;

use App\Actions\Studio\ComputeCharacterImportanceAction;
use App\Livewire\Studio\Concerns\WithBibleAudits;
use App\Livewire\Studio\Concerns\WithBibleEntities;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContextBibleDrawer extends Component
{
    use WithBibleAudits;
    use WithBibleEntities;
    use WithFileUploads;

    #[Locked]
    public Project $project;

    public bool $isOpen = false;

    public string $activeTab = 'characters'; // 'characters' or 'locations'

    // Formulario para nuevo personaje
    public bool $showNewCharacterForm = false;

    public string $newCharName = '';

    public string $newCharBio = '';

    public string $newCharPsychology = '';

    public string $newCharCostume = '';

    public string $newCharVoiceId = 'Puck';

    public string $newCharImportance = '';

    public $newCharReferenceImage = null;

    // Formulario para nueva localización
    public bool $showNewLocationForm = false;

    public string $newLocName = '';

    public string $newLocType = 'INT';

    public string $newLocArtNotes = '';

    public string $newLocLightingNotes = '';

    public string $newLocAddress = '';

    public $newLocReferenceImage = null;

    // Edición inline de personajes y localizaciones
    public ?string $editingCharacterId = null;

    public string $editingCharCostume = '';

    public string $editingCharBio = '';

    public string $editingCharVoiceId = 'Puck';

    public string $editingCharImportance = '';

    public $editingCharReferenceImage = null;

    public array $availableVoices = [];

    public ?string $editingLocationId = null;

    public string $editingLocArtNotes = '';

    public string $editingLocLightingNotes = '';

    public $editingLocReferenceImage = null;

    // Modal de advertencia y confirmación de borrado
    #[Locked]
    public ?string $deletingType = null; // 'character' o 'location'

    #[Locked]
    public ?string $deletingId = null;

    #[Locked]
    public ?string $deletingName = null;

    #[Locked]
    public array $deletingUsages = [];

    public bool $showDeleteModal = false;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
        $tts = app(TextToSpeechService::class);
        $this->availableVoices = $tts->getAvailableVoices();
        $this->newCharVoiceId = $tts->getDefaultVoice();
        $this->editingCharVoiceId = $tts->getDefaultVoice();
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

    #[On('bible-updated')]
    public function refreshBible(): void
    {
        $this->project->refresh();
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->showDeleteModal = false;
        $this->reset(['editingCharacterId', 'editingLocationId', 'showNewCharacterForm', 'showNewLocationForm', 'deletingType', 'deletingId', 'deletingName', 'deletingUsages', 'newCharReferenceImage', 'editingCharReferenceImage', 'newLocReferenceImage', 'editingLocReferenceImage']);
    }

    public function render(?ComputeCharacterImportanceAction $action = null)
    {
        $characters = $this->project->characters()->latest()->get();
        $locations = $this->project->locations()->latest()->get();

        return view('livewire.studio.context-bible-drawer', [
            'characters' => $characters,
            'locations' => $locations,
            'characterInterventions' => $this->computeCharacterInterventions($action),
        ]);
    }
}
