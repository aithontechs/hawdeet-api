<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use ResponseApi ;

    public function __construct(private CartService $cartService) {}

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required|min:8|max:25'
        ]);

        $loginField = filter_var($request->login , FILTER_VALIDATE_EMAIL) ? 'email' :  'phone' ;

        $credentials = [
            $loginField => $request->login,
            'password' => $request->password,
        ];
        if (!$token = auth('user-api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        /** @var User $user */
        $user = auth('user-api')->user();

        if (! $user->hasVerifiedEmail()) {
            return $this->errorApi('Please verify your email first' , 403) ;
        }

        $guestToken = $request->header('X-Guest-Token') ?? $request->input('guest_token');
        if ($guestToken) {
            $this->cartService->mergeGuestCart($guestToken, $user);
        }

        $data =  $this->respondWithToken($token);
        return $this->successApi($data , 'User Log in Successfully');
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('user-api')->factory()->getTTL() * 120,
            'user'         => auth('user-api')->user()
        ];
    }
}
