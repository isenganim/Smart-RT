<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RondaScanSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'pin',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RondaScanSession $session) {
            if (blank($session->pin)) {
                $session->pin = self::generatePin();
            }
        });
    }

    public static function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $now->betweenIncluded($this->starts_at, $this->ends_at);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'ronda_scan_session_id');
    }
}
