<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPackagePurchaseModel extends Model
{
    protected $table = 'customer_package_purchases';

    protected $fillable = [
        'customer_id',
        'package_id',
        'amelia_package_id',
        'total_sessions',
        'remaining_sessions',
        'purchase_date',
        'status',
        'amelia_package_customer_id',
        'expires_by_months_only',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'expires_by_months_only' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $purchase): void {
            // Validation/invariant: only ACTIVE purchases may carry remaining sessions.
            // If an admin marks a purchase inactive/expired, it must no longer be usable
            // and remaining sessions should be zeroed for consistency.
            $status = (string) ($purchase->status ?? '');
            if ($status !== 'active') {
                $purchase->remaining_sessions = 0;
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PackageModel::class, 'package_id');
    }
}
