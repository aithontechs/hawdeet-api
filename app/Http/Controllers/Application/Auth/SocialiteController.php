<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ResponseApi;
use Laravel\Socialite\Socialite;

class SocialiteController extends Controller
{
    use ResponseApi ;

    public function login($provider)
    {
        $allowedProviders = ['google', 'github'];
        abort_unless(in_array($provider, $allowedProviders), 404);
        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();
        return $this->successApi(['url' => $url], 'Redirect to social provider');
    }

    public function redirect($provider)
    {
        $allowedProviders = ['google', 'facebook'];
        abort_unless(in_array($provider, $allowedProviders), 404);
        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return $this->errorApi('Social login failed', 422);
        }

        $user = User::where('email', $socialiteUser->getEmail())->first();

        if(! $user){
            $user = User::create([
                'name' => $socialiteUser->getName() ,
                'email' => $socialiteUser->getEmail() ,
                'social_id' => $socialiteUser->getId() ,
                'social_provider' => $provider,
                'email_verified_at' => now(),
                'avatar_url' => $socialiteUser->getAvatar()
            ]) ;
        }

        $user->update([
            'social_id' => $socialiteUser->getId() ,
            'social_provider' => $provider,
            'avatar_url' => $socialiteUser->getAvatar(),
        ]) ;

        /** @var User $user */
        $token = auth('user-api')->login($user);

        return $this->successApi(
        [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('user-api')->factory()->getTTL() * 60,
            'user'         => auth('user-api')->user()
        ],
        'User logged in successfully via ' . ucfirst($provider)
        );

    }
}
