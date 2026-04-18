<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class CustomerModel extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable;
    use CanResetPassword;
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'customers';

    protected $guard = 'api';

    protected $fillable = [
        'uuid',
        'uid',
        'firebase_uid',
        'first_name',
        'last_name',
        'email',
        'profile_image',
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

        static::deleting(function (self $customer): void {
            if ($customer->isForceDeleting()) {
                return;
            }
            // Clear unique auth identifiers so the same Firebase user can register again.
            $customer->uid = null;
            $customer->firebase_uid = null;
        });
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = PhoneNumberNormalizer::normalizeNullable(is_string($value) ? $value : null);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(BookingModel::class, 'customer_id');
    }

    public function packagePurchases(): HasMany
    {
        return $this->hasMany(CustomerPackagePurchaseModel::class, 'customer_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(CustomerDeviceTokenModel::class, 'customer_id');
    }
}

