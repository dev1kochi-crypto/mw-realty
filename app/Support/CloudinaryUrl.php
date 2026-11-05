<?php

namespace App\Support;

/**
 * Server-side twin of resources/js/utils/cloudinary.js: asks Cloudinary for a resized,
 * auto-format, auto-quality copy instead of the full-size original. Other URLs pass through.
 */
class CloudinaryUrl
{
    private const UPLOAD = '#^(https://res\.cloudinary\.com/[^/]+/(image|video)/upload/)(?:(?:[a-z]{1,3}_[^/,]+,?)+/)?(?=(?:v\d+/)?[^/]+/)#';

    public static function sized(string $url, int $width): string
    {
        if (!str_contains($url, 'res.cloudinary.com/') || !preg_match(self::UPLOAD, $url, $match)) {
            return $url;
        }

        $rest = substr($url, strlen($match[0]));
        if (preg_match('/\.(pdf|svg)(\?|$)/i', $rest)) {
            return $url;
        }

        $transform = $match[2] === 'video'
            ? "q_auto,vc_auto,c_limit,w_{$width}"
            : "f_auto,q_auto,c_limit,w_{$width}";

        return $match[1] . $transform . '/' . $rest;
    }
}
