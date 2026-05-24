<?php

namespace App\Http\Controllers\Application\Setting;

use App\Http\Controllers\Controller;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    use ResponseApi ;
    public function update(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|max:25|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->errorApi('Current password is incorrect.', 422);
        }

        $user->password = $request->new_password;
        $user->save();

        return $this->successApi($user, 'Password changed successfully.');
    }
}
