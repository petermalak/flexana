<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessageLog extends Model
{
    protected $table = 'sms_message_logs';

    protected $fillable = [
        'msisdn',
        'verification_code',
        'reason',
        'driver',
        'success',
        'provider_message_id',
        'provider_code',
        'provider_cost',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'success' => 'boolean',
        'duration_ms' => 'integer',
    ];
}

