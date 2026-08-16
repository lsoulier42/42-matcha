<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\PhotoRepository;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Upload et gestion des photos de profil (maximum 5, une photo de profil).
 * Validation côté serveur : magic bytes via getimagesize(), taille max,
 * renommage systématique (aucun nom fourni par l'utilisateur), dossier
 * protégé contre l'exécution de scripts (public/assets/uploads/.htaccess).
 * Bonus galerie : édition d'image de base avec GD (rotation, filtres, recadrage).
 */
final class PhotoService
{
    public function __construct(
        private PhotoRepository $photos,
        private array $settings
    ) {
    }

    /**
     * Valide et enregistre une photo.
     *
     * @return string[] messages d'erreur (vide si succès)
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

        // Validation du vrai contenu (magic bytes), pas du MIME déclaré.
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

        // Renommage sûr : aucun élément fourni par l'utilisateur ne subsiste.
        $ext = $cfg['allowed'][$mime];
        $filename = 'u' . $userId . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
        $target = $cfg['dir'] . '/' . $filename;

        $file->moveTo($target);
        if (!is_file($target)) {
            return ['Impossible d\'enregistrer le fichier.'];
        }

        // Première photo = photo de profil par défaut.
        $isProfile = $this->photos->countForUser($userId) === 0 ? 1 : 0;
        $this->photos->create($userId, '/assets/uploads/' . $filename, $isProfile, $this->photos->nextPosition($userId));

        return [];
    }

    /** Désigne la photo de profil (une seule à la fois). */
    public function setProfile(int $userId, int $photoId): void
    {
        if ($this->photos->findOwned($photoId, $userId) === null) {
            return; // pas la propriété de l'utilisateur : on ignore
        }
        $this->photos->setProfile($userId, $photoId);
    }

    /** Supprime une photo (et son fichier). */
    public function delete(int $userId, int $photoId): void
    {
        $photo = $this->photos->findOwned($photoId, $userId);
        if ($photo === null) {
            return;
        }

        $filePath = $this->settings['uploads']['dir'] . str_replace('/assets/uploads', '', (string) $photo['path']);
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $wasProfile = (int) $photo['is_profile'] === 1;
        $this->photos->delete($photoId);

        if ($wasProfile) {
            // La photo suivante devient photo de profil, si elle existe.
            $next = $this->photos->next($userId);
            if ($next !== null) {
                $this->photos->setProfile($userId, (int) $next['id']);
            }
        }
    }

    /** Photo de profil d'un utilisateur (ou null). */
    public function profilePhoto(int $userId): ?array
    {
        return $this->photos->profilePhoto($userId);
    }

    /** Liste des photos d'un utilisateur (ordre de position). */
    public function listByUser(int $userId): array
    {
        return $this->photos->listByUser($userId);
    }

    // ------------------------------------------------------------
    // Bonus galerie : édition d'image de base avec GD
    // ------------------------------------------------------------

    /** Rotation de 90/180/270 degrés (GD imagerotate). */
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

    /** Filtres de base : grayscale, sepia, negative, blur (GD imagefilter). */
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
     * Recadrage (GD imagecrop). Les coordonnées sont en pixels de
     * l'image d'origine ; elles sont bornées côté serveur.
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

    /** Charge une photo appartenant à l'utilisateur, prête pour GD. */
    private function loadOwned(int $userId, int $photoId): ?array
    {
        $photo = $this->photos->findOwned($photoId, $userId);
        if ($photo === null) {
            return null;
        }
        $filePath = $this->settings['uploads']['dir'] . str_replace('/assets/uploads', '', (string) $photo['path']);
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

    /** Réécrit l'image dans son format d'origine. */
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
