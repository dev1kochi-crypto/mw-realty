<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client advertising (CRM › Advertisements, see App\Services\AdBookingService): agencies / agents on a
 * plan that includes ads book a banner on a page of the website for some hours or days. It runs in that
 * page's ad slider next to MW Realty's own ads once an admin approves it (CMS › Ads › Client Ads).
 *
 *  - ads: owner, review state, what the ad links to, how it was paid, and starts_at / ends_at become
 *    date-times (bookings are by the hour; ends_at is the moment it stops).
 *  - ad_placements: per page — how many client ads can run there at once and what an hour / a day
 *    costs in credits.
 *  - ad_stats: impressions and clicks per ad per hour (the CRM report).
 *  - plans: ads_per_month allowance + ad_max_hours (longest booking; 0 = advertising not included).
 */
return new class extends Migration
{
    /** Ad::PLACEMENTS at the time of writing — client bookable unless listed in NOT_BOOKABLE. */
    private const PLACEMENTS = [
        'home', 'commercial', 'agents', 'agent-details', 'agencies', 'agency-details', 'blogs', 'blog-details',
        'properties-dubai', 'premium-properties', 'marketing-properties', 'property-details', 'market-insights', 'terms-and-conditions',
    ];
    private const NOT_BOOKABLE = ['terms-and-conditions', 'marketing-properties'];

    public function up(): void
    {
        $existing = DB::table('ads')->get(['id', 'starts_at', 'ends_at']);

        Schema::table('ads', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->change();
            $table->dateTime('ends_at')->nullable()->change();
        });

        // Date-only windows become Dubai days: from the start of the first day until the end of the last.
        foreach ($existing as $row) {
            DB::table('ads')->where('id', $row->id)->update([
                'starts_at' => $row->starts_at ? Carbon::parse(substr($row->starts_at, 0, 10), 'Asia/Dubai')->startOfDay()->utc() : null,
                'ends_at' => $row->ends_at ? Carbon::parse(substr($row->ends_at, 0, 10), 'Asia/Dubai')->endOfDay()->utc() : null,
            ]);
        }

        Schema::table('ads', function (Blueprint $table) {
            $table->foreignId('portal_user_id')->nullable()->after('id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('review_status', 12)->default('approved')->after('status'); // pending | approved | rejected
            $table->text('review_note')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('review_note');
            $table->string('reviewed_by')->nullable()->after('reviewed_at');
            $table->string('link_type', 10)->default('url')->after('link_url');      // url | property | profile
            $table->foreignId('property_id')->nullable()->after('link_type')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('duration_hours')->nullable()->after('ends_at');
            $table->string('paid_with', 10)->nullable()->after('duration_hours');    // plan | credits
            $table->unsignedInteger('credits')->default(0)->after('paid_with');
            $table->foreignId('credit_transaction_id')->nullable()->after('credits')->constrained('credit_transactions')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('credit_transaction_id');

            $table->index(['placement', 'review_status', 'starts_at', 'ends_at']);
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('placement')->unique();
            $table->boolean('client_bookable')->default(true);
            $table->unsignedSmallInteger('max_slots')->default(5);       // client ads running at once
            $table->unsignedInteger('credits_per_hour')->default(10);
            $table->unsignedInteger('credits_per_day')->default(150);
            $table->timestamps();
        });

        foreach (self::PLACEMENTS as $placement) {
            DB::table('ad_placements')->insert([
                'placement' => $placement,
                'client_bookable' => !in_array($placement, self::NOT_BOOKABLE, true),
                'max_slots' => $placement === 'home' ? 6 : 4,
                'credits_per_hour' => $placement === 'home' ? 20 : 10,
                'credits_per_day' => $placement === 'home' ? 300 : 150,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('ad_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['ad_id', 'date', 'hour']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('ads_per_month')->default(0)->after('featured_period');
            $table->unsignedSmallInteger('ad_max_hours')->default(0)->after('ads_per_month');
        });

        // Paid plans, cheapest first: hours only → one day → two → three days (the top plans).
        $tiers = [[2, 6], [4, 24], [6, 48], [10, 72]];
        DB::table('plans')->where('billing_cycle', '!=', 'free')->orderBy('order_index')->pluck('id')
            ->each(function ($id, $i) use ($tiers) {
                [$perMonth, $maxHours] = $tiers[min($i, count($tiers) - 1)];
                DB::table('plans')->where('id', $id)->update(['ads_per_month' => $perMonth, 'ad_max_hours' => $maxHours]);
            });

        // The CRM's "!" guide for the new page (only adds guides that are missing).
        if (Schema::hasTable('help_guides')) {
            (new \Database\Seeders\HelpGuideSeeder())->run();
        }
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['ads_per_month', 'ad_max_hours']));
        Schema::dropIfExists('ad_stats');
        Schema::dropIfExists('ad_placements');

        DB::table('ads')->whereNotNull('portal_user_id')->delete();
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['placement', 'review_status', 'starts_at', 'ends_at']);
            $table->dropConstrainedForeignId('portal_user_id');
            $table->dropConstrainedForeignId('property_id');
            $table->dropConstrainedForeignId('credit_transaction_id');
            $table->dropColumn(['review_status', 'review_note', 'reviewed_at', 'reviewed_by', 'link_type', 'duration_hours', 'paid_with', 'credits', 'cancelled_at']);
        });
        Schema::table('ads', function (Blueprint $table) {
            $table->date('starts_at')->nullable()->change();
            $table->date('ends_at')->nullable()->change();
        });
    }
};
