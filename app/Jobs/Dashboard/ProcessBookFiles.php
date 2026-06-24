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

    // ✅ جلب مسار QPDF من config أو البحث عنه يدوياً
    private function getQpdfBinary(): ?string
    {
        // أولاً: من الـ .env عبر config
        $configured = config('services.qpdf.binary');
        if ($configured && file_exists($configured) && is_executable($configured)) {
            return $configured;
        }

        // ثانياً: مسارات شائعة على shared hosting
        $commonPaths = [
            '/home/aithonon/bin/qpdf',
            '/usr/local/bin/qpdf',
            '/usr/bin/qpdf',
            '/bin/qpdf',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // ثالثاً: محاولة which (قد تفشل في queue)
        exec('which qpdf 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out[0]) && file_exists(trim($out[0]))) {
            return trim($out[0]);
        }

        return null;
    }

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

            $qpdfBin = $this->getQpdfBinary();

            Log::info('ProcessBookFiles: qpdf binary resolved', [
                'qpdf_bin' => $qpdfBin ?? 'NOT FOUND',
            ]);

            $compatTmp   = $this->convertToCompatiblePdf($absolutePath, $qpdfBin);
            $workingPath = $compatTmp ?? $absolutePath;

            Log::info('ProcessBookFiles: using path', [
                'original' => $absolutePath,
                'compat'   => $compatTmp,
                'working'  => $workingPath,
            ]);

            $totalPages = $this->countPages($workingPath, $qpdfBin);
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

    private function countPages(string $absolutePath, ?string $qpdfBin): int
    {
        if ($qpdfBin) {
            exec(escapeshellarg($qpdfBin) . ' --show-npages ' . escapeshellarg($absolutePath) . ' 2>&1', $output, $exitCode);
            if ($exitCode === 0 && is_numeric(trim($output[0] ?? ''))) {
                Log::info('Page count via QPDF', ['count' => (int) trim($output[0])]);
                return (int) trim($output[0]);
            }
            Log::warning('QPDF --show-npages failed', [
                'exit_code' => $exitCode,
                'output'    => implode("\n", $output),
            ]);
        }

        try {
            $content = file_get_contents($absolutePath);
            preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
            if (count($matches[0]) > 0) {
                Log::info('Page count via regex', ['count' => count($matches[0])]);
                return count($matches[0]);
            }
        } catch (\Throwable $e) {
            Log::warning('Regex page count failed', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    private function convertToCompatiblePdf(string $absolutePath, ?string $qpdfBin): ?string
    {
        if (!$qpdfBin) {
            Log::warning('QPDF not found, skipping preprocessing');
            return null;
        }

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . Str::uuid() . '_compat.pdf';

        $cmd = implode(' ', [
            escapeshellarg($qpdfBin),
            '--object-streams=disable',
            escapeshellarg($absolutePath),
            escapeshellarg($outputPath),
            '2>&1',
        ]);

        exec($cmd, $output, $exitCode);

        Log::info('QPDF conversion attempt', [
            'cmd'       => $cmd,
            'exit_code' => $exitCode,
            'output'    => implode("\n", $output),
        ]);

        if ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
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
        try {
            return $this->generateWithFpdi($workingPath, $start, $end, $storage);
        } catch (\Throwable $e) {
            Log::error('FPDI preview failed', [
                'error' => $e->getMessage(),
                'path'  => $workingPath,
            ]);
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

    private function storePreview(string $tmpPath, StorageService $storage): string
    {
        $previewPath = 'books/previews/' . basename($tmpPath);
        $storage->put($previewPath, file_get_contents($tmpPath), StorageService::DISK_PRIVATE);
        @unlink($tmpPath);
        return $previewPath;
    }
}
