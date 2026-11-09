<?php

namespace App\Services\MarketDemand;

use App\Services\Chatbot\ChatbotProvider;
use Illuminate\Support\Facades\Cache;

/**
 * The written "AI market overview" over a Buyer Demand / Market Demand report. The AI only sees
 * the aggregated figures (no listings, people or contact details) and is told to use nothing
 * else. One summary is reused for the same figures and language (config summary_cache_ttl), so
 * listings in the same segment share it. With no AI configured, or on failure, returns
 * `source: rules` and the page words the key findings itself.
 */
class MarketDemandSummary
{
    public function __construct(private readonly ChatbotProvider $provider)
    {
    }

    /** @return array{source: string, summary?: string, highlights?: string[]} */
    public function summarize(array $report, string $lang): array
    {
        if (!($report['available'] ?? false)) {
            return ['source' => 'none'];
        }

        $facts = $this->facts($report);
        $key = 'market-demand-summary:' . md5(json_encode($facts)) . ":{$lang}";
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $summary = $this->provider->isConfigured() ? $this->fromAi($facts, $lang) : null;
        if ($summary) {
            Cache::put($key, $summary, (int) config('market_demand.summary_cache_ttl'));

            return $summary;
        }

        return ['source' => 'rules'];
    }

    private function facts(array $r): array
    {
        $seg = $r['segment'];
        $type = $r['segment_info']['type_label']['en'] ?? $r['segment_info']['type'];
        $beds = $r['segment_info']['beds'];
        $money = fn ($n) => $n === null ? null : 'AED ' . ($n >= 1e6 ? round($n / 1e6, 2) . 'M' : round($n / 1e3) . 'K');
        $range = fn ($range) => $range ? $money($range[0]) . ($range[1] ? ' – ' . $money($range[1]) : '+') : null;
        $bedLabel = fn ($b) => $b === null ? null : ($b === 0 ? 'studio' : $b . ($b >= (int) config('market_demand.max_bedrooms') ? '+' : '') . '-bedroom');
        $block = fn (?array $s) => $s ? array_filter([
            'demand_level' => $s['level']['key'] ?? 'not enough activity to rate',
            'interest_per_listing_vs_' . ($s['level']['baseline'] ?? 'platform') . '_average' => isset($s['level']['ratio']) ? $s['level']['ratio'] . 'x' : null,
            'searches' => $s['searches'],
            'searches_change_pct' => $s['change']['searches'],
            'listing_views' => $s['views'],
            'views_change_pct' => $s['change']['views'],
            'enquiries' => $s['enquiries'],
            'enquiries_change_pct' => $s['change']['enquiries'],
            'live_listings' => $s['listings'],
        ], fn ($v) => $v !== null) : null;

        return array_filter([
            'area' => $r['area']['name'] ?? 'UAE',
            'city' => $r['area']['city'],
            'purpose' => $r['purpose'] === 'rent' ? 'rent (tenants)' : 'sale (buyers)',
            'period' => "last {$r['window_days']} days compared with the {$r['window_days']} days before",
            'segment' => $seg ? trim(($bedLabel($beds) ?? '') . ' ' . ($type ?? 'properties')) : null,
            'segment_demand' => $block($seg),
            'area_demand_all_types' => $block($r['area_demand']),
            'underserved_price_bands' => collect($r['price_bands']['items'])->where('underserved', true)
                ->map(fn ($b) => $range($b['range']) . " ({$b['demand_share']}% of interest, {$b['supply_share']}% of listings)")->values()->all(),
            'underserved_bedrooms' => collect($r['bedrooms']['items'])->where('underserved', true)
                ->map(fn ($b) => $bedLabel($b['key']) . " ({$b['demand_share']}% of interest, {$b['supply_share']}% of listings)")->values()->all(),
            'most_wanted_amenities' => collect($r['amenities'])->map(fn ($a) => $a['label']['en'] ?? str_replace('_', ' ', $a['key']))->values()->all(),
            'asking_price_median_same_type_and_bedrooms' => $money($r['pricing']['median'] ?? null),
            'this_listing_vs_that_median_pct' => $r['pricing']['vs_median_pct'] ?? null,
            'area_price_change_12_months_pct' => $r['pricing']['growth']['pct'] ?? null,
            'monthly_trend' => collect($r['trend'])->map(fn ($m) => "{$m['month']}: {$m['searches']} searches, {$m['views']} views, {$m['enquiries']} enquiries/favourites")->values()->all(),
        ], fn ($v) => $v !== null && $v !== []);
    }

    private function fromAi(array $facts, string $lang): ?array
    {
        $system = <<<TXT
You write the short "market demand" overview shown under a property listing on MW Realty, a UAE property portal.
The facts are anonymised, aggregated activity on MW Realty only (searches, saved searches, listing views, favourites and enquiries) plus live listing counts — not the whole market and not sold prices.
Use ONLY the facts in the JSON. Never invent numbers, trends, places, amenities or reasons. If a figure is missing, don't mention it.
Demand level compares interest per live listing with the city (or platform) average. "Underserved" means a bigger share of interest than of listings.
Write for a buyer or tenant in plain words, neutral in tone, no hype, no advice to buy, no prediction of future prices.
Write in the language with code "{$lang}". Plain text inside the JSON strings, no markdown.
Reply with JSON only, exactly this shape:
{"summary": "2 or 3 sentences on demand in this area and segment", "highlights": ["2 to 4 short facts, each with its number"]}
TXT;

        $reply = $this->provider->complete($system, json_encode($facts, JSON_UNESCAPED_UNICODE), 700);
        $json = $reply && preg_match('/\{.*\}/s', $reply, $m) ? json_decode($m[0], true) : null;
        if (!is_array($json) || !is_string($json['summary'] ?? null) || trim($json['summary']) === '') {
            return null;
        }
        $clean = fn ($s) => trim(str_replace(['**', '__'], '', (string) $s));

        return [
            'source' => 'ai',
            'summary' => $clean($json['summary']),
            'highlights' => array_slice(array_values(array_filter(array_map($clean, array_filter((array) ($json['highlights'] ?? []), 'is_string')))), 0, 4),
        ];
    }
}
