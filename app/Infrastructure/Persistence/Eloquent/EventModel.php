<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventModel extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'category',
        'status',
        'timezone',
        'description',
        'capacity',
        'price',
        'deposit_amount',
        'allow_waitlist',
        'minutes_before_cancellation',
        'recurrence',
        'meta',
        'published_at',
        'instructor_id',
        'class_type_id',
    ];

    protected $casts = [
        'allow_waitlist' => 'boolean',
        'recurrence' => 'array',
        'meta' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (empty($event->uuid)) {
                $event->uuid = Str::uuid()->toString();
            }
        });
    }

    public function instances(): HasMany
    {
        return $this->hasMany(EventInstanceModel::class, 'event_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(EventPeriodModel::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EventTicketModel::class, 'event_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'instructor_id');
    }

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassTypeModel::class, 'class_type_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ServiceModel::class, 'event_service', 'event_id', 'service_id')
            ->withPivot('price')
            ->withTimestamps();
    }
}

