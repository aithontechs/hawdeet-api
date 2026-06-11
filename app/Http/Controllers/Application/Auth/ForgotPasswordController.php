<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Traits\ResponseApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    use ResponseApi ;

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:users,email'
        ]);
        $user = User::where('email', $request->email)->first();

        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => bcrypt($token),
                'created_at' => Carbon::now()
            ]
        );

        $user->notify(new ResetPasswordNotification($token));
        return $this->successApi(null , 'Reset link sent to your email');

    }

    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|exists:users,email']);
        return $this->sendPasswordResetOtp($request->email) ;
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        return  $this->sendPasswordResetOtp($request->email) ;
    }

    private function sendPasswordResetOtp(string $email)
    {
        $user = User::where('email', $email)->first();

        $reset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (
            $reset &&
            Carbon::parse($reset->created_at)
                ->addSeconds(60)
                ->isFuture()
        ) {
            return $this->errorApi(
                'Please wait 60 seconds before requesting a new OTP',
                429
            );
        }

        $otp = random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => bcrypt($otp),
                'created_at' => now(),
            ]
        );

        $user->notify(new ResetPasswordNotification($otp));

        return $this->successApi(
            null,
            'OTP sent successfully'
        );
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|digits:6',
        ]);

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset) {
            return $this->errorApi('Invalid OTP', 400);
        }

        if (Carbon::parse($reset->created_at)->addMinutes(10)->isPast()) {

            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return $this->errorApi('OTP expired', 400);
        }

        if (! Hash::check($request->otp, $reset->token)) {
            return $this->errorApi('Invalid OTP', 400);
        }

        return $this->successApi(['verified' => true], 'OTP verified successfully');
    }
}
