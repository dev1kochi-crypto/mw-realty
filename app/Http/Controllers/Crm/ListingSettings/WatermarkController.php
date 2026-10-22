<?php

namespace App\Http\Controllers\Crm\ListingSettings;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\PortalUser;
use App\Services\Watermark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * @group CRM Listing Settings
 *
 * Listings Settings → Watermark.
 *  - Agency / independent agent: the logo / text stamped onto every listing photo they upload from
 *    now on. An agency's agents use the agency's watermark (read-only for them). Without one of
 *    their own, Super Admin's default watermark is used.
 *  - Super Admin: the default watermark (its own listings + every account without one), and the
 *    "Agency & agent watermarks" list — who has set their own, searched and paged on the server.
 * Watermark images are on a private disk: GET /listing-settings/watermark/image.
 */
class WatermarkController extends Controller
{
    use ScopesPortalOwner;

    public function __construct(private Watermark $watermark)
    {
    }

    /**
     * The watermark settings
     *
     * Super Admin: the default watermark (+ how many accounts set their own). Anyone else: theirs —
     * an agency agent gets the agency's, `read_only`. `using_default` = theirs is off, so MW Realty's is stamped.
     */
    public function show()
    {
        if ($this->isAdmin()) {
            return response()->json([
                'is_admin' => true,
                'read_only' => false,
                'using_default' => false,
                'brand' => 'MW Realty',
                'settings' => $this->settingsJson($this->watermark->settings(null)),
                'own_count' => PortalUser::where('watermark->enabled', true)->count(),
            ]);
        }

        $portalUser = $this->portalUser();
        $owner = $this->watermark->ownerFor($portalUser);
        $settings = $this->watermark->settings($owner);

        return response()->json([
            'is_admin' => false,
            'read_only' => $owner->id !== $portalUser->id,
            // Nothing of their own switched on → MW Realty's default is stamped on their photos.
            'using_default' => !$this->watermark->ready($settings) && (bool) $this->watermark->ready($this->watermark->settings(null)),
            'brand' => $owner->company_name ?: $owner->name,
            'settings' => $this->settingsJson($settings),
        ]);
    }

