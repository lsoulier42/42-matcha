<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dto\LocationUpdateData;
use App\Dto\ProfileUpdateData;
use App\Dto\TagData;
use App\Entity\User;
use App\Repository\LikeRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Repository\VisitRepository;
use App\Services\PhotoService;
use App\Services\PopularityService;
use App\Support\Flash;
use App\Validation\LocationValidator;
use App\Validation\ProfileValidator;
use App\Validation\TagValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Profil utilisateur (section 3.2 du sujet) : genre, préférences, bio,
 * tags réutilisables, photos (max 5), note de popularité, localisation
 * (GPS avec consentement explicite ou saisie manuelle), qui m'a consulté,
 * qui m'a liké. Les règles de saisie vivent dans les validateurs, le SQL
 * dans les repositories.
 */
final class ProfileController
{
    public function __construct(
        private Twig $twig,
        private UserRepository $users,
        private TagRepository $tags,
        private LikeRepository $likes,
        private VisitRepository $visits,
        private PhotoService $photos,
        private PopularityService $popularity,
        private ProfileValidator $profileValidator,
        private LocationValidator $locationValidator,
        private TagValidator $tagValidator
    ) {
    }

    // -------------------------------------------------------------
    // Affichage / édition
    // -------------------------------------------------------------

    public function show(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $user = $this->users->findById($userId);

        return $this->twig->render($response, 'profile/show.html.twig', $this->profileViewData($user));
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $user = $this->users->findById($userId);
        $data = ProfileUpdateData::fromRequest((array) $request->getParsedBody());

        $errors = $this->profileValidator->validate($this->users, $data, $userId);
        if ($errors !== []) {
            Flash::set('error', 'Certains champs sont invalides.');
            return $this->twig->render($response, 'profile/show.html.twig', $this->profileViewData($user, $errors));
        }

        $this->users->update($userId, $data->toRecord());

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
        $data = LocationUpdateData::fromRequest((array) $request->getParsedBody());

        $errors = $this->locationValidator->validate($data);
        if ($errors !== []) {
            Flash::set('error', 'Localisation invalide.');
            return $response->withHeader('Location', '/profile')->withStatus(302);
        }

        $this->users->update($userId, $data->toRecord());

        $this->refreshSession($userId);
        Flash::set('success', $data->gpsConsent ? 'Position GPS enregistrée.' : 'Localisation manuelle enregistrée.');
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
        $data = TagData::fromRequest((array) $request->getParsedBody());

        $errors = $this->tagValidator->validate($this->tags, $data->name, $userId);
        if ($errors !== []) {
            Flash::set('error', reset($errors));
            return $response->withHeader('Location', '/profile')->withStatus(302);
        }

        // Tags réutilisables : on récupère l'existant ou on le crée.
        $tagId = $this->tags->findIdByName($data->name);
        if ($tagId === null) {
            $tagId = $this->tags->create($data->name);
        }
        $this->tags->attach($userId, $tagId);

        Flash::set('success', 'Tag « #' . $data->name . ' » ajouté.');
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    public function removeTag(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $this->tags->detach($userId, (int) ($args['id'] ?? 0));
        return $response->withHeader('Location', '/profile')->withStatus(302);
    }

    /** Autocomplétion des tags existants (AJAX, JSON). */
    public function apiTags(Request $request, Response $response): Response
    {
        $q = mb_strtolower(trim((string) $request->getQueryParams()['q'] ?? ''));
        $q = substr($q, 0, 30);

        $response->getBody()->write(json_encode($this->tags->search($q), JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    // -------------------------------------------------------------
    // Qui m'a consulté / qui m'a liké
    // -------------------------------------------------------------

    public function visits(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        return $this->twig->render($response, 'profile/visits.html.twig', [
            'visitors' => $this->visits->listVisitors($userId),
        ]);
    }

    public function likes(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        return $this->twig->render($response, 'profile/likes.html.twig', [
            'likers' => $this->likes->listLikers($userId),
        ]);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    /** Données partagées de la page profil (formulaire + galerie). */
    private function profileViewData(User $user, array $errors = []): array
    {
        $userId = $user->id;

        return [
            'user' => $user->withoutPassword(),
            'photos' => $this->photos->listByUser($userId),
            'tags' => $this->tags->listByUser($userId),
            'popularity' => $this->popularity->score($userId),
            'errors' => $errors,
        ];
    }

    /** Après toute modification, la session reflète le profil à jour. */
    private function refreshSession(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user !== null) {
            $_SESSION['user'] = $user->withoutPassword();
        }
    }
}
