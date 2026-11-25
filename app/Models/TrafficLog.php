<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'logged_rule_ids' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }
}
