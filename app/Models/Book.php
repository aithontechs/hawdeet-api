<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
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
        'type',
        'price',
        'compare_price',
        'physical_price',
        'physical_compare_price',
        'physical_stock',
        'age_min',
        'total_pages',
        'avg_rating',
        'reviews_count',
        'is_free',
        'published',
        'published_at',
        'uploaded_by',
        'author_id',
        'is_subscription_included',
        'file_processed'
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'physical_price' => 'decimal:2',
        'physical_compare_price' => 'decimal:2',
        'physical_stock'=> 'integer',
        'avg_rating'    => 'float',
        'is_free'       => 'boolean',
        'published'     => 'boolean',
        'published_at'  => 'datetime',
        'age_min'       => 'integer',
        'total_pages'   => 'integer',
        'reviews_count' => 'integer',
    ];

    protected $hidden = ['file', 'preview' , 'cover'];
    protected $appends = ['cover_url'] ;

    // scope filters in dashboard
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
                    $q->where('categories.id', $filters->integer('category'));
                });
            });
    }

    // scope search in app
    public function scopeSearch(Builder $query, $filters)
    {
        return $query->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['sort'] ?? null, function ($q, $sort) {
                if ($sort === 'price_asc') {
                    $q->orderBy('price', 'asc');
                } elseif ($sort === 'price_desc') {
                    $q->orderBy('price', 'desc');
                }
            });
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['categories','uploader:id,name','author:id,name']);
    }

    public function scopeDigital($query)
    {
        return $query->whereIn('type', ['digital', 'both']);
    }

    public function scopePhysical($query)
    {
        return $query->whereIn('type', ['physical', 'both']);
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

    public function userBooks()
    {
        return $this->hasMany(UserBook::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(BookReview::class);
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



    public function hasReviewBy(int $userId): bool
    {
        return $this->reviews()->where('user_id', $userId)->exists();
    }

    public function isDigital(): bool
    {
        return in_array($this->type, ['digital', 'both']);
    }

    public function isPhysical(): bool
    {
        return in_array($this->type, ['physical', 'both']);
    }

    public function hasPhysicalStock(): bool
    {
        return $this->isPhysical() && $this->physical_stock > 0;
    }

    public function isAccessibleByUser(int $userId): bool
    {
        return $this->userBooks()
            ->where('user_id', $userId)
            ->active()
            ->exists();
    }

}
