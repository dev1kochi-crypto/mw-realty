<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * A phone number WITHOUT its country code (that travels separately as phone_country_code):
 * 7–13 digits, optionally grouped with spaces, dashes, dots or brackets. Country/dial-code data
 * comes from libphonenumber (server) and intl-tel-input (browser pickers) — no hand-kept lists.
 * Digit limits match resources/js/composables/useContactValidation.js — keep them in step.
 */
class PhoneNumber implements ValidationRule
{
    public const MIN_DIGITS = 7;
    public const MAX_DIGITS = 13;

    /** "+971573758388" → ['+971', '573758388'] (libphonenumber); anything else → [null, as given]. */
    public static function split(?string $phone): array
    {
        $phone = trim((string) $phone);
        if (str_starts_with($phone, '+')) {
            try {
                $parsed = PhoneNumberUtil::getInstance()->parse($phone, null);

                return ['+' . $parsed->getCountryCode(), (string) $parsed->getNationalNumber()];
            } catch (NumberParseException) {
                // not a parseable international number — leave it for the rule to reject
            }
        }

        return [null, $phone];
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $value = trim((string) $value);
        if (str_starts_with($value, '+')) {
            $fail('Enter the phone number without the country code — pick the code from the list.');
            return;
        }
        if (!preg_match('/^[\d\s().\-]+$/', $value)) {
            $fail('The phone number may only contain digits.');
            return;
        }
        $digits = strlen(preg_replace('/\D/', '', $value));
        if ($digits < self::MIN_DIGITS || $digits > self::MAX_DIGITS) {
            $fail('The phone number must be ' . self::MIN_DIGITS . ' to ' . self::MAX_DIGITS . ' digits (without the country code).');
        }
    }

    /** Validation rules for the phone field itself. */
    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:30', new self()];
    }

    /** "+971" style dial code sent next to the phone — must be a real calling code (libphonenumber). */
    public static function countryCodeRules(): array
    {
        return ['nullable', 'regex:/^\+\d{1,4}$/', function (string $attribute, mixed $value, \Closure $fail) {
            if (!PhoneNumberUtil::getInstance()->getRegionCodesForCountryCode((int) ltrim((string) $value, '+'))) {
                $fail('Choose a valid country code.');
            }
        }];
    }

    /** Strict email: RFC-valid AND a real domain with a dot (rejects "name@host"). */
    public static function emailRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:255', 'email:rfc,filter'];
    }
}
