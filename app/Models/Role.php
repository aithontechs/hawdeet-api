<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'] ;


    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    public static function createWithPermission($data)
    {
        return DB::transaction(function () use ($data) {
            $role = self::create([
                'name' => $data['name'],
            ]);
            $permissions = array_unique($data['permissions'] ?? []);
            if(!empty($permissions)){
                $role->permissions()->syncWithoutDetaching($permissions) ;
            }
            return $role->load('permissions');
        });
    }

    public function updateWithPermissions(array $data)
    {
        return DB::transaction(function () use ($data) {
            $this->update([
                'name' => $data['name'] ?? $this->name,
            ]);

            if(isset($data['permissions'])){
                $permissions = array_unique($data['permissions']);
                $this->permissions()->sync($permissions);
            }

            return $this->load('permissions');
        });
    }



    public function permissions()
    {
        return $this->belongsToMany( Permission::class, 'role_permissions' ,  'role_id','permission_id');
    }
}
