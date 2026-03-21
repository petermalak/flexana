<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class PackageModel extends Model
{
    use HasFactory;

    protected $table = 'packages';

    protected $fillable = [
        'uuid',
        'amelia_package_id',
        'class_type_id',
        'service_type',
        'title',
        'description',
        'total_sessions',
        'discount',
        'price',
        'expiry',
        'package_duration',
        'package_duration_days',
        'status',
    ];

    protected $casts = [
        'expiry' => 'date',
        'package_duration' => 'integer',
        'package_duration_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $package): void {
            if (empty($package->uuid)) {
                $package->uuid = Str::uuid()->toString();
            }
        });
    }

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassTypeModel::class, 'class_type_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ServiceModel::class, 'package_service', 'package_id', 'service_id')
            ->withPivot(['provider_id', 'location_id', 'quantity'])
            ->withTimestamps();
    }
}

