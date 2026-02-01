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
        'title',
        'description',
        'total_sessions',
        'discount',
        'price',
        'expiry',
        'status',
    ];

    protected $casts = [
        'expiry' => 'date',
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

