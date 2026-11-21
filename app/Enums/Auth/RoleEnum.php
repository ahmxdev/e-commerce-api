<?php

namespace App\Enums\Auth;

enum RoleEnum: string
{
    case SUPER_ADMIN = 'super-admin';
    case USER = 'user';
}
