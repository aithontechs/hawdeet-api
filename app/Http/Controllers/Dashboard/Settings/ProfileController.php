<?php

namespace App\Http\Controllers\Dashboard\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Admin\ProfileUpdateRequest;
use App\Services\Storage\StorageService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly StorageService $storageService)
    {

    }

    public function profile(Request $request)
    {
        $admin = $request->user('admin-api')->load('role:id,name')->first() ;
        return $this->successApi([
            'id'        => $admin->id,
            'name'      => $admin->name,
            'email'     => $admin->email,
            'phone'     => $admin->phone,
            'avater_url' => $admin->avatar_url,
            'is_active' => $admin->is_active,
            'role'      => $admin->role->name ,
        ], 'Admin profile retrieved successfully');
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {
        $admin = $request->user('admin-api');

        if (!Hash::check($request->current_password, $admin->password)) {
            return $this->errorApi('Current password is incorrect', 422);
        }
        if ($request->hasFile('avatar_url')) {
            $data['avatar_url'] = $this->storageService->replace(
                $request->file('avatar_url'),
                $admin->avatar_url,
                'avater/admins'
            );
        }
        $admin->update([
            'name'     => $request->name ?? $admin->name,
            'email'    => $request->email ?? $admin->email,
            'phone'    => $request->phone ?? $admin->phone,
            'password' => $request->new_password ?? $admin->password,
            'avatar_url' => $data['avatar_url'] ?? $admin->avatar_url,
        ]);

        return $this->successApi($admin->load('role:id,name'),'Profile updated successfully');
    }



}
