<?php

namespace App\Http\Controllers;

use CMS\SiteManager\Models\CmsKit\Language;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint the frontend's language dropdown uses — options
 * come straight from the admin-managed Languages screen, so a new language
 * (or a status/flag change there) shows up here without a code change.
 */
class PublicLanguageController extends Controller
{
    public function index()
    {
        return Language::active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'flag_image', 'is_default'])
            ->map(fn (Language $language) => [
                'id' => $language->id,
                'name' => $language->name,
                'code' => $language->code,
                'is_default' => (bool) $language->is_default,
                'flag_url' => $language->flag_image ? media_url($language->flag_image) : null,
            ]);
    }
}
