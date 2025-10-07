<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversion extends Model
{
    protected $fillable = [
        'user_id',
        'source_language',
        'target_language',
        'source_code',
        'converted_code',
        'conversion_status',
        'rdp_parsing_time_ms',
        'conversion_time_ms',
        'ast_nodes',
        'tokens_processed',
        'memory_usage_kb',
        'error_recovery_count',
        'syntax_accuracy',
        'semantic_preservation',
        'syntax_errors',
        'semantic_analysis',
    ];

    protected $casts = [
        'syntax_errors' => 'array',
        'semantic_analysis' => 'array',
        'syntax_accuracy' => 'decimal:2',
        'semantic_preservation' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    public function errorLogs(): HasMany
    {
        return $this->hasMany(ErrorLog::class);
    }
}
