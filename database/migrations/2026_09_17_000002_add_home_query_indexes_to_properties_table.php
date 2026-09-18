<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every home-page query (Developments, Premium Properties, Luxury, Realty) filters on
 * `status` first, then sorts by `published_at` or `price`, always taking a small, fixed
 * slice (take(4)/take(8)). Without these composite indexes, each of those becomes a full
 * table scan once `properties` grows into the tens of thousands of rows.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'properties_status_published_at_index');
            $table->index(['status', 'price'], 'properties_status_price_index');
            $table->index(['status', 'featured', 'published_at'], 'properties_status_featured_published_at_index');
        });
    }

    public function down()
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_status_published_at_index');
            $table->dropIndex('properties_status_price_index');
            $table->dropIndex('properties_status_featured_published_at_index');
        });
    }
};
