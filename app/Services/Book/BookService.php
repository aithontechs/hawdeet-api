<?php

namespace App\Services\Book;

use App\Jobs\Dashboard\ProcessBookFiles;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Notifications\BookPublishedNotification;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class BookService
{
    const COVER_FOLDER   = 'books/covers';
    const FILE_FOLDER    = 'books/files';
    const PREVIEW_FOLDER = 'books/previews';

    public function __construct(private readonly StorageService $storage) {}

    public function create(array $data, UploadedFile $coverFile, ?UploadedFile $bookFile): Book
    {
        return DB::transaction(function () use ($data, $coverFile, $bookFile) {
            $user = User::findOrFail($data['author_id']);
            abort_if(!$user->is_author, 403, 'This user is not an author.');

            $type = $data['type'] ?? 'digital';

            $data['cover']          = $this->storage->upload($coverFile, self::COVER_FOLDER, StorageService::DISK_PUBLIC);
            $data['slug']           = $data['slug'] ?? Str::slug($data['title']);
            $data['uploaded_by']    = auth()->id();
            $data['file_processed'] = false;

            $previewStart = $data['preview_start_page'] ?? 1;
            $previewEnd   = $data['preview_end_page'] ?? 10;

            $tmpPath = null;
            if (in_array($type, ['digital', 'both'])) {
                abort_unless($bookFile, 422, 'Book file is required for digital books.');

                $tmpPath = $bookFile->store('pending_books', 'local');
            }

            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids'], $data['preview_start_page'], $data['preview_end_page']);

            $book = Book::create($data);

            if (!empty($categoryIds)) {
                $book->categories()->sync($categoryIds);
            }

            if ($tmpPath) {
                ProcessBookFiles::dispatch(
                    $book->id,
                    $tmpPath,
                    $previewStart,
                    $previewEnd,
                )->afterCommit();
            }

            return $book->load('categories');
        });
    }

    public function update(Book $book, array $data, ?UploadedFile $coverFile, ?UploadedFile $bookFile)
    {
        return DB::transaction(function () use ($book, $data, $coverFile, $bookFile) {
            if (!empty($data['author_id'])) {
                $user = User::findOrFail($data['author_id']);
                abort_if(!$user->is_author, 403, 'This user is not an author.');
            }

            $data = collect($data)->except([
                'total_pages',
                'file_processed',
                'avg_rating',
                'reviews_count',
                'published',
                'published_at',
            ])->toArray();

            $type = $data['type'] ?? $book->type;

            $isLockedFromFileEdits = $book->published && in_array($book->type, ['digital', 'both']);

            if ($isLockedFromFileEdits) {
                abort_if($coverFile, 403, 'Cannot change the cover of a published digital book.');
                abort_if($bookFile, 403, 'Cannot change the file of a published digital book.');
            }

            if ($coverFile) {
                $data['cover'] = $this->storage->replace(
                    $coverFile, $book->cover, self::COVER_FOLDER, StorageService::DISK_PUBLIC
                );
            }

            $previewStart = $data['preview_start_page'] ?? 1;
            $previewEnd   = $data['preview_end_page']   ?? 10;
            $tmpPath      = null;

            if (in_array($type, ['digital', 'both'])) {
                if ($bookFile) {
                    $this->storage->deleteMany([$book->file, $book->preview], StorageService::DISK_PRIVATE);

                    $tmpPath = $bookFile->store('pending_books', 'local');

                    $data['file_processed'] = false;
                    $data['file']        = null;
                    $data['preview']     = null;
                    $data['total_pages'] = 0;
                } else {
                    unset($data['file'], $data['preview']);
                }
            } elseif ($type === 'physical') {
                if ($book->file || $book->preview) {
                    $this->storage->deleteMany([$book->file, $book->preview], StorageService::DISK_PRIVATE);
                    $data['file']        = null;
                    $data['preview']     = null;
                    $data['total_pages'] = 0;
                }
                $data['price']         = 0;
                $data['compare_price'] = 0;
            }

            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids'], $data['preview_start_page'], $data['preview_end_page']);

            $book->update($data);

            if (!is_null($categoryIds)) {
                $book->categories()->sync($categoryIds);
            }

            if ($tmpPath) {
                ProcessBookFiles::dispatch(
                    $book->id,
                    $tmpPath,
                    $previewStart,
                    $previewEnd,
                )->afterCommit();
            }

            return $book->load('categories');
        });
    }

    public function publish(Book $book): Book
    {
        if(in_array($book->type, ['digital', 'both']) && (!$book->file)) {
            throw new \Exception('Digital books must have a file to be published , Confirm from file of book is be uploaded and processed successfully') ;
        }
        $book->update(['published' => true, 'published_at' => now()]);
        User::where('is_active', true)->whereNotNull('email_verified_at')->
            chunk(100, function ($users) use ($book) {
                Notification::send($users, new BookPublishedNotification($book));
        });
        return $book;
    }

    // public function unpublish(Book $book): Book
    // {
    //     $book->update(['published' => false]);
    //     return $book;
    // }

    public function delete(Book $book): void
    {
        DB::transaction(function () use ($book) {
            $this->storage->delete($book->cover, StorageService::DISK_PUBLIC);
            $this->storage->deleteMany([$book->file, $book->preview], StorageService::DISK_PRIVATE);
            $book->delete();
        });
    }

    public function streamBook(Book $book)
    {
        abort_unless($book->isDigital(), 403, 'This book has no digital version.');

        return $this->storage->streamResponse(
            $book->file,
            StorageService::DISK_PRIVATE,
            'inline',
            Str::slug($book->title) . '.pdf'
        );
    }

    public function streamPreview(Book $book)
    {
        abort_unless($book->preview, 404, 'No preview available.');

        return $this->storage->streamResponse(
            $book->preview,
            StorageService::DISK_PRIVATE,
            'inline',
            Str::slug($book->title) . '_preview.pdf'
        );
    }

    public function uploadCover(UploadedFile $file): string
    {
        return $this->storage->upload($file, self::COVER_FOLDER, StorageService::DISK_PUBLIC);
    }

    public function uploadBook(UploadedFile $file): string
    {
        return $this->storage->upload($file, self::FILE_FOLDER, StorageService::DISK_PRIVATE);
    }

    private function uploadDigitsBook(array $data, ?UploadedFile $bookFile): array
    {
        abort_unless($bookFile, 422, 'Book file is required for digital books.');

        $data['file'] = $this->storage->upload($bookFile, self::FILE_FOLDER, StorageService::DISK_PRIVATE);
        $sourcePath   = Storage::disk(StorageService::DISK_PRIVATE)->path($data['file']);

        $compatPath          = $this->preprocessPdfForFpdi($sourcePath);
        $pdf                 = new Fpdi();
        $data['total_pages'] = $pdf->setSourceFile($compatPath);

        abort_if($data['preview_start_page'] > $data['total_pages'], 422, 'Preview start page exceeds total pages.');
        abort_if($data['preview_end_page']   > $data['total_pages'], 422, 'Preview end page exceeds total pages.');

        $data['preview'] = $this->generatePreview(
            $data['file'],
            $data['preview_start_page'],
            $data['preview_end_page'],
            $compatPath
        );

        if ($compatPath !== $sourcePath) {
            @unlink($compatPath);
        }

        return $data;
    }

    private function generatePreview(string $storedFilePath,int $startPage  = 1,int $endPage    = 10,string $compatPath = null): ?string
    {
        try {
            $sourcePath = $compatPath
                ?? $this->preprocessPdfForFpdi(
                    Storage::disk(StorageService::DISK_PRIVATE)->path($storedFilePath)
                );

            $previewName = Str::uuid() . '_preview.pdf';
            $previewTmp  = sys_get_temp_dir() . '/' . $previewName;

            $pdf        = new Fpdi();
            $totalPages = $pdf->setSourceFile($sourcePath);
            $startPage  = max(1, $startPage);
            $endPage    = min($endPage, $totalPages);

            for ($i = $startPage; $i <= $endPage; $i++) {
                $tpl  = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage(
                    $size['width'] > $size['height'] ? 'L' : 'P',
                    [$size['width'], $size['height']]
                );
                $pdf->useTemplate($tpl);
            }

            $pdf->Output($previewTmp, 'F');

            $previewPath = self::PREVIEW_FOLDER . '/' . $previewName;
            $this->storage->put($previewPath, file_get_contents($previewTmp), StorageService::DISK_PRIVATE);

            @unlink($previewTmp);

            return $previewPath;

        } catch (\Throwable $e) {
            logger()->error('Preview generation failed', [
                'path'  => $storedFilePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }


    private function preprocessPdfForFpdi(string $sourcePath): string
    {
        $outputPath = sys_get_temp_dir() . '/' . Str::uuid() . '_compat.pdf';

        $exitCode = 0;
        $output   = [];
        exec(
            "qpdf --object-streams=disable " . escapeshellarg($sourcePath) . " " . escapeshellarg($outputPath) . " 2>&1",
            $output,
            $exitCode
        );

        if ($exitCode !== 0 || !file_exists($outputPath)) {
            logger()->warning('QPDF preprocessing failed, using original', [
                'path'   => $sourcePath,
                'output' => implode("\n", $output),
            ]);
            return $sourcePath;
        }

        return $outputPath;
    }

    public function stats()
    {
        $books = Book::selectRaw("
            COUNT(*) as total_books,
            SUM(CASE WHEN published = 1 THEN 1 ELSE 0 END) as published_books,
            SUM(CASE WHEN published = 0 THEN 1 ELSE 0 END) as unpublished_books
        ")->first();

        return [
            'total_books'       => $books->total_books,
            'published_books'   => $books->published_books,
            'unpublished_books' => $books->unpublished_books,
            'total_sales'       => Order::where('payment_status', 'paid')->sum('total'),
        ];
    }
}
