<?php

namespace App\Http\Controllers\Dashboard\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ResponseApi;

class LogoutController extends Controller
{
    use ResponseApi ;

    public function logout()
    {
        auth('admin-api')->logout();
        return $this->successApi(null , 'Admin logged out Seccessfully') ;
    }
}