    /**
     * Agency & agent watermarks
     *
     * Super Admin only: accounts that set up their own, 20 per page, most recently changed first.
     *
     * @queryParam q string Agency, agent or email. Example: marina
     * @queryParam status string on | off. Example: on
     * @queryParam page integer Example: 1
     */
    public function accounts(Request $request)
    {
        abort_unless($this->isAdmin(), 403);
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = in_array($request->query('status'), ['on', 'off'], true) ? $request->query('status') : null;

        $accounts = PortalUser::query()
            ->whereNotNull('watermark')
            ->when($status === 'on', fn (Builder $q) => $q->where('watermark->enabled', true))
            ->when($status === 'off', fn (Builder $q) => $q->where(fn ($w) => $w->where('watermark->enabled', false)->orWhereNull('watermark->enabled')))
            ->when($search !== '', fn (Builder $q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(20, ['id', 'name', 'company_name', 'email', 'type', 'company_id', 'watermark', 'updated_at']);

        return response()->json([
            'data' => collect($accounts->items())->map(fn (PortalUser $account) => [
                'id' => $account->id,
                'name' => $account->displayName(),
                'kind' => $account->type === 'company' ? 'Agency' : ($account->company_id ? 'Agency agent' : 'Independent agent'),
                'email' => $account->email,
                'settings' => $this->settingsJson(array_merge(Watermark::DEFAULTS, $account->watermark ?? [])),
                'updated_at' => $account->updated_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $accounts->currentPage(), 'last_page' => $accounts->lastPage(), 'total' => $accounts->total(),
                'from' => $accounts->firstItem(), 'to' => $accounts->lastItem(),
            ],
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Save the watermark
     *
     * Multipart (for the logo). Super Admin saves the default one; an agency's agent can't (403).
     *
     * @bodyParam enabled boolean Example: true
     * @bodyParam type string required image | text. Example: text
     * @bodyParam image file PNG / JPG / WebP, max 4 MB.
     * @bodyParam remove_image boolean Example: false
     * @bodyParam text string Max 60. Example: MW Realty
     * @bodyParam color string #rrggbb. Example: #ffffff
     * @bodyParam opacity integer required 5–100. Example: 60
     * @bodyParam size integer required 5–80 (% of the photo width). Example: 25
     * @bodyParam position string required tl tc tr ml mc mr bl bc br. Example: br
     */
    public function update(Request $request)
    {
        $subject = $this->isAdmin() ? null : $this->portalUser();
        abort_if($subject?->isAgencyAgent(), 403, 'Your agency manages the watermark for your listings.');

        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'type' => ['required', Rule::in(['image', 'text'])],
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'remove_image' => 'nullable|boolean',
            'text' => 'nullable|string|max:60',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'opacity' => 'required|integer|min:5|max:100',
            'size' => 'required|integer|min:5|max:80',
            'position' => ['required', Rule::in(Watermark::POSITIONS)],
        ]);

        $settings = $this->watermark->settings($subject);
        $enabled = $request->boolean('enabled');
        $text = trim((string) ($data['text'] ?? ''));
        $keepsImage = $request->hasFile('image') || (!$request->boolean('remove_image') && $settings['image']);

        // Checked before any file changes, so a refused save leaves the stored logo alone.
        if ($enabled && $data['type'] === 'image' && !$keepsImage) {
            return $this->invalid('image', 'Upload a watermark image (e.g. your logo), or switch to a text watermark.');
        }
        if ($enabled && $data['type'] === 'text' && $text === '') {
            return $this->invalid('text', 'Enter the watermark text.');
        }

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            $this->watermark->deleteImage($settings['image']);
            $settings['image'] = null;
        }
        if ($request->hasFile('image')) {
            $settings['image'] = $this->watermark->storeImage($request->file('image'), $subject);
        }

        $settings = array_merge($settings, [
            'enabled' => $enabled,
            'type' => $data['type'],
            'text' => $text,
            'color' => strtolower($data['color'] ?? '#ffffff'),
            'opacity' => (int) $data['opacity'],
            'size' => (int) $data['size'],
            'position' => $data['position'],
        ]);

        $this->watermark->save($subject, $settings);

        $message = match (true) {
            !$subject && $settings['enabled'] => 'Default watermark saved. It goes on your listings and on every agency / agent without their own, for photos uploaded from now on.',
            !$subject => 'Default watermark saved. It is currently turned off.',
            $settings['enabled'] => 'Watermark saved. It will be added to every listing photo you upload from now on.',
            default => 'Watermark settings saved. Yours is off, so the MW Realty default watermark (if any) is used.',
        };

        return response()->json(['success' => true, 'message' => $message, 'settings' => $this->settingsJson($settings)]);
    }

    /**
     * Watermark image
     *
     * The viewer's own (an agency agent's = the agency's); `source=default` → Super Admin's default;
     * Super Admin may pass `account`.
     *
     * @queryParam source string default. Example: default
     * @queryParam account integer Super Admin only. Example: 12
     */
    public function image(Request $request)
    {
        $subject = match (true) {
            $request->query('source') === 'default' => null,
            $this->isAdmin() => $request->filled('account') ? PortalUser::findOrFail((int) $request->query('account')) : null,
            default => $this->watermark->ownerFor($this->portalUser()),
        };
        $path = $this->watermark->settings($subject)['image'];
        abort_unless($path, 404);

        return response()->file($this->watermark->imagePath($path), ['Cache-Control' => 'private, max-age=300']);
    }

    /** Settings without the private storage path — `image_version` changes whenever the logo does. */
    private function settingsJson(array $settings): array
    {
        return [
            'enabled' => (bool) $settings['enabled'],
            'type' => $settings['type'],
            'text' => (string) $settings['text'],
            'color' => $settings['color'],
            'opacity' => (int) $settings['opacity'],
            'size' => (int) $settings['size'],
            'position' => $settings['position'],
            'has_image' => (bool) $settings['image'],
            'image_version' => $settings['image'] ? md5($settings['image']) : null,
        ];
    }

    private function invalid(string $field, string $message)
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }

    private function portalUser(): PortalUser
    {
        $user = $this->owner();
        abort_unless($user, 403);

        return $user;
    }
}
