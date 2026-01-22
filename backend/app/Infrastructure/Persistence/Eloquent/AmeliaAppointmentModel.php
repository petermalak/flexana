<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaAppointmentModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'appointments';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'status',
        'bookingStart',
        'bookingEnd',
        'notifyParticipants',
        'createPaymentLinks',
        'serviceId',
        'packageId',
        'providerId',
        'locationId',
        'internalNotes',
        'googleCalendarEventId',
        'googleMeetUrl',
        'outlookCalendarEventId',
        'microsoftTeamsUrl',
        'appleCalendarEventId',
        'zoomMeeting',
        'lessonSpace',
        'parentId',
        'error',
    ];

    protected $casts = [
        'bookingStart' => 'datetime',
        'bookingEnd' => 'datetime',
        'notifyParticipants' => 'boolean',
        'createPaymentLinks' => 'boolean',
    ];

    public function customerBookings()
    {
        return $this->hasMany(AmeliaCustomerBookingModel::class, 'appointmentId', 'id');
    }

    public function service()
    {
        return $this->belongsTo(AmeliaServiceModel::class, 'serviceId', 'id');
    }

    public function provider()
    {
        return $this->belongsTo(AmeliaUserModel::class, 'providerId', 'id');
    }
}
