<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Auth\RegisterRequest;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\NewUserRegistered;
use App\Services\Cart\CartService;
use App\Services\Storage\StorageService;
use App\Traits\ResponseApi;
use Illuminate\Support\Facades\Notification;

class RegisterController extends Controller
{
    use ResponseApi ;

    public function __construct(
        protected StorageService $storageService, protected CartService $cartService
    ) {}

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('avatar_url')) {

            $data['avatar_url'] = $this->storageService->upload(
                file: $request->file('avatar_url'),
                folder: 'avatar/users'
            );
        }
        $user = User::create($data) ;

        // $guestToken = $request->header('X-Guest-Token') ?? $request->input('guest_token');
        // if ($guestToken) {
        //     $this->cartService->mergeGuestCart($guestToken, $user);
        // }

        $user->sendEmailVerificationNotification();
        Notification::send(Admin::where('is_active' , 1)->get(), new NewUserRegistered($user));
        return $this->successApi($user , 'User created successfully , you must verify email for login , check your email for OTP') ;
    }
}
