<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomerModel extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'uuid',
        'firebase_uid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'timezone',
        'preferences',
        'notes',
        'source',
        'last_seen_at',
        'gender',
        'birthday',
        'country_phone_iso',
        'external_id',
        'language',
    ];

    protected $casts = [
        'preferences' => 'array',
        'last_seen_at' => 'datetime',
        'birthday' => 'date',
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
}

