<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentModel extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'booking_id',
        'provider',
        'provider_reference',
        'transaction_id',
        'status',
        'amount',
        'currency',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BookingModel::class, 'booking_id');
    }
}

