<?php

namespace App\Http\Controllers\Api;

use App\Mail\NewsletterSignupReceived;
use App\Models\CmsKit\NewsletterSignup;
use App\Models\CmsKit\SiteInformation;
use App\Rules\RecaptchaRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Public, unauthenticated — the footer newsletter form (POST /api/newsletter/subscribe).
 *
 * @group Contact & Newsletter
 */
class NewsletterController extends Controller
{
    /**
     * Subscribe to the newsletter
     *
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"already_subscribed": false, "message": "Thanks for subscribing!"}
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $email = mb_strtolower(trim($request->input('email')));
        $signup = NewsletterSignup::whereRaw('LOWER(email) = ?', [$email])->first();
        $alreadySubscribed = $signup?->is_subscribed ?? false;

        if (!$signup) {
            $signup = NewsletterSignup::create([
                'email' => $email,
                'is_subscribed' => true,
                'unsubscribe_token' => Str::random(64),
            ]);
        } elseif (!$signup->is_subscribed) {
            $signup->update(['is_subscribed' => true, 'unsubscribe_token' => Str::random(64)]);
        }

        if (!$alreadySubscribed) {
            $adminEmail = SiteInformation::notificationEmail();
            if ($adminEmail) {
                Mail::to($adminEmail)->queue((new NewsletterSignupReceived($signup))->afterCommit());
            }
        }

        return response()->json([
            'already_subscribed' => $alreadySubscribed,
            'message' => $alreadySubscribed ? 'You are already subscribed.' : 'Thanks for subscribing!',
        ]);
    }

    public function unsubscribe(string $token)
    {
        $signup = NewsletterSignup::where('unsubscribe_token', $token)->firstOrFail();
        $signup->update(['is_subscribed' => false]);

        return view('emails.newsletter.unsubscribed');
    }
}
