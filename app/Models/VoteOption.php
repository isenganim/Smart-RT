<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoteOption extends Model
{
    use HasFactory;

    protected $fillable = ['vote_id', 'label'];

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }
}
