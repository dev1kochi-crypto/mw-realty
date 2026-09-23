<?php

namespace App\Http\Controllers\Api;

use App\Mail\NewsletterSignupReceived;
use App\Models\CmsKit\NewsletterSignup;
use App\Models\CmsKit\SiteInformation;
use App\Rules\RecaptchaRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

/** Public, unauthenticated — the footer newsletter form (POST /api/newsletter/subscribe). */
class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $email = $request->input('email');
        $alreadySubscribed = NewsletterSignup::where('email', $email)->exists();
        $signup = NewsletterSignup::firstOrCreate(['email' => $email]);

        if (!$alreadySubscribed) {
            $adminEmail = SiteInformation::notificationEmail();
            if ($adminEmail) {
                Mail::to($adminEmail)->queue((new NewsletterSignupReceived($signup))->afterCommit());
            }
        }

        return response()->json(['message' => 'Thanks for subscribing!']);
    }
}
