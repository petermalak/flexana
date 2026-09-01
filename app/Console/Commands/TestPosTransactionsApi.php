<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\BranchModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class TestPosTransactionsApi extends Command
{
    protected $signature = 'pos:test-transactions
                            {--branch= : Branch ID (uses first active branch if omitted)}
                            {--from= : Start date Y-m-d (default: 30 days ago)}
                            {--to= : End date Y-m-d (default: today)}
                            {--url= : Base URL (default: APP_URL)}
                            {--username= : Existing POS username (API key name)}
                            {--secret= : Existing POS secret key}
                            {--create-key : Create a temporary branch-scoped API key for this test}';

    protected $description = 'Call GET /api/v1/pos/transactions with Username + Secret-Key headers';

    public function handle(): int
    {
        $branchId = $this->option('branch');
        if ($branchId === null || $branchId === '') {
            $branch = BranchModel::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->first();
            if (! $branch) {
                $this->error('No active branches found.');

                return self::FAILURE;
            }
            $branchId = (string) $branch->id;
            $this->line("Using branch: {$branch->name} (id={$branchId})");
        } else {
            $branchId = (string) (int) $branchId;
        }

        $bizTz = (string) config('app.business_timezone');
        $to = (string) ($this->option('to') ?: now($bizTz)->format('Y-m-d'));
        $from = (string) ($this->option('from') ?: now($bizTz)->subDays(30)->format('Y-m-d'));

        $paymentCount = PaymentModel::query()
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [
                Carbon::parse($from, $bizTz)->startOfDay(),
                Carbon::parse($to, $bizTz)->endOfDay(),
            ])
            ->count();
        $this->line("Paid payments in range (all branches): {$paymentCount}");

        $username = (string) ($this->option('username') ?? '');
        $secretKey = (string) ($this->option('secret') ?? '');

        if ($this->option('create-key') || $username === '' || $secretKey === '') {
            $result = ApiKey::generate('ritsa-pos-test-'.now()->format('YmdHis'), null, null, (int) $branchId);
            $username = $result['name'];
            $secretKey = $result['secret_key'];
            $this->info("Created POS user: {$username}");
            $this->line("Secret-Key: {$secretKey}");
        }

        $baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $query = http_build_query([
            'from' => $from,
            'to' => $to,
        ]);
        $fullUrl = "{$baseUrl}/api/v1/pos/transactions?{$query}";

        $this->newLine();
        $this->line('GET '.$fullUrl);
        $this->line("Username: {$username}");
        $this->newLine();

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Username' => $username,
                    'Secret-Key' => $secretKey,
                    'Accept' => 'application/json',
                ])
                ->get($fullUrl);

            $this->line('HTTP '.$response->status());
            $json = $response->json();
            if ($json !== null) {
                $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->line($response->body());
            }

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());
            $this->line('Start the server with: php artisan serve');

            return self::FAILURE;
        }
    }
}
