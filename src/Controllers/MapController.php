<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\UserRepository;
use App\Services\MatchingService;
use App\ViewModel\MapView;
use App\ViewModel\ProfileCard;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Bonus : carte interactive des utilisateurs (Leaflet + tuiles OSM).
 * Affiche les profils suggérés (même algorithme que /suggestions)
 * ayant une position GPS connue, plus la position de l'utilisateur.
 */
final class MapController
{
    public function __construct(
        private Twig $twig,
        private UserRepository $users,
        private MatchingService $matching
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];

        $profiles = $this->matching->suggest($userId, [], 'score');
        $ids = array_map(static fn (ProfileCard $card): int => $card->id, $profiles);
        $markers = $this->users->findPositionsByIds($ids);

        return $this->twig->render($response, 'map/index.html.twig', [
            'map' => new MapView($this->users->findWithPosition($userId), $markers),
        ]);
    }
}
