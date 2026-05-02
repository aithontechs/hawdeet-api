<?php

namespace App\Http\Controllers\Dashboard\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ResponseApi ;

    public function index(Request $request)
    {
        $orders = Order::with('user:id,name')->filter($request)->latest()->paginate(15) ;
        return $this->successApi($orders , 'Orders fetched successully') ;
    }

    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        $order = Order::with('items')->findorfail($id);
        return $this->successApi($order ,'Order details fetched successfully');
    }


    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }
}
