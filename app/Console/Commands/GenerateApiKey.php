<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-key 
                            {name : The name/identifier for this API key}
                            {--ips=* : Comma-separated list of allowed IP addresses (optional)}
                            {--expires= : Expiration date (Y-m-d format, optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new API key pair for frontend authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $ips = $this->option('ips');
        $expires = $this->option('expires');

        // Parse IPs
        $allowedIps = null;
        if (!empty($ips)) {
            $allowedIps = [];
            foreach ($ips as $ipGroup) {
                $allowedIps = array_merge($allowedIps, explode(',', $ipGroup));
            }
            $allowedIps = array_map('trim', $allowedIps);
            $allowedIps = array_filter($allowedIps);
        }

        // Parse expiration date
        $expiresAt = null;
        if ($expires) {
            try {
                $expiresAt = new \DateTime($expires);
            } catch (\Exception $e) {
                $this->error("Invalid expiration date format. Use Y-m-d format (e.g., 2025-12-31)");
                return Command::FAILURE;
            }
        }

        // Generate the key
        $result = ApiKey::generate($name, $allowedIps, $expiresAt);

        $this->info('API Key generated successfully!');
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════');
        $this->line('  IMPORTANT: Save these credentials securely!');
        $this->line('  The secret key will NOT be shown again.');
        $this->line('═══════════════════════════════════════════════════════════');
        $this->newLine();
        
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $result['id']],
                ['UUID', $result['uuid']],
                ['Name', $result['name']],
                ['Key ID', $result['key_id']],
                ['Secret Key', $result['secret_key']],
                ['Created At', $result['created_at']],
                ['Allowed IPs', $allowedIps ? implode(', ', $allowedIps) : 'Any'],
                ['Expires At', $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : 'Never'],
            ]
        );

        $this->newLine();
        $this->warn('⚠️  Store the secret key securely. It cannot be retrieved later!');
        $this->newLine();

        return Command::SUCCESS;
    }
}
