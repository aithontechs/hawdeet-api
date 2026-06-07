<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait PdfCompatibilityTrait
{
    private function ensureCompatible(string $absolutePath): string
    {
        $checkCmd = PHP_OS_FAMILY === 'Windows' ? 'where qpdf 2>NUL' : 'which qpdf 2>/dev/null';
        exec($checkCmd, $out, $code);

        if ($code !== 0) {
            Log::warning('QPDF not found, using original file');
            return $absolutePath;
        }

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . Str::uuid() . '_compat.pdf';

        $cmd = implode(' ', [
            'qpdf',
            '--compress-streams=n',
            '--decode-level=all',
            '--stream-data=uncompress',
            '--object-streams=disable',
            escapeshellarg($absolutePath),
            escapeshellarg($outputPath),
            '2>&1',
        ]);

        exec($cmd, $output, $exitCode);

        if ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            return $outputPath;
        }

        if (file_exists($outputPath)) @unlink($outputPath);
        return $absolutePath;
    }

    // ✅ بدل stream، نقرأ الـ PDF في memory ونمسح الـ compat فوراً
    private function buildPagePdf(string $workingPath, callable $builder): string
    {
        try {
            return $builder($workingPath);
        } finally {
            // ✅ الـ builder رجع الـ string في memory — نمسح الـ tmp دلوقتي بأمان
            if (str_contains($workingPath, '_compat.pdf') && file_exists($workingPath)) {
                @unlink($workingPath);
            }
        }
    }

    private function cleanupCompat(string $workingPath, string $originalPath): void
    {
        if ($workingPath !== $originalPath && file_exists($workingPath)) {
            @unlink($workingPath);
        }
    }
}
