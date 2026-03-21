<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRedemptionModel extends Model
{
    protected $table = 'promo_code_redemptions';

    protected $fillable = [
        'promo_code_id',
        'customer_id',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCodeModel::class, 'promo_code_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id');
    }
}
