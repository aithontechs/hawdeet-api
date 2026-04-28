<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    use ResponseApi ;
    public function logout(Request $request)
    {
        auth('user-api')->logout();
        return $this->successApi(null , 'User logged out Seccessfully') ;
    }
}
