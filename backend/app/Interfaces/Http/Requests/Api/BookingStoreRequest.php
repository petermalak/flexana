<?php

namespace App\Interfaces\Http\Requests\Api;

use App\Application\Bookings\Data\BookingCreateData;
use Illuminate\Foundation\Http\FormRequest;

class BookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return BookingCreateData::rules();
    }

    public function payload(): BookingCreateData
    {
        return BookingCreateData::from($this->validated());
    }
}

