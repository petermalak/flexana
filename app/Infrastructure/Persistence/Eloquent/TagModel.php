<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TagModel extends Model
{
    protected $table = 'tags';

    protected $fillable = [
        'name',
        'slug',
        'type',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }
}
