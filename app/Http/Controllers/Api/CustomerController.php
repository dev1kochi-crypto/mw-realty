<?php

namespace App\Http\Controllers\Api;

use App\Models\Lead;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\Visitors\VisitorEvent;
use App\Support\MapsPropertyCards;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @group User Account
 *
 * The logged-in customer's own dashboard data (Profile.vue / the app's account screens) —
 * wishlist, saved searches, enquiries, settings. Served twice: under /customer/* for the
 * website's session login and under /api/customer/* for the app's Bearer token; the 'sanctum'
 * guard resolves either.
 */
class CustomerController extends Controller
{
    use MapsPropertyCards;

    private const WISHLIST_PER_PAGE = 16;

    /**
     * Session state
     *
     * Is someone signed in, and which property ids are in their wishlist (to draw the heart
     * icons). Works without a token too — guests get `authenticated: false` instead of a 401.
     *
     * Public — every page's shared session state (useWishlist.js) calls this once on load.
     *
     * @response 200 scenario="Signed in" {"authenticated": true, "wishlist_ids": [12, 48], "user": {"name": "Sara Ahmed", "avatar_url": null}}
     * @response 200 scenario="Guest" {"authenticated": false, "wishlist_ids": [], "user": null}
     */
    public function session(Request $request)
    {
        $user = $request->user('sanctum');
        if (!$user) {
            return response()->json(['authenticated' => false, 'wishlist_ids' => [], 'user' => null]);
        }

        return response()->json([
            'authenticated' => true,
            'wishlist_ids' => $user->wishlistProperties()->pluck('properties.id'),
            'user' => [
                'name' => $user->name,
                'avatar_url' => $user->avatar ? media_url($user->avatar) : null,
            ],
        ]);
    }

