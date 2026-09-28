<?php

namespace App\Support;

use App\Models\Property;
use App\Models\PropertyFloorPlan;
use App\Services\CloudinaryMedia;
use App\Services\PropertyGallery;
use Illuminate\Support\Facades\Storage;

/**
 * Photos for local/demo listings (DemoFilterCoveragePropertiesSeeder, demo:media-to-cloudinary).
 *
 * The source images ship with the repo (public/frontend/assets/images), so this works on any
 * server. With Cloudinary configured, each listing's gallery is uploaded (resized to ~1400px so a
 * few hundred demo listings stay small) under properties/demo/{ref} — a folder of its own, so a
 * demo upload can never overwrite a real listing's photos at properties/{ref} on another database.
 * Without Cloudinary the files are hard-linked on the public disk (no extra space per listing).
 */
class DemoPropertyMedia
{
    public const IMAGES = [
        'frontend/assets/images/home/project-card-1.jpg',
        'frontend/assets/images/home/project-card-2.jpg',
        'frontend/assets/images/home/project-card-3.jpg',
        'frontend/assets/images/home/project-card-4.jpg',
        'frontend/assets/images/home/project-card-5.jpg',
        'frontend/assets/images/home/project-card-6.jpg',
        'frontend/assets/images/home/luxury-card-1.jpg',
        'frontend/assets/images/home/luxury-card-2.jpg',
        'frontend/assets/images/home/luxury-card-3.jpg',
        'frontend/assets/images/home/realty-card-1.jpg',
        'frontend/assets/images/home/realty-card-2.jpg',
        'frontend/assets/images/home/realty-card-3.jpg',
        'frontend/assets/images/home/realty-card-4.jpg',
    ];
    public const FLOOR_PLAN = 'frontend/assets/images/property-details/floorplan.png';

    private const PHOTOS_PER_LISTING = 3;
    private const MAX_EDGE = 1400;
    private const JPEG_QUALITY = 80;
    private const LOCAL_MASTER_FOLDER = 'properties/_demo-masters';

    /** @var array<int, string> resized JPEG bytes, per IMAGES index */
    private array $resized = [];

    public function __construct(
        private readonly CloudinaryMedia $cloudinary,
        private readonly PropertyGallery $gallery,
    ) {
    }

    public function usesCloudinary(): bool
    {
        return CloudinaryMedia::enabled();
    }

    /** Gives $property a PHOTOS_PER_LISTING-photo gallery, picking demo images by $seed. */
    public function attachGallery(Property $property, int $seed): void
    {
        $ref = $property->reference_no;
        $numbers = range(1, self::PHOTOS_PER_LISTING);

        if ($this->usesCloudinary()) {
            $folder = $this->cloudinary->folderUrl('properties/demo/' . $ref);
            foreach ($numbers as $n) {
                $this->gallery->put($folder, "{$ref}-{$n}.jpeg", $this->imageBytes($this->pick($seed, $n)));
            }
        } else {
            $folder = 'properties/' . $ref;
            foreach ($numbers as $n) {
                $this->linkLocal($this->localMaster($this->pick($seed, $n)), "{$folder}/{$ref}-{$n}.jpeg");
            }
        }

        $property->update([
            'image_path' => $folder,
            'image_sequence' => implode(',', $numbers),
            'image_next_number' => max($numbers),
        ]);
    }

    /** Stored path/URL of a floor-plan image for $property. */
    public function floorPlanImage(Property $property): string
    {
        $relative = 'properties/demo/' . $property->reference_no . '/floor-plan-1.png';
        if ($this->usesCloudinary()) {
            return $this->cloudinary->uploadContents(file_get_contents(public_path(self::FLOOR_PLAN)), $relative);
        }

        $local = 'properties/' . $property->reference_no . '/floor-plan-1.png';
        $master = self::LOCAL_MASTER_FOLDER . '/floorplan.png';
        if (!Storage::disk('public')->exists($master)) {
            Storage::disk('public')->put($master, file_get_contents(public_path(self::FLOOR_PLAN)));
        }
        $this->linkLocal($master, $local);

        return $local;
    }

    /** Re-points an existing demo listing's gallery + floor plans at Cloudinary. */
    public function moveToCloudinary(Property $property, int $seed): void
    {
        $this->attachGallery($property, $seed);
        foreach (PropertyFloorPlan::where('property_id', $property->id)->get() as $plan) {
            if ($plan->image && !CloudinaryMedia::isCloudinaryUrl($plan->image)) {
                $plan->update(['image' => $this->floorPlanImage($property)]);
            }
        }
    }

    private function pick(int $seed, int $n): int
    {
        return ($seed * self::PHOTOS_PER_LISTING + $n - 1) % count(self::IMAGES);
    }

    /** The demo image, scaled down to MAX_EDGE and re-encoded (cached per image). */
    private function imageBytes(int $index): string
    {
        if (isset($this->resized[$index])) {
            return $this->resized[$index];
        }
        $raw = file_get_contents(public_path(self::IMAGES[$index]));
        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($raw) : false;
        if (!$image) {
            return $this->resized[$index] = $raw;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagejpeg($out, null, self::JPEG_QUALITY);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($out);

        return $this->resized[$index] = $bytes;
    }

    private function localMaster(int $index): string
    {
        $path = self::LOCAL_MASTER_FOLDER . '/demo-' . ($index + 1) . '.jpeg';
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $this->imageBytes($index));
        }

        return $path;
    }

    /** Hard link (no extra disk space); falls back to a copy where links aren't supported. */
    private function linkLocal(string $from, string $to): void
    {
        $disk = Storage::disk('public');
        if ($disk->exists($to)) {
            return;
        }
        $disk->makeDirectory(dirname($to));
        if (!@link($disk->path($from), $disk->path($to))) {
            $disk->copy($from, $to);
        }
    }
}
