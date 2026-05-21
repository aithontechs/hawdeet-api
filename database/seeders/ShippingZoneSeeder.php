<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingZoneSeeder extends Seeder
{
public function run(): void
    {
        $zones = [
            ['name' => 'القاهرة', 'cost' => 50, 'days_min' => 1, 'days_max' => 2],
            ['name' => 'الجيزة', 'cost' => 55, 'days_min' => 1, 'days_max' => 2],
            ['name' => 'الإسكندرية', 'cost' => 70, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'الدقهلية', 'cost' => 65, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'البحر الأحمر', 'cost' => 120, 'days_min' => 3, 'days_max' => 5],
            ['name' => 'البحيرة', 'cost' => 75, 'days_min' => 2, 'days_max' => 4],
            ['name' => 'الفيوم', 'cost' => 70, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'الغربية', 'cost' => 65, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'الإسماعيلية', 'cost' => 80, 'days_min' => 2, 'days_max' => 4],
            ['name' => 'المنوفية', 'cost' => 60, 'days_min' => 1, 'days_max' => 3],
            ['name' => 'المنيا', 'cost' => 90, 'days_min' => 3, 'days_max' => 4],
            ['name' => 'القليوبية', 'cost' => 55, 'days_min' => 1, 'days_max' => 2],
            ['name' => 'الوادي الجديد', 'cost' => 150, 'days_min' => 4, 'days_max' => 6],
            ['name' => 'السويس', 'cost' => 85, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'اسوان', 'cost' => 140, 'days_min' => 4, 'days_max' => 6],
            ['name' => 'اسيوط', 'cost' => 100, 'days_min' => 3, 'days_max' => 5],
            ['name' => 'بني سويف', 'cost' => 80, 'days_min' => 2, 'days_max' => 4],
            ['name' => 'بورسعيد', 'cost' => 90, 'days_min' => 2, 'days_max' => 4],
            ['name' => 'دمياط', 'cost' => 75, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'جنوب سيناء', 'cost' => 130, 'days_min' => 3, 'days_max' => 5],
            ['name' => 'كفر الشيخ', 'cost' => 70, 'days_min' => 2, 'days_max' => 3],
            ['name' => 'مطروح', 'cost' => 160, 'days_min' => 4, 'days_max' => 6],
            ['name' => 'الأقصر', 'cost' => 130, 'days_min' => 4, 'days_max' => 5],
            ['name' => 'قنا', 'cost' => 120, 'days_min' => 3, 'days_max' => 5],
            ['name' => 'شمال سيناء', 'cost' => 140, 'days_min' => 4, 'days_max' => 6],
            ['name' => 'سوهاج', 'cost' => 110, 'days_min' => 3, 'days_max' => 5],
        ];

        foreach ($zones as $index => $zone) {

            DB::table('shipping_zones')->insert([
                'name'       => $zone['name'],
                'cost'       => $zone['cost'],
                'days_min'   => $zone['days_min'],
                'days_max'   => $zone['days_max'],
                'is_active'  => true,

                'is_default' => $index === 0,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
