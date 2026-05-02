<?php

namespace App\Http\Controllers\Dashboard\Coupon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Coupon\CouponStoreRequest;
use App\Http\Requests\Dashboard\Coupon\CouponUpdateRequest;
use App\Models\Coupon;
use App\Traits\ResponseApi;

class CouponController extends Controller
{
    use ResponseApi ;

    public function __construct()
    {
        $this->authorizeResource(Coupon::class, 'coupon') ;
    }

    public function index()
    {
        $coupons = Coupon::latest()->paginate(15);
        return $this->successApi($coupons , 'Coupons fetched successfully') ;
    }


    public function store(CouponStoreRequest $request)
    {
        $coupon = Coupon::create($request->validated()) ;
        return $this->successApi($coupon , 'Coupon created successfully');
    }


    public function show(Coupon $coupon)
    {
        // $coupon->load('coupon_usages') ;
        // return $this->successApi($coupon ,'Coupon fetched successfully') ;
    }

    function update(CouponUpdateRequest $request, Coupon $coupon)
    {
        if($coupon->used_count > 0)
        {
            $validated = $request->validated();
            unset($validated['discount_type'], $validated['discount_value'], $validated['code']);
            // return $this->errorApi('Coupon cannot be updated because it has been used before!' , 422 );
            $coupon->update($validated);
            return $this->successApi($coupon, 'Coupon updated partially successfully');
        }
        $coupon->update($request->validated()) ;
        return $this->successApi($coupon ,'Coupon updated successfully') ;
    }


    public function destroy(Coupon $coupon)
    {
        if($coupon->used_count > 0)
        {
            return $this->errorApi('Coupon cannot be deleted because it has been used before !' , 422) ;
        }
        $coupon->delete();
        return $this->successApi(null ,'Coupon fetched successfully') ;
    }
}
