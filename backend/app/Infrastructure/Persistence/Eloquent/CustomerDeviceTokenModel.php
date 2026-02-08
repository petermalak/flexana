<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDeviceTokenModel extends Model
{
    protected $table = 'customer_device_tokens';

    protected $fillable = [
        'customer_id',
        'fcm_token',
        'platform',
        'device_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }
}
