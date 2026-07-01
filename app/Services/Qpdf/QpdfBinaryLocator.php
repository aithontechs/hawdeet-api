<?php

namespace App\Services\Qpdf;

class QpdfBinaryLocator
{
    public static function resolve(): ?string
    {
        $configured = config('services.qpdf.binary');
        if ($configured && file_exists($configured) && is_executable($configured)) {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $paths = [
                'C:\Program Files\qpdf\bin\qpdf.exe',
                'C:\Program Files (x86)\qpdf\bin\qpdf.exe',
                'C:\qpdf\bin\qpdf.exe',
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) return $path;
            }
            exec('where qpdf 2>NUL', $out, $code);
            if ($code === 0 && !empty($out[0])) return trim($out[0]);
        } else {
            $paths = [
                '/home/aithonon/bin/qpdf',
                '/usr/local/bin/qpdf',
                '/usr/bin/qpdf',
                '/bin/qpdf',
            ];
            foreach ($paths as $path) {
                if (file_exists($path) && is_executable($path)) return $path;
            }
            exec('which qpdf 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) return trim($out[0]);
        }

        return null;
    }


    public static function isEncrypted(string $qpdfBin, string $filePath): ?bool
    {
        exec(escapeshellarg($qpdfBin) . ' --is-encrypted ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);

        return match (true) {
            $exitCode === 0 => true,
            $exitCode === 2 => false,
            default         => null,
        };
    }
}
