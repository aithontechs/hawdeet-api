<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'   => 'platform_name',
                'value' => 'My Platform',
                'type'  => 'string',
                'group' => 'general',
            ],
            [
                'key'   => 'default_language',
                'value' => 'ar',
                'type'  => 'string',
                'group' => 'general',
            ],
            [
                'key'   => 'current_currency',
                'value' => 'EGP',
                'type'  => 'string',
                'group' => 'general',
            ],

            [
                'key'   => 'max_failed_login_attempts',
                'value' => '5',
                'type'  => 'integer',
                'group' => 'users_registration',
            ],
            [
                'key'   => 'temporary_ban_duration_minutes',
                'value' => '15',
                'type'  => 'integer',
                'group' => 'users_registration',
            ],

            [
                'key'   => 'platform_logo',
                'value' => null,
                'type'  => 'string',
                'group' => 'general',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
