<?php

namespace App\Application\Auth;

use App\Models\SmsMessageLog;
use App\Models\Customer;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PhoneVerificationService
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL_MINUTES = 10;

    public function __construct(
        private readonly SmsVerificationServiceInterface $sms,
    ) {
    }

    /**
     * Signup: create or update customer by phone, store verification code, send OTP SMS.
     * Used only for new user signup. Throttles by phone. Does not return a token.
     *
     * @return array{success: bool, message: string, code?: string}
     */
    public function sendSignupCode(string $phone, ?string $firstName = null, ?string $lastName = null, ?string $email = null, ?string $profileImagePath = null): array
    {
        $phone = $this->normalizePhone($phone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $useTwilioVerify = config('sms.driver') === 'twilio' && ! empty(config('sms.twilio.verify_service_sid'));
        $now = $this->nowUtc();

        $customer = Customer::query()->where('phone', $phone)->first();
        if (! $customer) {
            $customer = new Customer();
            $customer->phone = $phone;
            $customer->uid = (string) Str::uuid();
            $customer->first_name = $firstName ?? 'Guest'; // required column, non-null
        } else {
            if ($firstName !== null) {
                $customer->first_name = $firstName;
            }
        }
        if ($lastName !== null) {
            $customer->last_name = $lastName;
        }
        if ($email !== null) {
            $customer->email = $email;
        }
        if ($profileImagePath !== null) {
            $customer->profile_image = $profileImagePath;
        }
        $customer->save();

        if ($useTwilioVerify) {
            $log = $this->createSmsLog($phone, null, 'signup');
            if (! $this->sms->sendCode($phone, '')) {
                $this->markSmsLogFailed($log, 'Verification code could not be sent.');
                return ['success' => false, 'message' => 'Verification code could not be sent. Please check your number and try again.'];
            }
            $this->markSmsLogSuccess($log);
            // Throttle: insert placeholder so we don't spam Twilio (verify uses provider check, not this row)
            DB::table('phone_verification_codes')->insert([
                'phone' => $phone,
                'code' => '',
                'purpose' => 'signup',
                'customer_id' => null,
                'expires_at' => $now->copy()->addMinutes(self::CODE_TTL_MINUTES),
                'created_at' => $now,
            ]);

            return ['success' => true, 'message' => 'Verification code sent.'];
        }

        $code = $this->generateCode();
        $expiresAt = $now->copy()->addMinutes(self::CODE_TTL_MINUTES);

        DB::table('phone_verification_codes')->insert([
            'phone' => $phone,
            'code' => $code,
            'purpose' => 'signup',
            'customer_id' => null,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $log = $this->createSmsLog($phone, $code, 'signup');
        if (! $this->sms->sendCode($phone, $code)) {
            $this->markSmsLogFailed($log, 'Verification code could not be sent.');
            return ['success' => false, 'message' => 'Verification code could not be sent. Please check your number and try again.'];
        }
        $this->markSmsLogSuccess($log);

        return ['success' => true, 'message' => 'Verification code sent.', 'code' => $code];
    }

    /**
     * Verify code for phone. On success, mark phone_verified_at and return customer.
     *
     * @return array{success: bool, message: string, customer?: Customer}
     */
    public function verifyCode(string $phone, string $code): array
    {
        $phone = $this->normalizePhone($phone);
        $code = trim($code);
        if (empty($phone) || $code === '') {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $now = $this->nowUtc();

        // Latest signup OTP row for this phone (Twilio Verify uses an empty DB code placeholder).
        $latestSignup = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->orderByDesc('created_at')
            ->first();

        // DB-backed OTP (SMS Misr, Twilio programmable SMS, log): must verify here first.
        // If TWILIO_VERIFY_SERVICE_SID is set, checkVerification() would return false for these
        // codes and we would never reach the DB path — so provider check must come after this.
        if ($latestSignup && (string) ($latestSignup->code ?? '') !== '') {
            if ((string) $latestSignup->code !== $code) {
                return ['success' => false, 'message' => 'Invalid or expired code.'];
            }
            if (! $this->phoneVerificationRowNotExpired($latestSignup, $now)) {
                return ['success' => false, 'message' => 'Invalid or expired code.'];
            }

            return $this->finishSignupVerification($phone);
        }

        $providerResult = $this->sms->checkVerification($phone, $code);
        if ($providerResult === true) {
            return $this->finishSignupVerification($phone);
        }
        if ($providerResult === false) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        // Fallback: match any signup row (same as older installs); expiry in PHP avoids MySQL
        // session timezone vs UTC_TIMESTAMP() mismatches on some hosts.
        $row = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('code', $code)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->orderByDesc('created_at')
            ->first();

        if (! $row || ! $this->phoneVerificationRowNotExpired($row, $now)) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        return $this->finishSignupVerification($phone);
    }

    /**
     * @param  object{expires_at?: mixed}  $row
     */
    private function phoneVerificationRowNotExpired(object $row, Carbon $nowUtc): bool
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

    /**
     * Used by MobileAuthController: when VERIFY_PASSWORD_RESET_WORKAROUND is on, do not route
     * signup verify (phone + code + password + password_confirmation) to reset-password unless
     * there is no active signup OTP for this number.
     */
    public function hasPendingSignupVerification(string $phone): bool
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return false;
        }

        $now = $this->nowUtc();
        $row = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
            ->orderByDesc('created_at')
            ->first();

        return $row !== null && $this->phoneVerificationRowNotExpired($row, $now);
    }

    private function finishSignupVerification(string $phone): array
    {
        $customer = Customer::query()->where('phone', $phone)->first();
        if (! $customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }

        $customer->phone_verified_at = Carbon::now();
        $customer->save();

        DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->where('purpose', 'signup')
            ->whereNull('customer_id')
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
        $now = $this->nowUtc();

        $existing = Customer::query()->where('phone', $phone)->where('id', '!=', $customerId)->exists();
        if ($existing) {
            return ['success' => false, 'message' => 'This phone number is already used by another account.'];
        }

        $code = $this->generateCode();
        $expiresAt = $now->copy()->addMinutes(self::CODE_TTL_MINUTES);

        DB::table('phone_verification_codes')->insert([
            'phone' => $phone,
            'code' => $code,
            'purpose' => 'phone_change',
            'customer_id' => $customerId,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $log = $this->createSmsLog($phone, $code, 'phone_change');
        if (! $this->sms->sendCode($phone, $code)) {
            $this->markSmsLogFailed($log, 'Verification code could not be sent.');
            return ['success' => false, 'message' => 'Verification code could not be sent. Please check the number and try again.'];
        }
        $this->markSmsLogSuccess($log);

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
            ->whereRaw('expires_at > UTC_TIMESTAMP()')
            ->orderByDesc('created_at')
            ->first();

        if ($row) {
            DB::table('phone_verification_codes')
                ->where('phone', $phone)
                ->where('code', $code)
                ->where('purpose', 'phone_change')
                ->where('customer_id', $customerId)
                ->delete();

            return ['success' => true, 'message' => 'Verified.', 'phone' => $phone];
        }

        // Fallback: provider may have sent the code (e.g. Twilio Verify) instead of our DB code
        $providerResult = $this->sms->checkVerification($phone, $code);
        if ($providerResult === true) {
            DB::table('phone_verification_codes')
                ->where('phone', $phone)
                ->where('purpose', 'phone_change')
                ->where('customer_id', $customerId)
                ->delete();

            return ['success' => true, 'message' => 'Verified.', 'phone' => $phone];
        }

        return ['success' => false, 'message' => 'Invalid or expired code.'];
    }

    /**
     * Send password reset OTP code to phone. Throttles by phone.
     *
     * @return array{success: bool, message: string, code?: string}
     */
    public function sendPasswordResetCode(string $phone): array
    {
        $phone = $this->normalizePhone($phone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }
        $now = $this->nowUtc();

        $variants = $this->phoneLookupVariants($phone);
        $customer = Customer::query()
            ->whereIn('phone', $variants)
            ->first();
        if (! $customer) {
            // Don't reveal if phone exists or not for security
            return ['success' => true, 'message' => 'If that phone number exists, we have sent a password reset code.'];
        }

        $useTwilioVerify = config('sms.driver') === 'twilio' && ! empty(config('sms.twilio.verify_service_sid'));

        if ($useTwilioVerify) {
            $log = $this->createSmsLog($phone, null, 'password_reset');
            if (! $this->sms->sendCode($phone, '')) {
                $this->markSmsLogFailed($log, 'Verification code could not be sent.');
                return ['success' => false, 'message' => 'Verification code could not be sent. Please check your number and try again.'];
            }
            $this->markSmsLogSuccess($log);
            // Throttle: insert placeholder so we don't spam Twilio
            DB::table('phone_verification_codes')->insert([
                'phone' => $phone,
                'code' => '',
                'purpose' => 'password_reset',
                'customer_id' => $customer->id,
                'expires_at' => $now->copy()->addMinutes(self::CODE_TTL_MINUTES),
                'created_at' => $now,
            ]);

            return ['success' => true, 'message' => 'If that phone number exists, we have sent a password reset code.'];
        }

        $code = $this->generateCode();
        $expiresAt = $now->copy()->addMinutes(self::CODE_TTL_MINUTES);

        DB::table('phone_verification_codes')->insert([
            'phone' => $phone,
            'code' => $code,
            'purpose' => 'password_reset',
            'customer_id' => $customer->id,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $log = $this->createSmsLog($phone, $code, 'password_reset');
        if (! $this->sms->sendCode($phone, $code)) {
            $this->markSmsLogFailed($log, 'Verification code could not be sent.');
            return ['success' => false, 'message' => 'Verification code could not be sent. Please check your number and try again.'];
        }
        $this->markSmsLogSuccess($log);

        $response = ['success' => true, 'message' => 'If that phone number exists, we have sent a password reset code.'];
        if (app()->environment('local', 'testing')) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Customers in production might have legacy phone formats (e.g. "0123..." stored instead of "+20123...").
     * We look up by multiple variants to avoid "account exists but we never send OTP".
     *
     * @return array<int, string>
     */
    private function phoneLookupVariants(string $normalizedPhone): array
    {
        $p = preg_replace('/\s+/', '', $normalizedPhone) ?? '';
        $variants = [];
        if ($p !== '') {
            $variants[] = $p;
        }

        // "+2012..." → "012..." (Egypt local)
        if (str_starts_with($p, '+20') && strlen($p) > 3) {
            $variants[] = '0' . substr($p, 3);
        }

        // "+2012..." → "2012..." (no plus)
        if (str_starts_with($p, '+') && strlen($p) > 1) {
            $variants[] = substr($p, 1);
        }

        // De-duplicate & drop empties
        $variants = array_values(array_unique(array_filter($variants, fn ($v) => is_string($v) && $v !== '')));

        return $variants === [] ? [$normalizedPhone] : $variants;
    }

    private function createSmsLog(string $msisdn, ?string $code, string $reason): SmsMessageLog
    {
        return SmsMessageLog::query()->create([
            'msisdn' => $msisdn,
            'verification_code' => $code,
            'reason' => $reason,
            'driver' => (string) config('sms.driver', 'log'),
            'success' => null,
        ]);
    }

    private function markSmsLogSuccess(SmsMessageLog $log): void
    {
        $log->update([
            'success' => true,
            'error_message' => null,
        ]);
    }

    private function markSmsLogFailed(SmsMessageLog $log, string $message): void
    {
        $log->update([
            'success' => false,
            'error_message' => $message,
        ]);
    }

    /**
     * Verify password reset code. Returns success and customer if valid.
     *
     * @return array{success: bool, message: string, customer?: Customer}
     */
    public function verifyPasswordResetCode(string $phone, string $code): array
    {
        $phone = $this->normalizePhone($phone);
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }
        $variants = $this->phoneLookupVariants($phone);
        $now = $this->nowUtc(); // used for logging/debug; DB comparison uses UTC_TIMESTAMP() to avoid tz conversion bugs.

        $providerResult = $this->sms->checkVerification($phone, $code);
        if ($providerResult === true) {
            $customer = Customer::query()->whereIn('phone', $variants)->first();
            if (! $customer) {
                return ['success' => false, 'message' => 'Customer not found.'];
            }

            // Delete used code
            DB::table('phone_verification_codes')
                ->whereIn('phone', $variants)
                ->where('purpose', 'password_reset')
                ->where('customer_id', $customer->id)
                ->delete();

            return ['success' => true, 'message' => 'Code verified.', 'customer' => $customer];
        }
        if ($providerResult === false) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        $row = DB::table('phone_verification_codes')
            ->whereIn('phone', $variants)
            ->where('code', $code)
            ->where('purpose', 'password_reset')
            ->whereRaw('expires_at > UTC_TIMESTAMP()')
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            // Fallback: fetch latest row and compare in PHP (helps diagnose + avoids MySQL timezone quirks).
            $latestRow = DB::table('phone_verification_codes')
                ->whereIn('phone', $variants)
                ->where('purpose', 'password_reset')
                ->orderByDesc('created_at')
                ->first(['phone', 'code', 'created_at', 'expires_at', 'customer_id']);

            if ($latestRow) {
                $latestCode = (string) ($latestRow->code ?? '');
                $latestExpiresAt = $latestRow->expires_at ? Carbon::parse($latestRow->expires_at, 'UTC') : null;

                if ($latestCode === $code && $latestExpiresAt && $latestExpiresAt->gt($now)) {
                    $row = $latestRow;
                }
            }

            if (! $row) {
            // Debug: log the latest rows for this phone to diagnose timezone / normalization issues in production.
            $latest = DB::table('phone_verification_codes')
                ->whereIn('phone', $variants)
                ->where('purpose', 'password_reset')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get(['phone', 'code', 'created_at', 'expires_at', 'customer_id']);
            Log::warning('Password reset OTP not found or expired', [
                'phone' => $phone,
                'phone_variants' => $variants,
                'entered_code' => $code,
                'now_utc' => $now->toDateTimeString(),
                'latest_rows' => $latest,
            ]);
            return ['success' => false, 'message' => 'Invalid or expired code.'];
            }
        }

        $customer = Customer::query()->whereIn('phone', $variants)->first();
        if (! $customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }

        // Delete used code
        DB::table('phone_verification_codes')
            ->whereIn('phone', $variants)
            ->where('code', $code)
            ->where('purpose', 'password_reset')
            ->delete();

        return ['success' => true, 'message' => 'Code verified.', 'customer' => $customer];
    }

    /**
     * Canonical format so "01234567890" and "+201234567890" match when storing vs verifying.
     * Must match SmsVerificationService normalization so send and verify use the same key.
     */
    private function normalizePhone(string $phone): string
    {
        return PhoneNumberNormalizer::normalize($phone);
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
