<?php

namespace App\Application\Auth;

use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PhoneVerificationService
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL_MINUTES = 10;
    private const THROTTLE_SECONDS = 60;

    public function __construct(
        private readonly SmsVerificationServiceInterface $sms,
    ) {
    }

    /**
     * Signup: create or update customer by phone, store verification code, send OTP SMS.
     * Used only for new user signup. Throttles by phone. Does not return a token.
     *
     * @return array{success: bool, message: string}
     */
    public function sendSignupCode(string $phone, ?string $firstName = null, ?string $lastName = null, ?string $email = null): array
    {
        $phone = $this->normalizePhone($phone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $throttle = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('created_at', '>=', Carbon::now()->subSeconds(self::THROTTLE_SECONDS))
            ->exists();

        if ($throttle) {
            return ['success' => false, 'message' => 'Please wait before requesting another code.'];
        }

        $customer = Customer::query()->where('phone', $phone)->first();
        if (! $customer) {
            $customer = new Customer();
            $customer->phone = $phone;
            $customer->uid = (string) Str::uuid();
        }
        if ($firstName !== null) {
            $customer->first_name = $firstName;
        }
        if ($lastName !== null) {
            $customer->last_name = $lastName;
        }
        if ($email !== null) {
            $customer->email = $email;
        }
        $customer->save();

        $code = $this->generateCode();
        $expiresAt = Carbon::now()->addMinutes(self::CODE_TTL_MINUTES);

        DB::table('phone_verification_codes')->insert([
            'phone' => $phone,
            'code' => $code,
            'purpose' => 'signup',
            'customer_id' => null,
            'expires_at' => $expiresAt,
            'created_at' => Carbon::now(),
        ]);

        $this->sms->sendCode($phone, $code);

        return ['success' => true, 'message' => 'Verification code sent.'];
    }

    /**
     * Verify code for phone. On success, mark phone_verified_at and return customer.
     *
     * @return array{success: bool, message: string, customer?: Customer}
     */
    public function verifyCode(string $phone, string $code): array
    {
        $phone = $this->normalizePhone($phone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $row = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('code', $code)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        $customer = Customer::query()->where('phone', $phone)->first();
        if (! $customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }

        $customer->phone_verified_at = Carbon::now();
        $customer->save();

        // Invalidate used code (optional: delete or mark used)
        DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('code', $code)
            ->delete();

        return ['success' => true, 'message' => 'Verified.', 'customer' => $customer];
    }

    /**
     * Send OTP to a new phone for an existing customer (phone change). Throttles by phone.
     *
     * @return array{success: bool, message: string}
     */
    public function sendPhoneChangeCode(string $newPhone, int $customerId): array
    {
        $phone = $this->normalizePhone($newPhone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $existing = Customer::query()->where('phone', $phone)->where('id', '!=', $customerId)->exists();
        if ($existing) {
            return ['success' => false, 'message' => 'This phone number is already used by another account.'];
        }

        $throttle = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('purpose', 'phone_change')
            ->where('customer_id', $customerId)
            ->where('created_at', '>=', Carbon::now()->subSeconds(self::THROTTLE_SECONDS))
            ->exists();

        if ($throttle) {
            return ['success' => false, 'message' => 'Please wait before requesting another code.'];
        }

        $code = $this->generateCode();
        $expiresAt = Carbon::now()->addMinutes(self::CODE_TTL_MINUTES);

        DB::table('phone_verification_codes')->insert([
            'phone' => $phone,
            'code' => $code,
            'purpose' => 'phone_change',
            'customer_id' => $customerId,
            'expires_at' => $expiresAt,
            'created_at' => Carbon::now(),
        ]);

        $this->sms->sendCode($phone, $code);

        return ['success' => true, 'message' => 'Verification code sent.'];
    }

    /**
     * Verify code for phone change. Returns success and the new phone if valid.
     *
     * @return array{success: bool, message: string, phone?: string}
     */
    public function verifyPhoneChangeCode(string $phone, string $code, int $customerId): array
    {
        $phone = $this->normalizePhone($phone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $row = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('code', $code)
            ->where('purpose', 'phone_change')
            ->where('customer_id', $customerId)
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('code', $code)
            ->where('purpose', 'phone_change')
            ->where('customer_id', $customerId)
            ->delete();

        return ['success' => true, 'message' => 'Verified.', 'phone' => $phone];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', $phone);
    }

    private function generateCode(): string
    {
        $min = (int) str_pad('1', self::CODE_LENGTH, '0');
        $max = (int) str_pad('9', self::CODE_LENGTH, '9');

        return (string) random_int($min, $max);
    }
}
