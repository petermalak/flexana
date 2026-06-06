<?php

namespace App\Console\Commands;

use App\Application\Auth\FirebaseSendVerificationCodeService;
use App\Application\Auth\FirebaseSignInWithPhoneService;
use App\Infrastructure\Auth\FirebaseTokenVerifier;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestFirebaseSmsCommand extends Command
{
    protected $signature = 'auth:test-firebase-sms
                            {phone : Phone number (e.g. 01274235122 or +201274235122)}
                            {--code= : Verification code (for test numbers, use the code you set in Firebase Console)}
                            {--name= : First name}
                            {--password= : Optional password to set after verify}';

    protected $description = 'Test Firebase SMS flow. Add the phone as a test number in Firebase Console first (no real SMS, no reCAPTCHA).';

    public function handle(
        FirebaseSendVerificationCodeService $firebaseSendCode,
        FirebaseSignInWithPhoneService $firebaseSignIn,
        FirebaseTokenVerifier $firebaseVerifier,
    ): int {
        $phone = $this->argument('phone');
        $code = $this->option('code');
        $firstName = $this->option('name');
        $password = $this->option('password');

        $phoneE164 = \App\Support\PhoneNumberNormalizer::normalize($phone);
        $this->info("Phone (E.164): {$phoneE164}");
        $this->newLine();

        $this->info('Step 1: Request verification code from Firebase (sendVerificationCode)...');
        $this->line('  (For test numbers only: add this number in Firebase Console → Auth → Phone → Test numbers)');
        $result = $firebaseSendCode->sendCode($phone, []);

        if (! $result['success']) {
            $this->error($result['message']);
            $this->newLine();
            $this->line('To test without real SMS:');
            $this->line('  1. Firebase Console → Authentication → Sign-in method → Phone');
            $this->line('  2. Under "Phone numbers for testing" add: ' . $phoneE164 . ' with a 6-digit code (e.g. 123456)');
            $this->line('  3. Run this command again. Leave --code= empty and enter the code when asked.');

            return self::FAILURE;
        }

        $sessionInfo = $result['sessionInfo'];
        $this->line('  <info>Got sessionInfo from Firebase.</info>');

        if (empty($code)) {
            $code = $this->ask('Enter the 6-digit code (from SMS or the test code you set in Firebase)');
        } else {
            $this->line("  Code: <comment>{$code}</comment>");
        }

        $this->newLine();
        $this->info('Step 2: Verify code with Firebase (signInWithPhoneNumber)...');
        $signInResult = $firebaseSignIn->signIn($sessionInfo, $code);

        if (! $signInResult['success']) {
            $this->error($signInResult['message']);

            return self::FAILURE;
        }

        $this->line('  Got Firebase idToken.');
        $this->newLine();
        $this->info('Step 3: Create/find customer and issue Sanctum token...');

        try {
            $firebaseToken = $firebaseVerifier->verify($signInResult['idToken']);
        } catch (\Throwable $e) {
            $this->error('Invalid Firebase token: ' . $e->getMessage());

            return self::FAILURE;
        }

        $uid = $firebaseToken->uid;
        $customer = Customer::query()
            ->where('firebase_uid', $uid)
            ->orWhere('uid', $uid)
            ->first();

        if (! $customer) {
            $customer = new Customer();
            $customer->firebase_uid = $uid;
            $customer->uid = (string) Str::uuid();
            $customer->phone = $phoneE164;
        } else {
            $customer->phone = $phoneE164;
        }

        $customer->phone_verified_at = now();
        if ($firstName) {
            $customer->first_name = $firstName;
        }
        if ($password) {
            $customer->password = Hash::make($password);
            $this->line('  Password set.');
        }
        $customer->save();

        $token = $customer->createToken('mobile')->plainTextToken;

        $this->newLine();
        $this->info('Success. Customer verified and signed in (Firebase SMS flow).');
        $this->table(
            ['Field', 'Value'],
            [
                ['Customer ID', $customer->id],
                ['Phone', $customer->phone],
                ['Firebase UID', $uid],
                ['Bearer token', $token],
            ]
        );
        $this->line('Use the token as: Authorization: Bearer ' . $token);

        return self::SUCCESS;
    }

}
