<?php

namespace App\Interfaces\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FlutterBookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:appointment,package'],
            'bookings' => ['required', 'array', 'min:1'],
            'bookings.*.extras' => ['nullable', 'array'],
            'bookings.*.customFields' => ['nullable', 'array'],
            'bookings.*.deposit' => ['nullable', 'boolean'],
            'bookings.*.locale' => ['nullable', 'string'],
            'bookings.*.utcOffset' => ['nullable', 'integer'],
            'bookings.*.persons' => ['nullable', 'integer', 'min:1'],
            'bookings.*.customerId' => ['nullable', 'string'],
            'bookings.*.customer.firstName' => ['required_without:bookings.*.customerId', 'string'],
            'bookings.*.customer.lastName' => ['nullable', 'string'],
            'bookings.*.customer.email' => ['nullable', 'email'],
            'bookings.*.customer.phone' => ['nullable', 'string'],
            'bookings.*.customer.countryPhoneIso' => ['nullable', 'string'],
            'bookings.*.customer.externalId' => ['nullable', 'string'],
            'bookings.*.duration' => ['nullable', 'integer'],
            'payment.gateway' => ['nullable', 'string'],
            'payment.currency' => ['nullable', 'string', 'size:3'],
            'payment.data' => ['nullable', 'array'],
            'recaptcha' => ['nullable', 'string'],
            'locale' => ['nullable', 'string'],
            'timeZone' => ['nullable', 'string'],
            'bookingStart' => ['required_if:type,appointment', 'date'],
            'notifyParticipants' => ['nullable', 'integer', 'in:0,1'],
            'locationId' => ['nullable', 'integer'],
            'providerId' => ['nullable', 'integer'],
            'serviceId' => ['required_if:type,appointment', 'integer'],
            'utcOffset' => ['nullable', 'integer'],
            'recurring' => ['nullable', 'array'],
            'package' => ['required_if:type,package', 'array'],
            'packageId' => ['nullable', 'integer'],
            'packageRules' => ['nullable', 'array'],
            'couponCode' => ['nullable', 'string'],
            'runInstantPostBookingActions' => ['nullable', 'boolean'],
        ];
    }
}

