<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class BookableResourceModel extends Model
{
    protected $table = 'resources';

    protected $fillable = [
        'amelia_resource_id',
        'name',
        'description',
        'quantity',
        'meta',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'meta' => 'array',
        'status' => 'boolean',
    ];
}
