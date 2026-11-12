<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Smalot\PdfParser\Parser as PdfParser;

class ScriptFileExtractor
{
    public function extractTextFromFile(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = (string) $file->getMimeType();

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return $this->extractTextFromPdf($file->getRealPath());
        }

        if (in_array($extension, ['txt', 'fountain', 'text'], true) || str_contains($mimeType, 'text')) {
            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                throw new InvalidArgumentException('No se pudo leer el contenido del archivo de texto.');
            }

            return $this->normalizeEncoding($content);
        }

        throw new InvalidArgumentException("Formato de archivo no soportado (.{$extension}). Solo se admiten archivos .txt y .pdf.");
    }

    public function extractTextFromPdf(string $filePath): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            if (! empty(trim($text))) {
                return $this->normalizeEncoding($text);
            }
        } catch (\Throwable $e) {
            Log::warning('Smalot PdfParser falló al extraer texto: '.$e->getMessage());
        }

        $escapedPath = escapeshellarg($filePath);
        $output = @shell_exec("pdftotext {$escapedPath} - 2>/dev/null");
        if ($output && ! empty(trim($output))) {
            return $this->normalizeEncoding($output);
        }

        throw new InvalidArgumentException('No se pudo extraer texto legible del PDF. Asegúrate de que no sea un PDF escaneado como imagen o protegido con contraseña.');
    }

    public function normalizeEncoding(string $text): string
    {
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = (string) mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }
}
