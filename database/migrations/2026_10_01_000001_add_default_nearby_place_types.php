<?php

use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\NearbyPlace;
use Illuminate\Database\Migrations\Migration;

/**
 * Nearby place types are now managed by Super Admin under Master › Property Options — add the
 * starter types (NearbyPlace::DEFAULT_TYPES) that don't exist yet. Existing ones, and any labels
 * already edited there, are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $filter = Filter::firstOrCreate(['key' => NearbyPlace::FILTER_KEY], [
            'type' => 'select',
            'translations' => ['en' => ['label' => 'Nearby Place Type']],
            'show_on' => [],
            'order_index' => (int) Filter::max('order_index') + 1,
            'status' => true,
        ]);

        $order = (int) $filter->values()->max('order_index');
        $existing = $filter->values()->pluck('value')->all();
        foreach (array_diff_key(NearbyPlace::DEFAULT_TYPES, array_flip($existing)) as $value => $labels) {
            FilterValue::create([
                'filter_id' => $filter->id,
                'value' => $value,
                'translations' => collect($labels)->map(fn ($label) => ['label' => $label])->all(),
                'order_index' => ++$order,
                'status' => true,
            ]);
        }
    }

    public function down(): void
    {
        // Data fill only — the types may already be on nearby places.
    }
};
