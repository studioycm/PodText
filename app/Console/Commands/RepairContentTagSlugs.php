<?php

namespace App\Console\Commands;

use App\Models\ContentTag;
use App\Support\Slugs\HebrewSlugger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RepairContentTagSlugs extends Command
{
    protected $signature = 'content-tags:repair-slugs';

    protected $description = 'Repair empty or legacy fallback content tag slugs with the Hebrew-aware slugger.';

    public function handle(): int
    {
        $scanned = 0;
        $repairedTags = 0;
        $repairedTranslations = 0;

        ContentTag::query()
            ->orderBy('id')
            ->each(function (ContentTag $tag) use (&$scanned, &$repairedTags, &$repairedTranslations): void {
                $scanned++;
                $tagWasRepaired = false;

                foreach ($tag->getTranslatedLocales('name') as $locale) {
                    $name = $tag->getTranslation('name', $locale, false);
                    $currentSlug = (string) $tag->getTranslation('slug', $locale, false);

                    if (! $this->shouldRepair($name, $currentSlug)) {
                        continue;
                    }

                    $tag->setTranslation('slug', $locale, $this->uniqueSlugFor($tag, $locale, $name));
                    $tagWasRepaired = true;
                    $repairedTranslations++;
                }

                if (! $tagWasRepaired) {
                    return;
                }

                $tag->saveQuietly();
                $repairedTags++;
            });

        $this->components->info("Scanned {$scanned} content tag(s); repaired {$repairedTranslations} slug translation(s) on {$repairedTags} tag(s).");

        return self::SUCCESS;
    }

    private function shouldRepair(string $name, string $currentSlug): bool
    {
        if (blank($currentSlug)) {
            return true;
        }

        if (! HebrewSlugger::isUlidLike($currentSlug)) {
            return false;
        }

        return filled(HebrewSlugger::slug($name, fallback: ''));
    }

    private function uniqueSlugFor(ContentTag $tag, string $locale, string $name): string
    {
        return HebrewSlugger::unique(
            $name,
            fn (string $slug): bool => ContentTag::query()
                ->where('type', $tag->type)
                ->where("slug->{$locale}", $slug)
                ->when($tag->exists, fn (Builder $query): Builder => $query->whereKeyNot($tag))
                ->exists(),
        );
    }
}
