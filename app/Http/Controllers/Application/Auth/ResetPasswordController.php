<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ResponseApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ResetPasswordController extends Controller
{
    use ResponseApi ;

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required',
            'password' => ['required' , 'confirmed' , 'max:50', Password::min(12)->mixedCase()->symbols()->numbers()]
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $reset) {
            return $this->errorApi('Invalid token' , 400) ;
        }

        if (Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
            return $this->errorApi('Token expired' , 400) ;
        }

        if (! Hash::check($request->token, $reset->token)) {
            return $this->errorApi('Invalid token' , 400) ;
        }

        $user = User::where('email', $request->email)->first();

        $user->update([
            'password' => $request->password
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->successApi(null ,'Password reset successfully' ) ;

    }

    public function resetWithinOtp(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|digits:6',
            'password' => ['required' , 'confirmed' , 'max:50', Password::min(12)->mixedCase()->symbols()->numbers()]
        ]);

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset) {
            return $this->errorApi('Invalid OTP', 400);
        }

        if (Carbon::parse($reset->created_at)->addMinutes(10)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return $this->errorApi('OTP expired', 400);
        }

        if (!Hash::check($request->otp, $reset->token)) {
            return $this->errorApi('Invalid OTP', 400);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->successApi(null, 'Password reset successfully');
    }
}
