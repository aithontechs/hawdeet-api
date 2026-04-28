<?php

namespace App\Http\Controllers\Dashboard\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Traits\ResponseApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    use ResponseApi ;

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:admins,email',
            'token' => 'required',
            'password' => 'required|min:8|max:25|confirmed'
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

        $admin = Admin::where('email', $request->email)->first();

        $admin->update([
            'password' => $request->password
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->successApi(null ,'Password reset successfully' ) ;

    }
}
