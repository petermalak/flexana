<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaCustomerBookingModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'customer_bookings';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'appointmentId',
        'customerId',
        'status',
        'price',
        'tax',
        'persons',
        'couponId',
        'token',
        'customFields',
        'info',
        'utcOffset',
        'aggregatedPrice',
        'packageCustomerServiceId',
        'duration',
        'created',
        'actionsCompleted',
        'qrCodes',
    ];

    protected $casts = [
        'price' => 'float',
        'persons' => 'integer',
        'aggregatedPrice' => 'boolean',
        'created' => 'datetime',
        'actionsCompleted' => 'boolean',
        'customFields' => 'array',
        'qrCodes' => 'array',
    ];

    public function appointment()
    {
        return $this->belongsTo(AmeliaAppointmentModel::class, 'appointmentId', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(AmeliaUserModel::class, 'customerId', 'id');
    }

    public function payments()
    {
        return $this->hasMany(AmeliaPaymentModel::class, 'customerBookingId', 'id');
    }
}
