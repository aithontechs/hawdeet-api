<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?string $search;

    public function __construct(?string $search = null)
    {
        $this->search = $search;
    }

    public function query()
    {
        return User::query()
            ->where('is_author', false)
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'is_active',
                'created_at',
            ])
            ->withCount([
                'followers',
                'following',
                'userBooks',
                'orders',
                'payments',
                'shippingAddresses',
            ])
            ->with([
                'activeSubscriptions.plan:id,name,duration_months'
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
            'رقم الهاتف',
            'الحالة',
            'الكتب المفتوحة',
            'نوع الاشتراك',
            'بداية الاشتراك',
            'نهاية الاشتراك',
            'مدة الاشتراك',
            'تاريخ التسجيل',
        ];
    }

    public function map($user): array
    {
        static $index = 0;

        $subscription = $user->activeSubscriptions;

        return [
            ++$index,
            $user->name,
            $user->email,
            $user->phone ?? '-',
            $user->is_active ? 'نشط' : 'محظور',
            $user?->user_books_count ?: 0,
            $subscription?->plan?->name ?? 'لا يوجد',
            $subscription?->start_at?->format('Y-m-d') ?? '-',
            $subscription?->end_at?->format('Y-m-d') ?? '-',
            $subscription?->plan?->duration_months
                ? $subscription->plan->duration_months . ' شهر'
                : '-',
            $user->created_at->format('Y-m-d'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => [
                        'argb' => 'FFFFFFFF',
                    ],
                ],

                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF6366F1',
                    ],
                ],

                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'المستخدمين';
    }
}
