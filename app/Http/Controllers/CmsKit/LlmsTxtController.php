<?php

namespace App\Http\Controllers\CmsKit;

use CMS\SiteManager\Services\LlmsTxtService;
use CMS\SiteManager\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LlmsTxtController extends Controller
{
    protected $llmsTxtService;
    protected $sitemapService;

    public function __construct(LlmsTxtService $llmsTxtService, SitemapService $sitemapService)
    {
        $this->llmsTxtService = $llmsTxtService;
        $this->sitemapService = $sitemapService;
    }

    public function index()
    {
        $exists = $this->llmsTxtService->exists();

        return view('cms-kit::llms-txt.index', compact('exists'));
    }

    public function generate()
    {
        $this->sitemapService->generate();
        $this->llmsTxtService->generate();

        return redirect()->back()->with('success', 'llms.txt generated successfully.');
    }

    public function edit()
    {
        $path = $this->llmsTxtService->path();
        $content = file_exists($path) ? file_get_contents($path) : '';

        return view('cms-kit::llms-txt.edit', compact('content'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        file_put_contents($this->llmsTxtService->path(), $request->input('content'));

        return redirect()->route('cms.llms-txt.index')->with('success', 'llms.txt updated successfully.');
    }
}
