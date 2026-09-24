<?php

namespace App\Http\Controllers\Api;

use CMS\SiteManager\Services\StaticTranslationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only — the frontend's static UI copy (nav labels, footer labels, button
 * text, etc.), keyed by ?lang= code. Reads the same JSON files the admin's
 * Languages > Translations screen edits (StaticTranslationService), so an admin
 * edit there shows up here immediately, no deploy needed.
 */
class StaticTranslationController extends Controller
{
    public function __construct(protected StaticTranslationService $staticTranslations)
    {
    }

    public function index(Request $request)
    {
        $code = strtolower((string) $request->query('lang', $this->staticTranslations->masterCode()));

        return response()->json($this->staticTranslations->read($code));
    }
}
