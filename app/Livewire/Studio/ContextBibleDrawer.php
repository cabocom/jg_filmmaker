<?php

namespace App\Livewire\Studio;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Services\TextToSpeechService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    public string $newCharVoiceId = 'sharvard#M';

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

    public string $editingCharVoiceId = 'sharvard#M';

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
        $this->availableVoices = app(TextToSpeechService::class)->getAvailableVoices();
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
        $this->editingCharVoiceId = $custom['voice_id'] ?? 'sharvard#M';
    }

    public function saveCharacter(): void
    {
        if (! $this->editingCharacterId) {
            return;
        }

        $this->validate([
            'editingCharReferenceImage' => 'nullable|image|max:10240',
        ]);

        $char = $this->project->characters()->findOrFail($this->editingCharacterId);
        $custom = is_array($char->custom_attributes) ? $char->custom_attributes : [];
        $custom['voice_id'] = $this->editingCharVoiceId;

        $updateData = [
            'costume_notes' => $this->editingCharCostume,
            'bio' => $this->editingCharBio,
            'custom_attributes' => $custom,
        ];

        if ($this->editingCharReferenceImage) {
            if ($char->reference_image_path && Storage::disk('public')->exists($char->reference_image_path)) {
                Storage::disk('public')->delete($char->reference_image_path);
            }
            $updateData['reference_image_path'] = $this->editingCharReferenceImage->store("projects/{$this->project->id}/characters", 'public');
        }

        $char->update($updateData);

        $this->editingCharacterId = null;
        $this->editingCharReferenceImage = null;
        session()->flash('bible_success', "Personaje \"{$char->name}\" actualizado.");
    }

    public function deleteCharacterReferenceImage(string $characterId): void
    {
        $char = $this->project->characters()->findOrFail($characterId);
        if ($char->reference_image_path && Storage::disk('public')->exists($char->reference_image_path)) {
            Storage::disk('public')->delete($char->reference_image_path);
        }
        $char->update(['reference_image_path' => null]);
        session()->flash('bible_success', "Imagen de referencia de \"{$char->name}\" eliminada.");
    }

    public function createCharacter(): void
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
            'newCharReferenceImage' => 'nullable|image|max:10240',
        ], [
            'newCharName.required' => 'Debes indicar el nombre del personaje.',
            'newCharName.unique' => 'Ya existe un personaje registrado con este nombre en la Biblia del proyecto.',
        ]);

        $custom = [];
        if ($this->newCharVoiceId) {
            $custom['voice_id'] = $this->newCharVoiceId;
        }

        $character = $this->project->characters()->create([
            'name' => trim($this->newCharName),
            'bio' => $this->newCharBio ?: null,
            'psychology' => $this->newCharPsychology ?: null,
            'costume_notes' => $this->newCharCostume ?: null,
            'custom_attributes' => $custom,
        ]);

        if ($this->newCharReferenceImage) {
            $path = $this->newCharReferenceImage->store("projects/{$this->project->id}/characters", 'public');
            $character->update(['reference_image_path' => $path]);
        }

        $this->reset(['newCharName', 'newCharBio', 'newCharPsychology', 'newCharCostume', 'newCharVoiceId', 'newCharReferenceImage', 'showNewCharacterForm']);
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

    public function saveLocation(): void
    {
        if (! $this->editingLocationId) {
            return;
        }

        $this->validate([
            'editingLocArtNotes' => 'nullable|string|max:5000',
            'editingLocLightingNotes' => 'nullable|string|max:5000',
            'editingLocReferenceImage' => 'nullable|image|max:10240',
        ]);

        $loc = $this->project->locations()->findOrFail($this->editingLocationId);
        $updateData = [
            'art_department_notes' => $this->editingLocArtNotes,
            'lighting_notes' => $this->editingLocLightingNotes,
        ];

        if ($this->editingLocReferenceImage) {
            if ($loc->reference_image_path && Storage::disk('public')->exists($loc->reference_image_path)) {
                Storage::disk('public')->delete($loc->reference_image_path);
            }
            $updateData['reference_image_path'] = $this->editingLocReferenceImage->store("projects/{$this->project->id}/locations", 'public');
        }

        $loc->update($updateData);

        $this->editingLocationId = null;
        $this->editingLocReferenceImage = null;
        session()->flash('bible_success', "Localización \"{$loc->name}\" actualizada.");
    }

    public function deleteLocationReferenceImage(string $locationId): void
    {
        $loc = $this->project->locations()->findOrFail($locationId);
        if ($loc->reference_image_path && Storage::disk('public')->exists($loc->reference_image_path)) {
            Storage::disk('public')->delete($loc->reference_image_path);
        }
        $loc->update(['reference_image_path' => null]);
        $this->editingLocReferenceImage = null;
        session()->flash('bible_success', "Imagen de referencia de \"{$loc->name}\" eliminada.");
    }

    public function createLocation(): void
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
            'newLocReferenceImage' => 'nullable|image|max:10240',
        ], [
            'newLocName.required' => 'Debes indicar el nombre de la localización.',
            'newLocName.unique' => 'Ya existe una localización registrada con este nombre en la Biblia del proyecto.',
            'newLocType.in' => 'El tipo de localización debe ser INT, EXT o INT/EXT.',
        ]);

        $loc = $this->project->locations()->create([
            'name' => strtoupper(trim($this->newLocName)),
            'type' => $this->newLocType,
            'art_department_notes' => $this->newLocArtNotes ?: null,
            'lighting_notes' => $this->newLocLightingNotes ?: null,
            'address_reference' => $this->newLocAddress ?: null,
        ]);

        if ($this->newLocReferenceImage) {
            $path = $this->newLocReferenceImage->store("projects/{$this->project->id}/locations", 'public');
            $loc->update(['reference_image_path' => $path]);
        }

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

        return array_values(array_unique($usages));
    }

    /**
     * Audita deterministamente las apariciones de una localización en escenas y bloques.
     *
     * @return list<string>
     */
    private function auditLocationUsages(Location $loc): array
    {
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

        return array_values(array_unique($usages));
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
    public function confirmDelete(): void
    {
        if ($this->deletingType === 'character' && $this->deletingId) {
            $char = $this->project->characters()->findOrFail($this->deletingId);
            $serverUsages = $this->auditCharacterUsages($char);

            if (! empty($serverUsages)) {
                session()->flash('bible_error', 'No se puede eliminar: el elemento está siendo utilizado en el guión.');
                $this->cancelDelete();

                return;
            }

            $name = $char->name;
            $char->delete();
            session()->flash('bible_success', "Personaje \"{$name}\" eliminado correctamente de la Biblia.");
        } elseif ($this->deletingType === 'location' && $this->deletingId) {
            $loc = $this->project->locations()->findOrFail($this->deletingId);
            $serverUsages = $this->auditLocationUsages($loc);

            if (! empty($serverUsages)) {
                session()->flash('bible_error', 'No se puede eliminar: el elemento está siendo utilizado en el guión.');
                $this->cancelDelete();

                return;
            }

            $name = $loc->name;
            $loc->delete();
            session()->flash('bible_success', "Localización \"{$name}\" eliminada correctamente de la Biblia.");
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
