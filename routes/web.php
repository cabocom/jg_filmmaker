<?php

use App\Livewire\Projects\ProjectList;
use App\Livewire\Studio\ScriptEditor;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('projects.index');
});

Route::get('/projects', ProjectList::class)->name('projects.index');
Route::get('/projects/{project}', ScriptEditor::class)->name('projects.show');
