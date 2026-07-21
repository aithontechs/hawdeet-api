<?php

namespace App\Http\Controllers\Application\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShippingAddress;
use App\Services\Currency\CurrencyResolver;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Propaganistas\LaravelPhone\Rules\Phone;

class ShippingAddressController extends Controller
{
    use ResponseApi;


    public function __construct(private CurrencyResolver $currencyResolver) {}

    public function index()
    {
        $addresses = auth('user-api')->user()
                        ->shippingAddresses()
                        ->with('zone:id,name,cost,days_min,days_max')
                        ->latest()
                        ->get();

        return $this->successApi($addresses, 'Addresses fetched successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'recipient_name'   => 'nullable|string|max:255',
            'phone'            => 'required|digits:11',
            'address_line'     => 'required|string|max:500',
            'city'             => 'required|string|max:100',
            'is_default'       => 'nullable|boolean',
        ]);

        $user = auth('user-api')->user();
        $currency = $this->currencyResolver->resolve($request);


        $data['recipient_name'] = $data['recipient_name'] ?? $user->name;
        $data['phone']          = $data['phone'] ?? $user->phone;
        $data['country']        = 'EG';

        $user->shippingAddresses()
            ->update(['is_default' => false]);

        $address = $user->shippingAddresses()->create([
            ...$data,
            'is_default' => true,
        ]);

        return $this->successApi(
            $this->withZoneCurrency($address, $currency),
            'Address added successfully',
            201
        );
    }

    public function update(Request $request, ShippingAddress $address)
    {
        if ($address->user_id !== auth('user-api')->id()) {
            return $this->errorApi('Unauthorized', 403);
        }

        $currency = $this->currencyResolver->resolve($request);

        $data = $request->validate([
            'shipping_zone_id' => 'sometimes|exists:shipping_zones,id',
            'recipient_name'   => 'sometimes|string|max:255',
            'phone'            => ['sometimes', (new Phone())->international(), 'unique:users,phone'],
            'address_line'     => 'sometimes|string|max:500',
            'city'             => 'sometimes|string|max:100',
            'is_default'       => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($address, $data) {

            $makeDefault = $data['is_default'] ?? false;

            unset($data['is_default']);
            $address->update($data);

            if ($makeDefault) {
                $address->user->shippingAddresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
                $address->update(['is_default' => true]);
            }
        });

        return $this->successApi($this->withZoneCurrency($address->fresh(), $currency),'Address updated successfully');
    }

    public function setDefault(ShippingAddress $address)
    {
        if ($address->user_id !== auth('user-api')->id()) {
            return $this->errorApi('Unauthorized', 403);
        }

        DB::transaction(function () use ($address) {

            $address->user->shippingAddresses()
                ->update(['is_default' => false]);

            $address->update([
                'is_default' => true
            ]);
        });

        return $this->successApi(
            null,
            'Default address updated successfully'
        );
    }


    public function destroy(ShippingAddress $address)
    {
        if ($address->user_id !== auth('user-api')->id()) {
            return $this->errorApi('Unauthorized', 403);
        }

        $wasDefault = $address->is_default;

        $address->delete();

        if ($wasDefault) {
            $newDefault = auth('user-api')->user()->shippingAddresses()->latest()->first();

            if ($newDefault) {
                $newDefault->update([
                    'is_default' => true
                ]);
            }
        }

        return $this->successApi(
            null,
            'Address deleted successfully'
        );
    }

    private function withZoneCurrency(ShippingAddress $address, string $currency): ShippingAddress
    {
        $address->load('zone:id,name,cost,cost_usd,days_min,days_max');

        if ($address->zone) {
            $address->zone->cost = $address->zone->costFor($currency);
        }

        return $address;
    }
}
