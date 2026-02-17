<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffServiceScheduleModel extends Model
{
    use HasFactory;

    protected $table = 'staff_service_schedules';

    protected $fillable = [
        'staff_id',
        'service_id',
        'day_of_week',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'recurrence_type',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'staff_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceModel::class, 'service_id');
    }
}
