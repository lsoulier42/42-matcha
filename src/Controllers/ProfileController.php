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
use App\Services\ReverseGeocoder;
use App\Support\Flash;
use App\Support\Http;
use App\Validation\LocationValidator;
use App\Validation\ProfileValidator;
use App\Validation\TagValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * User profile (section 3.2 of the spec): gender, preferences, bio,
 * reusable tags, photos (max 5), popularity score, location
 * (GPS with explicit consent or manual input), who viewed me,
 * who liked me. Input rules live in validators; SQL lives in
 * repositories.
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
        private TagValidator $tagValidator,
        private ReverseGeocoder $geocoder
    ) {
    }

    // -------------------------------------------------------------
    // Display / editing
    // -------------------------------------------------------------

    public function show(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $user = $this->users->findById($userId);

        return $this->twig->render($response, 'profile/show.html.twig', $this->profileViewData($user));
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
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
        return Http::redirect($response, '/profile');
    }

    // -------------------------------------------------------------
    // Location (explicit GPS consent or manual input)
    // -------------------------------------------------------------

    public function updateLocation(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = LocationUpdateData::fromRequest((array) $request->getParsedBody());

        $errors = $this->locationValidator->validate($data);
        if ($errors !== []) {
            Flash::set('error', 'Localisation invalide.');
            return Http::redirect($response, '/profile');
        }

        $record = $data->toRecord();

        // GPS : déduire la ville réelle des coordonnées (géocodage inverse
        // Nominatim/OSM). En cas d'échec (hors-ligne, zone inconnue), on garde
        // la valeur saisie — les coordonnées restent la source de vérité.
        $city = null;
        if ($data->gpsConsent && $data->lat !== null && $data->lng !== null) {
            $city = $this->geocoder->reverse($data->lat, $data->lng);
            if ($city !== null) {
                $record['ville'] = $city;
            }
        }

        $this->users->update($userId, $record);

        $this->refreshSession($userId);
        $flash = $data->gpsConsent
            ? ($city !== null ? "Position GPS enregistrée — $city." : 'Position GPS enregistrée.')
            : 'Localisation manuelle enregistrée.';
        Flash::set('success', $flash);
        return Http::redirect($response, '/profile');
    }

    // -------------------------------------------------------------
    // Photos
    // -------------------------------------------------------------

    public function uploadPhoto(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $files = $request->getUploadedFiles();
        $errors = $this->photos->upload($userId, $files['photo'] ?? null);

        if ($errors !== []) {
            Flash::set('error', $errors[0]);
        } else {
            Flash::set('success', 'Photo ajoutée.');
        }
        $this->refreshSession($userId);
        return Http::redirect($response, '/profile');
    }

    public function setProfilePhoto(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $this->photos->setProfile($userId, (int) ($args['id'] ?? 0));
        $this->refreshSession($userId);
        return Http::redirect($response, '/profile');
    }

    public function deletePhoto(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $this->photos->delete($userId, (int) ($args['id'] ?? 0));
        $this->refreshSession($userId);
        Flash::set('success', 'Photo supprimée.');
        return Http::redirect($response, '/profile');
    }

    // -------------------------------------------------------------
    // Bonus gallery: basic image editing (GD)
    // -------------------------------------------------------------

    public function rotatePhoto(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $degrees = (int) (($request->getParsedBody() ?? [])['degrees'] ?? 90);
        $ok = $this->photos->rotate($userId, (int) ($args['id'] ?? 0), $degrees);
        Flash::set($ok ? 'success' : 'error', $ok ? 'Photo pivotée.' : 'Rotation impossible sur cette photo.');
        $this->refreshSession($userId);
        return Http::redirect($response, '/profile');
    }

    public function filterPhoto(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $filter = (string) (($request->getParsedBody() ?? [])['filter'] ?? '');
        $ok = $this->photos->applyFilter($userId, (int) ($args['id'] ?? 0), $filter);
        Flash::set($ok ? 'success' : 'error', $ok ? 'Filtre appliqué.' : 'Filtre inconnu.');
        $this->refreshSession($userId);
        return Http::redirect($response, '/profile');
    }

    public function cropPhoto(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
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
        return Http::redirect($response, '/profile');
    }

    // -------------------------------------------------------------
    // Reusable tags
    // -------------------------------------------------------------

    public function addTag(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = TagData::fromRequest((array) $request->getParsedBody());

        $errors = $this->tagValidator->validate($this->tags, $data->name, $userId);
        if ($errors !== []) {
            Flash::set('error', reset($errors));
            return Http::redirect($response, '/profile');
        }

        // Reusable tags: reuse existing or create new.
        $tagId = $this->tags->findIdByName($data->name);
        if ($tagId === null) {
            $tagId = $this->tags->create($data->name);
        }
        $this->tags->attach($userId, $tagId);

        Flash::set('success', 'Tag « #' . $data->name . ' » ajouté.');
        return Http::redirect($response, '/profile');
    }

    public function removeTag(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $this->tags->detach($userId, (int) ($args['id'] ?? 0));
        return Http::redirect($response, '/profile');
    }

    /** Autocomplete existing tags (AJAX, JSON). */
    public function apiTags(Request $request, Response $response): Response
    {
        $q = mb_strtolower(trim((string) $request->getQueryParams()['q'] ?? ''));
        $q = substr($q, 0, 30);

        return Http::json($response, $this->tags->search($q));
    }

    // -------------------------------------------------------------
    // Who viewed me / Who liked me
    // -------------------------------------------------------------

    public function visits(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        return $this->twig->render($response, 'profile/visits.html.twig', [
            'visitors' => $this->visits->listVisitors($userId),
        ]);
    }

    public function likes(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        return $this->twig->render($response, 'profile/likes.html.twig', [
            'likers' => $this->likes->listLikers($userId),
        ]);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    /** Shared data for the profile page (form + gallery). */
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

    /** After any edit, the session reflects the up-to-date profile. */
    private function refreshSession(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user !== null) {
            $_SESSION['user'] = $user->withoutPassword();
        }
    }
}
