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
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PackageModel::class, 'package_id');
    }
}
