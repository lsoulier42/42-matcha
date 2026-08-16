<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Db\Query;
use App\Services\PhotoService;
use App\Services\PopularityService;
use App\Support\Flash;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Profil utilisateur (section 3.2 du sujet) : genre, préférences, bio,
 * tags réutilisables, photos (max 5), note de popularité, localisation
 * (GPS avec consentement explicite ou saisie manuelle), qui m'a consulté,
 * qui m'a liké.
 */
final class ProfileController
{
    private const GENRES = ['homme', 'femme', 'autre'];
    private const ORIENTATIONS = ['hetero', 'homo', 'bi'];

    public function __construct(
        private Twig $twig,
        private Query $db,
        private PhotoService $photos,
        private PopularityService $popularity
    ) {
    }

    // -------------------------------------------------------------
    // Affichage / édition
    // -------------------------------------------------------------

    public function show(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);

        return $this->twig->render($response, 'profile/show.html.twig', $this->profileViewData($user));
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        $data = (array) $request->getParsedBody();

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $genre = (string) ($data['genre'] ?? '');
        $orientation = (string) ($data['orientation'] ?? '');
        $bio = trim((string) ($data['bio'] ?? ''));
        $birthdate = (string) ($data['birthdate'] ?? '');

        $v = new Validator();
        $v->required('email', $email, 'adresse e-mail')
            ->email('email', $email, 'Adresse e-mail')
            ->required('genre', $genre, 'genre')
            ->required('orientation', $orientation, 'préférences')
            ->length('bio', $bio, 0, 500, 'Biographie');

        if (!in_array($genre, self::GENRES, true)) {
            $v->add('genre', 'Genre invalide.');
        }
        if (!in_array($orientation, self::ORIENTATIONS, true)) {
            $v->add('orientation', 'Préférences invalides.');
        }

