<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Support\BranchSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchModel extends Model
{
    protected $table = 'branches';

    protected $fillable = [
        'name',
        'address',
        'phone',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $branch): void {
            BranchSettings::forgetCache();

            if (! $branch->is_default) {
                return;
            }

            static::query()
                ->where('id', '!=', $branch->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });

        static::deleted(function (): void {
            BranchSettings::forgetCache();
        });
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(AppointmentModel::class, 'branch_id');
    }
}
