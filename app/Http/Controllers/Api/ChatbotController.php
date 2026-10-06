<?php

namespace App\Http\Controllers\Api;

use App\Models\Visitors\ChatConversation;
use App\Models\Visitors\ChatMessage;
use App\Models\Visitors\VisitorBrowser;
use App\Models\Visitors\VisitorEvent;
use App\Models\Visitors\VisitorLead;
use App\Rules\PhoneNumber;
use App\Rules\RecaptchaRule;
use App\Services\ChatbotService;
use App\Services\Visitors\VisitorTracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The site-wide AI chat widget. Anyone can ask their first question straight away; to keep going
 * they give their name, email and phone (start) — that makes them a website lead (VisitorTracker)
 * and the anonymous part of the chat is attached to them. Every message is saved, so Super Admin /
 * the agency they're routed to can read the transcript. A signed-in customer is already known.
 * The history sent to the model comes from the database, not the browser. See ChatbotService.
 */
class ChatbotController extends Controller
{
    private const HISTORY_LIMIT = 20;
    /** Questions a visitor can ask before sharing their details — counted per browser, so clearing the chat doesn't reset it. */
    private const ANONYMOUS_MESSAGES = 1;

    public function __construct(
        private readonly ChatbotService $chatbot,
        private readonly VisitorTracker $tracker,
    ) {
    }

    /** Widget opened: is this visitor known yet, and the latest conversation to pick up from. */
    public function session(Request $request)
    {
        $lead = $this->tracker->currentLead($request);
        $browser = $this->tracker->browser($request);
        $conversation = $this->latestConversation($browser, $lead);

        return response()->json([
            'identified' => (bool) $lead,
            'details_required' => !$lead && $this->anonymousLimitReached($browser),
            'conversation_id' => $conversation?->id,
            'messages' => $conversation ? $conversation->messages->map(fn ($m) => [
                'role' => $m->role,
                'text' => $m->text,
                'properties' => $m->properties ?? [],
            ]) : [],
        ]);
    }

    /**
     * The details form — identifies the visitor (switching this browser to them; their anonymous
     * chat is attached by VisitorTracker) and carries on the conversation they started.
     */
    public function start(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => PhoneNumber::emailRules(),
            'phone' => PhoneNumber::rules(true),
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'conversation_id' => 'nullable|integer',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $lead = $this->tracker->identify($request, $data, VisitorLead::SOURCE_CHATBOT);
        $conversation = (!empty($data['conversation_id'])
            ? ChatConversation::where('visitor_lead_id', $lead->id)->find($data['conversation_id'])
            : null) ?? $this->newConversation($request, $lead);

        return response()->json(['conversation_id' => $conversation->id, 'name' => $data['name']]);
    }

    /** "Clear conversation" — the old transcript is kept, the visitor just starts a fresh one. */
    public function reset(Request $request)
    {
        $lead = $this->tracker->currentLead($request);

        return response()->json(['conversation_id' => $lead ? $this->newConversation($request, $lead)->id : null]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer',
            'lang' => 'nullable|string|max:5',
        ]);

        $lead = $this->tracker->currentLead($request);
        $browser = $this->tracker->browser($request);

        if (!$lead && (!$browser || $this->anonymousLimitReached($browser))) {
            return response()->json(['details_required' => true, 'message' => 'Please share your details to keep chatting.'], 422);
        }

        $conversation = (!empty($data['conversation_id'])
            ? ChatConversation::where($lead ? ['visitor_lead_id' => $lead->id] : ['visitor_browser_id' => $browser->id, 'visitor_lead_id' => null])
                ->find($data['conversation_id'])
            : null) ?? $this->newConversation($request, $lead);

        $history = $conversation->messages()->reorder()->latest('id')->limit(self::HISTORY_LIMIT)->get()
            ->reverse()->map(fn ($m) => ['role' => $m->role, 'text' => $m->text])->values()->all();

        $reply = $this->chatbot->reply($data['message'], $history, $data['lang'] ?? app()->getLocale());

        DB::transaction(function () use ($conversation, $data, $reply, $request) {
            $conversation->messages()->create(['role' => 'user', 'text' => $data['message']]);
            // Provider failures ("something went wrong") aren't part of the conversation.
            if (filled($reply['reply'] ?? null) && empty($reply['error'])) {
                $conversation->messages()->create([
                    'role' => 'model',
                    'text' => $reply['reply'],
                    'properties' => !empty($reply['properties']) ? $reply['properties'] : null,
                ]);
            }

            // A just-created conversation has no message_count attribute yet (DB default), hence the cast.
            $firstMessage = (int) $conversation->message_count === 0;
            $conversation->forceFill([
                'message_count' => $conversation->messages()->count(),
                'last_message_at' => now(),
            ])->save();

            // Anonymous too — the event moves to the lead with the rest of the browser's history.
            if ($firstMessage) {
                $this->tracker->record($request, VisitorEvent::CHAT_STARTED, [
                    'chat_conversation_id' => $conversation->id,
                    'title' => Str::limit($data['message'], 200),
                    'url' => $request->header('referer'),
                ]);
            }
        });

        return response()->json($reply + [
            'conversation_id' => $conversation->id,
            // Free question used up — the widget asks for their details before the next one.
            'details_required' => !$lead && $this->anonymousLimitReached($browser),
        ]);
    }

    private function newConversation(Request $request, ?VisitorLead $lead): ChatConversation
    {
        return ChatConversation::create([
            'visitor_lead_id' => $lead?->id,
            'visitor_browser_id' => $this->tracker->browser($request)?->id,
        ]);
    }

    /** Questions this browser has asked without being identified. */
    private function anonymousLimitReached(?VisitorBrowser $browser): bool
    {
        if (!$browser) {
            return true;
        }

        return ChatMessage::where('role', 'user')
            ->whereHas('conversation', fn ($q) => $q->where('visitor_browser_id', $browser->id)->whereNull('visitor_lead_id'))
            ->count() >= self::ANONYMOUS_MESSAGES;
    }

    /** The latest conversation in this browser (theirs, or the anonymous one) — a fresh, empty one after "clear". */
    private function latestConversation(?VisitorBrowser $browser, ?VisitorLead $lead): ?ChatConversation
    {
        if (!$browser) {
            return null;
        }

        return ChatConversation::where('visitor_browser_id', $browser->id)
            ->where('visitor_lead_id', $lead?->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->with('messages')->latest('id')->first();
    }
}
