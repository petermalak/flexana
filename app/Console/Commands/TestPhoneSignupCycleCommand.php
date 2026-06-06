<?php

namespace App\Console\Commands;

use App\Application\Auth\EmailVerificationService;
use Illuminate\Console\Command;

class TestPhoneSignupCycleCommand extends Command
{
    protected $signature = 'auth:test-phone-signup
                            {phone : Phone number (e.g. 01274235122)}
                            {email : Email address for verification}
                            {--name= : First name}
                            {--password= : Optional password to set after verify}';

    protected $description = 'Test the full signup cycle: signup → email code → verify → token';

    public function handle(EmailVerificationService $verification): int
    {
        $phone = $this->argument('phone');
        $email = strtolower(trim($this->argument('email')));
        $firstName = $this->option('name');
        $password = $this->option('password');

        $this->info('Step 1: Signup (send verification code by email)...');
        $result = $verification->sendSignupCode($email, $phone, $firstName, null, null);

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $code = $result['code'] ?? null;
        if (empty($code)) {
            $this->warn('Code not returned (check storage/logs/laravel.log for the code in local/testing).');
            $code = $this->ask('Enter the 6-digit code from email or logs');
        } else {
            $this->line("  Code (for testing): <comment>{$code}</comment>");
        }

        $this->newLine();
        $this->info('Step 2: Verify (email + code)...');
        $verifyResult = $verification->verifyCode($email, $code);

        if (! $verifyResult['success']) {
            $this->error($verifyResult['message']);

            return self::FAILURE;
        }

        $customer = $verifyResult['customer'];
        if ($password) {
            $customer->password = \Illuminate\Support\Facades\Hash::make($password);
            $customer->save();
            $this->line('  Password set.');
        }

        $token = $customer->createToken('mobile')->plainTextToken;

        $this->newLine();
        $this->info('Success. Customer verified and signed in.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Customer ID', $customer->id],
                ['Email', $customer->email],
                ['Phone', $customer->phone],
                ['Email verified at', $customer->email_verified_at?->toDateTimeString() ?? '-'],
                ['Bearer token', $token],
            ]
        );
        $this->line('Use the token as: Authorization: Bearer ' . $token);

        return self::SUCCESS;
    }
}
