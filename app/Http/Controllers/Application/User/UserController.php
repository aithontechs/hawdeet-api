<?php

namespace App\Http\Controllers\Application\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\User\UserUpdateRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User ;
use App\Services\Auth\PhoneNormalizer;
use App\Services\Book\BookReadingProgressService;
use App\Services\Currency\PhoneCurrencyService;
use App\Services\Storage\StorageService;
use App\Services\User\ProfileService;
use App\Traits\ResponseApi;

class UserController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly StorageService $storageService , private readonly BookReadingProgressService $progressService ,
                                private readonly ProfileService $profileService , private readonly PhoneCurrencyService $phoneCurrencyService,
                                private readonly PhoneNormalizer $phoneNormalizer)
    {

    }

    // MY PROFILE
    public function profile()
    {
        $user = auth()->user();
        $stats = $this->profileService->getProfileStats($user);

        $user->load([
            'subscriptions' => fn ($q) =>
                $q->select(['id','user_id','start_at','end_at','price','status','payment_status','canceled_at','ended_reason' , 'plan_id'])
                ->with('plan:id,name,duration_months,price,compare_price')->latest()->limit(1),
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
        $oldAvatar = $user->getRawOriginal('avatar_url');
        if ($request->hasFile('avatar_url')) {
            $data['avatar_url'] = $this->storageService->replace($request->file('avatar_url'),$oldAvatar,'avatar/users');
        }
        if (isset($data['phone'])) {
            $data['phone'] = $this->phoneNormalizer->normalize($data['phone']);
            $data['preferred_currency'] = $this->phoneCurrencyService->resolveFromPhoneOrDefault($data['phone'] ?? null);
        }
        $user->update($data);
        $this->profileService->clearCache($user->id);
        return $this->successApi($user, 'Profile updated successfully');
    }

    public function updateProfileForApp(UserUpdateRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        $oldAvatar = $user->getRawOriginal('avatar_url');
        if ($request->hasFile('avatar_url')) {
            $data['avatar_url'] = $this->storageService->replace(
                $request->file('avatar_url'),
                $oldAvatar,
                'avatar/users'
            );
        }
        if (isset($data['phone'])) {
            $data['phone'] = $this->phoneNormalizer->normalize($data['phone']);
            $data['preferred_currency'] = $this->phoneCurrencyService->resolveFromPhoneOrDefault($data['phone'] ?? null);
        }
        $user->update($data);

        $this->profileService->clearCache($user->id);

        return $this->successApi($user, 'Profile updated successfully');
    }
}
