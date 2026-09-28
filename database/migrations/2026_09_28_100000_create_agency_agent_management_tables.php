<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agency ⇄ Agent relationship history, lead (enquiry) assignment to agents with persistent
 * round-robin state, and property assignment history.
 *
 * portal_users.company_id stays as the agent's *current* agency pointer (null = independent);
 * agency_agents is the full history behind it — every invitation, join request, approval and
 * departure is its own row, so an agent moving ABC Realty → independent → XYZ Realty keeps
 * every stint. Leads keep portal_user_id as the owning account (the agency, or an independent
 * agent) and gain agent_id as "who is working this lead" — property assignment
 * (properties.agent_id) and lead assignment (leads.agent_id) are deliberately separate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('portal_users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('portal_users')->cascadeOnDelete();
            // invited | requested | pending | approved | suspended | inactive | rejected | declined | cancelled
            $table->string('status', 20);
            // Who started it: agency (added/invited), agent (join request), admin (direct assignment)
            $table->string('initiated_by', 20);
            // True when the agency created a brand-new account for this agent (it has no login of its
            // own until admin approval sends a set-password link).
            $table->boolean('account_created_by_agency')->default(false);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable(); // cms admin id
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('ended_by', 20)->nullable(); // agency | agent | admin
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index(['agent_id', 'status']);
        });

        Schema::create('agency_lead_assignment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained('portal_users')->cascadeOnDelete();
            $table->foreignId('last_agent_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('portal_user_id')->constrained('portal_users')->nullOnDelete();
            // property_agent | round_robin | agency_unassigned | manual | reassigned
            $table->string('assignment_type', 30)->nullable()->after('agent_id');
            $table->timestamp('assigned_at')->nullable()->after('assignment_type');

            $table->index(['agent_id', 'created_at']);
            $table->index(['portal_user_id', 'agent_id']);
        });

        Schema::create('lead_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->foreignId('previous_agent_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->string('assignment_type', 30);
            $table->string('assigned_by_type', 20)->default('system'); // system | agency | agent | admin
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(['lead_id', 'assigned_at']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('created_by_type', 20)->nullable()->after('agent_id'); // admin | agency | agent
            $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by_type');
            $table->index(['portal_user_id', 'agent_id']);
        });

        Schema::create('property_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // created | agent_assigned | agent_changed | agent_unassigned | agent_left | transferred_to_agency
            $table->string('action', 30);
            $table->foreignId('from_agency_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->foreignId('to_agency_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->foreignId('from_agent_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->foreignId('to_agent_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->string('changed_by_type', 20)->default('system');
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['property_id', 'created_at']);
        });

        $this->backfill();
    }

    /** Brings existing rows in line with the new model so nothing already live changes behaviour. */
    private function backfill(): void
    {
        $now = now();

        // Every agent currently affiliated through company_id becomes an approved membership.
        DB::table('portal_users')->where('type', 'agent')->whereNotNull('company_id')
            ->orderBy('id')->select(['id', 'company_id', 'created_at'])
            ->chunk(500, function ($agents) use ($now) {
                DB::table('agency_agents')->insert($agents->map(fn ($agent) => [
                    'agency_id' => $agent->company_id,
                    'agent_id' => $agent->id,
                    'status' => 'approved',
                    'initiated_by' => 'agency',
                    'approved_at' => $agent->created_at ?? $now,
                    'joined_at' => $agent->created_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        // Round-robin row for every agency that already has agents (see LeadAssignmentService).
        DB::table('agency_lead_assignment_settings')->insertUsing(
            ['agency_id', 'created_at', 'updated_at'],
            DB::table('agency_agents')->selectRaw('DISTINCT agency_id, ?, ?', [$now, $now])
        );

        // Property creator: the owning account, or Super Admin for house listings with no owner.
        DB::table('properties')->whereNull('portal_user_id')->update(['created_by_type' => 'admin']);
        DB::table('properties')->whereNotNull('portal_user_id')->update([
            'created_by_type' => DB::raw("(SELECT CASE WHEN pu.type = 'company' THEN 'agency' ELSE 'agent' END FROM portal_users pu WHERE pu.id = properties.portal_user_id)"),
            'created_by_id' => DB::raw('portal_user_id'),
        ]);

        // Existing leads: an independent agent's lead is theirs; an agency lead goes to the
        // property's agent if it had one, otherwise it is agency-level unassigned.
        DB::table('leads')->whereNotNull('portal_user_id')
            ->whereIn('portal_user_id', DB::table('portal_users')->where('type', 'agent')->select('id'))
            ->update(['agent_id' => DB::raw('portal_user_id'), 'assignment_type' => 'property_agent', 'assigned_at' => DB::raw('created_at')]);

        DB::table('leads')->whereNotNull('portal_user_id')->whereNull('agent_id')
            ->whereIn('portal_user_id', DB::table('portal_users')->where('type', 'company')->select('id'))
            ->update([
                'agent_id' => DB::raw('(SELECT p.agent_id FROM properties p WHERE p.id = leads.property_id)'),
                'assigned_at' => DB::raw('created_at'),
            ]);
        DB::table('leads')->whereNotNull('portal_user_id')->whereNotNull('agent_id')->whereNull('assignment_type')
            ->update(['assignment_type' => 'property_agent']);
        DB::table('leads')->whereNotNull('portal_user_id')->whereNull('agent_id')->whereNull('assignment_type')
            ->update(['assignment_type' => 'agency_unassigned', 'assigned_at' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('property_assignment_history');
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['portal_user_id', 'agent_id']);
            $table->dropColumn(['created_by_type', 'created_by_id']);
        });
        Schema::dropIfExists('lead_assignment_history');
        Schema::table('leads', function (Blueprint $table) {
            // FK first — MySQL backs it with the composite indexes, so they can't go before it.
            $table->dropForeign(['agent_id']);
            $table->dropIndex(['agent_id', 'created_at']);
            $table->dropIndex(['portal_user_id', 'agent_id']);
            $table->dropColumn('agent_id');
            $table->dropColumn(['assignment_type', 'assigned_at']);
        });
        Schema::dropIfExists('agency_lead_assignment_settings');
        Schema::dropIfExists('agency_agents');
    }
};
