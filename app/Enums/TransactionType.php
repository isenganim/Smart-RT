<?php

namespace App\Enums;

enum TransactionType: string
{
    case IURAN_HARIAN = 'iuran_harian';
    case DENDA = 'denda';
    case KOREKSI = 'koreksi';
    case PENGELUARAN = 'pengeluaran';

    public function label(): string
    {
        return match ($this) {
            self::IURAN_HARIAN => 'Iuran Harian',
            self::DENDA => 'Denda Ronda',
            self::KOREKSI => 'Koreksi',
            self::PENGELUARAN => 'Pengeluaran',
        };
    }
}
