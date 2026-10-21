<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook "Data deletion callback" (Meta app settings): remember which Facebook user connected each
 * Page, so a deletion request for that user can remove their Pages, and log each request for its status page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_page_connections', function (Blueprint $table) {
            // App-scoped Facebook user id of whoever logged in to connect the Page.
            $table->string('facebook_user_id', 64)->nullable()->index()->after('connected_by');
        });

        Schema::create('facebook_data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('confirmation_code', 32)->unique();
            $table->string('facebook_user_id', 64)->index();
            $table->unsignedInteger('pages_removed')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_data_deletion_requests');
        Schema::table('facebook_page_connections', function (Blueprint $table) {
            $table->dropColumn('facebook_user_id');
        });
    }
};
