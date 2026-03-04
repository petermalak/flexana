<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmailCommand extends Command
{
    protected $signature = 'mail:test {email : Recipient email address}';

    protected $description = 'Send a test email to the given address (uses MAIL_* from .env)';

    public function handle(): int
    {
        $to = $this->argument('email');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address: ' . $to);
            return self::FAILURE;
        }

        $this->line('Sending test email to <info>' . $to . '</info>...');

        try {
            Mail::raw(
                "This is a test email from " . config('app.name') . ".\n\nSent at: " . now()->toDateTimeString() . "\n\nIf you received this, your mail configuration is working.",
                function ($message) use ($to) {
                    $message->to($to)
                        ->subject('Test email from ' . config('app.name'));
                }
            );
            $this->info('Test email sent successfully. Check the inbox (and spam) for ' . $to);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
