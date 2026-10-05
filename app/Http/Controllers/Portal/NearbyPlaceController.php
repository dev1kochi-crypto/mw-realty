<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use App\Models\NearbyPlace;
use App\Models\Filter;
use App\Models\CmsKit\Language;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Nearby landmarks (schools, hospitals, restaurants, attractions, ...) that properties can be
 * tagged with — see Property::nearbyPlaces() / the property form's "Nearby Places" tab.
 *
 * Super Admin (portal_user_id NULL, "Shared") and Agents/Companies can all add places, and every
 * place is visible to and usable by everyone. Editing/toggling/deleting is limited to whoever added
 * it (Super Admin can manage any), and a place still tagged on a property can't be deleted.
 * ownerId() null means Super Admin, same as the properties side.
 */
class NearbyPlaceController extends Controller
{
    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function ownerId(): ?int
    {
        return Auth::guard('portal')->check() ? Auth::guard('portal')->user()->id : null;
    }

    protected function ensureAllowed(): void
    {
        abort_unless($this->isAdmin() || Auth::guard('portal')->check(), 403);
    }

    /** A place the current user may edit/toggle/delete: admin any, a portal user only their own. */
    protected function findManageable($id): NearbyPlace
    {
        $this->ensureAllowed();

        return NearbyPlace::manageableBy($this->ownerId())->findOrFail($id);
    }

    protected function typeFilter()
    {
        return Filter::where('key', NearbyPlace::FILTER_KEY)->with('activeValues')->first();
    }

    public function index(Request $request)
    {
        $this->ensureAllowed();

        $places = NearbyPlace::with('owner')
            ->withCount('properties')
            ->when($request->input('scope') === 'mine' && $this->ownerId(), fn ($q) => $q->where('portal_user_id', $this->ownerId()))
            ->when($request->input('scope') === 'shared', fn ($q) => $q->whereNull('portal_user_id'))
            ->orderByRaw('portal_user_id IS NULL')
            ->orderBy('category')
            ->paginate(20)
            ->withQueryString();

        return view('portal.nearby-places.index', [
            'places' => $places,
            'isAdmin' => $this->isAdmin(),
            'ownerId' => $this->ownerId(),
            'scope' => $request->input('scope', 'all'),
        ]);
    }

    public function create()
    {
        $this->ensureAllowed();

        $languages = Language::where('status', true)->get();
        $typeFilter = $this->typeFilter();
        $isAdmin = $this->isAdmin();
        return view('portal.nearby-places.create', compact('languages', 'typeFilter', 'isAdmin'));
    }

    protected function rules(): array
    {
        $languages = Language::where('status', true)->pluck('code');
        $rules = [
            // Must be one of the types Super Admin maintains under Master › Property Options.
            'category' => ['required', 'string', 'max:100', Rule::exists('filter_values', 'value')->where('filter_id', $this->typeFilter()?->id)],
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
        $this->ensureAllowed();

        $request->validate($this->rules());

        NearbyPlace::create([
            'portal_user_id' => $this->ownerId(),
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
        $place = $this->findManageable($id);
        $languages = Language::where('status', true)->get();
        $typeFilter = $this->typeFilter();
        $isAdmin = $this->isAdmin();
        return view('portal.nearby-places.edit', compact('place', 'languages', 'typeFilter', 'isAdmin'));
    }

    public function update(Request $request, $id)
    {
        $place = $this->findManageable($id);
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
        $place = $this->findManageable($id);
        $place->status = !$place->status;
        $place->save();

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $place = $this->findManageable($id);
        if ($used = $place->properties()->count()) {
            return response()->json(['message' => "Tagged on {$used} propert" . ($used === 1 ? 'y' : 'ies') . ' — remove it from those first, or switch it off instead.'], 422);
        }
        $place->delete();

        return response()->json(['success' => true]);
    }

    /** Feeds the property form's Type -> Place cascading picker: every active place, whoever added it. */
    public function byType(Request $request)
    {
        $places = NearbyPlace::active()
            ->when($request->input('type'), fn ($q, $type) => $q->where('category', $type))
            ->get(['id', 'translations', 'portal_user_id'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->getTranslation('name'), 'own' => !$p->isShared()]);

        return response()->json(['places' => $places]);
    }
}
