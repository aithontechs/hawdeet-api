<?php

namespace App\Http\Controllers\Dashboard\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingZoneController extends Controller
{
    use ResponseApi ;

    public function __construct(
        private ShippingService $shippingService
    ) {
            $this->authorizeResource(ShippingZone::class, 'shipping_zone');
    }

    public function index()
    {
        return $this->successApi(
            $this->shippingService->getZonesDashboard(),
            'Shipping zones fetched successfully'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:shipping_zones,name',
            'country' => 'required|boolean',
            'cost' => 'required|numeric|min:0',
            'cost_usd' => 'required|numeric|min:0',
            'days_min' => 'required|integer|min:1',
            'days_max' => 'required|integer|gte:days_min',
            'is_default' => 'boolean',
        ]);

        $zone = $this->shippingService->create($data);
        return $this->successApi($zone, 'Shipping zone created successfully', 201);
    }

    public function show($id)
    {
        $zone = $this->shippingService->find($id);
        return $this->successApi($zone, 'Shipping zone fetched successfully');
    }

    public function update(Request $request, ShippingZone $shipping_zone)
    {
        $data = $request->validate([
            'name' => ['sometimes' , 'string' ,Rule::unique('shipping_zones' , 'name')->ignore($shipping_zone)],
            'country' => 'sometimes|boolean',
            'cost' => 'sometimes|numeric|min:0',
            'cost_usd' => 'sometimes|numeric|min:0',
            'days_min' => 'sometimes|integer|min:1',
            'days_max' => 'sometimes|integer|gte:days_min',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
        ]);

        $zone = $this->shippingService->update($shipping_zone, $data);
        return $this->successApi($zone, 'Shipping zone updated successfully');
    }

    public function destroy(ShippingZone $shipping_zone)
    {
        $this->shippingService->delete($shipping_zone);
        return $this->successApi(null, 'Shipping zone deleted successfully');
    }
}
