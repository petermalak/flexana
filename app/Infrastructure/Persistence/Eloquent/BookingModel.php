<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BookingModel extends Model
{
    use HasFactory;

    protected $table = 'bookings';

    protected $fillable = [
        'uuid',
        'event_id',
        'event_instance_id',
        'appointment_id',
        'amelia_customer_booking_id',
        'customer_id',
        'package_id',
        'customer_package_purchase_id',
        'service_id',
        'provider_id',
        'location_id',
        'status',
        'payment_status',
        'party_size',
        'total_amount',
        'deposit_amount',
        'balance_amount',
        'currency',
        'channel',
        'answers',
        'notes',
        'booked_at',
        'cancelled_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'booked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            if (empty($booking->uuid)) {
                $booking->uuid = Str::uuid()->toString();
            }
            if ($booking->total_amount === null) {
                $booking->total_amount = 0;
            }
            if ($booking->deposit_amount === null) {
                $booking->deposit_amount = 0;
            }
            if ($booking->balance_amount === null) {
                $booking->balance_amount = $booking->total_amount - $booking->deposit_amount;
            }
            if (empty($booking->booked_at)) {
                $booking->booked_at = now();
            }
            if (empty($booking->currency)) {
                $booking->currency = 'USD';
            }
            if (empty($booking->channel)) {
                $booking->channel = 'admin';
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }

    public function eventInstance(): BelongsTo
    {
        return $this->belongsTo(EventInstanceModel::class, 'event_instance_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentModel::class, 'appointment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'booking_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PackageModel::class, 'package_id');
    }

    public function customerPackagePurchase(): BelongsTo
    {
        return $this->belongsTo(CustomerPackagePurchaseModel::class, 'customer_package_purchase_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceModel::class, 'service_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'provider_id');
    }
}

