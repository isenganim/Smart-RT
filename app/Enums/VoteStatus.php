<?php

namespace App\Enums;

enum VoteStatus: string
{
    case DRAFT = 'draft';
    case AKTIF = 'aktif';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::AKTIF => 'Aktif',
            self::SELESAI => 'Selesai',
        };
    }
}
