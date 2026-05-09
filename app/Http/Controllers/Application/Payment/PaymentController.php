<?php

namespace App\Http\Controllers\Application\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Subscription\SubscriptionService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ResponseApi ;
    public function __construct(private readonly SubscriptionService $subscription) {

    }

    // test only , this function change after integration with payment
    public function pay(Payment $payment)
    {
        // if success payment
        $this->subscription->activate($payment) ;
        return $this->successApi(null , 'Subscription is be paid suucessfully , the books become available to you') ;
    }
}
