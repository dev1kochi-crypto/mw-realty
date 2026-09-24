<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * All public media (images + videos) lives on Cloudinary. The database stores each file's full
 * secure URL; property galleries store a folder URL and build "{folder}/{ref}-{n}.jpeg" from it.
 * KYC documents never come here — they stay on the private `kyc` disk.
 *
 * Public IDs mirror the old local paths under the CLOUDINARY_FOLDER root, e.g.
 * storage/app/public/blogs/abc.jpg  →  MW/blogs/abc  →  https://res.cloudinary.com/<cloud>/image/upload/v…/MW/blogs/abc.jpg
 */
class CloudinaryMedia
{
    /** Free plan rejects images over 10 MB — larger ones are downscaled before upload. */
    private const MAX_IMAGE_BYTES = 9_500_000;

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv', 'ogv'];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico', 'tif', 'tiff', 'heic'];

    private ?Cloudinary $client = null;

    public static function enabled(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    public static function isCloudinaryUrl(?string $value): bool
    {
        return is_string($value) && str_contains($value, 'res.cloudinary.com/');
    }

    public function client(): Cloudinary
    {
        return $this->client ??= new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    public static function resourceTypeFor(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, self::VIDEO_EXTENSIONS, true) => 'video',
            in_array($ext, self::IMAGE_EXTENSIONS, true), $ext === 'pdf' => 'image',
            default => 'raw',
        };
    }

    /** "blogs/abc.jpg" → "MW/blogs/abc" (raw files keep their extension, as Cloudinary requires). */
    public function publicIdFor(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $root = trim((string) config('services.cloudinary.folder', 'MW'), '/');
        $id = self::resourceTypeFor($relativePath) === 'raw'
            ? $relativePath
            : preg_replace('/\.[^.\/]+$/', '', $relativePath);

        return ($root !== '' ? $root . '/' : '') . $id;
    }

    /** Uploads a new request file into $directory (random name, like $file->store()) — returns the secure URL. */
    public function storeUploaded(UploadedFile $file, string $directory): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $relative = trim($directory, '/') . '/' . Str::random(40) . '.' . $ext;

        return $this->uploadFile($file->getRealPath(), $relative);
    }

    /** Uploads a local file so it lives at the Cloudinary equivalent of $relativePath. */
    public function uploadFile(string $localPath, string $relativePath): string
    {
        $type = self::resourceTypeFor($relativePath);
        [$source, $temp] = $type === 'image' ? $this->shrinkIfTooLarge($localPath) : [$localPath, null];

        $publicId = $this->publicIdFor($relativePath);

        try {
            $result = $this->client()->uploadApi()->upload($source, [
                'public_id' => $publicId,
                // Dynamic-folder accounts: the Media Library folder is separate from the public ID —
                // set it too, so assets show up under MW/<section> in the Cloudinary console.
                'asset_folder' => dirname($publicId),
                'display_name' => basename($publicId),
                'resource_type' => $type,
                'overwrite' => true,
                'invalidate' => true,
                'use_filename' => false,
                'unique_filename' => false,
            ]);
        } finally {
            if ($temp) {
                @unlink($temp);
            }
        }

        return $result['secure_url'];
    }

    /** Uploads raw bytes (e.g. a converted JPEG) to the Cloudinary equivalent of $relativePath. */
    public function uploadContents(string $contents, string $relativePath): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'cld');
        file_put_contents($temp, $contents);
        try {
            return $this->uploadFile($temp, $relativePath);
        } finally {
            @unlink($temp);
        }
    }

    /** Moves an existing asset into its Media Library folder (dirname of its public ID). */
    public function assignFolder(string $url): bool
    {
        if (!($parsed = $this->parseUrl($url))) {
            return false;
        }

        try {
            $this->client()->adminApi()->update($parsed['public_id'], [
                'resource_type' => $parsed['resource_type'],
                'asset_folder' => dirname($parsed['public_id']),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Cloudinary folder assign failed for ' . $url . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Deletes the asset behind a Cloudinary URL. Returns false (and logs) on failure. */
    public function delete(?string $url): bool
    {
        if (!self::isCloudinaryUrl($url) || !($parsed = $this->parseUrl($url))) {
            return false;
        }

        try {
            $this->client()->uploadApi()->destroy($parsed['public_id'], ['resource_type' => $parsed['resource_type'], 'invalidate' => true]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed for ' . $url . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Deletes every asset under a gallery folder URL ("…/image/upload/MW/properties/PROP021"). */
    public function deleteFolder(?string $folderUrl): void
    {
        if (!self::isCloudinaryUrl($folderUrl) || !($parsed = $this->parseUrl(rtrim($folderUrl, '/') . '/x'))) {
            return;
        }
        $prefix = substr($parsed['public_id'], 0, -1); // strip the placeholder "x"

        try {
            $this->client()->adminApi()->deleteAssetsByPrefix($prefix, ['resource_type' => 'image']);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary folder delete failed for ' . $folderUrl . ': ' . $e->getMessage());
        }
    }

    /** Base URL for a gallery folder: images are then "{this}/{ref}-{n}.jpeg". */
    public function folderUrl(string $relativeFolder): string
    {
        $root = trim((string) config('services.cloudinary.folder', 'MW'), '/');

        return 'https://res.cloudinary.com/' . config('services.cloudinary.cloud_name') . '/image/upload/'
            . ($root !== '' ? $root . '/' : '') . trim($relativeFolder, '/');
    }

    /** ['resource_type', 'public_id'] from a delivery URL (handles version + transformations). */
    public function parseUrl(string $url): ?array
    {
        if (!preg_match('#res\.cloudinary\.com/[^/]+/(image|video|raw)/upload/(.+)$#', strtok($url, '?'), $m)) {
            return null;
        }
        $segments = explode('/', $m[2]);
        // Drop transformation segments ("w_300,c_fill") and the version ("v1712345678").
        while ($segments && (str_contains($segments[0], ',') || preg_match('/^(w|h|c|q|f|g|x|y|ar|dpr|e|t|fl|b|r|o|a)_[^\/]+$/', $segments[0]) || preg_match('/^v\d+$/', $segments[0]))) {
            array_shift($segments);
        }
        $path = implode('/', $segments);
        $publicId = $m[1] === 'raw' ? $path : preg_replace('/\.[^.\/]+$/', '', $path);

        return ['resource_type' => $m[1], 'public_id' => $publicId];
    }

    /** Re-encodes an oversized image (max 2560px, JPEG q85) into a temp file. */
    private function shrinkIfTooLarge(string $path): array
    {
        if (!is_file($path) || filesize($path) <= self::MAX_IMAGE_BYTES || !function_exists('imagecreatefromstring')) {
            return [$path, null];
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (!$image) {
            return [$path, null];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 2560 / max($width, $height));
        $resized = imagecreatetruecolor(max(1, (int) ($width * $scale)), max(1, (int) ($height * $scale)));
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
        imagedestroy($image);

        $temp = tempnam(sys_get_temp_dir(), 'cldimg') . '.jpg';
        imagejpeg($resized, $temp, 85);
        imagedestroy($resized);

        return [$temp, $temp];
    }
}
