<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes rich-text (TinyMCE) HTML before it's echoed unescaped, so formatting renders
 * instead of showing raw tags, without letting stored markup inject scripts/styles.
 */
class SafeHtml
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (!self::$purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,h2,h3,h4,h5,h6,ul,ol,li,blockquote,a[href|title|target],span,table,thead,tbody,tr,th,td,hr');
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('AutoFormat.RemoveEmpty', true);
            self::$purifier = new HTMLPurifier($config);
        }

        // Descriptions pasted from other sites may arrive entity-encoded ("&lt;p&gt;"), which is
        // why raw tags were showing — decode once so they become real markup, then sanitize.
        if (!str_contains($html, '<') && str_contains($html, '&lt;')) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return self::$purifier->purify($html);
    }
}
