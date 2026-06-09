<?php

namespace App\Models;

use App\Enums\VoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vote extends Model
{
    use HasFactory;

    protected $fillable = ['question', 'status', 'starts_at', 'ends_at', 'created_by'];

    protected function casts(): array
    {
        return ['status' => VoteStatus::class, 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VoteOption::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }

    public function isOpen(): bool
    {
        if ($this->status !== VoteStatus::AKTIF) {
            return false;
        }

        return (! $this->starts_at || now()->gte($this->starts_at->copy()->startOfDay()))
            && (! $this->ends_at || now()->lte($this->ends_at->copy()->endOfDay()));
    }
}
