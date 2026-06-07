<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'is_drop_in',
        'answers',
        'notes',
        'booked_at',
        'cancelled_at',
        'class_reminder_sent_at',
        'class_reminder_push_sent_at',
    ];

    protected $casts = [
        'is_drop_in' => 'boolean',
        'answers' => 'array',
        'booked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'class_reminder_sent_at' => 'datetime',
        'class_reminder_push_sent_at' => 'datetime',
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

        static::created(function (self $booking): void {
            // Safety net for admin/session booking flows that create a package booking
            // without decrementing remaining sessions on the purchase record.
            //
            // If the booking already has `customer_package_purchase_id`, the dedicated
            // booking services handle deduction and we must not double-deduct.
            if (
                $booking->appointment_id === null
                || (bool) $booking->is_drop_in
                || $booking->package_id === null
                || $booking->customer_id === null
                || $booking->customer_package_purchase_id !== null
            ) {
                return;
            }

            DB::transaction(function () use ($booking): void {
                // Re-load the booking row with a lock to avoid races.
                /** @var self|null $fresh */
                $fresh = self::query()
                    ->whereKey($booking->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $fresh) {
                    return;
                }

                if (
                    $fresh->customer_package_purchase_id !== null
                    || $fresh->package_id === null
                    || $fresh->customer_id === null
                    || (bool) $fresh->is_drop_in
                ) {
                    return;
                }

                $spots = max(1, (int) ($fresh->party_size ?? 1));

                $purchase = CustomerPackagePurchaseModel::query()
                    ->where('customer_id', $fresh->customer_id)
                    ->where('package_id', $fresh->package_id)
                    ->where('status', 'active')
                    ->where('remaining_sessions', '>=', $spots)
                    ->orderBy('purchase_date')
                    ->lockForUpdate()
                    ->first();

                if (! $purchase) {
                    return;
                }

                $purchase->decrement('remaining_sessions', $spots);
                $fresh->updateQuietly(['customer_package_purchase_id' => $purchase->id]);
            });
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

