<?php

namespace App\Http\Controllers\Application\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\User\UserUpdateRequest;
use App\Models\User ;
use App\Services\Storage\StorageService;
use App\Traits\ResponseApi;

class UserController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly StorageService $storageService)
    {

    }

    public function profile()
    {
        $user = auth()->user();
        return $this->successApi($user , 'User data fetched successfully') ;
    }

    public function updateProfile(UserUpdateRequest $request)
    {
        $user = auth()->user();

        $data = $request->validated();
        if ($request->hasFile('avatar_url')) {
            $data['avatar_url'] = $this->storageService->replace(
                $request->file('avatar_url'),
                $user->avatar_url,
                'avater/users'
            );
        }

        $user->update($data);

        return $this->successApi($user, 'Profile updated successfully');
    }
}
