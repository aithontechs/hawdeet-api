<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Traits\ResponseApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
}
