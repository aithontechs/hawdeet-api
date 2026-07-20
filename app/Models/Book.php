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
        'physical_hard_cover_price',
        'physical_hard_cover_compare_price',
        'physical_hard_cover_stock',
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
        'file_processed',
        'size_book' ,
        'release_year',
        'price_usd',
        'compare_price_usd',
        'physical_price_usd',
        'physical_compare_price_usd',
        'physical_hard_cover_price_usd',
        'physical_hard_cover_compare_price_usd',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'physical_price' => 'decimal:2',
        'physical_compare_price' => 'decimal:2',
        'physical_stock'=> 'integer',
        'physical_hard_cover_price' => 'decimal:2',
        'physical_hard_cover_compare_price' => 'decimal:2',
        'physical_hard_cover_stock' => 'integer',
        'avg_rating'    => 'float',
        'is_free'       => 'boolean',
        'published'     => 'boolean',
        'published_at'  => 'datetime',
        'age_min'       => 'integer',
        'total_pages'   => 'integer',
        'reviews_count' => 'integer',
        'price_usd' => 'decimal:2',
        'compare_price_usd' => 'decimal:2',
        'physical_price_usd' => 'decimal:2',
        'physical_compare_price_usd' => 'decimal:2',
        'physical_hard_cover_price_usd' => 'decimal:2',
        'physical_hard_cover_compare_price_usd' => 'decimal:2',
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
                    $q->where('categories.name', $filters->integer('category'));
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
        return $this->isPhysical() && ($this->physical_stock > 0 || $this->physical_hard_cover_stock > 0);
    }

    public function isAccessibleByUser(int $userId): bool
    {
        return $this->userBooks()
            ->where('user_id', $userId)
            ->active()
            ->exists();
    }


    public function physicalPriceFor($coverType, string $currency = 'EGP'): ?float
    {
        $field = match ($coverType) {
            'hard_cover' => 'physical_hard_cover_price',
            default      => 'physical_price',
        };

        if ($currency === 'USD') {
            return $this->{$field . '_usd'} !== null
                ? (float) $this->{$field . '_usd'}
                : (float) $this->{$field};
        }

        return (float) $this->{$field};
    }

    public function physicalStockFor(string $coverType): int
    {
        return match ($coverType) {
            'hard_cover' => (int) $this->physical_hard_cover_stock,
            default      => (int) $this->physical_stock,
        };
    }

    public function offersCoverType(string $coverType): bool
    {
        if (!$this->isPhysical()) return false;

        return match ($coverType) {
            'hard_cover' => !is_null($this->physical_hard_cover_price),
            'normal'     => !is_null($this->physical_price),
            default      => false,
        };
    }

    public function hasPhysicalStockFor(string $coverType)
    {
        return match ($coverType) {
            'normal'  => $this->isPhysical() && $this->physical_stock > 0,
            'hard_cover' => $this->isPhysical() && $this->physical_hard_cover_stock > 0,
            default   => false,
        };
    }

    public function digitalPriceFor(string $currency = 'EGP'): ?float
    {
        if ($currency === 'USD') {
            return $this->price_usd !== null ? (float) $this->price_usd : (float) $this->price;
        }
        return (float) $this->price;
    }

    public function comparePriceFor(string $field, string $currency = 'EGP'): ?float
    {
        if ($currency === 'USD') {
            $usdField = $field . '_usd';
            if ($this->{$usdField} !== null) {
                return (float) $this->{$usdField};
            }
        }
        return $this->{$field} !== null ? (float) $this->{$field} : null;
    }

}
