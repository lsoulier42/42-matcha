<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Upload and management of profile photos (maximum 5, one profile photo).
 * Server-side validation: magic bytes via getimagesize(), max size,
 * systematic renaming (no user-supplied filenames), directory protected
 * against script execution (public/assets/uploads/.htaccess).
 * Bonus gallery: basic image editing with GD (rotation, filters, crop).
 */
final class PhotoService
{
    public function __construct(
        private PhotoRepository $photos,
        private array $settings
    ) {
    }

    /**
     * Validates and saves a photo.
     *
     * @return string[] error messages (empty on success)
     */
    public function upload(int $userId, ?UploadedFileInterface $file): array
    {
        $cfg = $this->settings['uploads'];
        $errors = [];

        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            return ['Aucun fichier reçu ou upload interrompu.'];
        }

        if ($this->photos->countForUser($userId) >= $cfg['max_photos']) {
            return ['Nombre maximal de photos atteint (' . $cfg['max_photos'] . ').'];
        }

        if ($file->getSize() > $cfg['max_size']) {
            return ['Fichier trop volumineux (maximum 5 Mo).'];
        }

        // Validate actual content (magic bytes), not the declared MIME type.
        $stream = $file->getStream();
        $tmpPath = (string) $stream->getMetadata('uri');
        $info = @getimagesize($tmpPath);
        if ($info === false || !isset($info['mime'])) {
            return ['Ce fichier n\'est pas une image valide (PNG, JPG, GIF ou WebP attendu).'];
        }
        $mime = $info['mime'];
        if (!isset($cfg['allowed'][$mime])) {
            return ['Format d\'image non autorisé (PNG, JPG, GIF ou WebP uniquement).'];
        }

        // Safe renaming: no user-supplied element survives in the filename.
        $ext = $cfg['allowed'][$mime];
        $filename = 'u' . $userId . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
        $target = $cfg['dir'] . '/' . $filename;

        $file->moveTo($target);
        if (!is_file($target)) {
            return ['Impossible d\'enregistrer le fichier.'];
        }

        // First photo = default profile photo.
        $isProfile = $this->photos->countForUser($userId) === 0 ? 1 : 0;
        $this->photos->create($userId, '/assets/uploads/' . $filename, $isProfile, $this->photos->nextPosition($userId));

        return [];
    }

    /** Sets the profile photo (one at a time). */
    public function setProfile(int $userId, int $photoId): void
    {
        if ($this->photos->findOwned($photoId, $userId) === null) {
            return; // not owned by the user: silently ignore
        }
        $this->photos->setProfile($userId, $photoId);
    }

    /** Deletes a photo (and its file). */
    public function delete(int $userId, int $photoId): void
    {
        $photo = $this->photos->findOwned($photoId, $userId);
        if ($photo === null) {
            return;
        }

        $filePath = $this->settings['uploads']['dir'] . str_replace('/assets/uploads', '', $photo->path);
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $wasProfile = $photo->isProfile;
        $this->photos->delete($photoId);

        if ($wasProfile) {
            // The next photo becomes profile photo, if it exists.
            $next = $this->photos->next($userId);
            if ($next !== null) {
                $this->photos->setProfile($userId, $next->id);
            }
        }
    }

    /** Profile photo for a user (or null). */
    public function profilePhoto(int $userId): ?Photo
    {
        return $this->photos->profilePhoto($userId);
    }

    /**
     * List of photos for a user (position order).
     *
     * @return Photo[]
     */
    public function listByUser(int $userId): array
    {
        return $this->photos->listByUser($userId);
    }

    // ------------------------------------------------------------
    // Bonus gallery: basic image editing with GD
    // ------------------------------------------------------------

    /** Rotate by 90/180/270 degrees (GD imagerotate). */
    public function rotate(int $userId, int $photoId, int $degrees): bool
    {
        $degrees = ((int) $degrees % 360 + 360) % 360;
        if (!in_array($degrees, [90, 180, 270], true)) {
            return false;
        }
        $img = $this->loadOwned($userId, $photoId);
        if ($img === null) {
            return false;
        }
        [$image, $path, $mime] = $img;

        $rotated = imagerotate($image, $degrees, 0);
        $ok = $this->save($rotated, $path, $mime);
        imagedestroy($image);
        imagedestroy($rotated);
        return $ok;
    }

    /** Basic filters: grayscale, sepia, negative, blur (GD imagefilter). */
    public function applyFilter(int $userId, int $photoId, string $filter): bool
    {
        $img = $this->loadOwned($userId, $photoId);
        if ($img === null) {
            return false;
        }
        [$image, $path, $mime] = $img;

        switch ($filter) {
            case 'grayscale':
                $ok = imagefilter($image, IMG_FILTER_GRAYSCALE);
                break;
            case 'sepia':
                $ok = imagefilter($image, IMG_FILTER_GRAYSCALE)
                    && imagefilter($image, IMG_FILTER_COLORIZE, 112, 66, 20);
                break;
            case 'negative':
                $ok = imagefilter($image, IMG_FILTER_NEGATE);
                break;
            case 'blur':
                $ok = imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
                break;
            default:
                $ok = false;
        }

        if ($ok) {
            $ok = $this->save($image, $path, $mime);
        }
        imagedestroy($image);
        return $ok;
    }

    /**
     * Crop (GD imagecrop). Coordinates are in original-image pixels;
     * they are clamped server-side.
     */
    public function crop(int $userId, int $photoId, int $x, int $y, int $width, int $height): bool
    {
        $img = $this->loadOwned($userId, $photoId);
        if ($img === null) {
            return false;
        }
        [$image, $path, $mime] = $img;

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $x = max(0, min($srcW - 1, $x));
        $y = max(0, min($srcH - 1, $y));
        $width = max(50, min($srcW - $x, $width));
        $height = max(50, min($srcH - $y, $height));

        $cropped = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        $ok = $cropped !== false && $this->save($cropped, $path, $mime);
        if ($cropped !== false) {
            imagedestroy($cropped);
        }
        imagedestroy($image);
        return $ok;
    }

    /** Loads a photo owned by the user, ready for GD processing. */
    private function loadOwned(int $userId, int $photoId): ?array
    {
        $photo = $this->photos->findOwned($photoId, $userId);
        if ($photo === null) {
            return null;
        }
        $filePath = $this->settings['uploads']['dir'] . str_replace('/assets/uploads', '', $photo->path);
        if (!is_file($filePath)) {
            return null;
        }
        $info = @getimagesize($filePath);
        if ($info === false || !isset($info['mime'])) {
            return null;
        }
        $mime = $info['mime'];
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            default => false,
        };
        if ($image === false) {
            return null;
        }
        return [$image, $filePath, $mime];
    }

    /** Rewrites the image in its original format. */
    private function save($image, string $path, string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 90),
            'image/png' => imagepng($image, $path, 8),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, 90),
            default => false,
        };
    }
}
