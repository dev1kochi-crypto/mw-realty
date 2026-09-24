<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Property galleries are files named "{reference_no}-{n}.jpeg" inside one folder, and the
 * property's `image_path` stores that folder: a Cloudinary folder URL
 * (https://res.cloudinary.com/<cloud>/image/upload/MW/properties/PROP021) or, for legacy data, a
 * local public-disk folder ("properties/PROP021"). This keeps both working behind one API.
 */
class PropertyGallery
{
    public function __construct(private readonly CloudinaryMedia $cloudinary)
    {
    }

    /** The `image_path` value to save for a gallery folder like "properties/PROP021". */
    public function folderValue(string $folderOrUrl): string
    {
        if (CloudinaryMedia::isCloudinaryUrl($folderOrUrl) || !CloudinaryMedia::enabled()) {
            return $folderOrUrl;
        }

        return $this->cloudinary->folderUrl($folderOrUrl);
    }

    /** "properties/PROP021" for either form of `image_path`. */
    public function relativeFolder(string $folderOrUrl): string
    {
        if (!CloudinaryMedia::isCloudinaryUrl($folderOrUrl)) {
            return trim($folderOrUrl, '/');
        }
        $parsed = $this->cloudinary->parseUrl(rtrim($folderOrUrl, '/') . '/x.jpg');
        $root = trim((string) config('services.cloudinary.folder', 'MW'), '/');
        $publicId = substr($parsed['public_id'] ?? '', 0, -2); // drop "/x"

        return $root !== '' && str_starts_with($publicId, $root . '/') ? substr($publicId, strlen($root) + 1) : $publicId;
    }

    public function put(string $folderValue, string $filename, string $contents): void
    {
        $relative = $this->relativeFolder($folderValue) . '/' . $filename;

        if (CloudinaryMedia::isCloudinaryUrl($folderValue)) {
            $this->cloudinary->uploadContents($contents, $relative);
        } else {
            Storage::disk('public')->put($relative, $contents);
        }
    }

    public function delete(string $folderValue, string $filename): void
    {
        if (CloudinaryMedia::isCloudinaryUrl($folderValue)) {
            $this->cloudinary->delete(rtrim($folderValue, '/') . '/' . $filename);
        } else {
            Storage::disk('public')->delete($this->relativeFolder($folderValue) . '/' . $filename);
        }
    }

    public function deleteAll(?string $folderValue): void
    {
        if (!$folderValue) {
            return;
        }
        if (CloudinaryMedia::isCloudinaryUrl($folderValue)) {
            $this->cloudinary->deleteFolder($folderValue);
        } else {
            Storage::disk('public')->deleteDirectory($this->relativeFolder($folderValue));
        }
    }
}
