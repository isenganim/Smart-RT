<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'household_id',
        'resident_id',
        'ronda_scan_session_id',
        'reverses_id',
        'type',
        'amount',
        'status',
        'cancelled_at',
        'cancelled_by',
        'source',
        'recorded_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'amount' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function scanSession(): BelongsTo
    {
        return $this->belongsTo(RondaScanSession::class, 'ronda_scan_session_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class, 'reverses_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'reverses_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeIuranHarian(Builder $query): Builder
    {
        return $query->where('type', TransactionType::IURAN_HARIAN->value);
    }

    public function scopeDenda(Builder $query): Builder
    {
        return $query->where('type', TransactionType::DENDA->value);
    }
}
