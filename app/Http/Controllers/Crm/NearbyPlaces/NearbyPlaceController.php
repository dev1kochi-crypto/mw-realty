<?php

namespace App\Http\Controllers\Crm\NearbyPlaces;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\CmsKit\Language;
use App\Models\Filter;
use App\Models\NearbyPlace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Nearby Places
 *
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
    use ScopesPortalOwner;

    /**
     * List nearby places
     *
     * Shared (Super Admin's) places last, then by type; 20 per page.
     *
     * @queryParam scope string all | mine | shared. Example: mine
     * @queryParam page integer Example: 1
     */
    public function index(Request $request)
    {
        $this->ensureAllowed();
        $scope = in_array($request->input('scope'), ['mine', 'shared'], true) ? $request->input('scope') : 'all';
        $ownerId = $this->ownerId();

        $places = NearbyPlace::with('owner')
            ->withCount('properties')
            ->when($scope === 'mine' && $ownerId, fn ($q) => $q->where('portal_user_id', $ownerId))
            ->when($scope === 'shared', fn ($q) => $q->whereNull('portal_user_id'))
            ->orderByRaw('portal_user_id IS NULL')
            ->orderBy('category')
            ->paginate(20);

        return response()->json([
            'data' => collect($places->items())->map(fn (NearbyPlace $place) => [
                'id' => $place->id,
                'name' => $place->getTranslation('name'),
                'type' => $place->typeLabel(),
                'address' => $place->getTranslation('address'),
                'shared' => $place->isShared(),
                'own' => $ownerId !== null && $place->portal_user_id === $ownerId,
                'owner' => $place->owner?->displayName(),
                'properties_count' => (int) $place->properties_count,
                'status' => (bool) $place->status,
                'can_manage' => $this->isAdmin() || ($ownerId !== null && $place->portal_user_id === $ownerId),
            ]),
            'meta' => [
                'current_page' => $places->currentPage(), 'last_page' => $places->lastPage(), 'total' => $places->total(),
                'from' => $places->firstItem(), 'to' => $places->lastItem(),
            ],
            'scope' => $scope,
            'has_own' => $ownerId !== null,
        ]);
    }

    /**
     * Form options
     *
     * The active languages (a name / address per language) and the place types Super Admin
     * maintains under Master › Property Options.
     */
    public function formOptions()
    {
        $this->ensureAllowed();

        return response()->json([
            'languages' => Language::where('status', true)->get(['code', 'name']),
            'types' => ($this->typeFilter()?->activeValues ?? collect())
                ->map(fn ($option) => ['value' => $option->value, 'label' => $option->getTranslation('label')])->values(),
            'is_admin' => $this->isAdmin(),
            'filter_key' => NearbyPlace::FILTER_KEY,
        ]);
    }

    /** One place, for editing — only one the viewer may manage. */
    public function show($id)
    {
        $place = $this->findManageable($id);

        return response()->json([
            'id' => $place->id,
            'name' => $place->getTranslation('name'),
            'category' => $place->category,
            'translations' => (object) ($place->translations ?? []),
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'status' => (bool) $place->status,
        ]);
    }

    /**
     * Add a place
     *
     * @bodyParam category string required A place type value. Example: school
     * @bodyParam translations object required `{code: {name, address}}` for every active language. Example: {"en": {"name": "Dubai Mall", "address": "Downtown"}}
     * @bodyParam latitude number required Example: 25.1972
     * @bodyParam longitude number required Example: 55.2744
     * @bodyParam status boolean Example: true
     */
    public function store(Request $request)
    {
        $this->ensureAllowed();
        $request->validate($this->rules());

        $place = NearbyPlace::create([
            'portal_user_id' => $this->ownerId(),
            'category' => $request->input('category'),
            'translations' => $request->input('translations', []),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'status' => $request->boolean('status', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Nearby place added.', 'id' => $place->id], 201);
    }

    /** Update a place (same fields as adding one) */
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

        return response()->json(['success' => true, 'message' => 'Nearby place updated.']);
    }

    /** Switch a place on / off */
    public function toggleStatus($id)
    {
        $place = $this->findManageable($id);
        $place->status = !$place->status;
        $place->save();

        return response()->json(['success' => true, 'status' => (bool) $place->status]);
    }

    /** Delete a place — refused (422) while it's tagged on properties. */
    public function destroy($id)
    {
        $place = $this->findManageable($id);
        if ($used = $place->properties()->count()) {
            return response()->json(['message' => "Tagged on {$used} propert" . ($used === 1 ? 'y' : 'ies') . ' — remove it from those first, or switch it off instead.'], 422);
        }
        $place->delete();

        return response()->json(['success' => true]);
    }

    private function ensureAllowed(): void
    {
        abort_unless($this->isAdmin() || $this->owner(), 403);
    }

    /** A place the current user may edit/toggle/delete: admin any, a portal user only their own. */
    private function findManageable($id): NearbyPlace
    {
        $this->ensureAllowed();

        return NearbyPlace::manageableBy($this->ownerId())->findOrFail($id);
    }

    private function typeFilter(): ?Filter
    {
        return Filter::where('key', NearbyPlace::FILTER_KEY)->with('activeValues')->first();
    }

    private function rules(): array
    {
        $rules = [
            // Must be one of the types Super Admin maintains under Master › Property Options.
            'category' => ['required', 'string', 'max:100', Rule::exists('filter_values', 'value')->where('filter_id', $this->typeFilter()?->id)],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
        foreach (Language::where('status', true)->pluck('code') as $code) {
            $rules["translations.{$code}.name"] = 'required|string|max:255';
            $rules["translations.{$code}.address"] = 'nullable|string|max:1000';
        }

        return $rules;
    }
}
