<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaEventModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'events';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'parentId',
        'name',
        'status',
        'bookingOpens',
        'bookingCloses',
        'bookingOpensRec',
        'bookingClosesRec',
        'ticketRangeRec',
        'recurringCycle',
        'recurringOrder',
        'recurringInterval',
        'recurringMonthly',
        'monthlyDate',
        'monthlyOnRepeat',
        'monthlyOnDay',
        'recurringUntil',
        'maxCapacity',
        'maxCustomCapacity',
        'maxExtraPeople',
        'price',
        'locationId',
        'customLocation',
        'description',
        'color',
        'show',
        'notifyParticipants',
        'created',
        'settings',
        'zoomUserId',
    ];

    protected $casts = [
        'price' => 'float',
        'maxCapacity' => 'integer',
        'maxCustomCapacity' => 'integer',
        'maxExtraPeople' => 'integer',
        'show' => 'boolean',
        'notifyParticipants' => 'boolean',
        'bookingOpens' => 'datetime',
        'bookingCloses' => 'datetime',
        'recurringUntil' => 'datetime',
        'monthlyDate' => 'datetime',
        'created' => 'datetime',
        'settings' => 'array',
    ];

    public function periods()
    {
        return $this->hasMany(AmeliaEventPeriodModel::class, 'eventId', 'id');
    }

    public function tickets()
    {
        return $this->hasMany(AmeliaEventTicketModel::class, 'eventId', 'id');
    }
}
