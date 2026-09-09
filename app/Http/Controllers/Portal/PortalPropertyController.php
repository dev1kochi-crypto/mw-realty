<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyImage;
use App\Models\Filter;
use App\Models\CmsKit\Language;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared Properties dashboard: Super Admin (cms guard) sees every listing;
 * an Agent/Company (portal guard) sees and can only touch their own. Which
 * behaviour applies is decided entirely by ownerId() — null means "no scope".
 */
class PortalPropertyController extends Controller
{
    protected function isAdmin(): bool
    {
        return (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    /**
     * The portal_user_id to scope queries to, or null for a Super Admin (global view).
     */
    protected function ownerId(): ?int
    {
        return $this->isAdmin() ? null : Auth::guard('portal')->user()->id;
    }

    protected function selectFilterOptions(): \Illuminate\Support\Collection
    {
        return Filter::whereIn('key', ['listing_type', 'completion_status', 'property_type', 'location'])
            ->where('type', 'select')
            ->with(['activeValues'])
            ->get()
            ->keyBy('key');
    }

    protected function storeNamedImage(UploadedFile $file, int $propertyId, string $suffix): string
    {
        $filename = 'property-' . $propertyId . '-' . $suffix . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('properties', $filename, 'public');
    }

    public function index()
    {
        $properties = Property::with('owner')
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->latest()
            ->paginate(15);

        $planUsage = null;
        if (!$this->isAdmin()) {
            $owner = Auth::guard('portal')->user();
            $planUsage = [
                'plan' => $owner->plan,
                'used' => $owner->properties()->count(),
                'remaining' => $owner->remainingPropertySlots(),
            ];
        }

        return view('portal.properties.index', ['properties' => $properties, 'isAdmin' => $this->isAdmin(), 'planUsage' => $planUsage]);
    }

    public function create()
    {
        $languages = Language::where('status', true)->get();
        $filterOptions = $this->selectFilterOptions();
        $remainingSlots = $this->isAdmin() ? null : Auth::guard('portal')->user()->remainingPropertySlots();

        if ($remainingSlots === 0) {
            return redirect()->route('portal.properties.index')
                ->with('error', "You've reached your plan's property limit. Upgrade your plan to add more listings.");
        }

        return view('portal.properties.create', ['languages' => $languages, 'filterOptions' => $filterOptions, 'isAdmin' => $this->isAdmin(), 'remainingSlots' => $remainingSlots]);
    }

    protected function rules(): array
    {
        return [
            'translations.*.title' => 'required',
            'slug' => 'nullable|alpha_dash',
            'listing_type' => 'nullable|string',
            'completion_status' => 'nullable|string',
            'property_type' => 'nullable|string',
            'location' => 'nullable|string',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'sqft' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:4096',
            'images.*' => 'nullable|image|max:4096',
        ];
    }

    public function store(Request $request)
    {
        if (!$this->isAdmin()) {
            $owner = Auth::guard('portal')->user();
            if ($owner->remainingPropertySlots() === 0) {
                return redirect()->route('portal.properties.index')
                    ->with('error', "You've reached your plan's property limit. Upgrade your plan to add more listings.");
            }
        }

        $request->validate($this->rules());

        $data = $request->only([
            'reference_no', 'listing_type', 'completion_status', 'property_type',
            'location', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
        ]);
        // null when Super Admin creates it directly (a house/MW Realty listing with no portal owner)
        $data['portal_user_id'] = $this->ownerId();
        $data['translations'] = $request->input('translations', []);
        $data['status'] = $request->has('status');
        $data['featured'] = $this->isAdmin() && $request->has('featured');

        $title = $request->input('translations.' . config('app.fallback_locale', 'en') . '.title')
            ?? collect($request->input('translations', []))->first()['title'] ?? null;
        $data['slug'] = $request->input('slug') ?: Str::slug($title . '-' . Str::random(5));

        $property = Property::create($data);

        if ($request->hasFile('image')) {
            $property->image = $this->storeNamedImage($request->file('image'), $property->id, 'cover');
            $property->image_alt = $request->input('image_alt');
            $property->save();
        }

        if ($request->hasFile('images')) {
            $order = 1;
            foreach ($request->file('images') as $file) {
                PropertyImage::create([
                    'property_id' => $property->id,
                    'image' => $this->storeNamedImage($file, $property->id, (string) $order),
                    'order_index' => $order,
                ]);
                $order++;
            }
        }

        PropertyDetail::create([
            'property_id' => $property->id,
            'amenities' => array_values(array_filter((array) $request->input('amenities', []))),
            'year_built' => $request->input('year_built'),
            'floor' => $request->input('floor'),
            'parking' => $request->input('parking'),
            'furnished' => $request->has('furnished'),
            'view' => $request->input('view'),
        ]);

        return redirect()->route('portal.properties.index')->with('success', 'Property created successfully.');
    }

    protected function findOwned($id): Property
    {
        return Property::with(['details', 'images'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->findOrFail($id);
    }

    public function edit($id)
    {
        $property = $this->findOwned($id);
        $languages = Language::where('status', true)->get();
        $filterOptions = $this->selectFilterOptions();
        return view('portal.properties.edit', ['property' => $property, 'languages' => $languages, 'filterOptions' => $filterOptions, 'isAdmin' => $this->isAdmin()]);
    }

    public function update(Request $request, $id)
    {
        $property = $this->findOwned($id);
        $request->validate(array_merge($this->rules(), [
            'slug' => ['nullable', 'alpha_dash', Rule::unique('properties', 'slug')->ignore($property->id)],
        ]));

        $data = $request->only([
            'reference_no', 'listing_type', 'completion_status', 'property_type',
            'location', 'bedrooms', 'bathrooms', 'sqft', 'price', 'currency',
        ]);
        $data['translations'] = $request->input('translations', []);
        $data['status'] = $request->has('status');
        if ($this->isAdmin()) {
            $data['featured'] = $request->has('featured');
        }
        if ($request->filled('slug')) {
            $data['slug'] = $request->input('slug');
        }

        if ($request->hasFile('image')) {
            if ($property->image) {
                Storage::disk('public')->delete($property->image);
            }
            $data['image'] = $this->storeNamedImage($request->file('image'), $property->id, 'cover');
        }
        $data['image_alt'] = $request->input('image_alt');

        $property->update($data);

        if ($request->hasFile('images')) {
            $nextOrder = (int) $property->images()->max('order_index') + 1;
            foreach ($request->file('images') as $file) {
                PropertyImage::create([
                    'property_id' => $property->id,
                    'image' => $this->storeNamedImage($file, $property->id, (string) $nextOrder),
                    'order_index' => $nextOrder,
                ]);
                $nextOrder++;
            }
        }

        PropertyDetail::updateOrCreate(
            ['property_id' => $property->id],
            [
                'amenities' => array_values(array_filter((array) $request->input('amenities', []))),
                'year_built' => $request->input('year_built'),
                'floor' => $request->input('floor'),
                'parking' => $request->input('parking'),
                'furnished' => $request->has('furnished'),
                'view' => $request->input('view'),
            ]
        );

        return redirect()->route('portal.properties.edit', $property->id)->with('success', 'Property updated successfully.');
    }

    public function destroy($id)
    {
        $property = $this->findOwned($id);
        if ($property->image) {
            Storage::disk('public')->delete($property->image);
        }
        foreach ($property->images as $image) {
            Storage::disk('public')->delete($image->image);
        }
        $property->delete();

        return response()->json(['success' => true]);
    }

    public function destroyImage($propertyId, $imageId)
    {
        $property = $this->findOwned($propertyId);
        $image = PropertyImage::where('property_id', $property->id)->findOrFail($imageId);
        Storage::disk('public')->delete($image->image);
        $image->delete();

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $property = $this->findOwned($id);
        $property->status = !$property->status;
        $property->save();

        return response()->json(['success' => true]);
    }
}
