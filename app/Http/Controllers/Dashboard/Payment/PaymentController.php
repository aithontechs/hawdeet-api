<?php

namespace App\Http\Controllers\Dashboard\Payment;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ResponseApi ;
    public function index()
    {
        $payments = Payment::latest()->paginate(20);

        return $this->successApi(
            PaymentResource::collection($payments),
            'Payments fetched successfully'
        );
    }
}
