<?php

namespace App\Enums;

enum LetterType: string
{
    case DOMISILI = 'domisili';
    case USAHA = 'usaha';
    case TIDAK_MAMPU = 'tidak_mampu';
    case PENGANTAR_KTP = 'pengantar_ktp';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::DOMISILI => 'Surat Keterangan Domisili',
            self::USAHA => 'Surat Keterangan Usaha',
            self::TIDAK_MAMPU => 'Surat Keterangan Tidak Mampu',
            self::PENGANTAR_KTP => 'Pengantar KTP',
            self::LAINNYA => 'Lainnya',
        };
    }
}
