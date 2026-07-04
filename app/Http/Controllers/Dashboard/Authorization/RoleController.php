<?php

namespace App\Http\Controllers\Dashboard\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Authorize\RoleRequest;
use App\Models\Role;
use App\Policies\RolePolicy;
use App\Traits\ResponseApi;

class RoleController extends Controller
{
    use ResponseApi ;
    public function __construct()
    {
        $this->authorizeResource(Role::class, 'role') ;
    }

    public function index()
    {
        $roles = Role::select('id','name')->get(); // from 3 : 5
        return $this->successApi($roles , 'Roles fetched successfully') ;
    }

    public function store(RoleRequest $request)
    {
        $roleAlreadyExists = Role::where('name' , $request->name)->first() ;
        if ($roleAlreadyExists) {
            return $this->errorApi('Role already exists before' , 400) ;
        }
        $role = Role::createWithPermission($request->validated()) ;
        return $this->successApi($role ,'Role Created successfully');
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        return $this->successApi($role ,'Role fetched successfully') ;
    }

    public function update(RoleRequest $request, Role $role)
    {
        $role = $role->updateWithPermissions($request->validated()) ;
        return $this->successApi($role ,'Role Updated successfully');
    }

    public function destroy(Role $role)
    {
        $role->delete() ;
        return $this->successApi($role ,'Role deleted successfully') ;
    }
}
