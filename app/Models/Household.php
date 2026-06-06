<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Household extends Model
{
    use HasFactory;

    protected $fillable = [
        'house_number',
        'address',
        'head_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Household $household) {
            if (blank($household->qr_token)) {
                $household->qr_token = (string) Str::uuid();
            }
        });
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function activeResidents(): HasMany
    {
        return $this->residents()->where('is_active', true);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }
}
