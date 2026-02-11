<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AmeliaEventTicketModel extends Model
{
    protected $connection = 'wordpress';
    protected $table = 'events_to_tickets';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'eventId',
        'enabled',
        'name',
        'price',
        'dateRanges',
        'spots',
        'waitingListSpots',
        'translations',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'price' => 'float',
        'spots' => 'integer',
        'waitingListSpots' => 'integer',
        'dateRanges' => 'array',
        'translations' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(AmeliaEventModel::class, 'eventId', 'id');
    }
}
