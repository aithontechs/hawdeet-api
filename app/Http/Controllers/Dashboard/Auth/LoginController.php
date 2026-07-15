<?php

namespace App\Http\Controllers\Dashboard\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use ResponseApi ;

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $credentials = $request->only('email', 'password');
        if (!$token = auth('admin-api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $admin = auth('admin-api')->user();
        if (!$admin->is_active) {
            auth('admin-api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive'
            ], 403);
        }
        $tokens =  $this->respondWithToken($token);
        $admin->tokens_invalidated_at = null;
        $admin->save();
        return $this->successApi($tokens , 'Admin login Seccessfully') ;
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('admin-api')->factory()->getTTL() * 120,
            'admin'         => auth('admin-api')->user()
        ];
    }
}
