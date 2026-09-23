<?php

namespace App\Http\Controllers\Api;

use App\Models\Lead;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Support\MapsPropertyCards;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** The logged-in customer's own dashboard data (Profile.vue) — wishlist, saved searches, enquiries, settings. */
class CustomerController extends Controller
{
    use MapsPropertyCards;

    /**
     * Public — every page's shared session state (useWishlist.js) calls this once on load to
     * know whether a customer is logged in, which properties they've already saved, and (for
     * SiteHeader.vue's account dropdown) their basic identity. Deliberately not behind
     * customer.auth: guests get a cheap {authenticated: false} instead of a console-cluttering
     * 401 on every single page.
     */
    public function session(Request $request)
    {
        $user = $request->user('web');
        if (!$user) {
            return response()->json(['authenticated' => false, 'wishlist_ids' => [], 'user' => null]);
        }

        return response()->json([
            'authenticated' => true,
            'wishlist_ids' => $user->wishlistProperties()->pluck('properties.id'),
            'user' => [
                'name' => $user->name,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user('web');
        $lang = $request->input('lang', app()->getLocale());

        $wishlist = $user->wishlistProperties()->where('status', true)->latest('property_wishlists.created_at')->get();
        $savedSearches = $user->savedSearches()->latest()->get();
        $leads = $user->leads()->with(['property', 'owner', 'stage'])->latest()->get();

        $activity = collect()
            ->concat($wishlist->map(fn ($p) => [
                'icon' => 'heart.svg',
                'text' => "You saved \"{$p->getTranslation('title', $lang)}\" to your wishlist.",
                'at' => $p->pivot->created_at,
            ]))
            ->concat($savedSearches->map(fn ($s) => [
                'icon' => 'search.svg',
                'text' => "You saved a new search: \"{$s->title}\".",
                'at' => $s->created_at,
            ]))
            ->concat($leads->map(fn ($l) => [
                'icon' => 'email.svg',
                'text' => 'You sent an enquiry for ' . ($l->property?->getTranslation('title', $lang) ?? 'a property') . '.',
                'at' => $l->created_at,
            ]))
            ->sortByDesc('at')
            ->take(6)
            ->map(fn ($item) => [
                'icon' => $item['icon'],
                'text' => $item['text'],
                'time' => $item['at']->diffForHumans(),
            ])
            ->values();

        return response()->json([
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'location' => $user->location,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
            'stats' => [
                'wishlist' => $wishlist->count(),
                'saved_searches' => $savedSearches->count(),
                'enquiries' => $leads->count(),
            ],
            'activity' => $activity,
            'wishlist' => $wishlist->map(fn ($p) => $this->mapProperty($p, $lang))->values(),
            'saved_searches' => $savedSearches->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'meta' => $this->savedSearchMeta($s->criteria ?? []),
            ])->values(),
            'enquiries' => $leads->map(fn ($l) => [
                'id' => $l->id,
                'property' => $l->property?->getTranslation('title', $lang) ?? 'Property no longer listed',
                'sent_to' => $l->owner?->displayName() ?? '—',
                'date' => $l->created_at->format('M d, Y'),
                'status' => $l->stage?->name ?? ucfirst($l->status),
            ])->values(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user('web');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user->fill($request->only(['name', 'email', 'phone', 'location']));
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        if ($request->hasFile('avatar')) {
            $managedFiles = app(\App\Services\ManagedFiles::class);
            $managedFiles->delete($user->avatar);
            $user->avatar = $managedFiles->store($request->file('avatar'), 'customers/avatars');
        }

        $user->save();

        return response()->json([
            'message' => 'Settings updated.',
            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
        ]);
    }

    public function toggleWishlist(Request $request, Property $property)
    {
        $user = $request->user('web');
        $existing = $user->wishlistProperties()->where('properties.id', $property->id)->exists();

        if ($existing) {
            $user->wishlistProperties()->detach($property->id);
        } else {
            $user->wishlistProperties()->attach($property->id);
        }

        return response()->json(['wishlisted' => !$existing]);
    }

    public function storeSavedSearch(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'criteria' => 'nullable|array',
        ]);

        $savedSearch = $request->user('web')->savedSearches()->create([
            'title' => $request->input('title'),
            'criteria' => $request->input('criteria', []),
        ]);

        return response()->json([
            'id' => $savedSearch->id,
            'title' => $savedSearch->title,
            'meta' => $this->savedSearchMeta($savedSearch->criteria ?? []),
        ]);
    }

    public function destroySavedSearch(Request $request, SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === $request->user('web')->id, 403);
        $savedSearch->delete();

        return response()->json(['message' => 'Removed.']);
    }

    /** Builds the human-readable "AED 1.5M – 2.5M · Ready · Furnished"-style summary line from stored filter criteria. */
    private function savedSearchMeta(array $criteria): string
    {
        $parts = [];
        if (!empty($criteria['location'])) $parts[] = $criteria['location'];
        if (!empty($criteria['property_type'])) $parts[] = ucfirst($criteria['property_type']);
        if (!empty($criteria['bedrooms'])) $parts[] = $criteria['bedrooms'] . ' Bed';
        if (!empty($criteria['category'])) $parts[] = ucfirst(str_replace('_', ' ', $criteria['category']));

        return $parts ? implode(' · ', $parts) : 'All properties';
    }
}
