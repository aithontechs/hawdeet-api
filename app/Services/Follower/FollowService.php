<?php

namespace App\Services\Follower;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FollowService
{
    public function toggle(User $authUser, int $targetUserId): array
    {
        $target = User::findOrFail($targetUserId);

        if ($authUser->id === $target->id) {
            return [
                'success' => false,
                'message' => 'You cannot follow yourself.',
            ];
        }

        $action = $authUser->toggleFollow($target->id);

        return [
            'success'          => true,
            'action'           => $action,
            'followers_count'  => $target->followersCount(),
            'is_following'     => $action === 'followed',
        ];
    }

    public function getFollowers(int $userId, int $perPage = 15)
    {
        $user = User::findOrFail($userId);

        return $user->followers()
            ->paginate($perPage)
            ->through(function ($follower) {
                $follower->setVisible(['id', 'name', 'avatar_url', 'is_author']);
                return $follower;
            });
    }

    public function getFollowing(int $userId, int $perPage = 15)
    {
        $user = User::findOrFail($userId);

        return $user->following()
            ->paginate($perPage)
            ->through(function ($followedUser) {
                $followedUser->setVisible(['id', 'name', 'avatar_url', 'is_author']);
                return $followedUser;
            });
    }

    public function getStats(int $userId, ?User $authUser = null): array
    {
        $user = User::findOrFail($userId);

        return [
            'followers_count' => $user->followersCount(),
            'following_count' => $user->followingCount(),
            'is_following'    => $authUser ? $authUser->isFollowing($userId) : null,
            'is_followed_by'  => $authUser ? $authUser->isFollowedBy($userId) : null,
            'is_mutual'       => $authUser ? $authUser->isMutualFollow($userId) : null,
        ];
    }

    public function getMutualFollows(User $authUser, int $perPage = 20)
    {
        return $authUser->following()
                    ->whereIn('users.id', $authUser->followers()->pluck('users.id'))
                    ->select(['users.id', 'users.name', 'users.avatar_url'])
                    ->paginate($perPage);
    }
}
