<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaProviderServiceModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'providers_to_services';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'userId',
        'serviceId',
        'price',
        'minCapacity',
        'maxCapacity',
        'customPricing',
    ];

    protected $casts = [
        'price' => 'float',
        'minCapacity' => 'integer',
        'maxCapacity' => 'integer',
        'customPricing' => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(AmeliaUserModel::class, 'userId', 'id');
    }

    public function service()
    {
        return $this->belongsTo(AmeliaServiceModel::class, 'serviceId', 'id');
    }
}
