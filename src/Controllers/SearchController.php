<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\TagRepository;
use App\Services\MatchingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Recherche avancée (section 3.4) : un ou plusieurs critères combinés
 * (tranche d'âge, plage de popularité, localisation, tags), résultats
 * triables et filtrables.
 */
final class SearchController
{
    private const SORTS = ['score', 'age', 'location', 'popularity', 'tags'];

    public function __construct(
        private Twig $twig,
        private TagRepository $tags,
        private MatchingService $matching
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $params = $request->getQueryParams();

        $sort = (string) ($params['sort'] ?? 'score');
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'score';
        }

        $filters = [
            'age_min' => trim((string) ($params['age_min'] ?? '')),
            'age_max' => trim((string) ($params['age_max'] ?? '')),
            'popularity_min' => trim((string) ($params['popularity_min'] ?? '')),
            'ville' => trim((string) ($params['ville'] ?? '')),
            'tags' => (array) ($params['tags'] ?? []),
        ];

        $searched = false;
        foreach ($filters as $value) {
            if (is_array($value) ? $value !== [] : $value !== '') {
                $searched = true;
                break;
            }
        }

        return $this->twig->render($response, 'search/index.html.twig', [
            'profiles' => $searched ? $this->matching->suggest($userId, $filters, $sort) : [],
            'sort' => $sort,
            'filters' => $filters,
            'searched' => $searched,
            'all_tags' => $this->tags->all(),
        ]);
    }
}
