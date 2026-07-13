<?php

namespace App\Http\Controllers\Dashboard\ReadingCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\ReadingCouncil\{CreateCouncilRequest};
use App\Http\Requests\Application\ReadingCouncil\UpdateCouncilRequest;
use App\Models\{ReadingCouncil};
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
    ) {
        $this->authorizeResource(ReadingCouncil::class, 'council') ;

    }

    public function index(Request $request)
    {
        $status = $request->status ?? 'all';
        return $this->successApi($this->councilService->getAllDashboard($status),'Councils fetched successfully');
    }


    public function show(ReadingCouncil $council)
    {
        $user = auth('admin-api')->user();
        return $this->successApi($this->councilService->getOne($council, $user),'Council fetched successfully');
    }

    public function store(CreateCouncilRequest $request)
    {
        $actor = auth('admin-api')->user();
        $council = $this->councilService->create($request->validated(), $actor);
        return $this->successApi($council, 'Council created successfully', 201);
    }

    public function update(UpdateCouncilRequest $request, ReadingCouncil $council)
    {
        $actor = auth('admin-api')->user();
        $updated = $this->councilService->update($council, $request->validated(), $actor);
        return $this->successApi($updated, 'Council updated successfully');
    }

    public function destroy(ReadingCouncil $council)
    {
        $actor = auth('admin-api')->user();
        $this->councilService->delete($council, $actor);
        return $this->successApi(null, 'Council deleted successfully');
    }
}
