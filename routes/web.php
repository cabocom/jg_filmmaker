<?php

declare(strict_types=1);

use App\Http\Controllers\AudioStreamController;
use App\Http\Controllers\Auth\IframeSsoController;
use App\Http\Controllers\MediaStreamController;
use App\Http\Controllers\ScriptExportController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Livewire\Admin\PricingManager;
use App\Livewire\Admin\ProjectManager;
use App\Livewire\Admin\SecurityLogsViewer;
use App\Livewire\Admin\UserDetail;
use App\Livewire\Admin\UserList;
use App\Livewire\Auth\Login;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Studio\ScriptEditor;
use App\Livewire\Tv\TvWorkspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Rutas de Autenticación Passwordless (Estilo jg_hub)
Route::get('/login', Login::class)->name('login');
Route::get('/auth/iframe-sso', IframeSsoController::class)
    ->middleware('throttle:30,1')
    ->name('auth.iframe-sso');

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
    Route::get('/tv', TvWorkspace::class)->name('tv.index');
    Route::get('/projects/{project}', ScriptEditor::class)
        ->middleware('can:view,project')
        ->name('projects.show');

    // Exportación del Guión Completo en PDF y Audio MP3
    Route::get('/projects/{project}/export-pdf', [ScriptExportController::class, 'exportPdf'])
        ->middleware('can:view,project')
        ->name('projects.export-pdf');
    Route::get('/projects/{project}/export-mp3', [AudioStreamController::class, 'exportMp3'])
        ->middleware(['can:view,project', 'throttle:15,1'])
        ->name('projects.export-mp3');
    Route::post('/projects/{project}/export-mp3/dispatch', [AudioStreamController::class, 'dispatchExportMp3'])
        ->middleware(['can:view,project', 'throttle:15,1'])
        ->name('projects.export-mp3.dispatch');
    Route::get('/projects/{project}/export-mp3/status', [AudioStreamController::class, 'exportMp3Status'])
        ->middleware('can:view,project')
        ->name('projects.export-mp3.status');

    // Síntesis de voz (TTS) y streaming de audio para lectura dramática
    Route::get('/projects/{project}/tts/manifest', [AudioStreamController::class, 'manifest'])
        ->middleware(['can:view,project', 'throttle:60,1'])
        ->name('projects.tts.manifest');
    Route::get('/projects/{project}/tts/stream', [AudioStreamController::class, 'streamAudio'])
        ->middleware(['can:view,project', 'throttle:120,1'])
        ->name('projects.tts.stream');
    Route::get('/projects/{project}/tts/demo-voice', [AudioStreamController::class, 'demoVoice'])
        ->middleware(['can:view,project', 'throttle:30,1'])
        ->name('projects.tts.demo-voice');

    // Streaming seguro de medios audiovisuales privados (ISSUE-006, ISSUE-003)
    Route::get('/media/takes/{take}', [MediaStreamController::class, 'streamTake'])->name('media.take');
    Route::get('/media/projects/{project}/renders/{filename}', [MediaStreamController::class, 'streamRender'])->name('media.render');
    Route::get('/media/shots/{shot}/storyboard', [MediaStreamController::class, 'streamStoryboard'])->name('media.storyboard');
    Route::get('/media/characters/{character}/reference', [MediaStreamController::class, 'streamCharacter'])->name('media.character');
    Route::get('/media/locations/{location}/reference', [MediaStreamController::class, 'streamLocation'])->name('media.location');
});

// Panel de Administración (Protegido por Middleware EnsureUserIsAdmin)
Route::prefix('admin')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.users');
    })->name('admin.index');

    Route::get('/users', UserList::class)->name('admin.users');
    Route::get('/users/{user}', UserDetail::class)->name('admin.users.show');
    Route::get('/projects', ProjectManager::class)->name('admin.projects');
    Route::get('/pricing', PricingManager::class)->name('admin.pricing');
    Route::get('/security', SecurityLogsViewer::class)->name('admin.security');
});
