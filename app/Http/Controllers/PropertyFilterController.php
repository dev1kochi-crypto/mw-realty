<?php

namespace App\Http\Controllers;

use App\Models\Filter;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public read-only endpoint the frontend (home banner + /properties listing)
 * uses to render the search filter bar. Options come straight from the
 * admin-managed filters/filter_values tables, so a new filter or option
 * shows up here without a code change.
 */
class PropertyFilterController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 'home'); // "home" or "listing"
        $lang = $request->input('lang', app()->getLocale());

        $filters = Filter::active()
            ->shownOn($page)
            ->orderBy('order_index')
            ->with(['activeValues'])
            ->get()
            ->map(function (Filter $filter) use ($lang) {
                $payload = [
                    'key' => $filter->key,
                    'label' => $filter->getTranslation('label', $lang),
                    'type' => $filter->type,
                ];

                if ($filter->type === 'select') {
                    $payload['options'] = $filter->activeValues->map(fn ($v) => [
                        'value' => $v->value,
                        'label' => $v->getTranslation('label', $lang),
                    ])->values();
                } else {
                    $payload['min'] = (float) (Property::active()->min($filter->key) ?? 0);
                    $payload['max'] = (float) (Property::active()->max($filter->key) ?? 0);
                }

                return $payload;
            });

        return response()->json(['filters' => $filters]);
    }
}
