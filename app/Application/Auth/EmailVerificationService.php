<?php

namespace App\Application\Auth;

use App\Models\Customer;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class EmailVerificationService
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL_MINUTES = 10;

    /**
     * Signup: create or update customer, store verification code, send OTP email.
     *
     * @return array{success: bool, message: string, code?: string}
     */
    public function sendSignupCode(
        string $email,
        string $phone,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $profileImagePath = null,
    ): array {
        $email = $this->normalizeEmail($email);
        $phone = PhoneNumberNormalizer::normalize($phone);

        if ($email === '') {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }
        if ($phone === '') {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $now = $this->nowUtc();

        $customer = Customer::query()->where('email', $email)->first();
        if (! $customer) {
            $customer = new Customer();
            $customer->email = $email;
            $customer->uid = (string) Str::uuid();
            $customer->first_name = $firstName ?? 'Guest';
        } else {
            if ($firstName !== null) {
                $customer->first_name = $firstName;
            }
        }

        $customer->phone = $phone;
        if ($lastName !== null) {
            $customer->last_name = $lastName;
        }
        if ($profileImagePath !== null) {
            $customer->profile_image = $profileImagePath;
        }
        $customer->save();

        $code = $this->generateCode();
        $expiresAt = $now->copy()->addMinutes(self::CODE_TTL_MINUTES);

        DB::table('email_verification_codes')->insert([
            'email' => $email,
            'code' => $code,
            'purpose' => 'signup',
            'customer_id' => null,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        if (! $this->sendCodeEmail($email, $code, $customer->first_name)) {
            return ['success' => false, 'message' => 'Verification code could not be sent. Please check your email and try again.'];
        }

        $response = ['success' => true, 'message' => 'Verification code sent to your email.'];
        if (app()->environment('local', 'testing')) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Verify signup code. On success, mark email_verified_at and return customer.
     *
     * @return array{success: bool, message: string, customer?: Customer}
     */
    public function verifyCode(string $email, string $code): array
    {
        $email = $this->normalizeEmail($email);
        $code = trim($code);

        if ($email === '' || $code === '') {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }

        $now = $this->nowUtc();

        $row = DB::table('email_verification_codes')
            ->where('email', $email)
            ->where('code', $code)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->orderByDesc('created_at')
            ->first();

        if (! $row || ! $this->verificationRowNotExpired($row, $now)) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        return $this->finishSignupVerification($email);
    }

    public function hasPendingSignupVerification(string $email): bool
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        $now = $this->nowUtc();
        $row = DB::table('email_verification_codes')
            ->where('email', $email)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->orderByDesc('created_at')
            ->first();

        return $row !== null && $this->verificationRowNotExpired($row, $now);
    }

    private function finishSignupVerification(string $email): array
    {
        $customer = Customer::query()->where('email', $email)->first();
        if (! $customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }

        $customer->email_verified_at = Carbon::now();
        $customer->save();

        DB::table('email_verification_codes')
            ->where('email', $email)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->delete();

        return ['success' => true, 'message' => 'Verified.', 'customer' => $customer];
    }

    /**
     * @param  object{expires_at?: mixed}  $row
     */
    private function verificationRowNotExpired(object $row, Carbon $nowUtc): bool
    {
        $raw = $row->expires_at ?? null;
        if ($raw === null || $raw === '') {
            return false;
        }
        try {
            $expiresAt = Carbon::parse((string) $raw, 'UTC');
        } catch (\Throwable) {
            return false;
        }

        return $expiresAt->gt($nowUtc);
    }

    private function sendCodeEmail(string $email, string $code, ?string $firstName): bool
    {
        $name = trim((string) $firstName);
        $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';

        $body = "{$greeting}\n\n"
            . "Your Flexana verification code is: {$code}\n\n"
            . 'This code expires in ' . self::CODE_TTL_MINUTES . " minutes.\n\n"
            . "If you did not request this, you can ignore this email.\n\n"
            . 'Flexana Team';

        if (app()->environment('local', 'testing')) {
            Log::channel('stack')->info('Email verification code', [
                'email' => $email,
                'code' => $code,
            ]);
        }

        try {
            Mail::raw($body, function ($message) use ($email, $name): void {
                if ($name !== '') {
                    $message->to($email, $name);
                } else {
                    $message->to($email);
                }
                $message->subject('Your Flexana verification code');
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send email verification code', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    private function generateCode(): string
    {
        $min = (int) str_pad('1', self::CODE_LENGTH, '0');
        $max = (int) str_pad('9', self::CODE_LENGTH, '9');

        return (string) random_int($min, $max);
    }
}
