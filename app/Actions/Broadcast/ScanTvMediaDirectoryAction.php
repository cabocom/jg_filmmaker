<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Models\TvMediaNormalization;
use App\Services\Broadcast\BroadcastProfileValidator;
use App\Services\Broadcast\TvMediaPathResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

final class ScanTvMediaDirectoryAction
{
    public function __construct(
        private readonly TvMediaPathResolver $pathResolver,
        private readonly BroadcastProfileValidator $validator,
    ) {}

    public function execute(string $relativeDir = '', string $profile = '720p30', bool $forceRefresh = false): array
    {
        $this->validator->assertValidProfile($profile);
        $cleanDir = $this->pathResolver->normalizeRelativePath($relativeDir, requireMp4Extension: false, allowEmpty: true);
        $absDir = $this->pathResolver->resolveAbsolutePath($cleanDir, requireMp4Extension: false, allowEmpty: true);

        if (! is_dir($absDir)) {
            return [
                'directory_exists' => false,
                'relative_dir' => $cleanDir,
                'profile' => $profile,
                'total' => 0,
                'compliant_count' => 0,
                'non_compliant_count' => 0,
                'files' => [],
            ];
        }

        return $this->scanDirectory($absDir, $cleanDir, $profile, $forceRefresh);
    }

    private function scanDirectory(string $absDir, string $cleanDir, string $profile, bool $forceRefresh): array
    {
        $mp4Files = array_filter(
            File::allFiles($absDir),
            fn (SplFileInfo $f): bool => str_ends_with(strtolower($f->getFilename()), '.mp4')
                && ! str_contains($f->getFilename(), '.normalizing.')
        );

        $activeNorms = TvMediaNormalization::query()
            ->whereIn('stage', ['queued', 'normalizing'])
            ->get()
            ->keyBy('relative_path');

        $files = [];
        $compliantCount = 0;
        $nonCompliantCount = 0;

        foreach ($mp4Files as $file) {
            $entry = $this->inspectFileEntry($file, $profile, $forceRefresh, $activeNorms);
            if ($entry['broadcast_compliant'] ?? false) {
                $compliantCount++;
            } else {
                $nonCompliantCount++;
            }
            $files[] = $entry;
        }

        usort($files, fn (array $a, array $b): int => strcmp($a['relative_path'], $b['relative_path']));

        return [
            'directory_exists' => true,
            'relative_dir' => $cleanDir,
            'profile' => $profile,
            'total' => count($files),
            'compliant_count' => $compliantCount,
            'non_compliant_count' => $nonCompliantCount,
            'files' => $files,
        ];
    }

    /**
     * @param  Collection<string, TvMediaNormalization>  $activeNorms
     */
    private function inspectFileEntry(
        SplFileInfo $file,
        string $profile,
        bool $forceRefresh,
        Collection $activeNorms,
    ): array {
        $relPath = $this->pathResolver->toRelativePath($file->getPathname());
        $cacheKey = "tv_media_probe:{$profile}:{$relPath}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $probe = Cache::remember(
            $cacheKey,
            86400,
            fn (): array => $this->validator->inspectMp4($file->getPathname(), $profile)
        );

        $active = $activeNorms->get($relPath);

        return array_merge($probe, [
            'relative_path' => $relPath,
            'filename' => $file->getFilename(),
            'size_bytes' => $file->getSize(),
            'modified_at' => $file->getMTime(),
            'normalizing' => $active !== null,
            'normalization_stage' => $active?->stage,
            'normalization_job_id' => $active?->id,
        ]);
    }
}
