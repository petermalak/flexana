<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceModel extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = [
        'uuid',
        'amelia_service_id',
        'name',
        'description',
        'duration',
        'price',
        'min_capacity',
        'max_capacity',
        'color_hex',
        'status',
        'picture_full_path',
        'picture_thumb_path',
        'extras',
        'custom_pricing',
        'settings',
        'gallery',
        'position',
        'deposit',
        'deposit_payment',
        'deposit_per_person',
        'full_payment',
        'bringing_anyone',
        'show',
        'aggregated_price',
        'recurring_cycle',
        'recurring_sub',
        'recurring_payment',
        'time_after',
        'time_before',
        'limit_per_customer',
        'min_selected_extras',
        'mandatory_extra',
        'max_extra_people',
        'category_id',
    ];

    protected $casts = [
        'extras' => 'array',
        'custom_pricing' => 'array',
        'settings' => 'array',
        'gallery' => 'array',
        'limit_per_customer' => 'array',
        'deposit_per_person' => 'boolean',
        'full_payment' => 'boolean',
        'bringing_anyone' => 'boolean',
        'show' => 'boolean',
        'aggregated_price' => 'boolean',
        'mandatory_extra' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $service): void {
            if (empty($service->uuid)) {
                $service->uuid = Str::uuid()->toString();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id', 'id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(EventModel::class, 'event_service', 'service_id', 'event_id')
            ->withPivot('price')
            ->withTimestamps();
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(StaffModel::class, 'service_staff', 'service_id', 'staff_id')
            ->withTimestamps();
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(PackageModel::class, 'package_service', 'service_id', 'package_id')
            ->withPivot(['provider_id', 'location_id', 'quantity'])
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(BranchModel::class, 'branch_service', 'service_id', 'branch_id')
            ->withTimestamps();
    }
}

