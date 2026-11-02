<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\ListingReview\ListingReviewService;
use Illuminate\Console\Command;

/**
 * Listing Review for listings saved before it existed (new saves are screened automatically). Without
 * --apply it only reports what would be flagged. With --apply the cases are opened and flagged
 * listings are held off the website — oldest first, so of a unit listed twice the first stays live.
 */
class ScreenExistingListings extends Command
{
    protected $signature = 'properties:screen
        {--apply : Open the Listing Review cases (flagged listings go off the website until reviewed)}
        {--photos : Also queue photo fingerprinting (downloads every gallery — needs the queue worker)}';

    protected $description = 'Screen existing listings for Listing Review (duplicates, price, consistency, advertiser) — a dry run unless --apply';

    public function handle(ListingReviewService $reviews): int
    {
        $apply = (bool) $this->option('apply');
        $listings = Property::query()->available()->orderBy('created_at')->orderBy('id');
        $this->info(($apply ? 'Screening' : 'Dry run — nothing is changed. Checking') . " {$listings->count()} available listings…");

        $duplicates = 0;
        $byCode = [];
        $examples = [];
        $bar = $this->output->createProgressBar($listings->count());

        $listings->with('details')->chunkById(200, function ($chunk) use ($reviews, $apply, &$duplicates, &$byCode, &$examples, $bar) {
            foreach ($chunk as $property) {
                $found = $reviews->inspect($property);
                if ($found['duplicates']) {
                    $duplicates++;
                }
                foreach ($found['signals'] as $signal) {
                    $byCode[$signal['label']] = ($byCode[$signal['label']] ?? 0) + 1;
                    if (count($examples) < 10) {
                        $examples[] = [$property->reference_no, $signal['label'], \Illuminate\Support\Str::limit($signal['detail'], 90)];
                    }
                }
                if ($apply && ($found['duplicates'] || $found['signals'])) {
                    $reviews->screen($property, record: false, photos: (bool) $this->option('photos'));
                } elseif ($apply && $this->option('photos')) {
                    $reviews->screen($property, record: false);
                }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine(2);

        $this->table(['Found', 'Listings'], array_merge(
            [['Same unit as another agency (the newer one waits)', $duplicates]],
            collect($byCode)->map(fn ($n, $label) => [$label, $n])->values()->all(),
        ));
        if ($examples) {
            $this->line('Examples:');
            $this->table(['Ref', 'Flag', 'Detail'], $examples);
        }
        $this->info($apply
            ? 'Done — the cases are on Listing Review.'
            : 'Nothing was changed. Run again with --apply to open these cases (flagged listings go off the website until reviewed).');

        return self::SUCCESS;
    }
}
