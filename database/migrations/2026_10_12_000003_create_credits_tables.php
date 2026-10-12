<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Credits — the add-on wallet next to plans (see App\Services\CreditService): bought in top-ups that
 * expire after credit_settings.validity_days, spent on an extra listing beyond the plan's allowance or
 * on Premium for 7 / 15 / 30 days without touching the plan's premium quota.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row: Super Admin's credit pricing (CMS › Credits).
        Schema::create('credit_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->decimal('price_per_credit', 10, 2)->default(2.77);
            $table->decimal('vat_percent', 5, 2)->default(5);
            $table->unsignedInteger('validity_days')->default(30);
            $table->unsignedInteger('min_topup')->default(100);
            $table->unsignedInteger('max_topup')->default(100000);
            $table->timestamps();
        });

        // What each add-on costs. The most specific active row matching a listing wins; empty
        // segment / listing type / property type / price bounds match anything.
        Schema::create('credit_rates', function (Blueprint $table) {
            $table->id();
            $table->string('segment', 20)->nullable();        // residential | commercial
            $table->string('listing_type', 20)->nullable();   // sale | rent
            $table->string('property_type', 50)->nullable();
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->unsignedInteger('listing')->default(0);    // one extra listing
            $table->unsignedInteger('premium_7')->default(0);
            $table->unsignedInteger('premium_15')->default(0);
            $table->unsignedInteger('premium_30')->default(0);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        // The ledger. Positive rows (topup / grant / refund) are lots with `remaining` + `expires_at`
        // that spending draws down oldest-expiring first; balance_after is the wallet after the row.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('actor_type', 10)->default('system'); // portal | admin | system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('type', 10);                           // topup | grant | spend | refund | expire
            $table->string('kind', 10)->nullable();               // listing | premium (spend / refund)
            $table->integer('credits');
            $table->integer('balance_after');
            $table->integer('remaining')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('description');
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('property_type')->nullable();
            $table->string('category')->nullable();
            $table->string('location')->nullable();
            $table->decimal('amount', 12, 2)->nullable();         // top-up price before VAT
            $table->decimal('vat', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['account_id', 'expires_at']);
            $table->index(['property_id', 'kind']);
        });

        // Premium paid with credits doesn't count against the plan's premium quota.
        Schema::table('property_featurings', function (Blueprint $table) {
            $table->foreignId('credit_transaction_id')->nullable()->after('property_id')->constrained('credit_transactions')->nullOnDelete();
        });

        DB::table('credit_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        $rates = [
            // segment, listing type, listing, premium 7 / 15 / 30
            ['residential', 'rent', 100, 101, 250, 453],
            ['residential', 'sale', 150, 150, 350, 600],
            ['commercial', 'rent', 150, 200, 380, 650],
            ['commercial', 'sale', 200, 280, 500, 850],
            [null, null, 150, 150, 350, 600], // anything else
        ];
        foreach ($rates as $i => [$segment, $type, $listing, $p7, $p15, $p30]) {
            DB::table('credit_rates')->insert([
                'segment' => $segment, 'listing_type' => $type,
                'listing' => $listing, 'premium_7' => $p7, 'premium_15' => $p15, 'premium_30' => $p30,
                'order_index' => $i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('property_featurings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_transaction_id');
        });
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_rates');
        Schema::dropIfExists('credit_settings');
    }
};
