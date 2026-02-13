<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaUserModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'users';
    public $timestamps = false;

    public function getTable(): string
    {
        if ($this->connection === 'wordpress') {
            return config('database.connections.wordpress.amelia_users_table', 'users');
        }

        return parent::getTable();
    }

    protected $fillable = [
        'id',
        'firstName',
        'lastName',
        'email',
        'phone',
        'gender',
        'birthday',
        'picture',
        'externalId',
        'status',
        'type',
        'description',
        'note',
        'translations',
        'timeZone',
        'usedTokens',
        'password',
        'created',
    ];

    protected $casts = [
        'birthday' => 'date',
        'usedTokens' => 'integer',
        'created' => 'datetime',
        'translations' => 'array',
    ];

    public function customerBookings()
    {
        return $this->hasMany(AmeliaCustomerBookingModel::class, 'customerId', 'id');
    }

    public function appointments()
    {
        return $this->hasMany(AmeliaAppointmentModel::class, 'providerId', 'id');
    }

    public function scopeEmployees($query)
    {
        return $query->whereIn('type', ['provider', 'manager', 'admin']);
    }
}
