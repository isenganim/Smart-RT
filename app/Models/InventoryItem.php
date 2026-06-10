<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'condition',
        'status',
        'location',
        'holder',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'status' => ItemStatus::class,
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', ItemStatus::TERSEDIA->value);
    }

    public function isOnLoan(): bool
    {
        return $this->status === ItemStatus::DIPINJAM;
    }
}
