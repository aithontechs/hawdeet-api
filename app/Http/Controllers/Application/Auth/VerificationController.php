<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ResponseApi;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    use ResponseApi ;

    public function verify(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if (! hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return $this->errorApi('Invalid verification link', 403);
        }

        if ($request->has('expires') && now()->timestamp > $request->expires) {
            return $this->errorApi('Verification link has expired', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->errorApi('Email already verified', 400);
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        $token = auth('user-api')->login($user);

        return $this->successApi([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $user
            ]  , 'Email verification successfully') ;
    }

    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            return $this->errorApi('Email already verified', 400);
        }

        $user->sendEmailVerificationNotification();

        return $this->successApi(null, 'Verification email sent successfully');
    }

}
