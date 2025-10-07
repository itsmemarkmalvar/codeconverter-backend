<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Execution extends Model
{
    protected $fillable = [
        'conversion_id',
        'user_id',
        'language',
        'code',
        'execution_output',
        'compilation_errors',
        'runtime_errors',
        'execution_time_ms',
        'compilation_time_ms',
        'memory_usage_kb',
        'success',
        'exit_code',
        'performance_metrics',
    ];

    protected $casts = [
        'compilation_errors' => 'array',
        'runtime_errors' => 'array',
        'performance_metrics' => 'array',
        'success' => 'boolean',
    ];

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
