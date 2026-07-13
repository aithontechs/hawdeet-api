<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['name' , 'permission'];
    public $timestamps = false ;

    public function scopeFilter(Builder $builder, array $filters)
    {
        $builder->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('permission', 'like', '%' . $search . '%');
        });
    }
}
