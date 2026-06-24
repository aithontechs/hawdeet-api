<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\User\UserRequest;
use App\Models\User;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ResponseApi ;

    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $users = User::query()->latest()->search($request->search)->type($request->type)
                    ->paginate(15);
        return $this->successApi($users , 'Users Fetched successfully') ;
    }


    public function store(UserRequest $request)
    {
        $validated = $request->validated();
        $validated['email_verified_at'] = now() ;
        $user = User::create($validated);
        return $this->successApi($user ,'User Created successfully',201) ;
    }

    public function show(User $user)
    {
        return $this->successApi($user ,'User fetched successfully') ;
    }


    public function update(UserRequest $request, User $user)
    {
        $user->update($request->validated());
        return $this->successApi($user ,'User updated successfully') ;
    }

    public function destroy(User $user)
    {
        $user->delete();
        return $this->successApi(null ,'User deleted successfully') ;
    }

}
