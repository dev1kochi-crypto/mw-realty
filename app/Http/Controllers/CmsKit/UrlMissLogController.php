<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\UrlMissLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UrlMissLogController extends Controller
{
    public function index(Request $request)
    {
        $query = UrlMissLog::query()->orderByDesc('hit_count')->orderByDesc('last_seen_at');

        if ($request->filled('q')) {
            $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('q')) . '%';
            $query->where('path', 'like', $q);
        }

        $misses = $query->paginate(50)->withQueryString();

        return view('cms-kit::url-miss-logs.index', compact('misses'));
    }

    public function destroy(UrlMissLog $url_miss_log)
    {
        $url_miss_log->delete();

        return redirect()->route('cms.url-miss-logs.index')->with('success', 'Entry removed.');
    }

    public function clear(Request $request)
    {
        $request->validate([
            'confirm_clear' => ['required', 'accepted'],
        ]);

        UrlMissLog::query()->delete();

        return redirect()->route('cms.url-miss-logs.index')->with('success', '404 log cleared.');
    }
}
