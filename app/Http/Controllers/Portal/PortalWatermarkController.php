<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Services\Watermark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Listings Settings → Watermark.
 *  - Agency / independent agent: the logo / text stamped onto every listing photo they upload from
 *    now on. An agency's agents use the agency's watermark (read-only for them). Without one of
 *    their own, Super Admin's default watermark is used.
 *  - Super Admin: the default watermark (its own listings + every account without one), and the
 *    "Agency & agent watermarks" tab — who has set their own, searched and paged on the server.
 */
class PortalWatermarkController extends Controller
{
    public const TABS = ['default' => 'Default watermark', 'accounts' => 'Agency & agent watermarks'];

    public function __construct(private Watermark $watermark)
    {
    }

    public function edit(Request $request)
    {
        if ($this->isAdmin()) {
            $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'default';

            return view('portal.watermark.edit', [
                'isAdmin' => true,
                'tab' => $tab,
                'tabs' => self::TABS,
                'owner' => null,
                'readOnly' => false,
                'settings' => $this->watermark->settings(null),
                'usingDefault' => false,
                'ownCount' => PortalUser::where('watermark->enabled', true)->count(),
            ] + ($tab === 'accounts' ? $this->accounts($request) : []));
        }

        $portalUser = $this->portalUser();
        $owner = $this->watermark->ownerFor($portalUser);
        $settings = $this->watermark->settings($owner);

        return view('portal.watermark.edit', [
            'isAdmin' => false,
            'tab' => 'default',
            'tabs' => ['default' => 'Watermark'],
            'owner' => $owner,
            'readOnly' => $owner->id !== $portalUser->id,
            'settings' => $settings,
            // Nothing of their own switched on → MW Realty's default is stamped on their photos.
            'usingDefault' => !$this->watermark->ready($settings) && $this->watermark->ready($this->watermark->settings(null)),
        ]);
    }

    /** "Agency & agent watermarks" tab: accounts that set up their own, 20 per page. */
    private function accounts(Request $request): array
    {
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
            ->paginate(20, ['id', 'name', 'company_name', 'email', 'type', 'company_id', 'watermark', 'updated_at'])
            ->withQueryString();

        return ['accounts' => $accounts, 'search' => $search, 'status' => $status];
    }

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

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            $this->watermark->deleteImage($settings['image']);
            $settings['image'] = null;
        }
        if ($request->hasFile('image')) {
            $settings['image'] = $this->watermark->storeImage($request->file('image'), $subject);
        }

        $settings = array_merge($settings, [
            'enabled' => $request->boolean('enabled'),
            'type' => $data['type'],
            'text' => trim((string) ($data['text'] ?? '')),
            'color' => strtolower($data['color'] ?? '#ffffff'),
            'opacity' => (int) $data['opacity'],
            'size' => (int) $data['size'],
            'position' => $data['position'],
        ]);

        if ($settings['enabled'] && $settings['type'] === 'image' && !$settings['image']) {
            return back()->withInput()->withErrors(['image' => 'Upload a watermark image (e.g. your logo), or switch to a text watermark.']);
        }
        if ($settings['enabled'] && $settings['type'] === 'text' && $settings['text'] === '') {
            return back()->withInput()->withErrors(['text' => 'Enter the watermark text.']);
        }

        $this->watermark->save($subject, $settings);

        $message = match (true) {
            !$subject && $settings['enabled'] => 'Default watermark saved. It goes on your listings and on every agency / agent without their own, for photos uploaded from now on.',
            !$subject => 'Default watermark saved. It is currently turned off.',
            $settings['enabled'] => 'Watermark saved. It will be added to every listing photo you upload from now on.',
            default => 'Watermark settings saved. Yours is off, so the MW Realty default watermark (if any) is used.',
        };

        return redirect()->route('portal.watermark.edit')->with('success', $message);
    }

    /**
     * A watermark image (private disk) for the settings page: the viewer's own (an agency agent's =
     * the agency's); ?source=default → Super Admin's default; Super Admin may pass ?account=ID.
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

    /** Same rule as EnsurePortalAccountApproved: a portal login wins over a Super Admin CMS session. */
    private function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    private function portalUser(): PortalUser
    {
        return Auth::guard('portal')->user();
    }
}
