<?php

namespace App\Http\Controllers;

use App\Models\CmsKit\Blog;
use App\Models\Property;
use App\Models\PortalUser;
use App\Support\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the same SPA shell (welcome.blade.php) every frontend page already used via a bare
 * Route::view(...), but with real per-page <title>/meta tags resolved server-side first — the
 * only way a crawler or link-preview bot (which doesn't run the Vue app's JS) ever sees them.
 * Mirrors the same approach LandingPageController/LandingPage::metaTagsHtml() already use for
 * admin-authored pages, generalized via SeoMeta for the rest of the site's pages.
 */
class SpaController extends Controller
{
    /** Static pages backed by a Metadata row (see config/cms/pages.php + config/seo-defaults.php). */
    public function staticPage(Request $request, string $pageKey): Response
    {
        $resolved = SeoMeta::forStaticPage($pageKey, app()->getLocale(), $request->url());

        return $this->renderWithSeo($resolved);
    }

    public function propertyDetails(Request $request, string $slug): Response
    {
        $property = Property::where('slug', $slug)->where('status', true)->first();

        if (!$property) {
            return $this->notFound($request, 'Property');
        }

        $fallback = array_merge($property->seoFallback(), ['canonical_url' => $request->url()]);

        return $this->renderWithSeo(SeoMeta::resolve($property->metadata, $fallback));
    }

    public function blogDetails(Request $request, string $slug): Response
    {
        $blog = Blog::where('slug', $slug)->where('status', true)->first();

        if (!$blog) {
            return $this->notFound($request, 'Blog Post');
        }

        $fallback = array_merge($blog->seoFallback(), ['canonical_url' => $request->url()]);

        return $this->renderWithSeo(SeoMeta::resolve($blog->metadata, $fallback));
    }

    public function agentDetails(Request $request, string $slug): Response
    {
        return $this->portalUserDetails($request, $slug, 'agent');
    }

    public function agencyDetails(Request $request, string $slug): Response
    {
        return $this->portalUserDetails($request, $slug, 'company');
    }

    private function portalUserDetails(Request $request, string $slug, string $type): Response
    {
        $portalUser = PortalUser::where('slug', $slug)->where('type', $type)->first();

        if (!$portalUser) {
            return $this->notFound($request, $type === 'company' ? 'Agency' : 'Agent');
        }

        $fallback = array_merge($portalUser->seoFallback(), ['canonical_url' => $request->url()]);

        return $this->renderWithSeo(SeoMeta::resolve($portalUser->metadata, $fallback));
    }

    private function notFound(Request $request, string $label): Response
    {
        $resolved = SeoMeta::resolve(null, [
            'meta_title' => "{$label} Not Found | MW Realty",
            'meta_description' => "The {$label} you're looking for doesn't exist or is no longer available.",
            'canonical_url' => $request->url(),
        ]);

        return $this->renderWithSeo($resolved, 404);
    }

    private function renderWithSeo(array $resolved, int $status = 200): Response
    {
        return response()
            ->view('welcome', ['seoTags' => SeoMeta::tagsHtml($resolved)])
            ->setStatusCode($status);
    }
}
