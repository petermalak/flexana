<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\CustomerModel;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Console\Command;

class NormalizeCustomerPhonesCommand extends Command
{
    protected $signature = 'customers:normalize-phones {--dry-run : Show changes without saving} {--limit= : Max rows to process}';

    protected $description = 'Normalize customer phone numbers to canonical E.164 format (+20...)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit');
        $limit = $limit !== null ? max(1, (int) $limit) : null;

        $query = CustomerModel::query()
            ->select(['id', 'phone'])
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $total = 0;
        $changed = 0;

        $query->chunkById(500, function ($rows) use ($dryRun, &$total, &$changed) {
            foreach ($rows as $row) {
                $total++;
                $before = (string) ($row->phone ?? '');
                $after = PhoneNumberNormalizer::normalize($before);

                if ($before === $after) {
                    continue;
                }

                $changed++;
                $this->line("{$row->id}: {$before} -> {$after}" . ($dryRun ? ' (dry-run)' : ''));

                if (! $dryRun) {
                    $row->phone = $after;
                    $row->save();
                }
            }
        });

        $this->newLine();
        $this->info("Processed: {$total}. Changed: {$changed}." . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}

