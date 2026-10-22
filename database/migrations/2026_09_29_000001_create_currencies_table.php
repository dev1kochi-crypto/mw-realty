<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Display currencies for the website's currency switcher. Every price is STORED in AED (the base
 * currency, rate 1); `rate` is how many units of this currency one AED buys, so the site shows
 * price_aed × rate. Admin manages the list and rates (General Settings › Currencies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->decimal('rate', 20, 8)->default(1); // units of this currency per 1 AED
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        // Starting rates (per 1 AED) — approximate; admin should keep them up to date.
        $now = now();
        DB::table('currencies')->insert(collect([
            ['AED', 'UAE Dirham', 'AED', 1, true],
            ['USD', 'US Dollar', '$', 0.27229, false],
            ['EUR', 'Euro', '€', 0.2350, false],
            ['GBP', 'British Pound', '£', 0.2030, false],
            ['INR', 'Indian Rupee', '₹', 23.90, false],
            ['SAR', 'Saudi Riyal', 'SAR', 1.0211, false],
            ['QAR', 'Qatari Riyal', 'QAR', 0.9912, false],
            ['KWD', 'Kuwaiti Dinar', 'KWD', 0.0834, false],
            ['CNY', 'Chinese Yuan', '¥', 1.9500, false],
            ['RUB', 'Russian Ruble', '₽', 22.00, false],
        ])->map(fn ($c, $i) => [
            'code' => $c[0], 'name' => $c[1], 'symbol' => $c[2], 'rate' => $c[3], 'is_default' => $c[4],
            'status' => true, 'order_index' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        $permissions = collect(['view', 'create', 'edit', 'delete'])
            ->map(fn ($action) => Permission::firstOrCreate(['name' => "currencies.{$action}", 'guard_name' => 'cms']));
        Role::where('name', 'superadmin')->where('guard_name', 'cms')->first()?->givePermissionTo($permissions);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
        Permission::where('guard_name', 'cms')->where('name', 'like', 'currencies.%')->delete();
    }
};
