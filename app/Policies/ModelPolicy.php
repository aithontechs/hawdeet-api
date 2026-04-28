<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Support\Str;

class ModelPolicy
{
    public function __call($name, $arguments)
    {
        $class = str_replace("Policy", '' ,class_basename($this)); // RolePolicy => Role
        $map = ['viewAny' => 'view' , 'view' => 'show'] ;
        $name = $map[$name] ?? $name;
        $permission = Str::lower($class . '.' . $name ) ; // role.view
        $admin = $arguments[0] ?? null  ;
        if(!$admin) return false ;
        return $admin->hasPermission($permission) ;
    }
}
