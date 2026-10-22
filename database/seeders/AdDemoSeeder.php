<?php

namespace Database\Seeders;

use App\Models\AdStat;
use App\Models\CmsKit\Ad;
use App\Models\CmsKit\HouseAd;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\AdBookingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo advertising data (not part of DatabaseSeeder — run it on demand):
 *   php artisan db:seed --class=AdDemoSeeder
 *
 * Client ads from agencies / agents whose plan includes ads, each banner made from one of their own
 * listings and (mostly) linking to that listing's page — in every state: live, waiting for review,
 * approved & upcoming, ended, stopped early, rejected, cancelled, not reviewed in time. Plus a few
 * MW Realty ads that are scheduled, ended and switched off, and hour-by-hour impressions / clicks
 * for everything that has run. Re-running replaces the previous demo ads (banners pointing at a
 * listing photo — real uploads live in the ads folder and are never touched).
 */
class AdDemoSeeder extends Seeder
{
    private const TZ = AdBookingService::TIMEZONE;

    private Carbon $hour;

    public function run(): void
    {
        $this->hour = now(self::TZ)->startOfHour();

        DB::transaction(function () {
            // Previous demo ads (their banner is a listing photo, not an upload) and their stats.
            Ad::where('image', 'like', '%/properties/%')->delete();

            $owners = PortalUser::where('status', 'approved')->with('plan')->get()
                ->filter(fn (PortalUser $u) => $u->listingOwner()->id === $u->id && $u->plan?->status && $u->plan->includesAds())
                ->filter(fn (PortalUser $u) => $this->listing($u))
                ->values();
            if ($owners->count() < 3) {
                $this->command?->warn('Needs at least 3 approved agencies / agents with an ads plan and an active listing with photos.');
                return;
            }
            // Longest plans first, so the multi-day bookings land on plans that allow them.
            $owners = $owners->sortByDesc(fn ($u) => $u->plan->ad_max_hours)->values();
            $pick = fn (int $i) => $owners[$i % $owners->count()];
            $fits = fn (PortalUser $u, int $hours) => min($hours, (int) $u->plan->ad_max_hours);

            $count = 0;
            foreach ([...$this->clientAds(), ...$this->perPage()] as $i => $spec) {
                $owner = $spec['hours'] > 24 ? $pick($i % 3) : $pick($i);
                $hours = $fits($owner, $spec['hours']);
                $this->client($owner, $spec, $hours, $i);
                $count++;
            }

            foreach ($this->houseAds() as $i => $spec) {
                $this->house($spec, $i);
                $count++;
            }

            $this->command?->info("Seeded {$count} demo ads with impressions and clicks.");
        });
    }

    /**
     * start = hours from the current hour (negative = already started), hours = length.
     * Client ads only: review = approved | pending | rejected | expired, cancelled = bool, stop = hours it ran before being stopped.
     */
    private function clientAds(): array
    {
        return [
            ['placement' => 'home', 'start' => -3, 'hours' => 72, 'review' => 'approved', 'pay' => 'credits', 'eyebrow' => 'New launch', 'cta' => 'Sea-view living, handover 2027'],
            ['placement' => 'properties-dubai', 'start' => -20, 'hours' => 48, 'review' => 'approved', 'pay' => 'plan', 'eyebrow' => 'Just listed', 'cta' => 'Book a private viewing this week'],
            ['placement' => 'property-details', 'start' => -2, 'hours' => 6, 'review' => 'approved', 'pay' => 'plan', 'eyebrow' => 'Featured home', 'cta' => 'Flexible payment plan available'],
            ['placement' => 'premium-properties', 'start' => -5, 'hours' => 12, 'review' => 'approved', 'pay' => 'credits', 'eyebrow' => 'Price reduced', 'cta' => 'Now 8% below market value'],
            ['placement' => 'home', 'start' => 3, 'hours' => 6, 'review' => 'pending', 'pay' => 'plan', 'eyebrow' => 'Open house', 'cta' => 'Saturday 11am – 4pm'],
            ['placement' => 'properties-dubai', 'start' => 26, 'hours' => 24, 'review' => 'pending', 'pay' => 'credits', 'eyebrow' => 'Exclusive', 'cta' => 'Only 3 units left', 'link' => 'profile'],
            ['placement' => 'commercial', 'start' => 9, 'hours' => 12, 'review' => 'pending', 'pay' => 'plan', 'eyebrow' => 'Office space', 'cta' => 'Fitted offices from AED 95/sq ft'],
            ['placement' => 'premium-properties', 'start' => 50, 'hours' => 48, 'review' => 'approved', 'pay' => 'credits', 'eyebrow' => 'Coming soon', 'cta' => 'Register your interest today'],
            ['placement' => 'blogs', 'start' => 20, 'hours' => 12, 'review' => 'approved', 'pay' => 'plan', 'eyebrow' => 'Investor pick', 'cta' => '7.2% expected rental yield', 'link' => 'url'],
            ['placement' => 'home', 'start' => -96, 'hours' => 72, 'review' => 'approved', 'pay' => 'credits', 'eyebrow' => 'Waterfront', 'cta' => 'Private beach access'],
            ['placement' => 'agents', 'start' => -50, 'hours' => 24, 'review' => 'approved', 'pay' => 'plan', 'eyebrow' => 'Meet our team', 'cta' => 'Free valuation for sellers', 'link' => 'profile'],
            ['placement' => 'properties-dubai', 'start' => -40, 'hours' => 24, 'review' => 'approved', 'pay' => 'plan', 'stop' => 9, 'eyebrow' => 'Ready to move', 'cta' => 'Fully furnished, vacant'],
            ['placement' => 'home', 'start' => 30, 'hours' => 6, 'review' => 'rejected', 'pay' => 'credits', 'note' => 'The banner text is too small to read on phones. Please use a bigger title and book again.', 'eyebrow' => 'BEST DEAL!!!', 'cta' => 'Call now call now call now'],
            ['placement' => 'property-details', 'start' => -30, 'hours' => 6, 'review' => 'rejected', 'pay' => 'plan', 'note' => 'The link opens a listing that has already been sold.', 'eyebrow' => 'Hot deal', 'cta' => 'Last chance'],
            ['placement' => 'market-insights', 'start' => 14, 'hours' => 12, 'review' => 'pending', 'pay' => 'plan', 'cancelled' => true, 'eyebrow' => 'Market update', 'cta' => 'Prices up 12% this year'],
            ['placement' => 'agency-details', 'start' => -15, 'hours' => 6, 'review' => 'expired', 'pay' => 'credits', 'eyebrow' => 'Weekend offer', 'cta' => 'Zero agency fee'],
        ];
    }

