<?php

namespace App\Http\Controllers;

use App\Models\PortalUser;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function admin(PortalUser $portalUser, string $field)
    {
        return $this->download($portalUser, $field);
    }

    public function own(string $field)
    {
        return $this->download(auth('portal')->user(), $field);
    }

    private function download(PortalUser $user, string $field)
    {
        abort_unless(in_array($field, PortalUser::DOCUMENT_FIELDS, true), 404);
        $path = $user->{$field};
        abort_unless($path && str_starts_with($path, 'portal-kyc/') && Storage::disk('kyc')->exists($path), 404);
        return Storage::disk('kyc')->download($path, $field.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
