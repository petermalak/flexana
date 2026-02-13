<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationModel extends Model
{
    protected $table = 'locations';

    protected $fillable = [
        'amelia_location_id',
        'name',
        'address',
        'phone',
        'latitude',
        'longitude',
        'meta',
        'status',
    ];

    protected $casts = [
        'meta' => 'array',
        'status' => 'boolean',
    ];

    public function eventInstances(): HasMany
    {
        return $this->hasMany(EventInstanceModel::class, 'location_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(AppointmentModel::class, 'location_id');
    }
}
