<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTicketModel extends Model
{
    protected $table = 'event_tickets';

    protected $fillable = [
        'event_id',
        'name',
        'price',
        'spots',
        'waiting_list_spots',
        'enabled',
        'date_ranges',
        'translations',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'spots' => 'integer',
        'waiting_list_spots' => 'integer',
        'enabled' => 'boolean',
        'date_ranges' => 'array',
        'translations' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }
}
