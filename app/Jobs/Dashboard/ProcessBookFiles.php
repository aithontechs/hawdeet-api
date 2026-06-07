<?php

namespace App\Jobs\Dashboard;

use App\Models\Book;
use App\Services\Storage\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class ProcessBookFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(
        private int    $bookId,
        private string $tmpPath,
        private int    $previewStart,
        private int    $previewEnd,
    ) {}

    public function handle(StorageService $storage): void
    {
        $book = Book::findOrFail($this->bookId);

        if (!Storage::disk('local')->exists($this->tmpPath)) {
            Log::error('ProcessBookFiles: tmp file not found', [
                'book_id'  => $this->bookId,
                'tmp_path' => $this->tmpPath,
            ]);
            $book->update(['file_processed' => false]);
            return;
        }

        $compatTmp = null;

        try {
            $fileContents = Storage::disk('local')->get($this->tmpPath);
            $filePath     = 'books/files/' . Str::uuid() . '.pdf';
            $storage->put($filePath, $fileContents, StorageService::DISK_PRIVATE);
            $absolutePath = Storage::disk(StorageService::DISK_PRIVATE)->path($filePath);

            $compatTmp   = $this->convertToCompatiblePdf($absolutePath);
            $workingPath = $compatTmp ?? $absolutePath;

            Log::info('ProcessBookFiles: using path', [
                'original' => $absolutePath,
                'compat'   => $compatTmp,
                'working'  => $workingPath,
            ]);

            $totalPages = $this->countPages($workingPath);
            if ($totalPages === 0) {
                throw new \RuntimeException('Could not determine page count');
            }

            $start       = max(1, $this->previewStart);
            $end         = min($this->previewEnd, $totalPages);
            $previewPath = $this->generatePreview($workingPath, $start, $end, $storage);

            if ($compatTmp && file_exists($compatTmp)) {
                $storage->put($filePath, file_get_contents($compatTmp), StorageService::DISK_PRIVATE);
            }

            $book->update([
                'file'           => $filePath,
                'total_pages'    => $totalPages,
                'preview'        => $previewPath,
                'file_processed' => true,
            ]);

            Log::info('ProcessBookFiles: completed', [
                'book_id'     => $this->bookId,
                'total_pages' => $totalPages,
                'preview'     => $previewPath,
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessBookFiles failed', [
                'book_id' => $this->bookId,
                'error'   => $e->getMessage(),
            ]);
            $book->update(['file_processed' => false]);
            throw $e;

        } finally {
            Storage::disk('local')->delete($this->tmpPath);
            if ($compatTmp && file_exists($compatTmp)) {
                @unlink($compatTmp);
            }
        }
    }

    // private function countPages(string $absolutePath): int
    // {
    //     try {
    //         $parser = new Parser();
    //         $pdf    = $parser->parseFile($absolutePath);
    //         $pages  = $pdf->getPages();
    //         $count  = count($pages);

    //         if ($count > 0) {
    //             Log::info('Page count via smalot', ['count' => $count]);
    //             return $count;
    //         }
    //     } catch (\Throwable $e) {
    //         Log::warning('smalot/pdfparser failed', ['error' => $e->getMessage()]);
    //     }

    //     try {
    //         $content = file_get_contents($absolutePath);
    //         preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
    //         $count = count($matches[0]);

    //         if ($count > 0) {
    //             Log::info('Page count via regex', ['count' => $count]);
    //             return $count;
    //         }
    //     } catch (\Throwable $e) {
    //         Log::warning('Regex page count failed', ['error' => $e->getMessage()]);
    //     }

    //     return 0;
    // }
    private function countPages(string $absolutePath): int
    {
        // ١. QPDF (الأدق)
        $checkCmd = PHP_OS_FAMILY === 'Windows' ? 'where qpdf 2>NUL' : 'which qpdf 2>/dev/null';
        exec($checkCmd, $out, $code);

        if ($code === 0) {
            exec('qpdf --show-npages ' . escapeshellarg($absolutePath), $output, $exitCode);
            if ($exitCode === 0 && is_numeric(trim($output[0] ?? ''))) {
                return (int) trim($output[0]);
            }
        }

        // ٢. Regex كـ fallback
        try {
            $content = file_get_contents($absolutePath);
            preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
            if (count($matches[0]) > 0) return count($matches[0]);
        } catch (\Throwable $e) {
            Log::warning('Regex page count failed', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    private function convertToCompatiblePdf(string $absolutePath): ?string
    {
        $checkCmd = PHP_OS_FAMILY === 'Windows' ? 'where qpdf 2>NUL' : 'which qpdf 2>/dev/null';
        exec($checkCmd, $out, $code);

        if ($code !== 0) {
            Log::warning('QPDF not installed, skipping preprocessing');
            return null;
        }

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . Str::uuid() . '_compat.pdf';

        // ✅ object-streams=disable فقط — هو اللي FPDI محتاجه بدون تكبير الملف
        $cmd = implode(' ', [
            'qpdf',
            '--object-streams=disable',
            escapeshellarg($absolutePath),
            escapeshellarg($outputPath),
            '2>&1',
        ]);

        exec($cmd, $output, $exitCode);

        Log::info('QPDF conversion attempt', [
            'exit_code' => $exitCode,
            'output'    => implode("\n", $output),
        ]);

        if ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            Log::info('QPDF conversion successful', [
                'original_size' => filesize($absolutePath),
                'compat_size'   => filesize($outputPath),
            ]);
            return $outputPath;
        }

        if (file_exists($outputPath)) @unlink($outputPath);
        return null;
    }

    private function generatePreview(
        string         $workingPath,
        int            $start,
        int            $end,
        StorageService $storage
    ): ?string {
        // ✅ بكده FPDI هتشتغل لأن workingPath هو الـ compatible PDF
        try {
            return $this->generateWithFpdi($workingPath, $start, $end, $storage);
        } catch (\Throwable $e) {
            Log::error('FPDI preview failed even with compatible PDF', [
                'error' => $e->getMessage(),
                'path'  => $workingPath,
            ]);
            // ✅ مش بنعمل fallback للـ PDF الأصلي تاني
            throw $e;
        }
    }

    private function generateWithFpdi(
        string         $absolutePath,
        int            $start,
        int            $end,
        StorageService $storage
    ): string {
        $previewTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . Str::uuid() . '_preview.pdf';

        $pdf = new Fpdi();
        $pdf->setSourceFile($absolutePath);

        for ($i = $start; $i <= $end; $i++) {
            $tpl  = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage(
                $size['width'] > $size['height'] ? 'L' : 'P',
                [$size['width'], $size['height']]
            );
            $pdf->useTemplate($tpl);
        }

        $pdf->Output($previewTmp, 'F');

        return $this->storePreview($previewTmp, $storage);
    }

    private function copyAsPreview(string $absolutePath, StorageService $storage): string
    {
        $previewName = Str::uuid() . '_preview.pdf';
        $previewPath = 'books/previews/' . $previewName;
        $storage->put($previewPath, file_get_contents($absolutePath), StorageService::DISK_PRIVATE);
        return $previewPath;
    }

    private function storePreview(string $tmpPath, StorageService $storage): string
    {
        $previewPath = 'books/previews/' . basename($tmpPath);
        $storage->put($previewPath, file_get_contents($tmpPath), StorageService::DISK_PRIVATE);
        @unlink($tmpPath);
        return $previewPath;
    }

    private function commandExists(string $command): bool
    {
        $check = PHP_OS_FAMILY === 'Windows'
            ? "where {$command} 2>NUL"
            : "which {$command} 2>/dev/null";

        exec($check, $output, $exitCode);
        return $exitCode === 0;
    }

    private function countPagesViaQpdf(string $absolutePath): int
    {
        exec('qpdf --show-npages ' . escapeshellarg($absolutePath), $output, $exitCode);

        if ($exitCode === 0 && !empty($output[0]) && is_numeric(trim($output[0]))) {
            return (int) trim($output[0]);
        }

        return 0;
    }
}
