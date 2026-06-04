<?php

namespace App\Jobs\Dashboard;

use App\Models\Book;
use App\Services\Storage\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class ProcessBookFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private  int    $bookId,
        private  string $tmpPath,      // المسار المؤقت local
        private  int    $previewStart,
        private  int    $previewEnd,
    ) {}

    public function handle(StorageService $storage): void
    {
        $book = Book::findOrFail($this->bookId);

        try {
            // 1. ارفع الـ PDF من tmp لـ private disk
            $fileContents = Storage::disk('local')->get($this->tmpPath);
            $fileName     = Str::uuid() . '.pdf';
            $filePath     = 'books/files/' . $fileName;
            $storage->put($filePath, $fileContents, StorageService::DISK_PRIVATE);

            $sourcePath = Storage::disk(StorageService::DISK_PRIVATE)->path($filePath);

            // 2. احسب الـ pages
            $totalPages = $this->countPages($sourcePath);

            // 3. اعمل preview
            $start       = max(1, $this->previewStart);
            $end         = min($this->previewEnd, $totalPages);
            $previewPath = $this->generatePreview($sourcePath, $start, $end, $storage);

            // 4. update الـ book
            $book->update([
                'file'           => $filePath,
                'total_pages'    => $totalPages,
                'preview'        => $previewPath,
                'file_processed' => true,
            ]);

        } catch (\Throwable $e) {
            logger()->error('ProcessBookFiles failed', [
                'book_id' => $this->bookId,
                'error'   => $e->getMessage(),
            ]);
            $book->update(['file_processed' => false]);
            throw $e;
        } finally {
            Storage::disk('local')->delete($this->tmpPath);
        }
    }

    private function countPages(string $sourcePath): int
    {
        // أولاً جرب FPDI
        try {
            $pdf = new Fpdi();
            return $pdf->setSourceFile($sourcePath);
        } catch (\Throwable $e) {
            logger()->warning('FPDI failed, falling back to ghostscript', [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: Ghostscript
        try {
            $output = [];
            exec(
                'gs -q -dNODISPLAY -dNOSAFER --permit-file-read=' . escapeshellarg($sourcePath) .
                ' -c "(' . addslashes($sourcePath) . ') (r) file runpdfbegin pdfpagecount = quit" 2>/dev/null',
                $output
            );

            if (!empty($output[0]) && is_numeric(trim($output[0]))) {
                return (int) trim($output[0]);
            }
        } catch (\Throwable $e) {
            logger()->warning('Ghostscript failed', ['error' => $e->getMessage()]);
        }

        // Fallback أخير: pdfinfo
        try {
            $output = [];
            exec('pdfinfo ' . escapeshellarg($sourcePath) . ' 2>/dev/null', $output);
            foreach ($output as $line) {
                if (str_starts_with($line, 'Pages:')) {
                    return (int) trim(str_replace('Pages:', '', $line));
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('pdfinfo failed', ['error' => $e->getMessage()]);
        }

        // مش قادر يحسب — رجع 0
        return 0;
    }

private function generatePreview(string $sourcePath, int $start, int $end, StorageService $storage): ?string
{
    // أولاً جرب FPDI
    try {
        return $this->generatePreviewWithFpdi($sourcePath, $start, $end, $storage);
    } catch (\Throwable $e) {
        logger()->warning('FPDI preview failed, falling back to ghostscript', [
            'error' => $e->getMessage()
        ]);
    }

    // Fallback: Ghostscript
    try {
        return $this->generatePreviewWithGhostscript($sourcePath, $start, $end, $storage);
    } catch (\Throwable $e) {
        logger()->error('Ghostscript preview also failed', ['error' => $e->getMessage()]);
        return null;
    }
}

private function generatePreviewWithFpdi(string $sourcePath, int $start, int $end, StorageService $storage): string
{
    $previewName = Str::uuid() . '_preview.pdf';
    $previewTmp  = sys_get_temp_dir() . '/' . $previewName;

    $pdf = new Fpdi();
    $pdf->setSourceFile($sourcePath);

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

    $previewPath = 'books/previews/' . $previewName;
    $storage->put($previewPath, file_get_contents($previewTmp), StorageService::DISK_PRIVATE);
    @unlink($previewTmp);

    return $previewPath;
}

    private function generatePreviewWithGhostscript(string $sourcePath, int $start, int $end, StorageService $storage): string
    {
        $previewName = Str::uuid() . '_preview.pdf';
        $previewTmp  = sys_get_temp_dir() . '/' . $previewName;

        $command = sprintf(
            'gs -dBATCH -dNOPAUSE -dNOSAFER -sDEVICE=pdfwrite -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>/dev/null',
            $start,
            $end,
            escapeshellarg($previewTmp),
            escapeshellarg($sourcePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($previewTmp)) {
            throw new \RuntimeException('Ghostscript preview generation failed');
        }

        $previewPath = 'books/previews/' . $previewName;
        $storage->put($previewPath, file_get_contents($previewTmp), StorageService::DISK_PRIVATE);
        @unlink($previewTmp);

        return $previewPath;
    }
}
