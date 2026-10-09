<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Optional paging over a list a page service already returns (and caches) whole — e.g. every
 * agent. Without ?per_page the response is untouched (the website shows them all); with it, the
 * list is sliced and a `pagination` block (same shape as /api/properties) is added for the app.
 */
trait PaginatesCachedList
{
    protected function paginateList(Request $request, array $data, string $key, int $maxPerPage = 50): array
    {
        if (!$request->filled('per_page')) {
            return $data;
        }

        $items = collect($data[$key] ?? []);
        $perPage = min($maxPerPage, max(1, (int) $request->input('per_page')));
        $lastPage = max(1, (int) ceil($items->count() / $perPage));
        // Not clamped to the last page — past the end is an empty page, so infinite scroll stops cleanly.
        $page = max(1, (int) $request->input('page', 1));

        $data[$key] = $items->forPage($page, $perPage)->values()->all();
        $data['pagination'] = [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $items->count(),
        ];

        return $data;
    }
}
