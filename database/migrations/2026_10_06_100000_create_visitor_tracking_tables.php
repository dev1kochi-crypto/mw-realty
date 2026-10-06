<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website visitor tracking (see App\Services\Visitors\VisitorTracker):
 *   visitor_leads      — a person who identified themselves on the site (AI chat details form,
 *                        contact / enquiry form, customer account). Super Admin's Website Leads.
 *   visitor_browsers   — one per browser (the mw_vid cookie); points at the lead currently
 *                        using it, so a new email / phone in the same browser switches tracking.
 *   visitor_events     — everything they do: page / property views (with time spent), searches,
 *                        favorites, saved searches, chats, enquiries, routing / transfers.
 *   chat_conversations / chat_messages — the AI chat transcript.
 * leads.visitor_lead_id links a CRM lead back to the website lead it came from (Insights tab).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('phone_country_code', 5)->nullable();
            // Last 9 phone digits (LeadContact::phoneKey) — how "same phone" is matched.
            $table->string('phone_key', 20)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 50);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('visitor_browsers', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('visitor_lead_id')->nullable()->constrained('visitor_leads')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_lead_id')->nullable()->constrained('visitor_leads')->cascadeOnDelete();
            $table->foreignId('visitor_browser_id')->nullable()->constrained('visitor_browsers')->nullOnDelete();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('text');
            // Property cards shown with a reply, as the widget rendered them.
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('visitor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_lead_id')->nullable()->constrained('visitor_leads')->cascadeOnDelete();
            $table->foreignId('visitor_browser_id')->nullable()->constrained('visitor_browsers')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->string('url', 1000)->nullable();
            $table->string('title')->nullable();
            $table->json('meta')->nullable();
            // Seconds the page was actually visible (page / property views only).
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();

            $table->index(['visitor_lead_id', 'created_at']);
            $table->index(['visitor_browser_id', 'visitor_lead_id']);
            $table->index(['type', 'created_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('visitor_lead_id')->nullable()->after('user_id')->constrained('visitor_leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visitor_lead_id');
        });
        Schema::dropIfExists('visitor_events');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('visitor_browsers');
        Schema::dropIfExists('visitor_leads');
    }
};
