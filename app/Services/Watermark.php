<?php

namespace App\Services;

use App\Models\PortalUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Listing-photo watermark for an agency or independent agent (Listings Settings → Watermark).
 * Stamped into each gallery photo at upload time (PortalPropertyController::storeGalleryImage()),
 * so it's part of the image file itself everywhere the photo is shown.
 *
 * Settings (portal_users.watermark JSON):
 *   enabled bool · type image|text · image (path on the private `local` disk) · text · color #rrggbb
 *   opacity 5–100 (%) · size 5–80 (% of the photo's width) · position tl|tc|tr|ml|mc|mr|bl|bc|br
 */
class Watermark
{
    public const POSITIONS = ['tl', 'tc', 'tr', 'ml', 'mc', 'mr', 'bl', 'bc', 'br'];

    public const DEFAULTS = [
        'enabled' => false,
        'type' => 'image',
        'image' => null,
        'text' => '',
        'color' => '#ffffff',
        'opacity' => 30,
        'size' => 25,
        'position' => 'mc',
    ];

    private const DISK = 'local';
    private const FONT = 'fonts/DejaVuSans-Bold.ttf';

    public function settings(PortalUser $user): array
    {
        return array_merge(self::DEFAULTS, array_intersect_key($user->watermark ?? [], self::DEFAULTS));
    }

    /** The watermark used on this account's listing photos — an agency's agents use the agency's. */
    public function ownerFor(PortalUser $user): PortalUser
    {
        return $user->isAgencyAgent() && $user->company ? $user->company : $user;
    }

    /** Settings to stamp with, or null when the watermark is off / incomplete. */
    public function activeSettings(?PortalUser $owner): ?array
    {
        if (!$owner) {
            return null;
        }
        $s = $this->settings($owner);
        $ready = $s['type'] === 'text'
            ? trim((string) $s['text']) !== ''
            : $s['image'] && Storage::disk(self::DISK)->exists($s['image']);

        return $s['enabled'] && $ready ? $s : null;
    }

    /** Normalises an uploaded logo to a PNG (alpha kept, max 1600px wide) on the private disk. */
    public function storeImage(UploadedFile $file, PortalUser $user): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (!$source) {
            throw new \RuntimeException('Could not read the watermark image.');
        }

        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, 1600 / $w);
        $image = $this->transparentCanvas((int) round($w * $scale), (int) round($h * $scale));
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $w, $h);
        imagedestroy($source);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $path = 'watermarks/' . $user->id . '-' . Str::random(16) . '.png';
        Storage::disk(self::DISK)->put($path, $png);

        return $path;
    }

    public function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function imagePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    /** Stamps the watermark onto $photo in place. */
    public function apply(\GdImage $photo, array $s): void
    {
        $photoW = imagesx($photo);
        $photoH = imagesy($photo);
        $targetW = max(1, (int) round($photoW * $s['size'] / 100));

        $mark = $s['type'] === 'text' ? $this->textMark($s, $targetW) : $this->imageMark($s, $targetW);
        if (!$mark) {
            return;
        }
        $this->fade($mark, $s['opacity'] / 100);

        $markW = imagesx($mark);
        $markH = imagesy($mark);
        $margin = (int) round(min($photoW, $photoH) * 0.03);
        $x = match ($s['position'][1]) {
            'l' => $margin,
            'r' => $photoW - $markW - $margin,
            default => intdiv($photoW - $markW, 2),
        };
        $y = match ($s['position'][0]) {
            't' => $margin,
            'b' => $photoH - $markH - $margin,
            default => intdiv($photoH - $markH, 2),
        };

        imagealphablending($photo, true);
        imagecopy($photo, $mark, $x, $y, 0, 0, $markW, $markH);
        imagedestroy($mark);
    }

    private function imageMark(array $s, int $targetW): ?\GdImage
    {
        $source = @imagecreatefrompng($this->imagePath($s['image']));
        if (!$source) {
            return null;
        }
        $targetH = max(1, (int) round(imagesy($source) * $targetW / imagesx($source)));
        $mark = $this->transparentCanvas($targetW, $targetH);
        imagecopyresampled($mark, $source, 0, 0, 0, 0, $targetW, $targetH, imagesx($source), imagesy($source));
        imagedestroy($source);

        return $mark;
    }

    private function textMark(array $s, int $targetW): ?\GdImage
    {
        $font = resource_path(self::FONT);
        $text = trim((string) $s['text']);

        // Font size that makes the text $targetW wide.
        $box = imagettfbbox(100, 0, $font, $text);
        $size = 100 * $targetW / max(1, $box[2] - $box[0]);
        $box = imagettfbbox($size, 0, $font, $text);
        $pad = (int) ceil($size * 0.15);
        $width = $box[2] - $box[0] + $pad * 2;
        $height = $box[1] - $box[7] + $pad * 2;

        $mark = $this->transparentCanvas($width, $height);
        imagealphablending($mark, true);
        [$r, $g, $b] = sscanf($s['color'], '#%02x%02x%02x');
        imagettftext($mark, $size, 0, $pad - $box[0], $pad - $box[7], imagecolorallocate($mark, $r, $g, $b), $font, $text);

        return $mark;
    }

    /** Multiplies every pixel's opacity by $opacity (GD alpha: 0 = opaque, 127 = transparent). */
    private function fade(\GdImage $mark, float $opacity): void
    {
        imagealphablending($mark, false);
        $w = imagesx($mark);
        $h = imagesy($mark);
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $color = imagecolorat($mark, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha === 127) {
                    continue;
                }
                $alpha = 127 - (int) round((127 - $alpha) * $opacity);
                imagesetpixel($mark, $x, $y, ($alpha << 24) | ($color & 0xFFFFFF));
            }
        }
    }

    private function transparentCanvas(int $w, int $h): \GdImage
    {
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }
}
