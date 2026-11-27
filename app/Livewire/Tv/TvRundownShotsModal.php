<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Actions\Tv\UpdateRundownShotsAction;
use App\Models\TvRundown;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

class TvRundownShotsModal extends Component
{
    public bool $isOpen = false;

    public ?string $rundownId = null;

    public string $rundownTitle = '';

    /** @var list<array{shot_id: string, shot_number: int, shot_type: string, description: string, oral_text: string, has_image: bool, storyboard_url: ?string}> */
    public array $shots = [];

    /** @var array<string, string> */
    public array $descriptions = [];

    /** @var array<string, string> */
    public array $oralTexts = [];

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function boot(): void
    {
        if (! (bool) Auth::user()?->is_admin) {
            abort(403);
        }
    }

    #[On('open-rundown-shots')]
    public function open(string $rundownId, UpdateRundownShotsAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        $rundown = TvRundown::query()->findOrFail($rundownId);

        $this->rundownId = (string) $rundown->id;
        $this->rundownTitle = (string) ($rundown->title ?? '');
        $this->shots = $action->loadEditableShots($rundown);
        $this->descriptions = [];
        $this->oralTexts = [];

        foreach ($this->shots as $shot) {
            $this->descriptions[$shot['shot_id']] = $shot['description'];
            $this->oralTexts[$shot['shot_id']] = $shot['oral_text'];
        }

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->rundownId = null;
        $this->rundownTitle = '';
        $this->shots = [];
        $this->descriptions = [];
        $this->oralTexts = [];
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }

    public function regenerateShot(string $shotId, UpdateRundownShotsAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        if (! $this->rundownId) {
            return;
        }

        try {
            $rundown = TvRundown::query()->findOrFail($this->rundownId);
            $newDescription = $this->descriptions[$shotId] ?? null;

            $action->regenerateSingleShotVisual($rundown, $shotId, $newDescription);

            $this->shots = $action->loadEditableShots($rundown->refresh());
            foreach ($this->shots as $shot) {
                if ($shot['shot_id'] === $shotId) {
                    $this->descriptions[$shotId] = $shot['description'];
                    break;
                }
            }

            $this->feedbackMessage = 'Viñeta regenerada correctamente.';
        } catch (Throwable $e) {
            $this->handleActionException($e, 'No se pudo regenerar la viñeta del shot.');
        }
    }

    public function saveTextsAndReassemble(UpdateRundownShotsAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        if (! $this->rundownId || $this->hasPendingDescriptionEdits()) {
            return;
        }

        try {
            $rundown = TvRundown::query()->findOrFail($this->rundownId);

            $changedOralTexts = [];
            foreach ($this->shots as $shot) {
                $shotId = $shot['shot_id'];
                $current = (string) ($this->oralTexts[$shotId] ?? '');
                if ($current !== (string) ($shot['oral_text'] ?? '')) {
                    $changedOralTexts[$shotId] = $current;
                }
            }

            if ($changedOralTexts !== []) {
                $action->updateOralTextsAndInvalidateAudio($rundown, $changedOralTexts);
                $rundown->refresh();
            }

            $action->reassembleAfterEdits($rundown);
            $this->close();
            $this->dispatch('rundown-reassembly-started');
        } catch (Throwable $e) {
            $this->handleActionException($e, 'No se pudo guardar y re-ensamblar la escaleta.');
        }
    }

    public function reassembleOnly(UpdateRundownShotsAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        if (! $this->rundownId || $this->hasPendingDescriptionEdits()) {
            return;
        }

        try {
            $rundown = TvRundown::query()->findOrFail($this->rundownId);
            $action->reassembleAfterEdits($rundown);
            $this->close();
            $this->dispatch('rundown-reassembly-started');
        } catch (Throwable $e) {
            $this->handleActionException($e, 'No se pudo iniciar el re-ensamblado de la escaleta.');
        }
    }

    public function render(): View
    {
        return view('livewire.tv.rundown-shots-modal');
    }

    private function hasPendingDescriptionEdits(): bool
    {
        foreach ($this->shots as $shot) {
            $shotId = $shot['shot_id'];
            $edited = trim((string) ($this->descriptions[$shotId] ?? ''));
            $original = trim((string) ($shot['description'] ?? ''));

            if ($edited !== $original) {
                $this->errorMessage = 'Has modificado la descripción visual de uno o más shots sin pulsar "Regenerar viñeta". Regenera cada viñeta modificada o revierte el texto antes de re-ensamblar.';

                return true;
            }
        }

        return false;
    }

    private function handleActionException(Throwable $e, string $fallbackMessage): void
    {
        if (($e instanceof InvalidArgumentException || $e instanceof RuntimeException) && ! $e instanceof QueryException) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        Log::error($fallbackMessage, [
            'rundown_id' => $this->rundownId,
            'exception' => $e->getMessage(),
        ]);
        $this->errorMessage = $fallbackMessage;
    }
}
