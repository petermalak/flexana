<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use Illuminate\Console\Command;

/**
 * Assign category_id to services based on any matching text: name, description, or other fields.
 * Matches: "yoga" → Yoga category; "reformer" / "reform pilates" → Reformer Pilates.
 * Use --only-uncategorized to update only services with no category; omit to process all services.
 */
class CategorizeServicesCommand extends Command
{
    protected $signature = 'services:categorize
                            {--only-uncategorized : Only assign category to services that have no category yet}
                            {--dry-run : Show what would be updated without saving}';

    protected $description = 'Assign category_id to all services from name, description, or any text that matches (yoga → Yoga, reformer → Reformer Pilates).';

    public function handle(): int
    {
        $onlyUncategorized = $this->option('only-uncategorized');
        $dryRun = $this->option('dry-run');

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            $this->warn('No active categories found. Create categories in Filament (Settings → Categories) first.');

            return self::FAILURE;
        }

        $query = ServiceModel::query();
        if ($onlyUncategorized) {
            $query->whereNull('category_id');
        }
        $services = $query->get();

        if ($services->isEmpty()) {
            $this->info($onlyUncategorized
                ? 'No uncategorized services found.'
                : 'No services found.');

            return self::SUCCESS;
        }

        $categoryByKey = $this->buildCategoryLookup($categories);
        $updated = 0;
        $skipped = 0;
        $unchanged = 0;

        foreach ($services as $service) {
            $inferred = $this->inferCategoryNameFromText($this->serviceTextToMatch($service));
            if ($inferred === null) {
                $this->line("  Skip (no match): {$service->name} (id: {$service->id})");
                $skipped++;
                continue;
            }

            $category = $categoryByKey[$inferred] ?? null;
            if ($category === null) {
                $this->line("  Skip (category '{$inferred}' not in DB): {$service->name} (id: {$service->id})");
                $skipped++;
                continue;
            }

            if ((int) $service->category_id === (int) $category->id) {
                $unchanged++;
                continue;
            }

            if (! $dryRun) {
                $service->category_id = $category->id;
                $service->save();
            }
            $this->line(($dryRun ? '[DRY-RUN] ' : '') . "  {$service->name} (id: {$service->id}) → {$category->name} (category id: {$category->id})");
            $updated++;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("[DRY-RUN] Would update {$updated} service(s). Run without --dry-run to apply.");
        } else {
            $this->info("Updated: {$updated}, Unchanged: {$unchanged}, Skipped (no match or category missing): {$skipped}.");
        }

        return self::SUCCESS;
    }

    /**
     * All service fields that may contain category hints (name, description, etc.).
     */
    private function serviceTextToMatch(ServiceModel $service): string
    {
        $parts = array_filter([
            $service->name ?? '',
            $service->description ?? '',
        ]);

        return implode(' ', $parts);
    }

    /**
     * Lookup by category name, slug, and canonical "Yoga" / "Reformer Pilates".
     * Each canonical key is assigned to at most one category: prefer a category dedicated to that type
     * (e.g. yoga-only or pilates-only) so that "Yoga & Mat Pilates Classes" doesn't claim both.
     */
    private function buildCategoryLookup($categories): array
    {
        $out = [];
        foreach ($categories as $cat) {
            $out[$cat->name] = $cat;
            if ($cat->slug) {
                $out[$cat->slug] = $cat;
            }
        }

        $nameLower = fn ($c) => strtolower($c->name ?? '');
        $slugLower = fn ($c) => strtolower($c->slug ?? '');

        // Yoga: prefer category that has yoga but NOT pilates, so the combined one doesn't steal Reformer Pilates
        foreach ($categories as $cat) {
            $nl = $nameLower($cat);
            $sl = $slugLower($cat);
            if ($sl === 'yoga' || (str_contains($nl, 'yoga') && ! str_contains($nl, 'pilates'))) {
                $out['Yoga'] = $cat;
                break;
            }
        }
        if (! isset($out['Yoga'])) {
            $yogaCat = $categories->first(fn ($c) => str_contains($nameLower($c), 'yoga'));
            if ($yogaCat) {
                $out['Yoga'] = $yogaCat;
            }
        }

        // Reformer Pilates: prefer category that has reformer/pilates but NOT yoga
        foreach ($categories as $cat) {
            $nl = $nameLower($cat);
            $sl = $slugLower($cat);
            if (in_array($sl, ['reformer-pilates', 'pilates'], true)
                || str_contains($nl, 'reformer')
                || (str_contains($nl, 'pilates') && ! str_contains($nl, 'yoga'))) {
                $out['Reformer Pilates'] = $cat;
                break;
            }
        }
        if (! isset($out['Reformer Pilates'])) {
            $reformerCat = $categories->first(fn ($c) => str_contains($nameLower($c), 'pilates') || str_contains($nameLower($c), 'reformer'));
            if ($reformerCat) {
                $out['Reformer Pilates'] = $reformerCat;
            }
        }

        return $out;
    }

    /**
     * Infer category name (Yoga | Reformer Pilates) from any text.
     * Uses service-type keywords: yoga styles (vinyasa, hatha, yin, flow, etc.) and pilates (mat pilates, barre, reformer).
     * If both match, Reformer Pilates wins.
     */
    public static function inferCategoryNameFromText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        $lower = strtolower($text);

        $reformerPilatesKeywords = [
            'reformer', 'reform pilates', 'reform pilate', 'mat pilates', 'pilates', 'barre', 'aerial pilates',
        ];
        foreach ($reformerPilatesKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Reformer Pilates';
            }
        }

        $yogaKeywords = [
            'yoga', 'vinyasa', 'hatha', 'ashtanga', 'restorative', 'yin yoga', 'yin ', 'yin-', 'yin&', 'flow',
            'meditation', 'breathwork', 'mindful', 'destress', 'gentle flow', 'prenatal', 'nidra', 'sukshma',
            'patanjali', 'splits', 'flexibility', 'aerial yoga', 'aerial hoop', 'aerial healing', 'sound meditation',
            'sculpt & yoga', 'hot sculpt', 'sculpt & strengthen', 'healing yoga', 'yin yang', 'bend & extend', 'stress relief',
        ];
        foreach ($yogaKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Yoga';
            }
        }
        if (preg_match('/\byin\b/', $lower) || str_contains($lower, 'vinyasa') || str_contains($lower, 'hatha')) {
            return 'Yoga';
        }

        return null;
    }
}
