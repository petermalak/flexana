<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaPaymentModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'payments';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'customerBookingId',
        'packageCustomerServiceId',
        'amount',
        'dateTime',
        'status',
        'gateway',
        'gatewayTitle',
        'transactionId',
        'data',
        'parentId',
        'wcOrderId',
        'wcOrderItemId',
    ];

    protected $casts = [
        'amount' => 'float',
        'dateTime' => 'datetime',
        'data' => 'array',
    ];

    public function customerBooking()
    {
        return $this->belongsTo(AmeliaCustomerBookingModel::class, 'customerBookingId', 'id');
    }
}
