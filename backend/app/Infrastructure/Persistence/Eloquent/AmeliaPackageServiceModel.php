<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaPackageServiceModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'packages_to_services';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'serviceId',
        'packageId',
        'quantity',
        'minimumScheduled',
        'maximumScheduled',
        'allowProviderSelection',
        'position',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'minimumScheduled' => 'integer',
        'maximumScheduled' => 'integer',
        'allowProviderSelection' => 'boolean',
        'position' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(AmeliaPackageModel::class, 'packageId', 'id');
    }

    public function service()
    {
        return $this->belongsTo(AmeliaServiceModel::class, 'serviceId', 'id');
    }
}
