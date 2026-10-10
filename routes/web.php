<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Livewire\Admin\PricingManager;
use App\Livewire\Admin\SecurityLogsViewer;
use App\Livewire\Admin\UserDetail;
use App\Livewire\Admin\UserList;
use App\Livewire\Auth\Login;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Studio\ScriptEditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Rutas de Autenticación Passwordless (Estilo jg_hub)
Route::get('/login', Login::class)->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// Rutas Principales del Estudio Cinematográfico
Route::get('/', function () {
    return redirect()->route('projects.index');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/projects', ProjectList::class)->name('projects.index');
    Route::get('/projects/{project}', ScriptEditor::class)->name('projects.show');
});

// Panel de Administración (Protegido por Middleware EnsureUserIsAdmin)
Route::prefix('admin')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.users');
    })->name('admin.index');

    Route::get('/users', UserList::class)->name('admin.users');
    Route::get('/users/{user}', UserDetail::class)->name('admin.users.show');
    Route::get('/pricing', PricingManager::class)->name('admin.pricing');
    Route::get('/security', SecurityLogsViewer::class)->name('admin.security');
});