    /**
     * Dashboard
     *
     * Everything for the account screen in one call: profile, counts, recent activity, the
     * wishlist (property cards), saved searches and sent enquiries. For long wishlists, use the
     * paginated `GET /api/customer/wishlist` instead.
     *
     * @authenticated
     *
     * @queryParam lang string Language code for translated titles. Example: en
     *
     * @response 200 {"profile": {"name": "Sara Ahmed", "email": "buyer@example.com", "phone": "+971 50 123 4567", "location": "Dubai Marina", "avatar_url": null}, "stats": {"wishlist": 2, "saved_searches": 1, "enquiries": 1}, "activity": [{"icon": "heart.svg", "text": "You saved \"Marina Gate 2BR\" to your wishlist.", "time": "2 hours ago"}], "wishlist": [{"id": 12, "slug": "marina-gate-2br", "image": "https://.../photo.jpg", "...": "same card shape as GET /api/properties"}], "saved_searches": [{"id": 3, "title": "2BR in Marina", "meta": "Dubai Marina · 2 Bed · For sale", "criteria": {"location": "Dubai Marina", "bedrooms": "2", "listing_type": "sale"}}], "enquiries": [{"id": 91, "property": "Marina Gate 2BR", "sent_to": "Ahmed Khan", "date": "Oct 08, 2026", "status": "New"}]}
     */
    public function me(Request $request)
    {
        $user = $request->user('sanctum');
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
                'avatar_url' => $user->avatar ? media_url($user->avatar) : null,
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
                // The listing page's own query string — "View results" reopens exactly this search.
                'criteria' => $s->criteria ?: new \stdClass(),
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

    /**
     * Update profile
     *
     * Send as `multipart/form-data` when uploading an avatar. Use `POST` from the app — PHP does
     * not parse file uploads on `PUT` (the `PUT` route is kept for the website).
     *
     * @authenticated
     *
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam phone string Example: +971 50 123 4567
     * @bodyParam location string Example: Dubai Marina
     * @bodyParam password string Leave out to keep the current password. Example: newSecret123
     * @bodyParam password_confirmation string Required with password. Example: newSecret123
     * @bodyParam avatar file Image, max 2 MB.
     *
     * @response 200 {"message": "Settings updated.", "avatar_url": "https://res.cloudinary.com/.../avatar.jpg"}
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user('sanctum');

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
            'avatar_url' => $user->avatar ? media_url($user->avatar) : null,
        ]);
    }

    /**
     * Wishlist (paginated)
     *
     * The saved properties as listing cards, newest first, 16 per page.
     *
     * @authenticated
     *
     * @queryParam page integer Example: 1
     * @queryParam lang string Example: en
     *
     * @response 200 {"properties": [{"id": 12, "slug": "marina-gate-2br", "title": "Marina Gate 2BR", "image": "https://.../photo.jpg", "...": "same card shape as GET /api/properties"}], "pagination": {"current_page": 1, "last_page": 1, "per_page": 16, "total": 2}}
     */
    public function wishlist(Request $request)
    {
        $lang = $request->input('lang', app()->getLocale());
        $paginator = $request->user('sanctum')->wishlistProperties()->where('status', true)
            ->latest('property_wishlists.created_at')
            ->paginate(self::WISHLIST_PER_PAGE);

        return response()->json([
            'properties' => collect($paginator->items())->map(fn ($p) => $this->mapProperty($p, $lang))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Delete account
     *
     * Permanently deletes the account (required by the App Store / Play Store for apps with
     * sign-up). Wishlist and saved searches are removed; enquiries already sent to agents stay
     * with them, unlinked from the account. Password accounts must confirm their password;
     * Google-only accounts send nothing.
     *
     * @authenticated
     *
     * @bodyParam password string The current password (accounts created with email + password). Example: secret123
     *
     * @response 200 {"message": "Your account has been deleted."}
     * @response 422 {"message": "The password is incorrect.", "errors": {"password": ["The password is incorrect."]}}
     */
    public function destroyAccount(Request $request)
    {
        $user = $request->user('sanctum');

        // Google sign-ups get a random password they never knew — nothing to confirm there.
        if (!$user->google_id && !Hash::check((string) $request->input('password'), $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        app(\App\Services\Visitors\VisitorTracker::class)->forget($request);
        app(\App\Services\ManagedFiles::class)->delete($user->avatar);
        $user->tokens()->delete();
        // FKs: wishlist + saved searches cascade; CRM leads and visitor leads are kept (user_id nulled).
        $user->delete();

        if ($request->hasSession()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
        }

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    /**
     * Toggle wishlist
     *
     * Adds the property to the wishlist, or removes it if it's already there.
     *
     * @authenticated
     *
     * @urlParam property integer required The property **id** (not slug). Example: 12
     *
     * @response 200 {"wishlisted": true}
     */
    public function toggleWishlist(Request $request, Property $property)
    {
        $user = $request->user('sanctum');
        $existing = $user->wishlistProperties()->where('properties.id', $property->id)->exists();

        if ($existing) {
            $user->wishlistProperties()->detach($property->id);
        } else {
            $user->wishlistProperties()->attach($property->id);
        }

        app(\App\Services\Visitors\VisitorTracker::class)->record($request, $existing ? VisitorEvent::FAVORITE_REMOVED : VisitorEvent::FAVORITE_ADDED, [
            'property_id' => $property->id,
            'title' => $property->getTranslation('title') ?: $property->reference_no,
            'url' => $request->header('referer'),
        ]);

        return response()->json(['wishlisted' => !$existing]);
    }

    /**
     * Save a search
     *
     * Stores the listing filters so the user can reopen them later. `criteria` takes the same
     * keys as the `GET /api/properties` query string.
     *
     * @authenticated
     *
     * @bodyParam title string required Example: 2BR in Marina
     * @bodyParam criteria object The listing filters. Example: {"location": "Dubai Marina", "bedrooms": "2", "listing_type": "sale", "min_price": 1500000}
     *
     * @response 200 {"id": 3, "title": "2BR in Marina", "meta": "Dubai Marina · 2 Bed · For sale · AED 1,500,000 – any", "criteria": {"location": "Dubai Marina", "bedrooms": "2", "listing_type": "sale", "min_price": 1500000}}
     */
    public function storeSavedSearch(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'criteria' => 'nullable|array',
        ]);

        $savedSearch = $request->user('sanctum')->savedSearches()->create([
            'title' => $request->input('title'),
            'criteria' => $request->input('criteria', []),
        ]);

        app(\App\Services\Visitors\VisitorTracker::class)->record($request, VisitorEvent::SAVED_SEARCH, [
            'title' => $savedSearch->title,
            'url' => $request->header('referer'),
            'meta' => ['summary' => $this->savedSearchMeta($savedSearch->criteria ?? []), 'filters' => $savedSearch->criteria ?: null],
        ]);

        return response()->json([
            'id' => $savedSearch->id,
            'title' => $savedSearch->title,
            'meta' => $this->savedSearchMeta($savedSearch->criteria ?? []),
            'criteria' => $savedSearch->criteria ?: new \stdClass(),
        ]);
    }

    /**
     * Delete a saved search
     *
     * @authenticated
     *
     * @urlParam savedSearch integer required The saved search id. Example: 3
     *
     * @response 200 {"message": "Removed."}
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function destroySavedSearch(Request $request, SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === $request->user('sanctum')->id, 403);
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
        if (!empty($criteria['listing_type'])) $parts[] = $criteria['listing_type'] === 'rent' ? 'For rent' : 'For sale';
        if (!empty($criteria['completion_status'])) $parts[] = ucfirst(str_replace('_', '-', $criteria['completion_status']));
        if (!empty($criteria['category'])) $parts[] = ucfirst(str_replace('_', ' ', $criteria['category']));
        if (!empty($criteria['min_price']) || !empty($criteria['max_price'])) {
            $parts[] = 'AED ' . number_format((float) ($criteria['min_price'] ?? 0)) . ' – ' . (!empty($criteria['max_price']) ? number_format((float) $criteria['max_price']) : 'any');
        }

        return $parts ? implode(' · ', $parts) : 'All properties';
    }
}
