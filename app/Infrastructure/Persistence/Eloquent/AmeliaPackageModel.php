<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaPackageModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'packages';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'color',
        'price',
        'status',
        'pictureFullPath',
        'pictureThumbPath',
        'position',
        'calculatedPrice',
        'discount',
        'endDate',
        'durationType',
        'durationCount',
        'settings',
        'translations',
        'depositPayment',
        'deposit',
        'fullPayment',
        'sharedCapacity',
        'quantity',
        'limitPerCustomer',
    ];

    protected $casts = [
        'price' => 'float',
        'discount' => 'float',
        'position' => 'integer',
        'calculatedPrice' => 'boolean',
        'endDate' => 'datetime',
        'durationCount' => 'integer',
        'deposit' => 'float',
        'fullPayment' => 'boolean',
        'sharedCapacity' => 'boolean',
        'quantity' => 'integer',
        'settings' => 'array',
        'translations' => 'array',
    ];
}

