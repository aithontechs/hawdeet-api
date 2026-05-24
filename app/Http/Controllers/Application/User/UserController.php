<?php

namespace App\Http\Controllers\Application\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\User\UserUpdateRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User ;
use App\Services\Book\BookReadingProgressService;
use App\Services\Storage\StorageService;
use App\Services\User\ProfileService;
use App\Traits\ResponseApi;

class UserController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly StorageService $storageService , private readonly BookReadingProgressService $progressService ,
                                private readonly ProfileService $profileService)
    {

    }
    
    // MY PROFILE
    public function profile()
    {
        $user = auth()->user();
        $stats = $this->profileService->getProfileStats($user);

        $user->load([
            'subscriptions' => fn ($q) =>
                $q->select(['id','user_id','start_at','end_at','price','status','payment_status','canceled_at','ended_reason'])
                ->latest()->limit(1),
            'authorBooks' => fn ($q) =>
                $q->select(['id','author_id','title','cover','avg_rating'])
                ->where('published', true),
        ]);

        $user->followers_count = $stats['followers_count'];
        $user->following_count = $stats['following_count'];

        $readingStats = $stats['reading_stats'] ?? [];

        return $this->successApi((new ProfileResource($user))->withReadingStats($readingStats),'My profile fetched successfully');
    }

    public function anyProfile(int $id)
    {
        $user = User::findOrFail($id);
        $stats = $this->profileService->getProfileStats($user);

        $user->load([
            'authorBooks' => fn ($q) =>
                $q->select(['id', 'author_id', 'title', 'cover', 'avg_rating'])
                ->where('published', true),
        ]);

        $user->followers_count = $stats['followers_count'];
        $user->following_count = $stats['following_count'];

        $readingStats = $stats['reading_stats'] ?? [];
        return $this->successApi((new ProfileResource($user))->withReadingStats($readingStats),'Profile fetched successfully');
    }

    public function updateProfile(UserUpdateRequest $request)
    {
        $user = auth()->user();

        $data = $request->validated();
        if ($request->hasFile('avatar_url')) {
            $data['avatar_url'] = $this->storageService->replace(
                $request->file('avatar_url'),
                $user->avatar_url,
                'avatar/users'
            );
        }
        $user->update($data);
        $this->profileService->clearCache($user->id);
        return $this->successApi($user, 'Profile updated successfully');
    }
}
