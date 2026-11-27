<?php

namespace App\Enums\Auth;

enum PermissionEnum: string
{
    case ManageCategories = 'manage-categories';
    case ManageProducts = 'manage-products';
    case ViewOrders = 'view-orders';
    case ManageOrders = 'manage-orders';
}
