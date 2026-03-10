<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StaffModel extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'uuid',
        'amelia_user_id',
        'name',
        'email',
        'phone',
        'photo_path',
        'role',
        'color_hex',
        'timezone',
        'skills',
        'is_active',
    ];

    protected $casts = [
        'skills' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $staff): void {
            if (empty($staff->uuid)) {
                $staff->uuid = Str::uuid()->toString();
            }
        });
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StaffScheduleModel::class, 'staff_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ServiceModel::class, 'service_staff', 'staff_id', 'service_id')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(EventModel::class, 'instructor_id');
    }

    public function eventInstances(): HasMany
    {
        return $this->hasMany(EventInstanceModel::class, 'instructor_id');
    }

    public function offDays(): HasMany
    {
        return $this->hasMany(StaffOffDayModel::class, 'staff_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(AppointmentModel::class, 'provider_id');
    }
}

