<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\ManageTvVisualStyleAction;
use App\Models\TvVisualStyle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class TvStylesPanel extends Component
{
    use WithFileUploads;

    public ?string $editingStyleId = null;

    public string $name = '';

    public string $promptBase = '';

    public string $characteristics = '';

    public mixed $referenceImage = null;

    public bool $isDefault = false;

    public bool $isActive = true;

    public bool $showForm = false;

    public function boot(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    public function mount(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    public function startCreate(): void
    {
        $this->resetValidation();
        $this->editingStyleId = null;
        $this->name = '';
        $this->promptBase = '';
        $this->characteristics = '';
        $this->referenceImage = null;
        $this->isDefault = false;
        $this->isActive = true;
        $this->showForm = true;
    }

    public function startEdit(string $id): void
    {
        $style = TvVisualStyle::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingStyleId = $style->id;
        $this->name = (string) $style->name;
        $this->promptBase = (string) $style->prompt_base;
        $this->characteristics = (string) ($style->characteristics ?? '');
        $this->referenceImage = null;
        $this->isDefault = (bool) $style->is_default;
        $this->isActive = (bool) $style->is_active;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetValidation();
        $this->editingStyleId = null;
        $this->referenceImage = null;
        $this->showForm = false;
    }

    public function saveStyle(ManageTvVisualStyleAction $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'promptBase' => ['required', 'string', 'max:4000'],
            'characteristics' => ['nullable', 'string', 'max:2000'],
            'referenceImage' => ['nullable', 'image', 'max:4096'],
            'isDefault' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        $style = $this->editingStyleId !== null
            ? TvVisualStyle::query()->findOrFail($this->editingStyleId)
            : null;

        $uploadedImage = $this->referenceImage instanceof UploadedFile ? $this->referenceImage : null;

        $action->save([
            'name' => $this->name,
            'prompt_base' => $this->promptBase,
            'characteristics' => trim($this->characteristics) !== '' ? trim($this->characteristics) : null,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
        ], $style, $uploadedImage);

        $wasEditing = $style !== null;
        $this->editingStyleId = null;
        $this->name = '';
        $this->promptBase = '';
        $this->characteristics = '';
        $this->referenceImage = null;
        $this->isDefault = false;
        $this->isActive = true;
        $this->showForm = false;

        session()->flash('success', $wasEditing ? 'Estilo visual actualizado correctamente.' : 'Estilo visual creado correctamente.');
    }

    public function setDefault(string $id, ManageTvVisualStyleAction $action): void
    {
        $style = TvVisualStyle::query()->findOrFail($id);
        $action->setDefault($style);

        session()->flash('success', "'{$style->name}' establecido como estilo visual por defecto.");
    }

    public function deleteStyle(string $id, ManageTvVisualStyleAction $action): void
    {
        $style = TvVisualStyle::query()->findOrFail($id);
        $name = $style->name;
        $action->delete($style);

        if ($this->editingStyleId === $id) {
            $this->cancelForm();
        }

        session()->flash('success', "Estilo '{$name}' eliminado.");
    }

    public function removeReferenceImage(string $id): void
    {
        $style = TvVisualStyle::query()->findOrFail($id);
        $imagePath = $style->reference_image_path;

        if (is_string($imagePath) && $imagePath !== '' && Storage::disk('local')->exists($imagePath)) {
            Storage::disk('local')->delete($imagePath);
        }

        $style->update(['reference_image_path' => null]);

        session()->flash('success', 'Imagen de referencia eliminada.');
    }

    public function render(ManageTvVisualStyleAction $action): View
    {
        $editingStyle = $this->editingStyleId !== null
            ? TvVisualStyle::query()->find($this->editingStyleId)
            : null;

        return view('livewire.tv.styles-panel', [
            'styles' => $action->listAll(),
            'editingStyle' => $editingStyle,
        ]);
    }
}