    /**
     * A full set on every page clients can book: live now (two on the busy pages), waiting for
     * review, approved & upcoming, ended — plus a rejected one on every third page and a cancelled
     * one on every fourth. Live ads stay within the page's slots.
     */
    private function perPage(): array
    {
        $hooks = [
            ['Just listed', 'Book a viewing this week'], ['Price reduced', 'Now below market value'], ['Open house', 'This Saturday, 11am – 4pm'],
            ['New launch', 'Flexible 60/40 payment plan'], ['Exclusive', 'Only a few units left'], ['Investor pick', 'Strong rental yield'],
            ['Ready to move', 'Vacant on transfer'], ['Waterfront', 'Private beach access'], ['Family home', 'Near top-rated schools'],
            ['Golden Visa', 'Eligible property'], ['Furnished', 'Move in with one suitcase'], ['Luxury', 'Panoramic skyline views'],
        ];
        $busy = ['home', 'properties-dubai', 'property-details'];
        $specs = [];

        foreach (\App\Models\AdPlacement::catalog()->where('client_bookable', true)->keys()->values() as $k => $page) {
            $hook = fn (int $n) => ['eyebrow' => $hooks[($k + $n) % count($hooks)][0], 'cta' => $hooks[($k + $n) % count($hooks)][1]];
            $link = fn (int $n) => ($k + $n) % 5 === 0 ? ['link' => 'profile'] : (($k + $n) % 7 === 0 ? ['link' => 'url'] : []);

            $specs[] = ['placement' => $page, 'start' => -(2 + $k % 5), 'hours' => $k % 2 ? 24 : 12, 'review' => 'approved', 'pay' => $k % 2 ? 'credits' : 'plan'] + $hook(0) + $link(0);
            if (in_array($page, $busy, true)) {
                $specs[] = ['placement' => $page, 'start' => -1, 'hours' => 6, 'review' => 'approved', 'pay' => 'credits'] + $hook(1) + $link(1);
            }
            $specs[] = ['placement' => $page, 'start' => 4 + $k * 2, 'hours' => $k % 3 ? 12 : 24, 'review' => 'pending', 'pay' => $k % 2 ? 'plan' : 'credits'] + $hook(2) + $link(2);
            $specs[] = ['placement' => $page, 'start' => 30 + $k * 3, 'hours' => $k % 2 ? 48 : 12, 'review' => 'approved', 'pay' => 'credits'] + $hook(3) + $link(3);
            $specs[] = ['placement' => $page, 'start' => -(60 + $k * 6), 'hours' => 24, 'review' => 'approved', 'pay' => $k % 2 ? 'plan' : 'credits'] + $hook(4) + $link(4);
            if ($k % 3 === 0) {
                $specs[] = ['placement' => $page, 'start' => 20 + $k, 'hours' => 6, 'review' => 'rejected', 'pay' => 'credits',
                    'note' => ['The banner text is too small to read on phones.', 'The link opens a listing that is no longer available.', 'The image is blurry — please upload a sharper banner.'][$k % 3 === 0 ? ($k / 3) % 3 : 0]] + $hook(5);
            }
            if ($k % 4 === 1) {
                $specs[] = ['placement' => $page, 'start' => 40 + $k, 'hours' => 12, 'review' => 'pending', 'pay' => 'plan', 'cancelled' => true] + $hook(6);
            }
        }

        return $specs;
    }

