<?php

namespace App\Http\Controllers\Application\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Community\Share\ShareService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    use ResponseApi ;
    public function __construct(private readonly ShareService $shareService) {}

    public function share(Request $request, Post $post)
    {
        $result = $this->shareService->share($request->user(), $post);
        return $this->successApi($result , 'The post shared successfully') ;
    }

    public function unshare(Request $request, Post $post)
    {
        $result = $this->shareService->unshare($request->user(), $post);
        return $this->successApi($result , 'The post removed from you shared successfully') ;
    }
}
