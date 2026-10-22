<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Market Insights — research-style posts (market reports, price trends, area spotlights),
 * managed in Admin > Market Insights (CmsKit\MarketInsightController).
 *
 * translations[lang]: title, summary, content (HTML), takeaways (one per line).
 * stats: up to 4 headline figures — [{value: "+11%", trend: "up|down|flat", label: {en, ar}}].
 * extra_fields: author_name, author_role.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('market_insights', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('topic', 50)->index();
            $table->string('region', 50)->nullable()->index();
            $table->date('published_at');
            // Three sizes of the post's image, each shown where it fits best (see MarketInsightPageService):
            // card = listing/related cards, detail = article banner, featured = featured-report card.
            $table->string('card_image')->nullable();
            $table->string('detail_image')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('report_file')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('order_index')->default(0);
            $table->boolean('status')->default(true);
            $table->json('translations')->nullable();
            $table->json('stats')->nullable();
            $table->json('extra_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'order_index']);
        });

        $permissions = collect(['view', 'create', 'edit', 'delete'])
            ->map(fn ($action) => Permission::firstOrCreate(['name' => "market-insights.{$action}", 'guard_name' => 'cms']));

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'cms')->first();
        $superadmin?->givePermissionTo($permissions);
    }

    public function down()
    {
        Permission::where('guard_name', 'cms')
            ->where('name', 'like', 'market-insights.%')
            ->delete();

        Schema::dropIfExists('market_insights');
    }
};
