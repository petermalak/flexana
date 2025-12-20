<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventInstanceModel extends Model
{
    use HasFactory;

    protected $table = 'event_instances';

    protected $fillable = [
        'uuid',
        'event_id',
        'starts_at',
        'ends_at',
        'capacity',
        'location',
        'location_id',
        'resources',
        'status',
        'booking_open_date',
        'booking_close_date',
        'instructor_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'booking_open_date' => 'datetime',
        'booking_close_date' => 'datetime',
        'resources' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $instance): void {
            if (empty($instance->uuid)) {
                $instance->uuid = Str::uuid()->toString();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'instructor_id');
    }
}

