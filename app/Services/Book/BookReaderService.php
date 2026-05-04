<?php

namespace App\Services\Book ;

use App\Models\{Book , User};
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookReaderService
{
    public function __construct(private readonly StorageService $storage)
    {

    }

    public function getReadingSessionInfo(Book $book , User $user): array
    {
        return [
            'book_id'       => $book->id,
            'user' => $user->name ,
            'title'      => $book->title,
            'total_pages'  => $book->total_pages,
            'has_access'   => true,
            'cover_url'    => $book->cover_url,
        ];
    }

    public function streamPage(Book $book , int $page , User $user) : StreamedResponse
    {
        abort_unless($book->published , 403 , 'Unauthorize access this book');
        $sourcePath = Storage::disk(StorageService::DISK_PRIVATE)->path($book->file);

        $watermarkedPdf = $this->buildWatermarkedPage($sourcePath, $page, $user);
         return response()->stream(
            function () use ($watermarkedPdf) {
                echo $watermarkedPdf;
                flush();
            },
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Length'      => strlen($watermarkedPdf),
                'Content-Disposition' => 'inline; filename="page_' . $page . '.pdf"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
                'Pragma'              => 'no-cache',
                'X-Robots-Tag'        => 'noindex, nofollow',
                'X-Frame-Options'     => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function buildWatermarkedPage(string $sourcePath, int $page, User $user): string
    {
        $pdf = new Fpdi();

        $totalPages = $pdf->setSourceFile($sourcePath);
        $page = max(1, min($page, $totalPages));

        $tpl  = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($tpl);

        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

        $width  = $size['width'];
        $height = $size['height'];

        $pdf->AddPage($orientation, [$width, $height]);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->useTemplate($tpl, 0, 0, $width, $height);
        $this->applyWatermark($pdf, $user, $width, $height);
        return $pdf->Output('S');
    }

    private function applyWatermark(Fpdi $pdf, User $user, float $width, float $height): void
    {
        $watermarkText = env("APP_NAME", "Haweedt") . ' | ID: ' . $user->id;
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(180, 180, 180);
        $textHeight = 10 ;
        $yPos = $height - $textHeight;
        $pdf->SetXY(0, $yPos);
        $pdf->Cell($width, $textHeight, $watermarkText, 0, 0, 'C');
    }


    // ============= Preview =========
    public function streamPreview(Book $book, int $page , User $user)
    {
        abort_unless($book->published , 403 , 'Unauthorize access this book');
        abort_unless($book->preview, 404, 'No preview available.');

        $sourcePath     = Storage::disk(StorageService::DISK_PRIVATE)->path($book->preview);
        $watermarkedPdf = $this->buildWatermarkedPreview($sourcePath, $page ,$user) ;
        return response()->stream(
            function () use ($watermarkedPdf) {
                echo $watermarkedPdf;
                flush();
            },
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Length'      => strlen($watermarkedPdf),
                'Content-Disposition' => 'inline; filename="page_' . $page . '.pdf"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
                'Pragma'              => 'no-cache',
                'X-Robots-Tag'        => 'noindex, nofollow',
                'X-Frame-Options'     => 'SAMEORIGIN',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function buildWatermarkedPreview(string $sourcePath,int $page , User $user)
    {
        $pdf= new Fpdi();
        $totalPages = $pdf->setSourceFile($sourcePath);
        abort_if(
            $page < 1 || $page > $totalPages,
            422,
            "Page {$page} is out of range."
        );

        $tpl  = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($tpl);

        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

        $width  = $size['width'];
        $height = $size['height'];

        $pdf->AddPage($orientation, [$width, $height]);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->useTemplate($tpl, 0, 0, $width, $height);
        $this->applyPreviewWatermark($pdf, $user, $width, $height);
        return $pdf->Output('S');
    }

    private function applyPreviewWatermark(Fpdi $pdf, User $user, float $width, float $height): void
    {
        $timestamp = now()->format('Y-m-d H:i:s') . ' UTC';
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(170, 170, 170);
        $pdf->SetXY(0, $height - 7);
        $pdf->Cell(
            $width, 5,
            sprintf('%s | PREVIEW | User: %d | %s', env('APP_NAME') , $user->id, $timestamp),
            0, 0, 'C'
        );
        $pdf->SetFont('Helvetica', 'B', 40);
        $pdf->SetTextColor(230, 230, 230);
        $pdf->SetXY(0, ($height / 2) - 10);
        $pdf->Cell($width, 20, env('APP_NAME') . ' PREVIEW', 0, 0, 'C');
    }
}
