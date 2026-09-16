<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('rera_id')->nullable()->after('reference_no');
            // Filter-driven, same pattern as property_type/listing_type/location — stores the FilterValue slug.
            $table->string('category')->nullable()->after('property_type');
            $table->string('postal_code')->nullable()->after('location');
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->unsignedInteger('order_index')->default(0)->after('published_at');
            // SEO block — same shape as Blog/Landing Pages/Ads: meta_title, meta_description, meta_keywords,
            // canonical_url, og_title, og_description, og_image, other_meta_tags.
            $table->json('metadata')->nullable()->after('order_index');

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['rera_id', 'category', 'postal_code', 'latitude', 'longitude', 'published_at', 'order_index', 'metadata']);
        });
    }
};
