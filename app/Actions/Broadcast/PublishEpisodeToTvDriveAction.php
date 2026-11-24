<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Models\BroadcastJob;
use App\Services\Broadcast\TvMediaPathResolver;
use Illuminate\Support\Facades\File;

final readonly class PublishEpisodeToTvDriveAction
{
    public function __construct(
        private TvMediaPathResolver $pathResolver
    ) {}

    public function publishIfConfigured(BroadcastJob $job, string $sourceMp4Path): ?string
    {
        $relPath = trim((string) (($job->artifacts['output_relative_path'] ?? '') ?: ''));
        if ($relPath === '') {
            return null;
        }

        $destAbs = $this->pathResolver->resolveAbsolutePath($relPath, requireMp4Extension: true);
        File::ensureDirectoryExists(dirname($destAbs));
        File::copy($sourceMp4Path, $destAbs);

        return $this->pathResolver->normalizeRelativePath($relPath, requireMp4Extension: true);
    }
}
