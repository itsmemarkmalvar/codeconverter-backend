<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    protected $fillable = [
        'conversion_id',
        'error_type',
        'error_message',
        'error_location',
        'rdp_analysis',
    ];

    protected $casts = [
        'error_location' => 'array',
        'rdp_analysis' => 'array',
    ];

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }
}
