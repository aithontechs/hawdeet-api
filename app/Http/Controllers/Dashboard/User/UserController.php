<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\User\UserRequest;
use App\Models\User;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    use ResponseApi ;

    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $users = User::query()->where('is_author' , 0)->latest()->search($request->search)
            ->when($request->filled('plan_id'), function ($query) use ($request) {
                $query->whereHas('subscriptions', function ($q) use ($request) {
                    $q->where('plan_id', $request->plan_id)
                    ->where('status', 'active');
                });
        })->when($request->filled('is_active') , function($query) use ($request){
            $query->where('is_active' , $request->is_active) ;
        })->paginate(15);
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

    public function export(Request $request)
    {
        $fileName = 'users_' . now()->format('Y_m_d_His') . '.xlsx';
        return Excel::download(new UsersExport($request->search), $fileName);
    }

}
