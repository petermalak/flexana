<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentModel extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = Str::uuid()->toString();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'booking_id',
        'promo_code_id',
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

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCodeModel::class, 'promo_code_id');
    }
}

