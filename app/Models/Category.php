<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name' , 'slug' , 'parent_id'];
    protected $hidden = ['created_at' , 'updated_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($category) {
            $category->slug ??= Str::slug($category->name);
        });

        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_categories');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public function isDescendantOf(int $ancestorId): bool
    {
        $parentId = $this->parent_id;
        $visited = [];

        while ($parentId) {
            if ($parentId == $ancestorId) return true;

            if (isset($visited[$parentId])) return false;
            $visited[$parentId] = true;

            $parentId = Category::where('id', $parentId)
                ->value('parent_id');
        }

        return false;
    }
}
