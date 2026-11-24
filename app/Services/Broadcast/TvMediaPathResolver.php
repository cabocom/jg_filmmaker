<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class TvMediaPathResolver
{
    public function mediaRoot(): string
    {
        return rtrim(
            (string) config('services.filmmaker.tv_media_root', storage_path('tv_media')),
            DIRECTORY_SEPARATOR.'/'
        );
    }

    public function normalizeRelativePath(
        string $relativePath,
        bool $requireMp4Extension = false,
        bool $allowEmpty = false
    ): string {
        $this->validatePathCharacters($relativePath);

        $clean = str_replace('\\', '/', $relativePath);
        if (str_contains($clean, '//')) {
            throw new InvalidArgumentException('Invalid path: contains double slashes.');
        }

        $this->validateSegments($clean);

        if ($clean === '' && ! $allowEmpty) {
            throw new InvalidArgumentException('Path cannot be empty.');
        }

        if ($requireMp4Extension && ! str_ends_with(strtolower($clean), '.mp4')) {
            throw new InvalidArgumentException('Path must have a .mp4 extension.');
        }

        return $clean;
    }

    public function resolveAbsolutePath(
        string $relativePath,
        bool $requireMp4Extension = false,
        bool $allowEmpty = false
    ): string {
        $clean = $this->normalizeRelativePath($relativePath, $requireMp4Extension, $allowEmpty);
        $root = $this->mediaRoot();
        File::ensureDirectoryExists($root);

        $realRoot = realpath($root);
        if ($realRoot === false) {
            throw new InvalidArgumentException("Media root directory cannot be resolved: {$root}");
        }

        if ($clean === '') {
            return $realRoot;
        }

        $fullPath = $realRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $clean);
        $this->assertWithinRoot($fullPath, $realRoot, $relativePath);

        return $fullPath;
    }

    public function toRelativePath(string $absolutePath): string
    {
        $root = str_replace('\\', '/', $this->mediaRoot());
        $path = str_replace('\\', '/', $absolutePath);

        $realRoot = realpath($this->mediaRoot());
        if ($realRoot !== false) {
            $normalizedRealRoot = str_replace('\\', '/', $realRoot);
            if (str_starts_with($path, $normalizedRealRoot)) {
                return ltrim(substr($path, strlen($normalizedRealRoot)), '/');
            }
        }

        if (str_starts_with($path, $root)) {
            return ltrim(substr($path, strlen($root)), '/');
        }

        throw new InvalidArgumentException("Path is not inside media root: {$absolutePath}");
    }

    private function validatePathCharacters(string $path): void
    {
        if (str_contains($path, "\0") || str_contains($path, '://')) {
            throw new InvalidArgumentException('Invalid path: contains illegal sequences.');
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new InvalidArgumentException('Invalid path: absolute paths are not allowed.');
        }
    }

    private function validateSegments(string $clean): void
    {
        $segments = explode('/', $clean);
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Invalid path: directory traversal is not allowed.');
            }
        }
    }

    private function assertWithinRoot(string $fullPath, string $realRoot, string $originalRelative): void
    {
        $checkPath = $fullPath;
        while (! file_exists($checkPath)) {
            $parent = dirname($checkPath);
            if ($parent === $checkPath) {
                break;
            }
            $checkPath = $parent;
        }

        $realAncestor = realpath($checkPath);
        $rootPrefix = $realRoot.DIRECTORY_SEPARATOR;

        if ($realAncestor === false || ($realAncestor !== $realRoot && ! str_starts_with($realAncestor, $rootPrefix))) {
            throw new InvalidArgumentException("Path escapes media root: {$originalRelative}");
        }
    }
}
