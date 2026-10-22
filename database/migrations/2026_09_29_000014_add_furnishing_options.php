<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Furnishing becomes a managed option list (CRM › Master › Property Options, Super Admin) like
 * Property Type / Listing Type / Completion Status. The existing property_details.furnished column
 * now holds the chosen option's value (was 1 / 0 → "furnished" / "unfurnished"); the website's
 * furnished filter treats anything but "unfurnished" as furnished (PropertyDetail::isFurnished).
 */
return new class extends Migration
{
    private const OPTIONS = [
        ['furnished', 'Furnished', 'مفروش'],
        ['semi_furnished', 'Semi-furnished', 'مفروش جزئياً'],
        ['unfurnished', 'Unfurnished', 'غير مفروش'],
    ];

    public function up(): void
    {
        // bool → option value, via a temporary column so it works on MySQL and SQLite alike.
        Schema::table('property_details', fn (Blueprint $table) => $table->string('furnished_option', 100)->nullable()->after('furnished'));
        DB::table('property_details')->where('furnished', true)->update(['furnished_option' => 'furnished']);
        DB::table('property_details')->where('furnished', false)->update(['furnished_option' => 'unfurnished']);
        Schema::table('property_details', fn (Blueprint $table) => $table->dropColumn('furnished'));
        Schema::table('property_details', fn (Blueprint $table) => $table->renameColumn('furnished_option', 'furnished'));

        if (!DB::table('filters')->where('key', 'furnishing')->exists()) {
            $filterId = DB::table('filters')->insertGetId([
                'key' => 'furnishing',
                'type' => 'select',
                'translations' => json_encode(['en' => ['label' => 'Furnishing'], 'ar' => ['label' => 'التأثيث']], JSON_UNESCAPED_UNICODE),
                'show_on' => json_encode([]), // not a website filter (the site keeps its furnished amenity filter)
                'order_index' => (int) DB::table('filters')->max('order_index') + 1,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (self::OPTIONS as $i => [$value, $en, $ar]) {
                DB::table('filter_values')->insert([
                    'filter_id' => $filterId,
                    'value' => $value,
                    'translations' => json_encode(['en' => ['label' => $en], 'ar' => ['label' => $ar]], JSON_UNESCAPED_UNICODE),
                    'order_index' => $i + 1,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $filterId = DB::table('filters')->where('key', 'furnishing')->value('id');
        if ($filterId) {
            DB::table('filter_values')->where('filter_id', $filterId)->delete();
            DB::table('filters')->where('id', $filterId)->delete();
        }

        Schema::table('property_details', fn (Blueprint $table) => $table->boolean('furnished_flag')->default(false)->after('furnished'));
        DB::table('property_details')->whereNotNull('furnished')->where('furnished', '!=', 'unfurnished')->update(['furnished_flag' => true]);
        Schema::table('property_details', fn (Blueprint $table) => $table->dropColumn('furnished'));
        Schema::table('property_details', fn (Blueprint $table) => $table->renameColumn('furnished_flag', 'furnished'));
    }
};
