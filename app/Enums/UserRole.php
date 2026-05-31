<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN_RT = 'admin_rt';
    case BENDAHARA = 'bendahara';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_RT => 'Admin RT',
            self::BENDAHARA => 'Bendahara',
        };
    }
}
