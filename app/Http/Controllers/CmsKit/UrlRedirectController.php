<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\CmsKit\UrlRedirect;
use CMS\SiteManager\Services\UrlRedirectService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class UrlRedirectController extends Controller
{
    public function __construct(
        protected UrlRedirectService $redirectService
    ) {}

    public function index(Request $request)
    {
        $query = UrlRedirect::query()->with('creator')->orderByDesc('updated_at');

        if ($request->filled('q')) {
            $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->input('q')) . '%';
            $query->where(function ($w) use ($q) {
                $w->where('old_path', 'like', $q)->orWhere('new_url', 'like', $q)->orWhere('notes', 'like', $q);
            });
        }

        $redirects = $query->paginate(25)->withQueryString();

        return view('cms-kit::url-redirects.index', compact('redirects'));
    }

    public function create()
    {
        return view('cms-kit::url-redirects.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $this->redirectService->upsertRedirect(
            $data['old_path'],
            $data['new_url'] ?? null,
            (int) $data['status_code'],
            auth('cms')->id(),
            'manual',
            $data['notes'] ?? null,
            (bool) $request->boolean('is_active'),
        );

        return redirect()->route('cms.url-redirects.index')->with('success', 'Redirect saved.');
    }

    public function edit(UrlRedirect $url_redirect)
    {
        return view('cms-kit::url-redirects.edit', ['redirect' => $url_redirect]);
    }

    public function update(Request $request, UrlRedirect $url_redirect)
    {
        $data = $this->validated($request, $url_redirect->id);

        $url_redirect->update([
            'old_path' => UrlRedirectService::normalizePath($data['old_path']),
            'new_url' => $this->normalizeNewUrlForStorage((int) $data['status_code'], $data['new_url'] ?? null),
            'status_code' => (int) $data['status_code'],
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('cms.url-redirects.index')->with('success', 'Redirect updated.');
    }

    public function destroy(UrlRedirect $url_redirect)
    {
        $url_redirect->delete();

        return redirect()->route('cms.url-redirects.index')->with('success', 'Redirect deleted.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'old_path' => ['required', 'string', 'max:2048', Rule::unique('url_redirects', 'old_path')->ignore($ignoreId)],
            'new_url' => [
                Rule::requiredIf(fn () => (int) $request->input('status_code') !== 410),
                'nullable',
                'string',
                'max:2048',
            ],
            'status_code' => ['required', Rule::in([301, 302, 303, 307, 308, 410])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'old_path' => 'old URL path',
            'new_url' => 'destination',
        ]);
    }

    protected function normalizeNewUrlForStorage(int $statusCode, ?string $newUrl): ?string
    {
        if ($statusCode === 410) {
            return null;
        }

        $newUrl = trim((string) $newUrl);
        if ($newUrl === '') {
            return null;
        }

        return preg_match('#^https?://#i', $newUrl)
            ? $newUrl
            : UrlRedirectService::normalizePath($newUrl);
    }
}
