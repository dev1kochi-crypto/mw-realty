<?php

namespace App\Http\Controllers\Api;

use App\Services\BlogPageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public, read-only endpoints for the /blogs listing page and /blog-details/{slug} page. */
class BlogController extends Controller
{
    public function __construct(private readonly BlogPageService $blogPage)
    {
    }

    public function index(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $page = max(1, (int) $request->input('page', 1));

        return response()->json($this->blogPage->getListingData($lang, $page));
    }

    public function show(Request $request, string $slug)
    {
        $lang = $request->input('lang', app()->getLocale());
        $data = $this->blogPage->getPostData($lang, $slug);

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($data);
    }
}
