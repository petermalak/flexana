<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClassTypeModel extends Model
{
    use HasFactory;

    protected $table = 'class_types';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'color_hex',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $classType): void {
            if (empty($classType->uuid)) {
                $classType->uuid = Str::uuid()->toString();
            }
            if (empty($classType->slug) && !empty($classType->name)) {
                $classType->slug = Str::slug($classType->name);
            }
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany(EventModel::class, 'class_type_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(PackageModel::class, 'class_type_id');
    }
}

