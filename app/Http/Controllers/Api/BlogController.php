<?php

namespace App\Http\Controllers\Api;

use App\Services\BlogPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only endpoints for the /blogs listing page and /blog-details/{slug} page.
 *
 * @group Content
 */
class BlogController extends Controller
{
    public function __construct(private readonly BlogPageService $blogPage)
    {
    }

    /**
     * List blog posts
     *
     * 12 per page.
     *
     * @queryParam page integer Example: 1
     * @queryParam category string A category from the response's category list. No-example
     * @queryParam lang string Example: en
     */
    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $page = max(1, (int) $request->input('page', 1));
        $category = $request->input('category') ?: null;

        return response()->json($this->blogPage->getListingData($lang, $page, 12, $category));
    }

    /**
     * Blog post
     *
     * @urlParam slug string required Example: 5-reasons-to-invest-in-dubai-off-plan-properties
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->blogPage->getPostData($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }

    /**
     * Landing page
     *
     * A marketing landing page (website URL `/{slug}`), in the same shape as a blog post.
     *
     * @urlParam slug string required No-example
     * @queryParam lang string Example: en
     *
     * @response 404 {"message": "Not found"}
     */
    public function landingPage(Request $request, string $slug)
    {
        $data = $this->blogPage->getLandingPageData($request->input('lang', app()->getLocale()), $slug);

        return $data ? response()->json($data) : response()->json(['message' => 'Not found'], 404);
    }
}
