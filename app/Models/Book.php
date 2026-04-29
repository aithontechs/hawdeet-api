<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover',
        'file',
        'preview',
        'price',
        'compare_price',
        'age_min',
        'total_pages',
        'avg_rating',
        'reviews_count',
        'is_free',
        'published',
        'published_at',
        'uploaded_by',
        'author_id'
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'avg_rating'    => 'float',
        'is_free'       => 'boolean',
        'published'     => 'boolean',
        'published_at'  => 'datetime',
        'age_min'       => 'integer',
        'total_pages'   => 'integer',
        'reviews_count' => 'integer',
    ];

    protected $hidden = ['file', 'preview' , 'cover'];

    // scope filters
    public function scopeFilter($query, $filters)
    {
        return $query
            ->when($filters->boolean('free'), fn ($q) => $q->free())


            ->when($filters->filled('status'), function ($q) use ($filters) {

                if ($filters->status === 'published') {
                    return $q->where('published', true);
                }

                if ($filters->status === 'unpublished') {
                    return $q->where('published', false);
                }

                return $q;
            })
            ->when($filters->filled('category'), function ($q) use ($filters) {
                $q->whereHas('categories', function ($q) use ($filters) {
                    $q->where('id', $filters->integer('category'));
                });
            });
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['categories','uploader:id,name','author:id,name']);
    }

    // Relationships
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'book_categories');
    }

    public function uploader()
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }



    // storage accessors
    public function getCoverUrlAttribute()
    {
        return $this->cover ? Storage::disk('public')->url($this->cover) : null;
    }


    public function getFileUrlAttribute(): ?string
    {
        return $this->file ? Storage::disk('private')->temporaryUrl($this->file, now()->addMinutes(30)) : null;
    }
}
