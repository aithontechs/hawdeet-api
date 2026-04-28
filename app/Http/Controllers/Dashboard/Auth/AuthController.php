<?php

namespace App\Http\Controllers\Dashboard\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;


class AuthController extends Controller
{
    use ResponseApi ;

    public function register(Request $request)
    {
        $validated = $request->validate( [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|digits:11' ,
            'role_id' => 'nullable|exists:roles,id' ,
            'password' => 'required|string|min:6|max:25',
        ]);
        $admin = Admin::create($validated) ;

        return $this->successApi($admin , 'Admin created successfully' , 201 ) ;
    }
}
