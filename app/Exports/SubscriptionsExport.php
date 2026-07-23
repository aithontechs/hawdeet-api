<?php

namespace App\Exports;

use App\Models\UserSubscription;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubscriptionsExport implements FromQuery, WithTitle, WithHeadings, WithMapping,
    WithStyles, ShouldAutoSize, WithEvents
{
    protected int $rowNumber = 1;
    protected ?string $status;

    public function __construct(?string $status = null)
    {
        $this->status = $status;
    }

    public function query()
    {
        return UserSubscription::with([
            'user:id,name,email,phone',
            'plan:id,name,duration_months,price',
            'coupon:id,code,discount_type,discount_value',
            'payment:id,user_subscription_id,currency,gateway_amount,gateway_currency', // 🆕
        ])
        ->status($this->status)
        ->latest();
    }

    public function headings(): array
    {
        return [
            '#',
            'اسم المستخدم',
            'البريد الإلكتروني',
            'رقم الهاتف',
            'الخطة',
            'مدة الخطة (أشهر)',
            'العملة',
            'قيمة الخصم',
            'السعر المدفوع',
            'المبلغ المحصّل فعلياً (بوابة الدفع)',
            'عملة بوابة الدفع',
            'كوبون الخصم',
            'حالة الاشتراك',
            'حالة الدفع',
            'تاريخ البداية',
            'تاريخ الانتهاء',
            'تاريخ الإلغاء',
            'سبب الإنهاء',
            'تاريخ الاشتراك',
        ];
    }

    public function map($subscription): array
    {
        $statusMap = [
            'active'    => 'نشط',
            'expired'   => 'منتهي',
            'canceled'  => 'ملغي',
            'pending'   => 'معلق',
        ];

        $paymentStatusMap = [
            'paid'    => 'مدفوع',
            'pending' => 'معلق',
            'failed'  => 'فاشل',
        ];

        return [
            $this->rowNumber++,
            $subscription->user->name              ?? 'لايوجد',
            $subscription->user->email             ?? 'لايوجد',
            $subscription->user->phone             ?? 'لايوجد',
            $subscription->plan->name              ?? 'لايوجد',
            $subscription->plan->duration_months   ?? 'لايوجد',
            $payment->currency ?? 'EGP',
            $subscription->original_amount         ?? 0,
            $subscription->discount_amount         ?? 0,
            $subscription->price,
            $payment->gateway_amount   ?? '-',
            $payment->gateway_currency ?? '-',
            $subscription->coupon->code            ?? 'لايوجد',
            $statusMap[$subscription->status]         ?? ($subscription->status ?? 'لايوجد'),
            $paymentStatusMap[$subscription->payment_status] ?? ($subscription->payment_status ?? 'لايوجد'),
            $subscription->start_at?->format('Y-m-d')    ?? 'لايوجد',
            $subscription->end_at?->format('Y-m-d')      ?? 'لايوجد',
            $subscription->canceled_at?->format('Y-m-d') ?? 'لايوجد',
            $subscription->ended_reason                   ?? 'لايوجد',
            $subscription->created_at->format('Y-m-d'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }


    public function title(): string
    {
        return 'الاشتراكات';
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
                $sheet->setRightToLeft(true);
                $sheet->getRowDimension(1)->setRowHeight(28);

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
                    $currency = $sheet->getCell("G{$row}")->getValue();
                    $color = $currency === 'USD' ? 'FFDDEBFF' : 'FFFFF3D6';
                    $sheet->getStyle("G{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
                        'font' => ['bold' => true],
                    ]);
                }

                for ($row = 2; $row <= $lastRow; $row++) {
                    $status = $sheet->getCell("N{$row}")->getValue();
                    $color  = match ($status) {
                        'نشط'   => 'FFD1FAE5', // green
                        'معلق'  => 'FFFEF3C7', // yellow
                        'ملغي'  => 'FFFEE2E2', // red
                        'منتهي' => 'FFF3F4F6', // gray
                        default => 'FFFFFFFF',
                    };
                    $sheet->getStyle("N{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
                        'font' => ['bold' => true],
                    ]);
                }

                for ($row = 2; $row <= $lastRow; $row++) {
                    $pStatus = $sheet->getCell("O{$row}")->getValue();
                    $color   = match ($pStatus) {
                        'مدفوع' => 'FFD1FAE5',
                        'معلق'  => 'FFFEF3C7',
                        'فاشل'  => 'FFFEE2E2',
                        default => 'FFFFFFFF',
                    };
                    $sheet->getStyle("O{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
                        'font' => ['bold' => true],
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
