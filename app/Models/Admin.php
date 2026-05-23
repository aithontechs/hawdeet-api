<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;


class Admin extends Authenticatable implements JWTSubject
{
    use HasFactory , HasApiTokens , Notifiable ;

    protected $fillable = [ 'name' , 'email' , 'phone' , 'password' , 'role_id' , 'avatar_url' , 'is_active' ] ;


    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'password' => 'hashed' ,
        'is_active' => 'boolean' ,
        'email_verified_at' => 'datetime',
    ] ;

    public function getAvatarUrlAttribute()
    {
        if (!$this->attributes['avatar_url']) {
            return null;
        }

        $avatar = $this->attributes['avatar_url'];

        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($avatar);
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }


    public function hasPermission(string $permission): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role.permissions');
        }

        return $this->role?->permissions
            ->contains('permission', $permission) ?? false;
    }

}
