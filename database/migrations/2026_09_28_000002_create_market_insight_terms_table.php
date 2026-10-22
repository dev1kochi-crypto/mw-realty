<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed Market Insights topics and regions (Admin > Market Insights > Topics / Regions),
 * one table told apart by `type`. A post stores the term's slug in market_insights.topic / .region.
 * Starts with the default list so the admin form and filters work straight after deploy.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'topic' => [
            'market-report' => ['en' => 'Market Report', 'ar' => 'تقرير السوق'],
            'price-trends' => ['en' => 'Price Trends', 'ar' => 'اتجاهات الأسعار'],
            'rental-market' => ['en' => 'Rental Market', 'ar' => 'سوق الإيجار'],
            'investment' => ['en' => 'Investment Analysis', 'ar' => 'تحليل الاستثمار'],
            'area-spotlight' => ['en' => 'Area Spotlight', 'ar' => 'تسليط الضوء على المناطق'],
            'finance-regulation' => ['en' => 'Finance & Regulation', 'ar' => 'التمويل والتشريعات'],
        ],
        'region' => [
            'uae' => ['en' => 'UAE', 'ar' => 'الإمارات'],
            'dubai' => ['en' => 'Dubai', 'ar' => 'دبي'],
            'abu-dhabi' => ['en' => 'Abu Dhabi', 'ar' => 'أبوظبي'],
            'sharjah' => ['en' => 'Sharjah', 'ar' => 'الشارقة'],
            'ras-al-khaimah' => ['en' => 'Ras Al Khaimah', 'ar' => 'رأس الخيمة'],
            'ajman' => ['en' => 'Ajman', 'ar' => 'عجمان'],
        ],
    ];

    public function up()
    {
        Schema::create('market_insight_terms', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('slug', 50);
            $table->json('translations')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'status', 'order_index']);
        });

        $now = now();
        foreach (self::DEFAULTS as $type => $terms) {
            $order = 0;
            foreach ($terms as $slug => $labels) {
                DB::table('market_insight_terms')->insert([
                    'type' => $type,
                    'slug' => $slug,
                    'translations' => json_encode(array_map(fn ($label) => ['title' => $label], $labels), JSON_UNESCAPED_UNICODE),
                    'order_index' => ++$order,
                    'status' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('market_insight_terms');
    }
};
