<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Db\Query;
use App\Services\MatchingService;
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
        private Query $db,
        private MatchingService $matching
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];

        $profiles = $this->matching->suggest($userId, [], 'score');
        $ids = array_column($profiles, 'id');

        $markers = [];
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id, prenom, lat, lng, note_popularite FROM users
                 WHERE id IN ($placeholders) AND lat IS NOT NULL AND lng IS NOT NULL",
                $ids
            );
            $byId = array_column($rows, null, 'id');
            foreach ($profiles as $profile) {
                if (isset($byId[$profile['id']])) {
                    $markers[] = [
                        'id' => (int) $profile['id'],
                        'prenom' => $profile['prenom'],
                        'lat' => (float) $byId[$profile['id']]['lat'],
                        'lng' => (float) $byId[$profile['id']]['lng'],
                        'popularity_display' => $profile['popularity_display'],
                        'ville' => $profile['ville'],
                    ];
                }
            }
        }

        $me = $this->db->fetch('SELECT id, prenom, lat, lng FROM users WHERE id = ?', [$userId]);

        return $this->twig->render($response, 'map/index.html.twig', [
            'markers' => $markers,
            'me' => [
                'prenom' => $me['prenom'] ?? '',
                'lat' => $me['lat'] !== null ? (float) $me['lat'] : null,
                'lng' => $me['lng'] !== null ? (float) $me['lng'] : null,
            ],
        ]);
    }
}
