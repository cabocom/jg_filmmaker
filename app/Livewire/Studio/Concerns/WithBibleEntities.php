<?php

namespace App\Livewire\Studio\Concerns;

use App\Actions\Studio\ComputeCharacterImportanceAction;
use App\Actions\Studio\ManageBibleCharacterAction;
use App\Actions\Studio\ManageBibleLocationAction;
use App\Models\Character;
use App\Services\TextToSpeechService;
use Illuminate\Validation\Rule;

trait WithBibleEntities
{
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
    /**
     * Calcula las intervenciones de diálogo por personaje en todo el proyecto.
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
}
