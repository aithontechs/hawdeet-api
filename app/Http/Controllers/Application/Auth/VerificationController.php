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

    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            return $this->errorApi('Email already verified', 400);
        }

        if (! $user->otp_expires_at || now()->isAfter($user->otp_expires_at)) {
            return $this->errorApi('OTP has expired', 403);
        }

        if ($request->otp !== $user->email_verification_otp) {
            return $this->errorApi('Invalid OTP', 403);
        }

        $user->markEmailAsVerified();

        $user->update([
            'email_verification_otp' => null,
            'otp_expires_at'         => null,
        ]);

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

        return $this->successApi(null, 'OTP sent to your email successfully');
    }

}
