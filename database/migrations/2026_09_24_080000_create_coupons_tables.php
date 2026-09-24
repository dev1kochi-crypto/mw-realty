<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('discount_type', 10); // percent | fixed (AED)
            $table->decimal('discount_value', 10, 2);
            $table->json('plan_ids')->nullable(); // null = every paid plan
            $table->string('duration', 12)->default('once'); // once | repeating | forever
            $table->unsignedSmallInteger('duration_months')->nullable(); // for "repeating"
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->unsignedInteger('max_uses_per_user')->nullable()->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // A coupon actually applied to an account — created when Super Admin approves the plan
        // request that used it. remaining_payments counts down per recorded payment (null = forever).
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_upgrade_request_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2);
            $table->unsignedSmallInteger('remaining_payments')->nullable();
            $table->unsignedSmallInteger('used_payments')->default(0);
            $table->dateTime('redeemed_at');
            $table->timestamps();

            $table->index(['portal_user_id', 'plan_id']);
        });

        Schema::table('plan_upgrade_requests', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('current_plan_id')->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->decimal('original_price', 10, 2)->nullable()->after('coupon_code');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('original_price');
            $table->decimal('final_price', 10, 2)->nullable()->after('discount_amount');
        });

        Schema::table('plan_payments', function (Blueprint $table) {
            $table->decimal('original_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('original_amount');
            $table->string('coupon_code', 40)->nullable()->after('discount_amount');
        });

        // Same permission shape every CMS module gets (see config/cms/common.php modules), granted
        // to superadmin here so existing installs don't need the roles seeder re-run.
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::firstOrCreate(['name' => "coupons.{$action}", 'guard_name' => 'cms']);
        }
        Role::where('name', 'superadmin')->where('guard_name', 'cms')->first()
            ?->givePermissionTo(['coupons.view', 'coupons.create', 'coupons.edit', 'coupons.delete']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('plan_payments', fn (Blueprint $table) => $table->dropColumn(['original_amount', 'discount_amount', 'coupon_code']));
        Schema::table('plan_upgrade_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'original_price', 'discount_amount', 'final_price']);
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Permission::where('name', 'like', 'coupons.%')->where('guard_name', 'cms')->delete();
    }
};
