<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaServiceModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'services';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'color',
        'price',
        'status',
        'categoryId',
        'minCapacity',
        'maxCapacity',
        'duration',
        'timeBefore',
        'timeAfter',
        'bringingAnyone',
        'bookMultipleTimes',
        'position',
        'show',
        'aggregatedPrice',
        'settings',
    ];

    protected $casts = [
        'price' => 'float',
        'minCapacity' => 'integer',
        'maxCapacity' => 'integer',
        'duration' => 'integer',
        'timeBefore' => 'integer',
        'timeAfter' => 'integer',
        'bringingAnyone' => 'boolean',
        'bookMultipleTimes' => 'boolean',
        'show' => 'boolean',
        'aggregatedPrice' => 'boolean',
        'settings' => 'array',
    ];

    public function appointments()
    {
        return $this->hasMany(AmeliaAppointmentModel::class, 'serviceId', 'id');
    }
}
