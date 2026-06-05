<?php

namespace App\Http\Controllers\Application\ReadingCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\ReadingCouncil\{CreateCouncilRequest};
use App\Http\Requests\Application\ReadingCouncil\UpdateCouncilRequest;
use App\Models\{Comment, ReadingCouncil};
use App\Models\CouncilComment;
use App\Services\ReadingCouncil\{CouncilCommentService, ReadingCouncilService};
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class ReadingCouncilController extends Controller
{
    use ResponseApi;

    public function __construct(
        private readonly ReadingCouncilService $councilService,
        private readonly CouncilCommentService $commentService,
    ) {}

    public function index(Request $request)
    {
        $status = $request->status ?? 'all';
        return $this->successApi($this->councilService->getAll($status),'Councils fetched successfully');
    }

    public function featured()
    {
        return $this->successApi($this->councilService->getFeatured(),'Featured council fetched successfully');
    }

    public function show(ReadingCouncil $council)
    {
        $user = auth('user-api')->user();
        return $this->successApi($this->councilService->getOne($council, $user),'Council fetched successfully');
    }

    public function store(CreateCouncilRequest $request)
    {
        $actor = auth('user-api')->user() ?? auth('admin-api')->user();
        $council = $this->councilService->create($request->validated(), $actor);
        return $this->successApi($council, 'Council created successfully', 201);
    }

    public function update(UpdateCouncilRequest $request, ReadingCouncil $council)
    {
        $actor = auth('user-api')->user() ?? auth('admin-api')->user();
        $updated = $this->councilService->update($council, $request->validated(), $actor);
        return $this->successApi($updated, 'Council updated successfully');
    }

    public function destroy(ReadingCouncil $council)
    {
        $actor = auth('user-api')->user() ?? auth('admin-api')->user();
        $this->councilService->delete($council, $actor);
        return $this->successApi(null, 'Council deleted successfully');
    }

    public function join(ReadingCouncil $council)
    {
        $user = auth('user-api')->user();
        $this->councilService->join($council, $user);
        return $this->successApi(null, 'Joined successfully');
    }

    public function leave(ReadingCouncil $council)
    {
        $user = auth('user-api')->user();
        $this->councilService->leave($council, $user);
        return $this->successApi(null, 'Left successfully');
    }

    public function members(ReadingCouncil $council)
    {
        return $this->successApi(
            $this->councilService->getMembers($council),
            'Members fetched successfully'
        );
    }


    public function comments(ReadingCouncil $council)
    {
        return $this->successApi($this->commentService->getComments($council),'Comments fetched successfully');
    }

    public function addComment(Request $request, ReadingCouncil $council)
    {
        $request->validate([
            'body'      => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:council_comments,id',
        ]);

        return $this->successApi(
            $this->commentService->addComment(
                $council,
                auth('user-api')->user(),
                $request->body,
                $request->parent_id
            ),
            'Comment added successfully',
            201
        );
    }

    public function updateComment(Request $request, ReadingCouncil $council, CouncilComment $comment)
    {
        $request->validate(['body' => 'required|string|max:1000']);

        return $this->successApi(
            $this->commentService->updateComment(
                $comment,
                auth('user-api')->user(),
                $request->body
            ),
            'Comment updated successfully'
        );
    }

    public function deleteComment(ReadingCouncil $council, CouncilComment $comment)
    {
        $this->commentService->deleteComment($comment, auth('user-api')->user());
        return $this->successApi(null, 'Comment deleted successfully');
    }

    public function replies(CouncilComment $comment)
    {
        return $this->successApi($this->commentService->getReplies($comment),'Replies fetched successfully');
    }
}
