<?php

declare(strict_types=1);

namespace App\Actions\Broadcast;

use App\Services\Broadcast\TvMediaPathResolver;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamTvMediaAction
{
    public function __construct(private readonly TvMediaPathResolver $pathResolver) {}

    public function execute(string $relativePath, ?string $rangeHeader = null): Response|StreamedResponse
    {
        $fullPath = $this->pathResolver->resolveAbsolutePath($relativePath, requireMp4Extension: true);

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            throw new RuntimeException("Media file not found: {$relativePath}", 404);
        }

        $size = (int) filesize($fullPath);

        if ($rangeHeader === null || trim($rangeHeader) === '') {
            return $this->streamFull($fullPath, $size);
        }

        return $this->streamRange($fullPath, $size, $rangeHeader);
    }

    private function streamFull(string $fullPath, int $size): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $size,
            'Cache-Control' => 'private, no-store',
        ];

        return response()->stream(function () use ($fullPath): void {
            $stream = fopen($fullPath, 'rb');
            if ($stream === false) {
                return;
            }

            try {
                while (! feof($stream) && ! connection_aborted()) {
                    $buffer = fread($stream, 65536);
                    if ($buffer === false || $buffer === '') {
                        break;
                    }
                    echo $buffer;
                    flush();
                }
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function streamRange(string $fullPath, int $size, string $rangeHeader): Response|StreamedResponse
    {
        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
            return response('', 416, ['Content-Range' => "bytes */{$size}"]);
        }

        $rawStart = $matches[1];
        $rawEnd = $matches[2];

        if ($rawStart === '' && $rawEnd === '') {
            return response('', 416, ['Content-Range' => "bytes */{$size}"]);
        }

        if ($rawStart === '') {
            $suffix = (int) $rawEnd;
            $start = max(0, $size - $suffix);
            $end = $size - 1;
        } elseif ($rawEnd === '') {
            $start = (int) $rawStart;
            $end = $size - 1;
        } else {
            $start = (int) $rawStart;
            $end = (int) $rawEnd;
        }

        if ($size <= 0 || $start > $end || $end >= $size) {
            return response('', 416, ['Content-Range' => "bytes */{$size}"]);
        }

        $length = ($end - $start) + 1;
        $headers = [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
            'Content-Range' => "bytes {$start}-{$end}/{$size}",
            'Content-Length' => (string) $length,
            'Cache-Control' => 'private, no-store',
        ];

        return response()->stream(function () use ($fullPath, $start, $length): void {
            $stream = fopen($fullPath, 'rb');
            if ($stream === false) {
                return;
            }

            try {
                fseek($stream, $start);
                $bytesRemaining = $length;

                while ($bytesRemaining > 0 && ! feof($stream) && ! connection_aborted()) {
                    $readLength = min(65536, $bytesRemaining);
                    $buffer = fread($stream, $readLength);
                    if ($buffer === false || $buffer === '') {
                        break;
                    }

                    echo $buffer;
                    flush();
                    $bytesRemaining -= strlen($buffer);
                }
            } finally {
                fclose($stream);
            }
        }, 206, $headers);
    }
}
