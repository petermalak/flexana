<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CustomFieldModel extends Model
{
    protected $table = 'custom_fields';

    protected $fillable = [
        'name',
        'label',
        'type',
        'entity_type',
        'required',
        'options',
        'position',
        'status',
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'array',
        'position' => 'integer',
        'status' => 'boolean',
    ];
}
