<?php

namespace App\Http\Controllers\Dashboard\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Setting\SettingUpdateRequest;
use App\Services\Setting\SettingService;
use App\Traits\ResponseApi;

class SettingController extends Controller
{
    use ResponseApi ;

    public function __construct(protected SettingService $settingService)
    {
    }

    public function index()
    {
        $settings = $this->settingService->all() ;
        return $this->successApi($settings, 'Settings retrieved successfully');
    }


    public function update(SettingUpdateRequest $request)
    {
        $settings = $request->input('settings', []);
        unset($settings['platform_logo']);

        if (! $request->has('settings') && ! $request->hasFile('settings.platform_logo')) {
            return $this->errorApi('you must provide either settings or a platform logo', 422);
        }

        if (! empty($settings)) {
            $this->settingService->bulkUpdate($settings);
        }

        if ($request->hasFile('settings.platform_logo')) {
            $this->settingService->updateLogo($request->file('settings.platform_logo'));
        }
        return $this->successApi($this->settingService->all(), 'Settings updated successfully');
    }

}