        $existingEmail = $this->db->value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId]);
        if ($existingEmail !== null) {
            $v->add('email', 'Cette adresse e-mail est déjà utilisée.');
        }

        $age = null;
        if ($birthdate !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
            if ($dt === false) {
                $v->add('birthdate', 'Date de naissance invalide.');
            } else {
                $age = (int) $dt->diff(new \DateTimeImmutable('now'))->y;
                if ($age < 16 || $age > 100) {
                    $v->add('birthdate', 'Âge invalide (16 à 100 ans attendu).');
                }
            }
        }

        if ($v->fails()) {
            Flash::set('error', 'Certains champs sont invalides.');
            return $this->twig->render($response, 'profile/show.html.twig', $this->profileViewData($user, $v->errors()));
        }

        $this->db->update('users', [
            'email' => $email,
            'genre' => $genre,
            'orientation' => $orientation === '' ? null : $orientation,
            'bio' => $bio === '' ? null : $bio,
            'birthdate' => $birthdate === '' ? null : $birthdate,
        ], 'id = ?', [$userId]);

        $this->refreshSession($userId);
        Flash::set('success', 'Profil mis à jour.');
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Localisation (consentement GPS explicite ou saisie manuelle)
    // -------------------------------------------------------------

    public function updateLocation(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $data = (array) $request->getParsedBody();

        $gpsConsent = (int) ($data['gps_consent'] ?? 0) === 1;
        $ville = trim((string) ($data['ville'] ?? ''));
        $lat = isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) && $data['lng'] !== '' ? (float) $data['lng'] : null;

        $v = new Validator();
        $v->length('ville', $ville, 2, 120, 'Ville / quartier');

        if ($gpsConsent) {
            if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $v->add('location', 'Coordonnées GPS invalides.');
            }
            // La ville reste utile à l'affichage (pas de reverse-geocoding local).
        } elseif ($ville === '') {
            // Exigence du sujet : refus du GPS → saisie manuelle obligatoire.
            $v->add('ville', 'La localisation manuelle (ville ou quartier) est obligatoire pour le matching.');
        }

        if ($v->fails()) {
            Flash::set('error', 'Localisation invalide.');
            return $response->withHeader('Location', '/profile')->withStatus(302);
        }

        $this->db->update('users', [
            'ville' => $ville === '' ? null : $ville,
            'lat' => $lat,
            'lng' => $lng,
            'gps_consent' => $gpsConsent ? 1 : 0,
        ], 'id = ?', [$userId]);

        $this->refreshSession($userId);
        Flash::set('success', $gpsConsent ? 'Position GPS enregistrée.' : 'Localisation manuelle enregistrée.');
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Photos
    // -------------------------------------------------------------

    public function uploadPhoto(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $files = $request->getUploadedFiles();
        $errors = $this->photos->upload($userId, $files['photo'] ?? null);

        if ($errors !== []) {
            Flash::set('error', $errors[0]);
        } else {
            Flash::set('success', 'Photo ajoutée.');
        }
        $this->refreshSession($userId);
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function setProfilePhoto(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $this->photos->setProfile($userId, (int) ($args['id'] ?? 0));
        $this->refreshSession($userId);
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function deletePhoto(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $this->photos->delete($userId, (int) ($args['id'] ?? 0));
        $this->refreshSession($userId);
        Flash::set('success', 'Photo supprimée.');
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Bonus galerie : édition d'image de base (GD)
    // -------------------------------------------------------------

    public function rotatePhoto(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $degrees = (int) (($request->getParsedBody() ?? [])['degrees'] ?? 90);
        $ok = $this->photos->rotate($userId, (int) ($args['id'] ?? 0), $degrees);
        Flash::set($ok ? 'success' : 'error', $ok ? 'Photo pivotée.' : 'Rotation impossible sur cette photo.');
        $this->refreshSession($userId);
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function filterPhoto(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $filter = (string) (($request->getParsedBody() ?? [])['filter'] ?? '');
        $ok = $this->photos->applyFilter($userId, (int) ($args['id'] ?? 0), $filter);
        Flash::set($ok ? 'success' : 'error', $ok ? 'Filtre appliqué.' : 'Filtre inconnu.');
        $this->refreshSession($userId);
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function cropPhoto(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $data = (array) $request->getParsedBody();
        $ok = $this->photos->crop(
            $userId,
            (int) ($args['id'] ?? 0),
            (int) ($data['x'] ?? 0),
            (int) ($data['y'] ?? 0),
            (int) ($data['width'] ?? 0),
            (int) ($data['height'] ?? 0)
        );
        Flash::set($ok ? 'success' : 'error', $ok ? 'Photo recadrée.' : 'Recadrage impossible.');
        $this->refreshSession($userId);
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Tags réutilisables
    // -------------------------------------------------------------

    public function addTag(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $data = (array) $request->getParsedBody();

        // Normalisation : minuscules, sans « # », espaces → tirets.
        $name = mb_strtolower(trim((string) ($data['tag'] ?? '')));
        $name = str_replace('#', '', $name);
        $name = preg_replace('/\s+/', '-', $name) ?? '';

        if (preg_match('/^[a-z0-9_-]{1,30}$/', $name) !== 1) {
            Flash::set('error', 'Tag invalide (1 à 30 caractères : lettres, chiffres, _ et -).');
            return $response->withHeader('Location', '/profile')->withStatus(302);
        }

        $count = (int) $this->db->value('SELECT COUNT(*) FROM user_tags WHERE user_id = ?', [$userId]);
        if ($count >= 20) {
            Flash::set('error', 'Nombre maximal de tags atteint (20).');
            return $response->withHeader('Location', '/profile')->withStatus(302);
        }

        // Tags réutilisables : on récupère l'existant ou on le crée.
        $tagId = $this->db->value('SELECT id FROM tags WHERE name = ?', [$name]);
        if ($tagId === null) {
            $this->db->insert('tags', ['name' => $name]);
            $tagId = $this->db->lastInsertId();
        }

        $this->db->run('INSERT IGNORE INTO user_tags (user_id, tag_id) VALUES (?, ?)', [$userId, $tagId]);
        Flash::set('success', 'Tag « #' . $name . ' » ajouté.');
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function removeTag(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $this->db->delete('user_tags', 'user_id = ? AND tag_id = ?', [$userId, (int) ($args['id'] ?? 0)]);
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    /** Autocomplétion des tags existants (AJAX, JSON). */
    public function apiTags(Request $request, Response $response): Response
    {
        $q = mb_strtolower(trim((string) $request->getQueryParams()['q'] ?? ''));
        $q = substr($q, 0, 30);

        if ($q === '') {
            $tags = $this->db->fetchAll('SELECT name FROM tags ORDER BY name ASC LIMIT 10');
        } else {
            $tags = $this->db->fetchAll('SELECT name FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT 10', [$q . '%']);
        }

        $names = array_column($tags, 'name');
        $response->getBody()->write(json_encode($names, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    // -------------------------------------------------------------
    // Qui m'a consulté / qui m'a liké
    // -------------------------------------------------------------

    public function visits(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $visitors = $this->db->fetchAll(
            'SELECT u.id, u.username, u.prenom, u.ville, u.note_popularite, u.birthdate,
                    v.viewed_at, p.path AS photo
             FROM visits v
             JOIN users u ON u.id = v.visitor_id
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE v.visited_id = ?
             ORDER BY v.viewed_at DESC',
            [$userId]
        );
        return $this->twig->render($response, 'profile/visits.html.twig', ['visitors' => $this->decorate($visitors)]);
    }

    public function likes(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $likers = $this->db->fetchAll(
            'SELECT u.id, u.username, u.prenom, u.ville, u.note_popularite, u.birthdate,
                    l.created_at, p.path AS photo
             FROM likes l
             JOIN users u ON u.id = l.from_user_id
             LEFT JOIN photos p ON p.user_id = u.id AND p.is_profile = 1
             WHERE l.to_user_id = ?
             ORDER BY l.created_at DESC',
            [$userId]
        );
        return $this->twig->render($response, 'profile/likes.html.twig', ['likers' => $this->decorate($likers)]);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    /** Données partagées de la page profil. */
    private function profileViewData(array $user, array $errors = []): array
    {
        $userId = (int) $user['id'];
        $photos = $this->db->fetchAll(
            'SELECT * FROM photos WHERE user_id = ? ORDER BY position ASC',
            [$userId]
        );
        $tags = $this->db->fetchAll(
            'SELECT t.id, t.name FROM tags t
             JOIN user_tags ut ON ut.tag_id = t.id
             WHERE ut.user_id = ? ORDER BY t.name ASC',
            [$userId]
        );

        return [
            'user' => $this->decorate([$user])[0],
            'photos' => $photos,
            'tags' => $tags,
            'popularity' => $this->popularity->score($userId),
            'errors' => $errors,
            'gps_available' => true,
        ];
    }

    /** Enrichit des lignes utilisateur : âge, avatar, note formatée. */
    private function decorate(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['age'] = $this->ageOf((string) ($row['birthdate'] ?? ''));
            $row['popularity_display'] = number_format((float) ($row['note_popularite'] ?? 0), 1, ',', ' ');
            $row['avatar'] = $row['photo'] ?? null;
            unset($row['birthdate'], $row['photo']);
        }
        return $rows;
    }

    private function ageOf(string $birthdate): ?int
    {
        if ($birthdate === '' || $birthdate === null) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        return $dt === false ? null : (int) $dt->diff(new \DateTimeImmutable('now'))->y;
    }

    /** Après toute modification, la session reflète le profil à jour. */
    private function refreshSession(int $userId): void
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user !== null) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
        }
    }
}
