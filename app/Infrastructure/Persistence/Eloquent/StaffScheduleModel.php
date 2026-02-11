<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffScheduleModel extends Model
{
    use HasFactory;

    protected $table = 'staff_schedules';

    protected $fillable = [
        'staff_id',
        'weekday',
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'staff_id');
    }
}

