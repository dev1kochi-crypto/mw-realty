<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\CmsKit\Language;
use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\Property;
use App\Models\PropertyDetail;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRM › Master › Property Options (Super Admin only): the option lists behind the property form's
 * Property Type, Listing Type, Completion Status and Furnishing dropdowns — the same Filter /
 * FilterValue records the website filters use. Agents / agencies ask for new options via a ticket.
 */
class PropertyOptionController extends Controller
{
    use ScopesPortalOwner;

    public const LISTS = [
        'property_type' => ['Property Type', 'fa-building'],
        'listing_type' => ['Listing Type', 'fa-tags'],
        'completion_status' => ['Completion Status', 'fa-hammer'],
        Filter::FURNISHING_KEY => ['Furnishing', 'fa-couch'],
    ];

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'Only Super Admin can manage property options.');
    }

    private function filterFor(string $key): Filter
    {
        abort_unless(array_key_exists($key, self::LISTS), 404);

        return Filter::firstOrCreate(['key' => $key], [
            'type' => 'select',
            'translations' => ['en' => ['label' => self::LISTS[$key][0]]],
            'show_on' => $key === Filter::FURNISHING_KEY ? [] : null,
            'order_index' => (int) Filter::max('order_index') + 1,
            'status' => true,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $key = array_key_exists($request->query('list'), self::LISTS) ? $request->query('list') : 'property_type';
        $filter = $this->filterFor($key);
        $values = $filter->values()->orderBy('order_index')->orderBy('id')->get();

        return view('portal.crm.master.property-options.index', [
            'lists' => self::LISTS,
            'key' => $key,
            'filter' => $filter,
            'values' => $values,
            'usage' => $this->usageCounts($key, $values->pluck('value')->all()),
            'languages' => Language::active()->orderByDesc('is_default')->orderBy('id')->get(),
            'counts' => Filter::whereIn('key', array_keys(self::LISTS))->withCount('values')->pluck('values_count', 'key'),
        ]);
    }

    public function store(Request $request, string $key)
    {
        $this->authorizeAdmin();
        $filter = $this->filterFor($key);
        $data = $this->validated($request, $filter);

        FilterValue::create([
            'filter_id' => $filter->id,
            'value' => $data['value'],
            'translations' => $data['translations'],
            'order_index' => (int) $filter->values()->max('order_index') + 1,
            'status' => true,
        ]);

        return redirect()->route('portal.crm.master.property-options.index', ['list' => $key])->with('success', 'Option added.');
    }

    public function update(Request $request, string $key, int $id)
    {
        $this->authorizeAdmin();
        $filter = $this->filterFor($key);
        $option = $filter->values()->findOrFail($id);
        // The stored value is what listings hold — it can't change once any listing uses it.
        $data = $this->validated($request, $filter, $option);
        if ($data['value'] !== $option->value && $this->usageCounts($key, [$option->value])[$option->value] ?? 0) {
            throw ValidationException::withMessages(['value' => 'This option is used by listings, so its code can\'t change. You can still rename its labels.']);
        }

        $option->update(['value' => $data['value'], 'translations' => $data['translations']]);

        return redirect()->route('portal.crm.master.property-options.index', ['list' => $key])->with('success', 'Option updated.');
    }

    public function toggle(string $key, int $id)
    {
        $this->authorizeAdmin();
        $option = $this->filterFor($key)->values()->findOrFail($id);
        $option->update(['status' => !$option->status]);

        return response()->json(['success' => true, 'status' => $option->status]);
    }

    public function destroy(string $key, int $id)
    {
        $this->authorizeAdmin();
        $option = $this->filterFor($key)->values()->findOrFail($id);
        if ($used = $this->usageCounts($key, [$option->value])[$option->value] ?? 0) {
            return response()->json(['message' => "Used by {$used} listing(s) — switch it off instead, or change those listings first."], 422);
        }
        $option->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, string $key)
    {
        $this->authorizeAdmin();
        $filter = $this->filterFor($key);
        $ids = $request->validate(['order' => 'required|array', 'order.*' => 'integer'])['order'];
        foreach (array_values($ids) as $i => $id) {
            $filter->values()->whereKey($id)->update(['order_index' => $i + 1]);
        }

        return response()->json(['success' => true]);
    }

    /** value + one label per active language (the default language is required). */
    private function validated(Request $request, Filter $filter, ?FilterValue $option = null): array
    {
        $languages = Language::active()->get();
        $default = $languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code ?? 'en';
        $defaultLabel = trim((string) $request->input("translations.{$default}.label"));
        $request->merge(['value' => Str::slug((string) ($request->input('value') ?: $defaultLabel), '_') ?: null]);

        $request->validate([
            'value' => ['required', 'string', 'max:100', Rule::unique('filter_values', 'value')->where('filter_id', $filter->id)->ignore($option?->id)],
            "translations.{$default}.label" => 'required|string|max:255',
            'translations.*.label' => 'nullable|string|max:255',
        ], [
            "translations.{$default}.label.required" => 'Enter the name (' . strtoupper($default) . ').',
            'value.unique' => 'That option already exists.',
        ]);

        $translations = [];
        foreach ($languages as $lang) {
            $label = trim((string) $request->input("translations.{$lang->code}.label"));
            if ($label !== '') {
                $translations[$lang->code] = ['label' => $label];
            }
        }

        return ['value' => $request->input('value'), 'translations' => $translations];
    }

    /** @return array<string, int> listings using each value */
    private function usageCounts(string $key, array $values): array
    {
        if (!$values) {
            return [];
        }
        $query = $key === Filter::FURNISHING_KEY
            ? PropertyDetail::whereIn('furnished', $values)->selectRaw('furnished as v, count(*) as c')->groupBy('furnished')
            : Property::whereIn($key, $values)->selectRaw("{$key} as v, count(*) as c")->groupBy($key);

        return $query->pluck('c', 'v')->map(fn ($c) => (int) $c)->all();
    }
}
