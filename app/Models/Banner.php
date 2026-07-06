<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    public const CATEGORY_HOME = 'home';

    public const CATEGORY_POPUP = 'popup';

    protected $fillable = [
        'title',
        'image_url',
        'link_url',
        'is_active',
        'sort_order',
        'category',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}

