<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    protected $fillable = [
        'priority', 'src_ip', 'dst_ip', 'protocol', 'dst_port',
        'time_start', 'time_end', 'action', 'enabled', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'dst_port' => 'integer',   // null tetap null (rule tanpa port)
            'enabled'  => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trafficLogs(): HasMany
    {
        return $this->hasMany(TrafficLog::class);
    }
}