    /** MW Realty's own: scheduled, ended and switched off (dates as whole Dubai days). */
    private function houseAds(): array
    {
        return [
            ['name' => 'Ramadan Offers — Home', 'placement' => 'home', 'from' => 5, 'until' => 19, 'status' => true, 'eyebrow' => 'Coming soon', 'cta' => 'Seasonal offers from MW Realty partners'],
            ['name' => 'Golden Visa Guide — Blogs', 'placement' => 'blogs', 'from' => 2, 'until' => null, 'status' => true, 'eyebrow' => 'Guide', 'cta' => 'Everything about the UAE Golden Visa'],
            ['name' => 'Summer Campaign — Properties', 'placement' => 'properties-dubai', 'from' => -40, 'until' => -10, 'status' => true, 'eyebrow' => 'Summer deals', 'cta' => 'Handpicked homes for the season'],
            ['name' => 'Q3 Market Report — Insights', 'placement' => 'market-insights', 'from' => -25, 'until' => -3, 'status' => true, 'eyebrow' => 'Report', 'cta' => 'Dubai Q3 market in numbers'],
            ['name' => 'Mortgage Partner — Property page', 'placement' => 'property-details', 'from' => null, 'until' => null, 'status' => false, 'eyebrow' => 'Finance', 'cta' => 'Pre-approval in 48 hours'],
            ['name' => 'Developer Showcase — Commercial', 'placement' => 'commercial', 'from' => -7, 'until' => 20, 'status' => false, 'eyebrow' => 'Showcase', 'cta' => 'New commercial towers'],
        ];
    }

    private function client(PortalUser $owner, array $spec, int $hours, int $i): void
    {
        $property = $this->listing($owner, $i);
        $start = $this->hour->copy()->addHours($spec['start']);
        $end = $start->copy()->addHours($hours);
        if (isset($spec['stop'])) {
            $end = $start->copy()->addHours(min($spec['stop'], $hours));
        }
        $placement = \App\Models\AdPlacement::for($spec['placement']);
        $link = $spec['link'] ?? 'property';
        $title = $property->getTranslation('title') ?: $property->reference_no;
        $review = $spec['review'] === 'expired' ? Ad::REJECTED : $spec['review'];

        $ad = Ad::create([
            'portal_user_id' => $owner->id,
            'name' => $this->short($title) . ' — ' . (\App\Models\CmsKit\Ad::PLACEMENTS[$spec['placement']] ?? $spec['placement']),
            'image' => $this->banner($property, 2400, 450),
            'mobile_image' => $this->banner($property, 800, 400),
            'image_alt' => $title,
            'translations' => ['en' => array_filter(['eyebrow' => $spec['eyebrow'], 'title' => $this->short($title), 'text' => $spec['cta']])],
            'link_type' => $link,
            'property_id' => $link === 'property' ? $property->id : null,
            'link_url' => $link === 'url' ? 'https://www.example.com/' . $property->slug : null,
            'placement' => $spec['placement'],
            'starts_at' => $start->copy()->utc(),
            'ends_at' => $end->copy()->utc(),
            'duration_hours' => $hours,
            'paid_with' => $spec['pay'],
            'credits' => $spec['pay'] === 'credits' ? ($placement?->cost($hours) ?? 0) : 0,
            'status' => true,
            'order_index' => 0,
            'review_status' => $review,
            'review_note' => $spec['review'] === 'expired' ? 'It wasn\'t reviewed before its booked time ended.' : ($spec['note'] ?? null),
            'reviewed_at' => $review === Ad::PENDING ? null : $start->copy()->subHours(5)->utc(),
            'reviewed_by' => match ($spec['review']) { 'pending' => null, 'expired' => 'System', default => 'Super Admin' },
            'cancelled_at' => !empty($spec['cancelled']) ? now()->subHours(2) : null,
        ]);

        if ($review === Ad::APPROVED && empty($spec['cancelled'])) {
            $this->stats($ad, $start, $end, $spec['placement'] === 'home' ? 1.6 : 1.0);
        }
    }

