<?php

namespace App\Services\Properties;

use App\Models\Property;
use App\Services\PropertyGallery;
use App\Services\Watermark;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Downloads a listing's photos from their URLs into its gallery, watermarked like uploads — for
 * listings that arrive from a feed (Property Finder sync, a spreadsheet's image URL column).
 * A photo that fails to download is skipped; the listing never fails because of one.
 */
class RemoteListingImages
{
    public const MAX_IMAGES = 30;

    public function __construct(private readonly PropertyGallery $gallery, private readonly Watermark $watermark)
    {
    }

    /** Returns how many photos were stored. */
    public function store(Property $property, array $urls): int
    {
        if (!$urls) {
            return 0;
        }
        $folder = $this->gallery->folderValue('properties/' . $property->reference_no);
        $watermark = $this->watermark->activeSettings($property->owner);
        $sequence = [];
        $number = 0;

        foreach (array_slice($urls, 0, self::MAX_IMAGES) as $url) {
            $tmp = tempnam(sys_get_temp_dir(), 'img');
            try {
                $response = Http::timeout(30)->retry(1, 500, throw: false)->sink($tmp)->get($url);
                if (!$response->successful()) {
                    continue;
                }
                $number++;
                $this->gallery->put($folder, $property->reference_no . '-' . $number . '.jpeg', PropertyGallery::jpeg($tmp, $watermark));
                $sequence[] = $number;
            } catch (\Throwable $e) {
                Log::info("Listing image for {$property->reference_no} skipped: " . $e->getMessage());
            } finally {
                @unlink($tmp);
            }
        }

        if ($sequence) {
            $property->update(['image_path' => $folder, 'image_sequence' => implode(',', $sequence), 'image_next_number' => $number]);
        }

        return count($sequence);
    }
}
