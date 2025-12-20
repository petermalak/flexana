<?php

namespace App\Interfaces\Http\Requests\Api;

use App\Application\Events\Data\EventUpsertData;
use Illuminate\Foundation\Http\FormRequest;

class EventUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return EventUpsertData::rules();
    }

    public function payload(): EventUpsertData
    {
        return EventUpsertData::from($this->validated());
    }
}

