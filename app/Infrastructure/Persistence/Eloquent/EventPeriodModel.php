<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPeriodModel extends Model
{
    protected $table = 'event_periods';

    protected $fillable = [
        'event_id',
        'period_start',
        'period_end',
        'zoom_meeting',
        'lesson_space',
        'google_calendar_event_id',
        'google_meet_url',
        'outlook_calendar_event_id',
        'microsoft_teams_url',
        'apple_calendar_event_id',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'zoom_meeting' => 'array',
        'lesson_space' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }
}
