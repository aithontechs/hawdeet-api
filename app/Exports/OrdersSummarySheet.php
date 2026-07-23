<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OrdersSummarySheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $request;
    protected $overallStats;
    protected $byCurrency;

    public function __construct($request = null)
    {
        $this->request = $request;

        $this->overallStats = Order::filter($request)->selectRaw("
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'paid'    THEN 1 ELSE 0 END) as paid_orders,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN payment_status = 'failed'  THEN 1 ELSE 0 END) as failed_orders,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
            SUM(CASE WHEN has_physical = 1 THEN 1 ELSE 0 END) as physical_orders
        ")->first();

        $this->byCurrency = Order::filter($request)
            ->selectRaw("
                COALESCE(currency, 'EGP') as currency,
                COUNT(*) as total_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
                AVG(CASE WHEN payment_status = 'paid' THEN total END) as avg_order_value
            ")
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');
    }

    public function array(): array
    {
        $s = $this->overallStats;

        $rows = [
            ['ملخص الطلبات', '', '', ''],
            ['تاريخ التصدير: ' . now()->format('Y-m-d H:i'), '', '', ''],
            ['', '', '', ''],
            ['المؤشر', 'القيمة', '', ''],
            ['إجمالي الطلبات',   number_format($s->total_orders)],
            ['الطلبات المدفوعة', number_format($s->paid_orders)],
            ['الطلبات المعلقة',  number_format($s->pending_orders)],
            ['الطلبات الفاشلة',  number_format($s->failed_orders)],
            ['طلبات اليوم',      number_format($s->today_orders)],
            ['طلبات ورقية',   number_format($s->physical_orders)],
            ['', '', '', ''],
        ];

        $rows[] = ['الإيرادات حسب العملة', '', '', ''];

        foreach (['EGP', 'USD'] as $currency) {
            $stat = $this->byCurrency->get($currency);

            $rows[] = [
                "إجمالي الإيرادات ({$currency})",
                $stat ? number_format($stat->total_revenue, 2) : '0.00',
            ];
            $rows[] = [
                "متوسط قيمة الطلب ({$currency})",
                $stat ? number_format($stat->avg_order_value, 2) : '0.00',
            ];
            $rows[] = [
                "عدد الطلبات المدفوعة ({$currency})",
                $stat ? number_format($stat->paid_orders) : '0',
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'الملخص';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 25,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF6366F1']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
            2 => [
                'font' => ['size' => 10, 'color' => ['argb' => 'FF888888']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF6366F1']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                foreach (range(5, 10) as $row) {
                    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $row % 2 === 0 ? 'FFF5F3FF' : 'FFFFFFFF'],
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);
                }

                $sheet->getStyle('A12:B12')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF6366F1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);

                foreach (range(13, 18) as $row) {
                    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $row <= 15 ? 'FFFFF3D6' : 'FFDDEBFF'], // EGP أصفر فاتح، USD أزرق فاتح
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);
                }

                $sheet->getStyle('A4:B18')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
