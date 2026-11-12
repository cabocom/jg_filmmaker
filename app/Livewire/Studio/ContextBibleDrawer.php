<?php

namespace App\Livewire\Studio;

use App\Actions\Studio\AuditBibleEntityUsageAction;
use App\Actions\Studio\ComputeCharacterImportanceAction;
use App\Actions\Studio\DeleteBibleEntityAction;
use App\Actions\Studio\ManageBibleCharacterAction;
use App\Actions\Studio\ManageBibleLocationAction;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContextBibleDrawer extends Component
{
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

    public function startEditCharacter(string $characterId): void
    {
        $char = $this->project->characters()->findOrFail($characterId);
        $this->editingCharacterId = $char->id;
        $this->editingCharCostume = $char->costume_notes ?? '';
        $this->editingCharBio = $char->bio ?? '';
        $this->editingCharReferenceImage = null;
        $custom = is_array($char->custom_attributes) ? $char->custom_attributes : [];
        $tts = app(TextToSpeechService::class);
        $this->editingCharVoiceId = $tts->resolveVoice($custom['voice_id'] ?? null);
        $this->editingCharImportance = (string) ($custom['importance'] ?? '');
    }

    public function saveCharacter(?ManageBibleCharacterAction $action = null): void
    {
        if (! $this->editingCharacterId) {
            return;
        }

        $this->validate([
            'editingCharReferenceImage' => 'nullable|mimes:jpeg,jpg,png,webp,heic,heif|max:10240',
        ]);

        $char = $this->project->characters()->findOrFail($this->editingCharacterId);
        ($action ?? app(ManageBibleCharacterAction::class))->update($char, [
            'costume_notes' => $this->editingCharCostume,
            'bio' => $this->editingCharBio,
            'voice_id' => $this->editingCharVoiceId,
            'importance' => $this->editingCharImportance,
        ], $this->editingCharReferenceImage);

        $this->editingCharacterId = null;
        $this->editingCharReferenceImage = null;
        $this->editingCharImportance = '';
        session()->flash('bible_success', "Personaje \"{$char->name}\" actualizado.");
    }

    public function deleteCharacterReferenceImage(string $characterId, ?ManageBibleCharacterAction $action = null): void
    {
        $char = $this->project->characters()->findOrFail($characterId);
        ($action ?? app(ManageBibleCharacterAction::class))->deleteReferenceImage($char);
        session()->flash('bible_success', "Imagen de referencia de \"{$char->name}\" eliminada.");
    }

    public function createCharacter(?ManageBibleCharacterAction $action = null): void
    {
        $this->validate([
            'newCharName' => [
                'required',
                'min:2',
                'max:80',
                Rule::unique('characters', 'name')->where('project_id', $this->project->id),
            ],
            'newCharBio' => 'nullable|string|max:5000',
            'newCharPsychology' => 'nullable|string|max:5000',
            'newCharCostume' => 'nullable|string|max:5000',
            'newCharVoiceId' => 'nullable|string|max:50',
            'newCharReferenceImage' => 'nullable|mimes:jpeg,jpg,png,webp,heic,heif|max:10240',
        ], [
            'newCharName.required' => 'Debes indicar el nombre del personaje.',
            'newCharName.unique' => 'Ya existe un personaje registrado con este nombre en la Biblia del proyecto.',
        ]);

        ($action ?? app(ManageBibleCharacterAction::class))->create($this->project, [
            'name' => $this->newCharName,
            'bio' => $this->newCharBio,
            'psychology' => $this->newCharPsychology,
            'costume_notes' => $this->newCharCostume,
            'voice_id' => $this->newCharVoiceId,
            'importance' => $this->newCharImportance,
        ], $this->newCharReferenceImage);

        $this->reset(['newCharName', 'newCharBio', 'newCharPsychology', 'newCharCostume', 'newCharVoiceId', 'newCharImportance', 'newCharReferenceImage', 'showNewCharacterForm']);
        session()->flash('bible_success', 'Nuevo personaje incorporado a la Biblia del proyecto.');
    }

    public function startEditLocation(string $locationId): void
    {
        $loc = $this->project->locations()->findOrFail($locationId);
        $this->editingLocationId = $loc->id;
        $this->editingLocArtNotes = $loc->art_department_notes ?? '';
        $this->editingLocLightingNotes = $loc->lighting_notes ?? '';
        $this->editingLocReferenceImage = null;
    }

    public function saveLocation(?ManageBibleLocationAction $action = null): void
    {
        if (! $this->editingLocationId) {
            return;
        }

        $this->validate([
            'editingLocArtNotes' => 'nullable|string|max:5000',
            'editingLocLightingNotes' => 'nullable|string|max:5000',
            'editingLocReferenceImage' => 'nullable|mimes:jpeg,jpg,png,webp,heic,heif|max:10240',
        ]);

        $loc = $this->project->locations()->findOrFail($this->editingLocationId);
        ($action ?? app(ManageBibleLocationAction::class))->update($loc, [
            'art_department_notes' => $this->editingLocArtNotes,
            'lighting_notes' => $this->editingLocLightingNotes,
        ], $this->editingLocReferenceImage);

        $this->editingLocationId = null;
        $this->editingLocReferenceImage = null;
        session()->flash('bible_success', "Localización \"{$loc->name}\" actualizada.");
    }

    public function deleteLocationReferenceImage(string $locationId, ?ManageBibleLocationAction $action = null): void
    {
        $loc = $this->project->locations()->findOrFail($locationId);
        ($action ?? app(ManageBibleLocationAction::class))->deleteReferenceImage($loc);
        $this->editingLocReferenceImage = null;
        session()->flash('bible_success', "Imagen de referencia de \"{$loc->name}\" eliminada.");
    }

    public function createLocation(?ManageBibleLocationAction $action = null): void
    {
        $this->validate([
            'newLocName' => [
                'required',
                'min:2',
                'max:100',
                Rule::unique('locations', 'name')->where('project_id', $this->project->id),
            ],
            'newLocType' => 'required|in:INT,EXT,INT/EXT',
            'newLocArtNotes' => 'nullable|string|max:5000',
            'newLocLightingNotes' => 'nullable|string|max:5000',
            'newLocAddress' => 'nullable|string|max:255',
            'newLocReferenceImage' => 'nullable|mimes:jpeg,jpg,png,webp,heic,heif|max:10240',
        ], [
            'newLocName.required' => 'Debes indicar el nombre de la localización.',
            'newLocName.unique' => 'Ya existe una localización registrada con este nombre en la Biblia del proyecto.',
            'newLocType.in' => 'El tipo de localización debe ser INT, EXT o INT/EXT.',
        ]);

        ($action ?? app(ManageBibleLocationAction::class))->create($this->project, [
            'name' => $this->newLocName,
            'type' => $this->newLocType,
            'art_department_notes' => $this->newLocArtNotes,
            'lighting_notes' => $this->newLocLightingNotes,
            'address_reference' => $this->newLocAddress,
        ], $this->newLocReferenceImage);

        $this->reset(['newLocName', 'newLocType', 'newLocArtNotes', 'newLocLightingNotes', 'newLocAddress', 'newLocReferenceImage', 'showNewLocationForm']);
        session()->flash('bible_success', 'Nueva localización incorporada a la Biblia del proyecto.');
    }

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

    /**
     * Calcula las intervenciones de diálogo por personaje en todo el proyecto.
     *
     * @return array<string, int>
     */
    public function computeCharacterInterventions(?ComputeCharacterImportanceAction $action = null): array
    {
        return ($action ?? app(ComputeCharacterImportanceAction::class))->computeInterventions($this->project);
    }

    /**
     * Resuelve el nivel de importancia del personaje (manual o automático según intervenciones).
     *
     * @param  array<string, int>  $counts
     * @return array{level: string, count: int, is_auto: bool}
     */
    public function resolveCharacterImportance(Character $character, array $counts, ?ComputeCharacterImportanceAction $action = null): array
    {
        return ($action ?? app(ComputeCharacterImportanceAction::class))->resolveImportance($character, $counts);
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
