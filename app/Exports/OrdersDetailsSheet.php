<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class OrdersDetailsSheet implements FromQuery, WithTitle, WithHeadings, WithMapping,
    WithStyles, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    protected $request;
    protected int $rowNumber = 1;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function query()
    {
        return Order::with(['user:id,name,email', 'items' , 'payments:id,order_id,payment_gateway'])
            ->filter($this->request)
            ->latest();
    }

    public function headings(): array
    {
        return [
            '#',
            'رقم الطلب',
            'العميل',
            'البريد الإلكتروني',
            'عدد العناصر',
            'الإجمالي الفرعي (EGP)',
            'الخصم (EGP)',
            'تكلفة الشحن (EGP)',
            'الإجمالي (EGP)',
            'طريقة الدفع',
            'حالة الدفع',
            'نوع الطلب',
            'تاريخ الدفع',
            'تاريخ الطلب',
            'المنتجات',

        ];
    }

    public function map($order): array
    {
        $paymentStatusMap = [
            'paid'    => 'مدفوع',
            'pending' => 'معلق',
            'failed'  => 'فاشل',
            'expired' => 'منتهي',
        ];

        $paymentMethodMap = [
            'card'          => 'بطاقة ائتمان',
            'wallet' => 'محفظة إلكترونية',
        ];

        $itemNames = $order->items
            ->pluck('book_name')
            ->filter()
            ->implode(' | ');

        return [
            $this->rowNumber++,
            $order->order_number,
            $order->user->name        ?? 'لايوجد',
            $order->user->email       ?? 'لايوجد',
            $order->items->count(),
            $order->subtotal,
            $order->discount          ?? 0,
            $order->shipping_cost     ?? 0,
            $order->total,
            $paymentMethodMap[$order->payment_method] ?? ($order->payment_method ?? 'لايوجد'),
            $paymentStatusMap[$order->payment_status] ?? ($order->payment_status ?? 'لايوجد'),
            $order->has_physical ? 'ورقي + رقمي' : 'رقمي',
            $order->paid_at?->format('Y-m-d H:i')    ?? 'لايوجد',
            $order->created_at->format('Y-m-d H:i'),
            $itemNames ?: 'لايوجد',

        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function title(): string
    {
        return 'تفاصيل الطلبات';
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
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $sheet->getHighestRow();
                $lastCol  = $sheet->getHighestColumn();

                // RTL
                $sheet->setRightToLeft(true);

                // Header row height
                $sheet->getRowDimension(1)->setRowHeight(30);

                // Zebra striping on data rows
                for ($row = 2; $row <= $lastRow; $row++) {
                    $color = $row % 2 === 0 ? 'FFF5F3FF' : 'FFFFFFFF';
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $color],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // Border on full table
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFE5E7EB'],
                        ],
                    ],
                ]);

                // Color payment status column (L = col 12)
                for ($row = 2; $row <= $lastRow; $row++) {
                    $status = $sheet->getCell("L{$row}")->getValue();
                    $color  = match ($status) {
                        'مدفوع' => 'FFD1FAE5', // green
                        'معلق'  => 'FFFEF3C7', // yellow
                        'فاشل'  => 'FFFEE2E2', // red
                        default => 'FFFFFFFF',
                    };
                    $sheet->getStyle("L{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
                        'font' => ['bold' => true],
                    ]);
                }

                // Freeze header row
                $sheet->freezePane('A2');
            },
        ];
    }
}
