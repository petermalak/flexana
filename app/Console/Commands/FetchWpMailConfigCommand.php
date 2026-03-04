<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fetch mail/SMTP configuration from the WordPress database (WP_DB_*).
 * Reads wp_options (or WP_OPTIONS_TABLE) for mail/smtp-related options and
 * prints Laravel .env-style lines you can copy into .env.
 */
class FetchWpMailConfigCommand extends Command
{
    protected $signature = 'wp:mail-config
                            {--show-raw : Show raw option_name and option_value for all mail-related options}
                            {--table= : Override options table name (e.g. rueyn_options)}';

    protected $description = 'Fetch mail configuration from WordPress database (WP_DB_*) and suggest MAIL_* for .env';

    public function handle(): int
    {
        $wpConfig = config('database.connections.wordpress');
        if (! $wpConfig || empty($wpConfig['database'])) {
            $this->error('WordPress DB not configured. Set WP_DB_HOST, WP_DB_DATABASE, WP_DB_USERNAME, WP_DB_PASSWORD in .env');
            return self::FAILURE;
        }

        try {
            DB::connection('wordpress')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to WordPress database: ' . $e->getMessage());
            return self::FAILURE;
        }

        $table = $this->option('table') ?: ($wpConfig['options_table'] ?? 'wp_options');
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->error('Invalid options table name. Use only letters, numbers, underscore.');
            return self::FAILURE;
        }

        $this->line('Using WordPress DB: <info>' . $wpConfig['database'] . '</info>, options table: <info>' . $table . '</info>');

        $rows = $this->fetchMailOptions($table);
        if ($rows->isEmpty()) {
            $this->warn('No mail/smtp-related options found in ' . $table . '.');
            $this->line('If your WordPress uses a different table prefix (e.g. rueyn_options), set WP_OPTIONS_TABLE=rueyn_options in .env or run: php artisan wp:mail-config --table=rueyn_options');
            return self::SUCCESS;
        }

        if ($this->option('show-raw')) {
            $this->table(['option_name', 'option_value'], $rows->map(fn ($r) => [
                $r->option_name,
                strlen($r->option_value) > 200 ? substr($r->option_value, 0, 200) . '...' : $r->option_value,
            ])->all());
            return self::SUCCESS;
        }

        $suggested = $this->parseMailOptions($rows);
        if (empty($suggested)) {
            $this->line('Mail-related options found but could not parse SMTP settings. Use --show-raw to inspect.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Add or update these in your <info>.env</info>:');
        $this->newLine();
        foreach ($suggested as $key => $value) {
            if ($key === '_MAIL_PASSWORD_NOTE') {
                $this->comment('# ' . $value);
                continue;
            }
            $display = $value;
            if (str_contains($value, "\n") || str_contains($value, '"')) {
                $display = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            }
            $this->line($key . '=' . $display);
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function fetchMailOptions(string $table): \Illuminate\Support\Collection
    {
        $sql = "SELECT option_name, option_value FROM `{$table}` WHERE " .
            "option_name LIKE '%mail%' OR option_name LIKE '%smtp%' OR option_name LIKE '%wp_mail%' OR option_name LIKE '%fluent%mail%' " .
            "ORDER BY option_name";
        try {
            return collect(DB::connection('wordpress')->select($sql));
        } catch (\Throwable $e) {
            $this->error('Query failed: ' . $e->getMessage());
            $this->line('Ensure the table exists (e.g. wp_options or rueyn_options). Set WP_OPTIONS_TABLE in .env to match your WordPress table prefix.');
            return collect();
        }
    }

    private function parseMailOptions(\Illuminate\Support\Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $value = @unserialize($row->option_value);
            if ($value === false && $row->option_value !== serialize(false)) {
                $value = $row->option_value;
            }
            if (! is_array($value)) {
                continue;
            }
            // WP Mail SMTP (smtp.host, mail.from_email) / FluentSMTP / common keys
            $host = $value['smtp']['host'] ?? $value['mail']['mailer_smtp_host'] ?? $value['smtp_host'] ?? $value['host'] ?? $value['mail']['smtp_host'] ?? null;
            $port = $value['smtp']['port'] ?? $value['mail']['mailer_smtp_port'] ?? $value['smtp_port'] ?? $value['port'] ?? $value['mail']['smtp_port'] ?? null;
            $user = $value['smtp']['user'] ?? $value['mail']['mailer_smtp_user'] ?? $value['smtp_user'] ?? $value['username'] ?? $value['mail']['smtp_user'] ?? null;
            $pass = $value['smtp']['pass'] ?? $value['mail']['mailer_smtp_pass'] ?? $value['smtp_pass'] ?? $value['password'] ?? $value['mail']['smtp_pass'] ?? null;
            $enc  = $value['smtp']['encryption'] ?? $value['mail']['mailer_smtp_encryption'] ?? $value['smtp_encryption'] ?? $value['encryption'] ?? $value['mail']['smtp_encryption'] ?? null;
            $from = $value['mail']['from_email'] ?? $value['mail']['mail_from_email'] ?? $value['from_email'] ?? $value['mail']['from_email'] ?? null;
            $name = $value['mail']['from_name'] ?? $value['mail']['mail_from_name'] ?? $value['from_name'] ?? $value['mail']['from_name'] ?? null;

            if ($host !== null && $host !== '') {
                $out['MAIL_MAILER'] = 'smtp';
                $out['MAIL_HOST'] = $host;
            }
            if ($port !== null && $port !== '') {
                $out['MAIL_PORT'] = (string) $port;
            }
            if ($user !== null && $user !== '') {
                $out['MAIL_USERNAME'] = $user;
            }
            // WP Mail SMTP Pro stores encrypted password; don't put it in .env (Laravel needs plain password)
            $passLooksEncrypted = is_string($pass) && strlen($pass) > 48 && base64_decode($pass, true) !== false;
            if ($pass !== null && $pass !== '' && ! $passLooksEncrypted) {
                $out['MAIL_PASSWORD'] = $pass;
            } elseif ($host !== null && ($user !== null && $user !== '')) {
                $out['_MAIL_PASSWORD_NOTE'] = '(set MAIL_PASSWORD manually - stored encrypted in WordPress)';
            }
            if ($from !== null && $from !== '') {
                $out['MAIL_FROM_ADDRESS'] = $from;
            }
            if ($name !== null && $name !== '') {
                $out['MAIL_FROM_NAME'] = $name;
            }
            if ($enc !== null && $enc !== '' && $enc !== 'none') {
                $out['MAIL_ENCRYPTION'] = strtolower($enc) === 'ssl' ? 'ssl' : 'tls';
            }
        }
        return $out;
    }
}
