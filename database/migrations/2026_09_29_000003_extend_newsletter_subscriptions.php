<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_signups', function (Blueprint $table) {
            $table->boolean('is_subscribed')->default(true)->index();
            $table->string('unsubscribe_token', 64)->nullable()->unique();
        });

        DB::table('newsletter_signups')->whereNull('unsubscribe_token')->orderBy('id')->each(function ($signup) {
            DB::table('newsletter_signups')->where('id', $signup->id)->update([
                'unsubscribe_token' => Str::random(64),
            ]);
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 32);
            $table->unsignedBigInteger('content_id');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['content_type', 'content_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
        Schema::table('newsletter_signups', function (Blueprint $table) {
            $table->dropUnique(['unsubscribe_token']);
            $table->dropIndex(['is_subscribed']);
            $table->dropColumn(['is_subscribed', 'unsubscribe_token']);
        });
    }
};
