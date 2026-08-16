<?php

declare(strict_types=1);

namespace App\Services;

use App\Db\Query;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Upload et gestion des photos de profil (maximum 5, une photo de profil).
 * Validation côté serveur : magic bytes via getimagesize(), taille max,
 * renommage systématique (aucun nom fourni par l'utilisateur), dossier
 * protégé contre l'exécution de scripts (public/assets/uploads/.htaccess).
 */
final class PhotoService
{
    public function __construct(
        private Query $db,
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

        $count = (int) $this->db->value('SELECT COUNT(*) FROM photos WHERE user_id = ?', [$userId]);
        if ($count >= $cfg['max_photos']) {
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
        $position = $this->db->value('SELECT COALESCE(MAX(position), -1) + 1 FROM photos WHERE user_id = ?', [$userId]);
        $this->db->insert('photos', [
            'user_id' => $userId,
            'path' => '/assets/uploads/' . $filename,
            'is_profile' => $count === 0 ? 1 : 0,
            'position' => (int) $position,
        ]);

        return [];
    }

    /** Désigne la photo de profil (une seule à la fois). */
    public function setProfile(int $userId, int $photoId): void
    {
        $owner = $this->db->value('SELECT user_id FROM photos WHERE id = ?', [$photoId]);
        if ((int) $owner !== $userId) {
            return; // pas la propriété de l'utilisateur : on ignore
        }
        $this->db->run('UPDATE photos SET is_profile = 0 WHERE user_id = ?', [$userId]);
        $this->db->update('photos', ['is_profile' => 1], 'id = ?', [$photoId]);
    }

    /** Supprime une photo (et son fichier). */
    public function delete(int $userId, int $photoId): void
    {
        $photo = $this->db->fetch('SELECT * FROM photos WHERE id = ? AND user_id = ?', [$photoId, $userId]);
        if ($photo === null) {
            return;
        }

        $filePath = $this->settings['uploads']['dir'] . str_replace('/assets/uploads', '', (string) $photo['path']);
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $wasProfile = (int) $photo['is_profile'] === 1;
        $this->db->delete('photos', 'id = ?', [$photoId]);

        if ($wasProfile) {
            // La photo suivante devient photo de profil, si elle existe.
            $next = $this->db->fetch(
                'SELECT id FROM photos WHERE user_id = ? ORDER BY position ASC LIMIT 1',
                [$userId]
            );
            if ($next !== null) {
                $this->db->update('photos', ['is_profile' => 1], 'id = ?', [$next['id']]);
            }
        }
    }

    /** Photo de profil d'un utilisateur (ou null). */
    public function profilePhoto(int $userId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM photos WHERE user_id = ? AND is_profile = 1 ORDER BY position ASC LIMIT 1',
            [$userId]
        );
    }
}
