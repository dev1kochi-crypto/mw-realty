<?php

namespace App\Http\Controllers\CmsKit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RobotsTxtController extends Controller
{
    public function index()
    {
        $exists = file_exists($this->path());

        return view('cms-kit::robots-txt.index', compact('exists'));
    }

    public function edit()
    {
        $content = file_exists($this->path()) ? file_get_contents($this->path()) : $this->defaultContent();

        return view('cms-kit::robots-txt.edit', compact('content'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        file_put_contents($this->path(), $request->input('content'));

        return redirect()->route('cms.robots-txt.index')->with('success', 'robots.txt updated successfully.');
    }

    protected function path(): string
    {
        return public_path('robots.txt');
    }

    protected function defaultContent(): string
    {
        return "User-agent: *\nAllow: /\n\nSitemap: " . url('sitemap.xml') . "\n";
    }
}
