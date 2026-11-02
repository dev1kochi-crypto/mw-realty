<?php

namespace App\Services\ListingReview;

use App\Models\Property;
use App\Models\PropertyImageHash;
use App\Services\CloudinaryMedia;
use App\Services\PropertyGallery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Perceptual "difference hash" of each gallery photo: the photo shrunk to 9×8 greyscale, one bit per
 * pixel saying whether it is brighter than its right-hand neighbour. Resizing, re-compression and a
 * different agency's watermark barely change it, so the same photo posted by two agencies gives two
 * hashes only a few bits apart (MATCH_BITS). The edges are cropped first — that's where watermarks
 * and agency banners usually sit.
 */
class ImageHasher
{
    /** At most this many differing bits (of 64) = the same photo. */
    public const MATCH_BITS = 8;

    /** Photos hashed per listing — enough to catch a reused gallery. */
    private const MAX_PHOTOS = 15;

    public function __construct(private readonly PropertyGallery $gallery)
    {
    }

    /** The gallery as it is now — hashes are only rebuilt when this changes. */
    public static function signature(Property $property): string
    {
        return md5($property->image_path . '|' . $property->image_sequence);
    }

    /** Re-hashes the listing's gallery. Returns false when nothing changed since the last run. */
    public function hash(Property $property): bool
    {
        $signature = self::signature($property);
        if (($property->metadata['photo_hash_signature'] ?? null) === $signature) {
            return false;
        }

        $rows = [];
        if ($property->image_path) {
            foreach (array_slice($property->galleryNumbers(), 0, self::MAX_PHOTOS) as $number) {
                $hash = $this->hashBytes($this->photoBytes($property, $number));
                if ($hash !== null) {
                    $rows[] = ['property_id' => $property->id, 'number' => $number, 'hash' => $hash, 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }

        PropertyImageHash::where('property_id', $property->id)->delete();
        PropertyImageHash::insert($rows);
        // Saved quietly: a metadata-only change must not count as an edit of the listing.
        $property->forceFill(['metadata' => array_merge((array) $property->metadata, ['photo_hash_signature' => $signature])])->saveQuietly();

        return true;
    }

    public function hashBytes(?string $bytes): ?int
    {
        $source = $bytes ? @imagecreatefromstring($bytes) : false;
        if (!$source) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        // Middle 80%: drops corner watermarks and edge banners.
        $cropX = (int) round($width * 0.1);
        $cropY = (int) round($height * 0.1);
        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $source, 0, 0, $cropX, $cropY, 9, 8, $width - 2 * $cropX, $height - 2 * $cropY);

        $hash = 0;
        $bit = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                if ($this->luma($small, $x, $y) > $this->luma($small, $x + 1, $y)) {
                    $hash |= 1 << $bit;
                }
                $bit++;
            }
        }

        // A flat image (plain colour, blank render) has almost no bits set — it would match every other one.
        $bits = substr_count(decbin($hash), '1');

        return $bits < 6 || $bits > 58 ? null : $hash;
    }

    private function luma(\GdImage $image, int $x, int $y): float
    {
        $rgb = imagecolorat($image, $x, $y);

        return 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
    }

    private function photoBytes(Property $property, int $number): ?string
    {
        $filename = $property->reference_no . '-' . $number . '.jpeg';
        try {
            if (CloudinaryMedia::isCloudinaryUrl($property->image_path)) {
                // A 256px rendition is plenty for a 9×8 hash and much quicker to download.
                $url = str_replace('/image/upload/', '/image/upload/w_256/', rtrim($property->image_path, '/') . '/' . $filename);
                $response = Http::timeout(10)->get($url);

                return $response->successful() ? $response->body() : null;
            }
            $path = $this->gallery->relativeFolder($property->image_path) . '/' . $filename;

            return Storage::disk('public')->exists($path) ? Storage::disk('public')->get($path) : null;
        } catch (\Throwable $e) {
            Log::warning("Listing Review: could not read photo {$filename}: " . $e->getMessage());

            return null;
        }
    }
}
