<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncAmeliaCategoriesToMySQLCommand extends Command
{
    protected $signature = 'amelia:sync-categories-to-mysql
                            {--dry-run : Only report what would be done, do not write}
                            {--skip-duplicates : Skip when a category already exists (by amelia_category_id or slug)}
                            {--only-uncategorized : Only assign category_id for services with null category_id}';

    protected $description = 'Fetch Amelia/WordPress categories into MySQL and assign services.category_id';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipDuplicates = (bool) $this->option('skip-duplicates');
        $onlyUncategorized = (bool) $this->option('only-uncategorized');

        $wpConfig = config('database.connections.wordpress');
        if (! $wpConfig) {
            $this->error('WordPress database connection is not configured. Check WP_DB_* in .env.');
            return self::FAILURE;
        }

        $this->info('Fetching categories from WordPress/Amelia...');

        // Note: DB connection already includes WP_DB_PREFIX, so we query logical table name "categories".
        $canAccess = false;
        try {
            $canAccess = DB::connection('wordpress')->getPdo() !== null;
        } catch (\Throwable) {
            $canAccess = false;
        }
        if (! $canAccess) {
            $this->error('Cannot connect to WordPress/Amelia DB via connection "wordpress".');
            return self::FAILURE;
        }

        // Ensure the table exists (with prefix).
        $tableExists = DB::connection('wordpress')->getSchemaBuilder()->hasTable('categories');
        if (! $tableExists) {
            $this->warn('WordPress table "categories" not found. Check WP_DB_PREFIX / WP_DB_DATABASE.');
            return self::FAILURE;
        }

        $rows = DB::connection('wordpress')->table('categories')->get();
        if ($rows->isEmpty()) {
            $this->warn('No categories found in WordPress DB.');
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $id = $row->id ?? $row->ID ?? null;
            if ($id === null) {
                continue;
            }

            $name = $row->name ?? $row->title ?? ('Category ' . $id);
            $slug = $row->slug ?? Str::slug((string) $name);
            $description = $row->description ?? $row->content ?? null;
            $image = $row->image ?? null;
            $position = (int) ($row->position ?? $row->ordering ?? 0);

            // Prefer matching by Amelia category id, fallback to slug.
            $existing = Category::query()
                ->where('amelia_category_id', $id)
                ->first()
                ?? Category::query()->where('slug', $slug)->first();

            if ($existing) {
                if ($skipDuplicates) {
                    $skipped++;
                    continue;
                }

                if (! $dryRun) {
                    $existing->update([
                        'amelia_category_id' => $id,
                        'name' => (string) $name,
                        'slug' => (string) $slug,
                        'type' => 'service',
                        'description' => $description,
                        'image' => $image,
                        'position' => $position,
                        'status' => true,
                    ]);
                }
                $updated++;
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            Category::query()->create([
                'amelia_category_id' => $id,
                'name' => (string) $name,
                'slug' => (string) $slug,
                'type' => 'service',
                'description' => $description,
                'image' => $image,
                'position' => $position,
                'status' => true,
            ]);

            $created++;
        }

        $this->newLine();
        $this->info('Category import summary:');
        $this->line("  created: {$created}");
        $this->line("  updated: {$updated}");
        $this->line("  skipped: {$skipped}");

        $this->newLine();
        $this->info('Assigning services -> category_id...');

        $categorizeArgs = [];
        if ($dryRun) {
            $categorizeArgs['--dry-run'] = true;
        }
        if ($onlyUncategorized) {
            $categorizeArgs['--only-uncategorized'] = true;
        }

        // Uses existing logic that infers Yoga/Reformer Pilates categories from service name/description.
        $this->call('services:categorize', $categorizeArgs);

        return self::SUCCESS;
    }
}

