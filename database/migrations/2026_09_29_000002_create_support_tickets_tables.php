<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Portal "Contact Us" support tickets — an agent/company raises a ticket (issue type, priority,
 * subject, message), then it becomes a thread between them and the admin team until solved.
 * Status changes are written into the same thread as `system` messages, so the ticket's full
 * history reads top to bottom in one place on both sides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('priority', 20)->default('normal');
            $table->string('subject', 150);
            $table->string('status', 20)->default('open');
            // Who spoke last — drives the "needs a reply" badge on each side.
            $table->string('last_reply_by', 10)->default('client');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['portal_user_id', 'status']);
            $table->index(['status', 'last_reply_at']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            // client | admin | system (status-change entries)
            $table->string('author_type', 10);
            $table->foreignId('portal_user_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('cms_admins')->nullOnDelete();
            $table->text('body');
            // Private `kyc` disk, served only through the ticket's own authorised routes.
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        // Same permission shape as other CMS modules, granted to superadmin so existing installs
        // don't need the roles seeder re-run. `edit` = reply + change status/priority.
        foreach (['view', 'edit'] as $action) {
            Permission::firstOrCreate(['name' => "support-tickets.{$action}", 'guard_name' => 'cms']);
        }
        Role::where('name', 'superadmin')->where('guard_name', 'cms')->first()
            ?->givePermissionTo(['support-tickets.view', 'support-tickets.edit']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Permission::where('name', 'like', 'support-tickets.%')->where('guard_name', 'cms')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
