<?php
// app/Http/Resources/ProfileResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    private array $readingStats = [];

    public function withReadingStats(array $stats): static
    {
        $this->readingStats = $stats;
        return $this;
    }

    public function toArray($request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'bio'    => $this->bio,
            'avatar_url' => $this->avatar_url,
            'is_author'  => $this->is_author,
            'is_active'  => $this->is_active,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,


            'subscription' => $this->whenLoaded('subscriptions',
                fn() => $this->subscriptions->first()
            ),

            // Author books
            $this->is_author
                ? 'author_stats' : 'reading_stats'
                => $this->is_author
                ? [
                    'published_books_count' => $this->authorBooks->count(),
                    'books' => $this->authorBooks->map(fn($book) => [
                        'id'         => $book->id,
                        'title'      => $book->title,
                        'cover_url'  => $book->cover_url,
                        'avg_rating' => $book->avg_rating,
                    ]),
                ]
                : [
                    'library_count'       => $this->readingStats['library_count']       ?? 0,
                    'completed_count'     => $this->readingStats['completed_count']     ?? 0,
                    'reading_count'       => $this->readingStats['reading_count']       ?? 0,
                    'monthly_achievement' => $this->readingStats['monthly_achievement'] ?? 0,
                ],
        ];
    }
}
