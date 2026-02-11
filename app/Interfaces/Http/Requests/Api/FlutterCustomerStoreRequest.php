<?php

namespace App\Interfaces\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FlutterCustomerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['nullable', 'string', 'max:120'],
            'externalId' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:32'],
            'countryPhoneIso' => ['nullable', 'string', 'max:2'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'birthday' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'language' => ['nullable', 'string'],
        ];
    }
}

