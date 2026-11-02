<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Images are decoded and re-encoded to WebP with GD. That strips metadata and any embedded
 * payload (polyglot files), and the stored name is a random UUID, never the client's filename.
 */
class ImageService
{
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_PIXELS = 16_000_000;

    public function store(UploadedFile $file, string $dir): string
    {
        return $this->storeContents((string) file_get_contents($file->getRealPath()), $dir);
    }

    /** Validate and re-encode an image downloaded from a trusted product CSV URL. */
    public function storeContents(string $contents, string $dir): string
    {
        $info = @getimagesizefromstring($contents);
        if ($info === false || ! in_array($info['mime'] ?? '', self::ALLOWED, true)) {
            throw ValidationException::withMessages(['images' => ['An imported image is not a valid JPEG, PNG or WebP file.']]);
        }
        if (($info[0] * $info[1]) > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['images' => ['An imported image has dimensions that are too large.']]);
        }
        if (! function_exists('imagecreatefromstring')) {
            abort(500, 'Image processing is not available on this server.');
        }

        $img = @imagecreatefromstring($contents);
        if ($img === false) {
            throw ValidationException::withMessages(['images' => ['An imported image could not be processed.']]);
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $webp = function_exists('imagewebp');
        ob_start();
        $webp ? imagewebp($img, null, 85) : imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        $path = trim($dir, '/').'/'.Str::uuid()->toString().($webp ? '.webp' : '.png');
        Storage::disk('public')->put($path, $binary);
        return $path;
    }

    public function delete(?string $path): void
    {
        // Paths come from our database, but guard against traversal anyway.
        if (! $path || str_contains($path, '..') || str_starts_with($path, '/')) {
            return;
        }
        Storage::disk('public')->delete($path);
    }
}
