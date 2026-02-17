<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffOffDayModel extends Model
{
    use HasFactory;

    protected $table = 'staff_off_days';

    protected $fillable = [
        'staff_id',
        'date',
        'reason',
        'notes',
        'is_all_day',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'date' => 'date',
        'is_all_day' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'staff_id');
    }
}
