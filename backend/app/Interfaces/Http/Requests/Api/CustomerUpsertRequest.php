<?php

namespace App\Interfaces\Http\Requests\Api;

use App\Application\Customers\Data\CustomerUpsertData;
use Illuminate\Foundation\Http\FormRequest;

class CustomerUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return CustomerUpsertData::rules();
    }

    public function payload(): CustomerUpsertData
    {
        return CustomerUpsertData::from($this->validated());
    }
}

