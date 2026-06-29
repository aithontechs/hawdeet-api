<?php

namespace App\Exports;

use App\Models\Book;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BooksExport implements FromQuery, WithTitle, WithHeadings, WithMapping,
    WithStyles, ShouldAutoSize, WithEvents
{
    protected int $rowNumber = 1;
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function query()
    {
        return Book::with([
            'author:id,name',
            'categories:id,name',
        ])
        ->withCount([
            'userBooks as total_access_count',
            'userBooks as purchase_access_count' => fn($q) => $q->where('access_type', 'purchase'),
            'userBooks as subscription_access_count' => fn($q) => $q->where('access_type', 'subscription'),
            'reviews as total_reviews_count',
            'reviews as approved_reviews_count' => fn($q) => $q->where('is_approve', true),
        ])
        ->withSum(
            ['orderItems as purchase_revenue' => fn($q) => $q->whereHas('order', fn($o) => $o->where('payment_status', 'paid'))],
            'price'
        )
        ->filter($this->request)
        ->latest();
    }

    public function headings(): array
    {
        return [
            '#',
            'عنوان الكتاب',
            'المؤلف',
            'التصنيفات',
            'النوع',
            'السعر الرقمي (EGP)',
            'السعر الفيزيائي (EGP)',
            'عدد الصفحات',
            'الحد الأدنى للعمر',
            'متوسط التقييم',
            'عدد التقييمات الكلي',
            'إجمالي المستخدمين (Access)',
            'مشتريات مباشرة',
            'عبر اشتراك',
            'إيرادات الشراء (EGP)',
            'حالة النشر',
            'تاريخ النشر',
            'تاريخ الإضافة',
        ];
    }

    public function map($book): array
    {
        $typeMap = [
            'digital'  => 'رقمي',
            'physical' => 'ورقي',
            'both'     => 'رقمي + ورقي',
        ];

        $categories = $book->categories->pluck('name')->implode(' | ');

        return [
            $this->rowNumber++,
            $book->title,
            $book->author->name  ?? 'لايوجد',
            $categories  ?: 'لايوجد',
            $typeMap[$book->type]              ?? $book->type,
            $book->price ?? 0,
            $book->physical_price ?? 'لايوجد',
            $book->total_pages ?? 'لايوجد',
            $book->age_min ?? 'لايوجد',
            $book->avg_rating  ? number_format($book->avg_rating, 1) : 'لايوجد',
            $book->total_reviews_count ? $book->total_reviews_count : 'لم يقيم حتي الان',
            $book->total_access_count ? $book->total_access_count :  'لايوجد',
            $book->purchase_access_count ? $book->purchase_access_count :'لايوجد',
            $book->subscription_access_count ? $book->subscription_access_count  : 'لايوجد',
            number_format($book->purchase_revenue ? $book->purchase_revenue :  0, 2),
            $book->published ? 'منشور' : 'غير منشور',
            $book->published_at?->format('Y-m-d') ?? 'لم يتم النشر',
            $book->created_at->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'الكتب';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size'  => 11,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF6366F1'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = $sheet->getHighestColumn();

                // RTL
                $sheet->setRightToLeft(true);

                // Header height
                $sheet->getRowDimension(1)->setRowHeight(32);

                // Zebra striping
                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $row % 2 === 0 ? 'FFF5F3FF' : 'FFFFFFFF'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                for ($row = 2; $row <= $lastRow; $row++) {
                    $status = $sheet->getCell("T{$row}")->getValue();
                    $color  = $status === 'منشور' ? 'FFD1FAE5' : 'FFFEE2E2';
                    $sheet->getStyle("T{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
                        'font' => ['bold' => true],
                    ]);
                }

                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getStyle("R{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDE9FE']],
                    ]);
                }

                $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFE5E7EB'],
                        ],
                    ],
                ]);

                $sheet->freezePane('A2');
            },
        ];
    }
}
