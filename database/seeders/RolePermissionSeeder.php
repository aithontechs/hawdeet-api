<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Roles', 'permission' => 'role.view'],
            ['name' => 'Show Role', 'permission' => 'role.show'],
            ['name' => 'Create Role', 'permission' => 'role.create'],
            ['name' => 'Update Role', 'permission' => 'role.update'],
            ['name' => 'Delete Role', 'permission' => 'role.delete'],

            ['name' => 'View Permissions', 'permission' => 'permission.view'],
            ['name' => 'Show Permission', 'permission' => 'permission.show'],
            ['name' => 'Create Permission', 'permission' => 'permission.create'],
            ['name' => 'Update Permission', 'permission' => 'permission.update'],
            ['name' => 'Delete Permission', 'permission' => 'permission.delete'],

            ['name' => 'View Users', 'permission' => 'user.view'],
            ['name' => 'Show User', 'permission' => 'user.show'],
            ['name' => 'Create User', 'permission' => 'user.create'],
            ['name' => 'Update User', 'permission' => 'user.update'],
            ['name' => 'Delete User', 'permission' => 'user.delete'],

            ['name' => 'View Categories', 'permission' => 'category.view'],
            ['name' => 'Show Category', 'permission' => 'category.show'],
            ['name' => 'Create Category', 'permission' => 'category.create'],
            ['name' => 'Update Category', 'permission' => 'category.update'],
            ['name' => 'Delete Category', 'permission' => 'category.delete'],

            ['name' => 'View Subscription Plans', 'permission' => 'subscriptionplan.view'],
            ['name' => 'Show Subscription Plan', 'permission' => 'subscriptionplan.show'],
            ['name' => 'Create Subscription Plan', 'permission' => 'subscriptionplan.create'],
            ['name' => 'Update Subscription Plan', 'permission' => 'subscriptionplan.update'],
            ['name' => 'Delete Subscription Plan', 'permission' => 'subscriptionplan.delete'],

            ['name' => 'View Coupons', 'permission' => 'coupon.view'],
            ['name' => 'Show Coupon', 'permission' => 'coupon.show'],
            ['name' => 'Create Coupon', 'permission' => 'coupon.create'],
            ['name' => 'Update Coupon', 'permission' => 'coupon.update'],
            ['name' => 'Delete Coupon', 'permission' => 'coupon.delete'],

            ['name' => 'View Books', 'permission' => 'book.view'],
            ['name' => 'Show Book Details', 'permission' => 'book.show'],
            ['name' => 'Upload Book', 'permission' => 'book.create'],
            ['name' => 'Update Book', 'permission' => 'book.update'],
            ['name' => 'Delete Book', 'permission' => 'book.delete'],
            ['name' => 'Publish Book', 'permission' => 'book.publish'],
            ['name' => 'Unpublish Book', 'permission' => 'book.unpublish'],
            ['name' => 'Stream Full Book', 'permission' => 'book.streamfull'],
            ['name' => 'Stream Preview Book', 'permission' => 'book.streampreview'],

            ['name' => 'View Orders', 'permission' => 'order.view'],
            ['name' => 'Show Order Details', 'permission' => 'order.show'],

            ['name' => 'View User Subscriptions', 'permission' => 'usersubscription.view'],
            ['name' => 'Show User Subscription Details', 'permission' => 'usersubscription.show'],
            ['name' => 'Assign Subscription to User', 'permission' => 'usersubscription.create'],
            ['name' => 'Activate User Subscription', 'permission' => 'usersubscription.activate'],
            ['name' => 'Cancel User Subscription', 'permission' => 'usersubscription.cancel'],

            ['name' => 'View Shipping Zones', 'permission' => 'shippingzone.view'],
            ['name' => 'Show Shipping Zone Details', 'permission' => 'shippingzone.show'],
            ['name' => 'Create Shipping Zone', 'permission' => 'shippingzone.create'],
            ['name' => 'Update Shipping Zone', 'permission' => 'shippingzone.update'],
            ['name' => 'Delete Shipping Zone', 'permission' => 'shippingzone.delete'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['permission' => $permission['permission']],
                ['name' => $permission['name']]
            );
        }

        $role = Role::firstOrCreate([
            'name' => 'Super Admin'
        ]);

        $role->permissions()->sync(
            Permission::pluck('id')->toArray()
        );

        Admin::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name'      => 'Super Admin',
                'phone'     => '0123456789',
                'password'  => '123456789',
                'role_id'   => $role->id,
                'is_active' => true,
            ]
        );
    }
}
