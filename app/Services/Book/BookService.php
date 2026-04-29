<?php

namespace App\Services\Book ;

use App\Models\Book;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class BookService
{
    const COVER_FOLDER   = 'books/covers';
    const FILE_FOLDER    = 'books/files';
    const PREVIEW_FOLDER = 'books/previews';
    const PREVIEW_PAGES  = 10;

    public function __construct(private readonly StorageService $storage) {}

    public function create(array $data, UploadedFile $coverFile, UploadedFile $bookFile)
    {
        return DB::transaction(function () use ($data, $coverFile, $bookFile) {
            $user = User::findorfail($data['author_id']) ;
            abort_if(! $user->is_author, 403, 'This user is not an author.');

            $data['cover']       = $this->storage->upload($coverFile, self::COVER_FOLDER, StorageService::DISK_PUBLIC);
            $data['file']        = $this->storage->upload($bookFile,  self::FILE_FOLDER,  StorageService::DISK_PRIVATE);
            $data['preview']     = $this->generatePreview($data['file'] , $data['preview_start_page'] , $data['preview_end_page']);
            $data['slug']        = $data['slug'] ?? Str::slug($data['title']);
            $data['uploaded_by'] = auth()->id();

            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids']);

            $book = Book::create($data);

            if (! empty($categoryIds)) {
                $book->categories()->sync($categoryIds);
            }

            return $book->load('categories');
        });
    }

    public function update(Book $book, array $data, ?UploadedFile $coverFile, ?UploadedFile $bookFile)
    {
        return DB::transaction(function () use ($book, $data, $coverFile, $bookFile) {
            if(!empty($data['author_id'])){
                $user = User::findorfail($data['author_id']) ;
                abort_if(! $user->is_author, 403, 'This user is not an author.');
            }

            if ($coverFile) {
                $data['cover'] = $this->storage->replace(
                    $coverFile, $book->cover, self::COVER_FOLDER, StorageService::DISK_PUBLIC
                );
            }

            if ($bookFile) {
                $this->storage->deleteMany([$book->file, $book->preview], StorageService::DISK_PRIVATE);
                $data['file']    = $this->storage->upload($bookFile, self::FILE_FOLDER, StorageService::DISK_PRIVATE);
                $data['preview'] = $this->generatePreview($data['file'] , $data['preview_start_page'] , $data['preview_end_page']);
            }

            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids']);

            $book->update($data);

            if (! is_null($categoryIds)) {
                $book->categories()->sync($categoryIds);
            }

            return $book->load('categories');
        });
    }

    public function publish(Book $book): Book
    {
        $book->update(['published' => true, 'published_at' => now()]);
        return $book;
    }

    public function unpublish(Book $book): Book
    {
        $book->update(['published' => false]);
        return $book;
    }

    public function delete(Book $book)
    {
        DB::transaction(function () use ($book) {
            $this->storage->delete($book->cover, StorageService::DISK_PUBLIC);
            $this->storage->deleteMany([$book->file, $book->preview], StorageService::DISK_PRIVATE);
            $book->delete();
        });
    }

    public function streamBook(Book $book)
    {
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

    private function generatePreview(string $storedFilePath, int $startPage = 1, int $endPage = 10)
    {
        try {
            $sourcePath  = Storage::disk(StorageService::DISK_PRIVATE)->path($storedFilePath);
            $previewName = Str::uuid() . '_preview.pdf';
            $previewTmp  = sys_get_temp_dir() . '/' . $previewName;

            $pdf = new Fpdi();
            $totalPages = $pdf->setSourceFile($sourcePath);

            $startPage = max(1, $startPage);
            $endPage   = min($endPage, $totalPages);

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

            $this->storage->put(
                $previewPath,
                file_get_contents($previewTmp),
                StorageService::DISK_PRIVATE
            );

            @unlink($previewTmp);

            return $previewPath;

        } catch (\Throwable $e) {
            logger()->error('Preview generation failed', [
                'path' => $storedFilePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function uploadCover(UploadedFile $file): string
    {
        return $this->storage->upload($file, self::COVER_FOLDER, StorageService::DISK_PUBLIC);
    }

    public function uploadBook(UploadedFile $file): string
    {
        return $this->storage->upload($file, self::FILE_FOLDER, StorageService::DISK_PRIVATE);
    }

    // دي هنستخدمها مع queue
    public function createFromPaths(array $data, string $coverPath, string $bookPath)
    {
        return DB::transaction(function () use ($data, $coverPath, $bookPath) {
            $user = User::findOrFail($data['author_id']);
            abort_if(!$user->is_author, 403, 'This user is not an author.');

            $data['cover']       = $coverPath;
            $data['file']        = $bookPath;
            $data['preview']     = $this->generatePreview($bookPath, $data['preview_start_page'], $data['preview_end_page']);
            $data['slug']        = $data['slug'] ?? Str::slug($data['title']);
            $data['uploaded_by'] = auth()->id();

            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids']);

            $book = Book::create($data);

            if (!empty($categoryIds)) {
                $book->categories()->sync($categoryIds);
            }

            return $book->load('categories');
        });
    }
}
