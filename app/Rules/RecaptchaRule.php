<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Google reCAPTCHA v3 token server-side. Silently passes when no secret key is
 * configured yet (RECAPTCHA_SECRET_KEY unset) so forms keep working before real keys are
 * issued — enforcement switches on automatically the moment a secret key is added.
 */
class RecaptchaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');
        if (!$secret) {
            return;
        }

        if (!$value) {
            $fail('Please complete the verification and try again.');
            return;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $value,
            ])->json();
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA verification request failed: ' . $e->getMessage());
            $fail('Verification failed — please try again.');
            return;
        }

        $minScore = (float) config('services.recaptcha.min_score', 0.5);
        if (!($response['success'] ?? false) || ($response['score'] ?? 0) < $minScore) {
            $fail('Verification failed — please try again.');
        }
    }
}
