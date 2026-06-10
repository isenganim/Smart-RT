<?php

namespace App\Enums;

enum ItemStatus: string
{
    case TERSEDIA = 'tersedia';
    case DIPINJAM = 'dipinjam';
    case TIDAK_AKTIF = 'tidak_aktif';

    public function label(): string
    {
        return match ($this) {
            self::TERSEDIA => 'Tersedia',
            self::DIPINJAM => 'Dipinjam',
            self::TIDAK_AKTIF => 'Tidak Aktif',
        };
    }
}
