<?php

namespace App\Interfaces\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FlutterServiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categoryId' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer'],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['integer'],
            'extras' => ['nullable', 'array'],
            'extras.*.name' => ['required_with:extras', 'string'],
            'extras.*.duration' => ['nullable', 'integer'],
            'extras.*.price' => ['nullable', 'numeric'],
            'extras.*.maxQuantity' => ['nullable', 'integer'],
            'maxCapacity' => ['nullable', 'integer'],
            'minCapacity' => ['nullable', 'integer'],
            'name' => ['required', 'string'],
            'pictureFullPath' => ['nullable', 'string'],
            'pictureThumbPath' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric'],
            'customPricing' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'timeAfter' => ['nullable', 'string'],
            'timeBefore' => ['nullable', 'string'],
            'bringingAnyone' => ['nullable', 'boolean'],
            'show' => ['nullable', 'boolean'],
            'gallery' => ['nullable', 'array'],
            'aggregatedPrice' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'string'],
            'recurringCycle' => ['nullable', 'string'],
            'recurringSub' => ['nullable', 'string'],
            'recurringPayment' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
            'deposit' => ['nullable', 'numeric'],
            'depositPayment' => ['nullable', 'string'],
            'depositPerPerson' => ['nullable', 'integer'],
            'fullPayment' => ['nullable', 'boolean'],
            'translations' => ['nullable', 'array'],
            'minSelectedExtras' => ['nullable', 'integer'],
            'mandatoryExtra' => ['nullable', 'boolean'],
            'maxExtraPeople' => ['nullable', 'integer'],
            'limitPerCustomer' => ['nullable', 'string'],
        ];
    }
}

