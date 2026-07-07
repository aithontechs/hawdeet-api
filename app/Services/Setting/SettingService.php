<?php

namespace App\Services\Setting ;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingService
{
    protected const CACHE_KEY = 'settings.all';

    public function all()
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()->mapWithKeys(function ($setting) {
                $value = $setting->casted_value;

                if ($setting->key === 'platform_logo' && $value) {
                    $value = url('/storage/' . $value);
                }

                return [$setting->key => $value];
            });
        });
    }

    public function get(string $key, $default = null)
    {
        return $this->all()->get($key, $default);
    }

    public function bulkUpdate(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $existing = Setting::where('key', $key)->first();

            if (! $existing) {
                continue;
            }

            $existing->update([
                'value' => in_array($existing->type, ['json', 'array']) ? json_encode($value) : $value,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function updateLogo(UploadedFile $file)
    {
        $setting = Setting::where('key', 'platform_logo')->first();

        if ($setting && $setting->value && Storage::disk('public')->exists($setting->value)) {
            Storage::disk('public')->delete($setting->value);
        }

        $path = $file->store('settings/logo', 'public');

        $setting = Setting::updateOrCreate(
            ['key' => 'platform_logo'],
            ['value' => $path, 'type' => 'string', 'group' => 'general']
        );
        Cache::forget(self::CACHE_KEY);
        return $setting;
    }
}
