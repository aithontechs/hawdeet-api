<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ResponseApi;

class RegisterController extends Controller
{
    use ResponseApi ;

    public function store(RegisterRequest $request)
    {
        $user = User::create($request->validated()) ;
        if($request->hasFile('avatar_url'))
        {
            $nameAvater = $request->file('avatar_url')->getClientOriginalName() ;
            $request->file('avatar_url')->storeAs('' , $nameAvater , 'avater');
        }
        $user->sendEmailVerificationNotification();
        return $this->successApi($user , 'User created successfully , you must verify email for login') ;
    }
}
