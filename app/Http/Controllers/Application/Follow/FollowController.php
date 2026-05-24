<?php

namespace App\Http\Controllers\Application\Follow;

use App\Http\Controllers\Controller;
use App\Services\Follower\FollowService;
use App\Services\User\ProfileService;
use App\Traits\ResponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    use ResponseApi ;

    public function __construct(protected FollowService $followService , protected ProfileService $profileService)
    {
    }


    public function toggle(int $userId)
    {
        $result = $this->followService->toggle(auth()->user(), $userId);
        if (!$result['success']) {
            return $this->errorApi($result['message'], 422);
        }
        $this->profileService->clearCache(auth()->id());
        $this->profileService->clearCache($userId);
        return $this->successApi($result , 'Operation Execution successfully') ;
    }



    public function followers(int $userId)
    {
        $followers = $this->followService->getFollowers($userId);
        return $this->successApi($followers, 'Followers retrieved successfully');
    }

    public function following(int $userId): JsonResponse
    {
        $following = $this->followService->getFollowing($userId);
        return $this->successApi($following, 'Following retrieved successfully');
    }


    public function stats(int $userId)
    {
        $stats = $this->followService->getStats($userId, auth()->user());
        return $this->successApi($stats, 'Follow stats retrieved successfully');
    }

    public function mutualFollows(Request $request)
    {
        $mutuals = $this->followService->getMutualFollows(auth()->user());
        return $this->successApi($mutuals, 'Followers retrieved successfully');

    }
}
