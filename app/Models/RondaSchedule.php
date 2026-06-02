<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RondaSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RondaAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
