<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureSetting extends Model
{
    protected $fillable = ['key', 'is_enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
