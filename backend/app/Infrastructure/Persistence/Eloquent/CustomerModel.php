<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class CustomerModel extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;
    use HasFactory;

    protected $table = 'customers';

    protected $guard = 'api';

    protected $fillable = [
        'uuid',
        'uid',
        'firebase_uid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_verified_at',
        'password',
        'email_verified_at',
        'timezone',
        'preferences',
        'notes',
        'source',
        'last_seen_at',
        'gender',
        'birthday',
        'country_phone_iso',
        'external_id',
        'amelia_user_id',
        'language',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'preferences' => 'array',
        'last_seen_at' => 'datetime',
        'birthday' => 'date',
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $customer): void {
            if (empty($customer->uuid)) {
                $customer->uuid = Str::uuid()->toString();
            }
        });
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(BookingModel::class, 'customer_id');
    }

    public function packagePurchases(): HasMany
    {
        return $this->hasMany(CustomerPackagePurchaseModel::class, 'customer_id');
    }
}

