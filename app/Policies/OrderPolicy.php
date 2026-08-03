<?php

namespace App\Policies;

use App\Models\Admin;

class OrderPolicy extends ModelPolicy
{
    public function __construct()
    {
        //
    }

    public function status(Admin $admin): bool
    {
        return $admin->hasPermission('order.status') ;
    }
}
