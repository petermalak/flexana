<?php

namespace App\Console\Commands;

use App\Support\InternalNotificationMail;
use Illuminate\Console\Command;

class MailTestTransactionalCommand extends Command
{
    protected $signature = 'mail:test-transactional
                            {customer : Customer email (same as booking/package To:)}
                            {--internal= : Internal copy address (default: mail.internal_copy.address)}';

    protected $description = 'Send a test like production: one message To customer, one InternalNotificationMail copy';

    public function handle(): int
    {
        $customer = $this->argument('customer');
        if (! filter_var($customer, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid customer email: ' . $customer);

            return self::FAILURE;
        }

        $internalOverride = $this->option('internal');
        if ($internalOverride !== null && $internalOverride !== '') {
            if (! filter_var($internalOverride, FILTER_VALIDATE_EMAIL)) {
                $this->error('Invalid internal email: ' . $internalOverride);

                return self::FAILURE;
            }
            config(['mail.internal_copy.address' => $internalOverride]);
        }

        $internal = config('mail.internal_copy.address');
        $subject = 'Flexana mail test (transactional pattern)';

        $body = "This is a test of the same flow as booking/package emails.\n\n"
            . 'Customer (To): ' . $customer . "\n"
            . 'Internal copy (separate message To): ' . ($internal ?: '(disabled — empty MAIL_INTERNAL_COPY_ADDRESS)') . "\n\n"
            . 'Sent at: ' . now()->toDateTimeString() . "\n\n"
            . "If both inboxes receive this, internal copy is working.\n";

        $this->line('MAIL_FROM: <info>' . config('mail.from.address') . '</info>');
        $this->line('Customer To: <info>' . $customer . '</info>');
        $this->line('Internal copy To: <info>' . ($internal ?: '(none)') . '</info>');
        $this->newLine();

        try {
            InternalNotificationMail::sendCustomerAndInternalCopy($body, $subject, $customer, '');
            $this->info('Customer + internal copy sent (same code path as booking/package emails).');
        } catch (\Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Check ' . $customer . ' and ' . ($internal ?: 'internal (skipped)') . ' (including spam).');

        return self::SUCCESS;
    }
}
