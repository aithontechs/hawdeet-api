<?php

namespace App\Http\Controllers\Dashboard\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Authorize\PermissionRequest;
use App\Models\Permission;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    use ResponseApi ;

    public function __construct()
    {
        $this->authorizeResource(Permission::class, 'permission');
    }

    public function index()
    {
        $permissions = Permission::paginate(20);
        return $this->successApi($permissions , 'Permissions fetched successfully') ;
    }

    public function store(PermissionRequest $request)
    {
        $permission = Permission::insert($request->validated()['permissions']) ;
        return $this->successApi($permission ,'Permission Created successfully');
    }

    public function show(Permission $permission)
    {
        return $this->successApi($permission ,'Permission fetched successfully') ;
    }


    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate(['name' => 'required|string|min:5|max:60']) ;
        $permission->update($validated) ;
        return $this->successApi($permission ,'Permission Updated successfully');
    }


    public function destroy(Permission $permission)
    {
        $permission->delete() ;
        return $this->successApi(null ,'Permission deleted successfully') ;

    }
}
