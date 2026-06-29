<?php

namespace App\Exports;

use App\Exports\OrdersSummarySheet;
use App\Models\Order;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OrdersExport implements WithMultipleSheets
{
    use Exportable;

    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function sheets(): array
    {
        return [
            new OrdersSummarySheet($this->request),
            new OrdersDetailsSheet($this->request),
        ];
    }
}
