<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Storage\StorageService;
use App\Traits\ResponseApi;

class RegisterController extends Controller
{
    use ResponseApi ;

    public function __construct(
        protected StorageService $storageService
    ) {}

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('avatar_url')) {

            $data['avatar_url'] = $this->storageService->upload(
                file: $request->file('avatar_url'),
                folder: 'avatars'
            );
        }
        $user = User::create($data) ;

        $user->sendEmailVerificationNotification();
        return $this->successApi($user , 'User created successfully , you must verify email for login') ;
    }
}
