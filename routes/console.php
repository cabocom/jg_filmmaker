<?php

use App\Models\Take;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:prune-orphans', function () {
    $this->info('Iniciando reconciliación y purga de archivos multimedia huérfanos...');

    $disks = ['local', 'public'];
    $totalPruned = 0;

    foreach ($disks as $diskName) {
        $disk = Storage::disk($diskName);
        if (! $disk->exists('media/projects')) {
            continue;
        }

        $allFiles = $disk->allFiles('media/projects');
        foreach ($allFiles as $filePath) {
            // Ignorar renders temporales con menos de 2 horas
            if (str_contains($filePath, '/renders/')) {
                continue;
            }

            // Comprobar si existe alguna toma registrada con este file_path
            $takeExists = Take::where('file_path', $filePath)->exists();
            if (! $takeExists) {
                $disk->delete($filePath);
                $totalPruned++;
                $this->line("Huérfano eliminado [{$diskName}]: {$filePath}");
            }
        }
    }

    $this->info("Purga completada exitosamente. {$totalPruned} archivos huérfanos eliminados.");
})->purpose('Reconcilia y elimina archivos de vídeo huérfanos que no pertenecen a ninguna toma activa');

Schedule::command('tts:prune-orphans --days=7 --force')->weeklyOn(0, '03:00');

Schedule::command('tts:prune-orphans --days=7 --force')->weeklyOn(0, '03:00');
