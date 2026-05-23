<?php

namespace App\Http\Controllers\Dashboard\Admins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Admin\AdminStoreRequest;
use App\Http\Requests\Dashboard\Admin\AdminUpdateRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\Storage\StorageService;
use App\Traits\ResponseApi;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    use ResponseApi ;
    public function __construct(private readonly StorageService $storageService)
    {
        $this->authorizeResource(Admin::class , 'admin') ;
    }

    public function index()
    {
        $admins = Admin::with(['role'])->latest()->paginate(15);
        $admins->setCollection(AdminResource::collection($admins->getCollection())->collection);
        return $this->successApi($admins , 'Admins retrieved successfully') ;
    }

    public function store(AdminStoreRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('avatar_url')) {
            $data['avatar_url'] = $this->storageService->upload($request->file('avatar_url'),'avater/admins');
        }
        $admin = Admin::create($data);

        return $this->successApi($admin->load('role') , 'Admin Created Successfully' , 201) ;
    }


    public function update(AdminUpdateRequest $request, Admin $admin)
    {
        $admin->update($request->validated());
        return $this->successApi($admin->fresh('role') , 'Admin Updated Successfully') ;
    }

    public function destroy(Admin $admin)
    {
        if ($admin->getRawOriginal('avatar_url')) {
            Storage::disk('public')->delete($admin->getRawOriginal('avatar_url'));
        }
        $admin->delete();
        return $this->successApi(null, 'Admin deleted successfully');
    }
}
