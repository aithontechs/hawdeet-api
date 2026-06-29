<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AuthorsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?string $search;

    public function __construct(?string $search = null)
    {
        $this->search = $search;
    }

    public function query()
    {
        return User::query()
            ->where('is_author', 1)
            ->select('id', 'name', 'email', 'is_active', 'created_at')
            ->withCount([
                'authorBooks as published_books_count' => fn($q) => $q->where('published', true),
                'followers',
            ])
            ->search($this->search)
            ->latest();
    }

    public function headings(): array
    {
        return [
            '#',
            'الاسم',
            'البريد الإلكتروني',
            'الحالة',
            'الكتب المنشورة',
            'عدد المتابعين',
            'تاريخ الإنضمام',
        ];
    }

    public function map($author): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $author->name,
            $author->email,
            $author->is_active ? 'نشط' : 'غير نشط',
            $author->published_books_count,
            $author->followers_count,
            $author->created_at->format('Y-m-d'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size'  => 12,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF6366F1'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'المؤلفون';
    }
}
