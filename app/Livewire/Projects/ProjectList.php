<?php

namespace App\Livewire\Projects;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Mis Proyectos Cinematográficos'])]
class ProjectList extends Component
{
    public bool $showCreateModal = false;

    #[Rule('required|min:3|max:120')]
    public string $title = '';

    #[Rule('nullable|max:1000')]
    public string $synopsis = '';

    public string $status = 'draft';

    public function createProject(): void
    {
        $this->validate();

        $user = Auth::user();

        // Validar límite de cuota
        $currentCount = $user->projects()->count();
        $allowedSlots = $user->paid_project_slots ?? 5;

        if ($currentCount >= $allowedSlots) {
            $this->addError('quota', "Has alcanzado el límite de proyectos permitidos ({$allowedSlots} slots en tu plan).");

            return;
        }

        $project = $user->projects()->create([
            'title' => $this->title,
            'synopsis' => $this->synopsis,
            'status' => $this->status,
        ]);

        $this->reset(['title', 'synopsis', 'status', 'showCreateModal']);

        session()->flash('success', "Proyecto \"{$project->title}\" creado con éxito.");

        $this->redirectRoute('projects.show', ['project' => $project->id]);
    }

    public function render()
    {
        $user = Auth::user();

        $projects = $user ? $user->projects()
            ->withCount(['scenes', 'characters', 'locations'])
            ->latest()
            ->get() : collect();

        return view('livewire.projects.project-list', [
            'projects' => $projects,
            'user' => $user,
        ]);
    }
}
