<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\NewUserRegistered;
use App\Traits\ResponseApi;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Socialite;

class SocialiteController extends Controller
{
    use ResponseApi;

    protected array $allowedProviders = ['google', 'facebook'];

    public function login($provider)
    {
        abort_unless(in_array($provider, $this->allowedProviders), 404);
        $platform = request('platform', 'mobile');

        $redirectUrl = route('social.redirect', ['provider' => $provider]) . '?platform=' . $platform;

        $url = Socialite::driver($provider)->stateless()->redirectUrl($redirectUrl)->redirect()->getTargetUrl();

        if ($platform === 'mobile') {
            return $this->successApi(['url' => $url], 'Redirect to social provider');
        }

        return redirect($url);
    }

    public function redirect($provider)
    {
        abort_unless(in_array($provider, $this->allowedProviders), 404);

        $platform = request('platform', 'mobile');

        $redirectUrl = route('social.redirect', ['provider' => $provider]) . '?platform=' . $platform;

        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->redirectUrl($redirectUrl)->user();
        } catch (\Exception $e) {
            return $this->handleFailure($platform, 'Social login failed');
        }

        $user = User::where('email', $socialiteUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialiteUser->getName(),
                'email' => $socialiteUser->getEmail(),
                'social_id' => $socialiteUser->getId(),
                'social_provider' => $provider,
                'email_verified_at' => now(),
                'avatar_url' => $socialiteUser->getAvatar(),
            ]);
        }

        if (! $user->is_active) {
            return $this->handleFailure($platform, 'Your account is inactive. Please contact support.', 403);
        }

        $user->update([
            'social_id' => $socialiteUser->getId(),
            'social_provider' => $provider,
            'avatar_url' => $socialiteUser->getAvatar(),
        ]);

        /** @var User $user */
        $token = auth('user-api')->login($user);
        Notification::send(Admin::where('is_active', 1)->get(), new NewUserRegistered($user));

        if ($platform === 'web') {
            return $this->handleWebRedirect($token);
        }

        return $this->successApi(
            [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('user-api')->factory()->getTTL() * 60,
                'user' => auth('user-api')->user(),
            ],
            'User logged in successfully via ' . ucfirst($provider)
        );
    }

    protected function handleFailure(string $platform, string $message, int $status = 422)
    {
        if ($platform === 'web') {
            $frontendUrl = rtrim(config('services.frontend.url'), '/');
            return redirect($frontendUrl . '/auth/callback?error=' . urlencode($message));
        }

        return $this->errorApi($message, $status);
    }

    protected function handleWebRedirect(string $token)
    {
        $frontendUrl = rtrim(config('services.frontend.url'), '/');

        return redirect($frontendUrl . '/auth/callback?token=' . $token);
    }
}
