<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PhoneNormalizer;
use App\Services\Cart\CartService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use ResponseApi ;

    public function __construct(private CartService $cartService , private PhoneNormalizer $phoneNormalizer,) {}

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required|min:8|max:25'
        ]);

        $isEmail = filter_var($request->login, FILTER_VALIDATE_EMAIL);
        $loginField = $isEmail ? 'email' : 'phone';

        $loginValue = $isEmail ? $request->login : $this->phoneNormalizer->normalize($request->login);

        $credentials = [
            $loginField => $loginValue,
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

        // $guestToken = $request->header('X-Guest-Token') ?? $request->input('guest_token');
        // if ($guestToken) {
        //     $this->cartService->mergeGuestCart($guestToken, $user);
        // }
        if(! $user->is_active){
            return $this->errorApi('Your account is inactive. Please contact support.' , 403) ;
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
