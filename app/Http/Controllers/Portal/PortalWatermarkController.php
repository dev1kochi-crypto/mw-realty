<?php

namespace App\Http\Controllers\Portal;

use App\Models\PortalUser;
use App\Services\Watermark;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Listings Settings → Watermark. An agency or independent agent sets the logo / text stamped onto
 * every listing photo they upload from now on. An agency's agents use the agency's watermark
 * (the agency owns their listings), so for them the page is read-only.
 */
class PortalWatermarkController extends Controller
{
    public function __construct(private Watermark $watermark)
    {
    }

    public function edit()
    {
        $portalUser = $this->portalUser();
        $owner = $this->watermark->ownerFor($portalUser);

        return view('portal.watermark.edit', [
            'portalUser' => $portalUser,
            'owner' => $owner,
            'readOnly' => $owner->id !== $portalUser->id,
            'settings' => $this->watermark->settings($owner),
        ]);
    }

    public function update(Request $request)
    {
        $portalUser = $this->portalUser();
        abort_if($portalUser->isAgencyAgent(), 403, 'Your agency manages the watermark for your listings.');

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

        $settings = $this->watermark->settings($portalUser);

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            $this->watermark->deleteImage($settings['image']);
            $settings['image'] = null;
        }
        if ($request->hasFile('image')) {
            $settings['image'] = $this->watermark->storeImage($request->file('image'), $portalUser);
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

        $portalUser->forceFill(['watermark' => $settings])->save();

        return redirect()->route('portal.watermark.edit')->with('success', $settings['enabled']
            ? 'Watermark saved. It will be added to every listing photo you upload from now on.'
            : 'Watermark settings saved. The watermark is currently turned off.');
    }

    /** The stored watermark image (private disk), for the settings preview. */
    public function image()
    {
        $owner = $this->watermark->ownerFor($this->portalUser());
        $path = $this->watermark->settings($owner)['image'];
        abort_unless($path, 404);

        return response()->file($this->watermark->imagePath($path), ['Cache-Control' => 'private, max-age=300']);
    }

    private function portalUser(): PortalUser
    {
        return Auth::guard('portal')->user();
    }
}
