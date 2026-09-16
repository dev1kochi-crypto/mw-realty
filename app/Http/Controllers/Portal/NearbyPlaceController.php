<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use App\Models\NearbyPlace;
use App\Models\Filter;
use App\Models\CmsKit\Language;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Global master list of nearby landmarks (schools, hospitals, restaurants, attractions, ...) that
 * properties can be tagged with — see Property::nearbyPlaces() / the property form's "Nearby
 * Places" tab. Lives under the portal/CRM area (not the admin CMS backend) since it's part of the
 * property/CRM workflow; only a Super Admin browsing the portal can manage it, matching the
 * sidebar link's existing $cmsActor gate.
 */
class NearbyPlaceController extends Controller
{
    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function typeFilter()
    {
        return Filter::where('key', NearbyPlace::FILTER_KEY)->with('activeValues')->first();
    }

    public function index(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $places = NearbyPlace::orderBy('category')->paginate(20);

        return view('portal.nearby-places.index', compact('places'));
    }

    public function create()
    {
        abort_unless($this->isAdmin(), 403);

        $languages = Language::where('status', true)->get();
        $typeFilter = $this->typeFilter();
        return view('portal.nearby-places.create', compact('languages', 'typeFilter'));
    }

    protected function rules(): array
    {
        $languages = Language::where('status', true)->pluck('code');
        $rules = [
            'category' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
        foreach ($languages as $code) {
            $rules["translations.{$code}.name"] = 'required|string|max:255';
            $rules["translations.{$code}.address"] = 'nullable|string|max:1000';
        }
        return $rules;
    }

    public function store(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $request->validate($this->rules());

        NearbyPlace::create([
            'category' => $request->input('category'),
            'translations' => $request->input('translations', []),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->route('portal.nearby-places.index')->with('success', 'Nearby place added.');
    }

    public function edit($id)
    {
        abort_unless($this->isAdmin(), 403);

        $place = NearbyPlace::findOrFail($id);
        $languages = Language::where('status', true)->get();
        $typeFilter = $this->typeFilter();
        return view('portal.nearby-places.edit', compact('place', 'languages', 'typeFilter'));
    }

    public function update(Request $request, $id)
    {
        abort_unless($this->isAdmin(), 403);

        $place = NearbyPlace::findOrFail($id);
        $request->validate($this->rules());

        $place->update([
            'category' => $request->input('category'),
            'translations' => $request->input('translations', []),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'status' => $request->boolean('status'),
        ]);

        return redirect()->route('portal.nearby-places.index')->with('success', 'Nearby place updated.');
    }

    public function toggleStatus($id)
    {
        abort_unless($this->isAdmin(), 403);

        $place = NearbyPlace::findOrFail($id);
        $place->status = !$place->status;
        $place->save();

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        abort_unless($this->isAdmin(), 403);

        NearbyPlace::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /** Feeds the property form's Type -> Place cascading picker. Reachable by any portal user (agent/company/admin). */
    public function byType(Request $request)
    {
        $places = NearbyPlace::active()
            ->when($request->input('type'), fn ($q, $type) => $q->where('category', $type))
            ->get(['id', 'translations'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->getTranslation('name')]);

        return response()->json(['places' => $places]);
    }
}
