<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaEventPeriodModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'events_periods';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'eventId',
        'periodStart',
        'periodEnd',
        'zoomMeeting',
        'lessonSpace',
        'googleCalendarEventId',
        'googleMeetUrl',
        'outlookCalendarEventId',
        'microsoftTeamsUrl',
        'appleCalendarEventId',
    ];

    protected $casts = [
        'periodStart' => 'datetime',
        'periodEnd' => 'datetime',
        'zoomMeeting' => 'array',
        'lessonSpace' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(AmeliaEventModel::class, 'eventId', 'id');
    }
}
