<?php

namespace App\Http\Controllers\Application\Shipping;

use App\Http\Controllers\Controller;
use App\Models\ShippingAddress;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingAddressController extends Controller
{
    use ResponseApi;

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
            $address->load('zone:id,name,cost'),
            'Address added successfully',
            201
        );
    }

    public function update(Request $request, ShippingAddress $address)
    {
        if ($address->user_id !== auth('user-api')->id()) {
            return $this->errorApi('Unauthorized', 403);
        }

        $data = $request->validate([
            'shipping_zone_id' => 'sometimes|exists:shipping_zones,id',
            'recipient_name'   => 'sometimes|string|max:255',
            'phone'            => 'sometimes|digits:11',
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

        return $this->successApi(
            $address->fresh()->load('zone:id,name,cost'),
            'Address updated successfully'
        );
    }

    public function setDefault(ShippingAddress $address)
    {
        if ($address->user_id !== auth('user-api')->id()) {
            return $this->errorApi('Unauthorized', 403);
        }

        DB::transaction(function () use ($address) {

            $address->user
                ->shippingAddresses()
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
}
