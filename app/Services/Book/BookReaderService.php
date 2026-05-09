<?php

namespace App\Services\Book;

use App\Models\{Book, BookHighlight, User};
use App\Services\Fpdi\TransparentFpdi;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Storage;

class BookReaderService
{
    public function __construct(private readonly StorageService $storage) {}

    public function getReadingSessionInfo(Book $book, User $user): array
    {
        return [
            'book_id'     => $book->id,
            'user'        => $user->name,
            'title'       => $book->title,
            'total_pages' => $book->total_pages,
            'has_access'  => true,
            'cover_url'   => $book->cover_url,
        ];
    }

    public function streamPage(Book $book, int $page, User $user)
    {
        abort_unless($book->published, 403, 'Unauthorize access this book');

        $sourcePath = Storage::disk(StorageService::DISK_PRIVATE)->path($book->file);

        $highlights = BookHighlight::query()->where('book_id', $book->id)->where('user_id', $user->id)
                                ->where('page_number', $page)
                                ->get(['color', 'position_data']);

        $pdf = $this->buildWatermarkedPage($sourcePath, $page, $user, $highlights);

        return $this->streamResponse($pdf, 'page_' . $page . '.pdf');
    }

    private function buildWatermarkedPage(string $sourcePath, int $page, User $user, $highlights): string
    {
        $pdf = new TransparentFpdi();

        $totalPages = $pdf->setSourceFile($sourcePath);
        $page       = max(1, min($page, $totalPages));

        $tpl  = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($tpl);

        $width  = $size['width'];
        $height = $size['height'];

        $pdf->AddPage($width > $height ? 'L' : 'P', [$width, $height]);
        $pdf->SetAutoPageBreak(false, 0);

        $pdf->useTemplate($tpl, 0, 0, $width, $height);

        if ($highlights->isNotEmpty()) {
            $this->drawHighlights($pdf, $highlights, $width, $height);
        }

        $this->applyWatermark($pdf, $user, $width, $height);

        return $pdf->Output('S');
    }

    private function drawHighlights(TransparentFpdi $pdf, $highlights, float $width, float $height): void
    {
        foreach ($highlights as $highlight) {
            $pos = $highlight->position_data;
            if (empty($pos)) continue;

            $x = $pos['x']      * $width;
            $y = $pos['y']      * $height;
            $w = $pos['width']  * $width;
            $h = $pos['height'] * $height;

            [$r, $g, $b] = $this->hexToRgb($highlight->color ?? '#FFFF00');

            $pdf->setAlpha(0.30);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect($x, $y, $w, $h, 'F');
        }

        $pdf->setAlpha(1.0);
        $pdf->SetFillColor(255, 255, 255);
    }

    private function applyWatermark(TransparentFpdi $pdf, User $user, float $width, float $height): void
    {
        $watermarkText = env('APP_NAME', 'Haweedt') . ' | ID: ' . $user->id;

        $pdf->setAlpha(0.40);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(0, $height - 10);
        $pdf->Cell($width, 10, $watermarkText, 0, 0, 'C');

        $pdf->setAlpha(1.0);
        $pdf->SetTextColor(0, 0, 0);
    }


    public function streamPreview(Book $book, int $page, ?User $user)
    {
        abort_unless($book->published, 403, 'Unauthorize access this book');
        abort_unless($book->preview, 404, 'No preview available.');

        $sourcePath = Storage::disk(StorageService::DISK_PRIVATE)->path($book->preview);
        $pdf        = $this->buildWatermarkedPreview($sourcePath, $page, $user);

        return $this->streamResponse($pdf, 'preview_' . $page . '.pdf');
    }

    private function buildWatermarkedPreview(string $sourcePath, int $page, ?User $user): string
    {
        $pdf        = new TransparentFpdi();
        $totalPages = $pdf->setSourceFile($sourcePath);

        abort_if($page < 1 || $page > $totalPages, 422, "Page {$page} is out of range.");

        $tpl  = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($tpl);

        $width  = $size['width'];
        $height = $size['height'];

        $pdf->AddPage($width > $height ? 'L' : 'P', [$width, $height]);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->useTemplate($tpl, 0, 0, $width, $height);

        $this->applyPreviewWatermark($pdf, $user, $width, $height);

        return $pdf->Output('S');
    }

    private function applyPreviewWatermark(TransparentFpdi $pdf, ?User $user, float $width, float $height): void
    {
        $timestamp = now()->format('Y-m-d H:i:s') . ' UTC';

        $pdf->setAlpha(0.40);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(0, $height - 7);
        $pdf->Cell(
            $width, 5,
            sprintf('%s | PREVIEW | User: %d | %s', env('APP_NAME'), optional($user)->id ?? 'Guest', $timestamp),
            0, 0, 'C'
        );

        $pdf->setAlpha(0.08);
        $pdf->SetFont('Helvetica', 'B', 40);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(0, ($height / 2) - 10);
        $pdf->Cell($width, 20, env('APP_NAME') . ' PREVIEW', 0, 0, 'C');

        $pdf->setAlpha(1.0);
        $pdf->SetTextColor(0, 0, 0);
    }


    private function streamResponse(string $pdfContent, string $filename)
    {
        return response()->stream(
            function () use ($pdfContent) {
                echo $pdfContent;
                flush();
            },
            200,
            [
                'Content-Type'           => 'application/pdf',
                'Content-Length'         => strlen($pdfContent),
                'Content-Disposition'    => 'inline; filename="' . $filename . '"',
                'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
                'Pragma'                 => 'no-cache',
                'X-Robots-Tag'           => 'noindex, nofollow',
                'X-Frame-Options'        => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
