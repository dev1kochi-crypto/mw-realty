<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\CloudinaryMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Serves a listing's brochure / downloadable floor plan as a same-origin attachment. Only reachable
 * through the short-lived signed URL LeadCaptureController hands out after the lead form, so the file
 * stays gated. Cloudinary files are fetched server-side via the signed API URL (public PDF delivery
 * may be blocked on the account), then streamed through with a Content-Length for the progress bar.
 */
class PropertyFileDownloadController extends Controller
{
    public const KINDS = ['brochure', 'floor-plan'];

    public function __invoke(Request $request, Property $property, string $kind)
    {
        $file = match ($kind) {
            'brochure' => $property->brochure_path,
            'floor-plan' => $property->details?->floor_plan_file,
            default => null,
        };
        abort_unless($property->status && $file, 404);

        $extension = strtolower(pathinfo(strtok($file, '?'), PATHINFO_EXTENSION)) ?: 'pdf';
        $filename = Str::slug($property->getTranslation('title') ?: $property->reference_no) . "-{$kind}.{$extension}";

        if (!CloudinaryMedia::isCloudinaryUrl($file)) {
            abort_unless(Storage::disk('public')->exists($file), 404);

            return Storage::disk('public')->download($file, $filename);
        }

        $source = app(CloudinaryMedia::class)->authenticatedDownloadUrl($file) ?? $file;
        $response = Http::withOptions(['stream' => true])->timeout(60)->get($source);
        abort_unless($response->successful(), 404);
        $body = $response->toPsrResponse()->getBody();

        $headers = ['Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream'];
        if ($length = $response->header('Content-Length')) {
            $headers['Content-Length'] = $length;
        }

        return response()->streamDownload(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(65536);
                flush();
            }
        }, $filename, $headers);
    }
}
