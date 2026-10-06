<?php

namespace App\Services\Integrations;

use RuntimeException;

/**
 * Facebook rejected the Page's access token (expired / revoked / permission removed), so the Page
 * must be reconnected — retrying won't help. Thrown by FacebookLeadAds; handled by FacebookConnectionHealth.
 */
class FacebookTokenException extends RuntimeException
{
    /** Graph API error codes that mean "log in with Facebook again": invalid token, session, permissions. */
    public const CODES = [102, 190, 10, 200];

    /** Plain-language reason shown on the Integrations page and in the email. */
    public function reason(): string
    {
        return match (true) {
            $this->getCode() === 190 => 'Facebook no longer accepts this Page\'s access. This happens when the Facebook password is changed, the person who connected the Page is no longer its admin, the app was removed from Facebook\'s settings, or Facebook logged the account out for security.',
            in_array($this->getCode(), [10, 200], true) => 'A permission the integration needs (such as reading leads) was removed or not granted on Facebook.',
            default => 'The Facebook login session for this Page has ended.',
        };
    }
}
