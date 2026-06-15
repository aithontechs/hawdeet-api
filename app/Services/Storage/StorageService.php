<?php

namespace App\Services\Storage ;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageService
{

    const DISK_PUBLIC  = 'public';
    const DISK_PRIVATE = 'private';
    const CHUNK_SIZE = 8192; // 8 KB


    public function upload(UploadedFile $file,string $folder,string $disk = self::DISK_PUBLIC,?string $name = null)
    {
        $filename = ($name ?? Str::uuid()) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($folder, $filename, $disk);
    }

    public function replace(UploadedFile $newFile, ?string $oldPath,string $folder,string $disk = self::DISK_PUBLIC,?string $name = null)
    {
        $data = $this->upload($newFile, $folder, $disk, $name);
        if (!empty($oldPath)) {
            $this->delete($oldPath, $disk);
        }

        return $data ;
    }

    public function delete(?string $path, string $disk = self::DISK_PUBLIC)
    {
        if (!$path) {
            return false;
        }
        return Storage::disk($disk)->delete($path);
    }


    public function deleteMany(array $paths, string $disk = self::DISK_PUBLIC)
    {
        $valid = array_filter($paths);

        if (! empty($valid)) {
            Storage::disk($disk)->delete(array_values($valid));
        }
    }


    public function deleteDirectory(string $directory, string $disk = self::DISK_PUBLIC): bool
    {
        return Storage::disk($disk)->deleteDirectory($directory);
    }


    public function exists(?string $path, string $disk = self::DISK_PUBLIC): bool
    {
        return $path ? Storage::disk($disk)->exists($path) : false;
    }


    public function metadata(string $path, string $disk = self::DISK_PUBLIC): array
    {
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404, "File not found: {$path}");

        return [
            'path'          => $path,
            'filename'      => basename($path),
            'size'          => $storage->size($path),
            'mime'          => $storage->mimeType($path),
            'last_modified' => $storage->lastModified($path),
        ];
    }

    public function url(string $path, string $disk = self::DISK_PUBLIC): string
    {
        return Storage::disk($disk)->url($path);
    }


    public function temporaryUrl(string $path,int $minutes = 30,string $disk = self::DISK_PRIVATE)
    {
        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }


    public function get(string $path, string $disk = self::DISK_PUBLIC): string
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);
        return Storage::disk($disk)->get($path);
    }

    public function copy(string $from, string $to, string $disk = self::DISK_PUBLIC): bool
    {
        return Storage::disk($disk)->copy($from, $to);
    }

    public function move(string $from, string $to, string $disk = self::DISK_PUBLIC): bool
    {
        return Storage::disk($disk)->move($from, $to);
    }


    public function copyAcrossDisks(string $path,string $sourceDisk,string $targetDisk,?string $targetPath = null)
    {
        $destination = $targetPath ?? $path;

        Storage::disk($targetDisk)->put(
            $destination,
            Storage::disk($sourceDisk)->get($path)
        );

        return $destination;
    }


    public function files(string $directory,string $disk = self::DISK_PUBLIC,bool $recursive = false)
    {
        return $recursive
            ? Storage::disk($disk)->allFiles($directory)
            : Storage::disk($disk)->files($directory);
    }


    public function directories(string $directory,string $disk = self::DISK_PUBLIC,bool $recursive = false)
    {
        return $recursive
            ? Storage::disk($disk)->allDirectories($directory)
            : Storage::disk($disk)->directories($directory);
    }



    public function streamResponse(string $path,string $disk = self::DISK_PRIVATE,string $disposition = 'inline',?string $filename = null): StreamedResponse
    {
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404, 'File not found.');

        $meta     = $this->metadata($path, $disk);
        $fname    = $filename ?? $meta['filename'];
        $dispHeader = "{$disposition}; filename=\"{$fname}\"";

        return response()->stream(
            function () use ($storage, $path) {
                $stream = $storage->readStream($path);

                abort_unless(is_resource($stream), 500, 'Could not open file stream.');

                while (! feof($stream)) {
                    echo fread($stream, self::CHUNK_SIZE); // 8 KB
                    flush();
                }

                fclose($stream);
            },
            200,
            [
                'Content-Type'        => $meta['mime'],
                'Content-Length'      => $meta['size'],
                'Content-Disposition' => $dispHeader,
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
                'X-Robots-Tag'        => 'noindex',
            ]
        );
    }


    public function downloadResponse(
        string $path,
        string $disk = self::DISK_PRIVATE,
        ?string $filename = null
    ): StreamedResponse {
        return $this->streamResponse($path, $disk, 'attachment', $filename);
    }


    public function pumpToOutput(string $path, string $disk = self::DISK_PRIVATE): void
    {
        $stream = Storage::disk($disk)->readStream($path);

        abort_unless(is_resource($stream), 500, 'Could not open file stream.');

        while (! feof($stream)) {
            echo fread($stream, self::CHUNK_SIZE);
            flush();
        }

        fclose($stream);
    }


    public function put(
        string $path,
        string $contents,
        string $disk = self::DISK_PRIVATE
    ): bool {
        return Storage::disk($disk)->put($path, $contents);
    }


    public function putStream(
        string $path,
        $resource,
        string $disk = self::DISK_PRIVATE
    ): bool {
        return Storage::disk($disk)->put($path, $resource);
    }


    public function makePublic(string $path, string $disk = self::DISK_PUBLIC): bool
    {
        Storage::disk($disk)->setVisibility($path, 'public');
        return true;
    }

    public function makePrivate(string $path, string $disk = self::DISK_PUBLIC): bool
    {
        Storage::disk($disk)->setVisibility($path, 'private');
        return true;
    }
}