    private function house(array $spec, int $i): void
    {
        $property = Property::where('status', true)->whereNotNull('slug')->orderBy('id')->skip(10 + $i * 3)->first()
            ?? Property::where('status', true)->first();
        $from = $spec['from'] !== null ? $this->hour->copy()->addDays($spec['from'])->startOfDay() : null;
        $until = $spec['until'] !== null ? $this->hour->copy()->addDays($spec['until'])->endOfDay() : null;

        HouseAd::query()->increment('order_index');
        $ad = HouseAd::create([
            'name' => $spec['name'],
            'image' => $this->banner($property, 2400, 450),
            'mobile_image' => $this->banner($property, 800, 400),
            'image_alt' => $spec['name'],
            'translations' => ['en' => ['eyebrow' => $spec['eyebrow'], 'title' => $spec['name'], 'text' => $spec['cta']]],
            'link_type' => Ad::LINK_URL,
            'link_url' => '/properties',
            'placement' => $spec['placement'],
            'starts_at' => $from?->copy()->utc(),
            'ends_at' => $until?->copy()->utc(),
            'status' => $spec['status'],
            'review_status' => Ad::APPROVED,
            'order_index' => 1,
        ]);

        // Ran in the past: the part of its window before now (or the last 2 days for an "off" one that ran before).
        if ($from && $from->lt($this->hour)) {
            $this->stats($ad, $from->copy()->max($this->hour->copy()->subDays(14)), ($until && $until->lt($this->hour) ? $until : $this->hour), 0.8);
        } elseif (!$spec['status']) {
            $this->stats($ad, $this->hour->copy()->subDays(3), $this->hour->copy()->subDay(), 0.7);
        }
    }

    /**
     * A banner title that fits Ad::TEXT_LIMITS['title'] without being cut mid-word: the listing title,
     * or its part before " in …" ("Bright 1 Bedroom Apartment"), or whole words up to the limit.
     */
    private function short(string $title): string
    {
        $max = Ad::TEXT_LIMITS['title'];
        if (mb_strlen($title) <= $max) {
            return $title;
        }
        $head = trim(preg_split('/\s+in\s+/i', $title)[0]);
        if (mb_strlen($head) <= $max) {
            return $head;
        }

        return trim(preg_replace('/\s+\S*$/u', '', mb_substr($head, 0, $max + 1)));
    }

    /** An active listing of $owner with a slug and a photo ($i picks a different one per ad). */
    private function listing(PortalUser $owner, int $i = 0): ?Property
    {
        $listings = Property::where('portal_user_id', $owner->id)->where('status', true)->whereNotNull('slug')->orderByDesc('id')->limit(12)->get()
            ->filter(fn (Property $p) => !empty($p->galleryImages()[0]['url']))->values();

        return $listings->isEmpty() ? null : $listings[$i % $listings->count()];
    }

    /** The listing's first photo, cropped to a banner by Cloudinary (or as-is elsewhere). */
    private function banner(Property $property, int $width, int $height): string
    {
        $url = $property->galleryImages()[0]['url'];

        return str_contains($url, 'res.cloudinary.com/') && str_contains($url, '/upload/')
            ? str_replace('/upload/', "/upload/c_fill,g_auto,w_{$width},h_{$height},q_auto,f_auto/", $url)
            : $url;
    }

    /**
     * Hour-by-hour impressions / clicks from $from to $until (Dubai), shaped like real traffic:
     * quiet at night, busy late morning and evening, a little busier at weekends; CTR ~1–3.5%.
     */
    private function stats(Ad $ad, Carbon $from, Carbon $until, float $weight): void
    {
        $until = $until->copy()->min($this->hour->copy()->addHour());
        $curve = [0.15, 0.1, 0.07, 0.05, 0.05, 0.08, 0.2, 0.4, 0.6, 0.75, 0.85, 0.9, 0.85, 0.8, 0.75, 0.75, 0.8, 0.9, 1.0, 1.0, 0.95, 0.8, 0.55, 0.3];
        $ctr = mt_rand(10, 35) / 1000;
        $rows = [];

        for ($at = $from->copy()->startOfHour(); $at->lt($until); $at->addHour()) {
            $weekend = in_array($at->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true) ? 1.2 : 1.0;
            $impressions = (int) round(mt_rand(140, 220) * $curve[$at->hour] * $weekend * $weight);
            $clicks = (int) round($impressions * $ctr * (mt_rand(60, 140) / 100));
            $utc = $at->copy()->utc();
            $rows[] = [
                'ad_id' => $ad->id, 'date' => $utc->toDateString(), 'hour' => $utc->hour,
                'impressions' => $impressions, 'clicks' => $clicks, 'created_at' => $utc, 'updated_at' => $utc,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AdStat::insert($chunk);
        }
    }
}
