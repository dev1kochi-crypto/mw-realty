<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            // SEO block — same shape as Property/Blog/Landing Pages: meta_title, meta_description,
            // meta_keywords, canonical_url, og_title, og_description, og_image, other_meta_tags.
            $table->json('metadata')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('portal_users', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
