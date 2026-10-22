<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['blogs' => 'blog', 'market_insights' => 'market-insight'] as $table => $type) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('status', true)->whereDate('published_at', '<=', today())
                ->select('id')->orderBy('id')->chunkById(500, function ($items) use ($type, $now) {
                    DB::table('newsletter_campaigns')->insertOrIgnore($items->map(fn ($item) => [
                        'content_type' => $type,
                        'content_id' => $item->id,
                        'sent_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
        }
    }

    public function down(): void
    {
        // Existing content should remain excluded from subscriber mailouts after rollback.
    }
};
