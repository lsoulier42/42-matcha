<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\TagRepository;
use App\Services\MatchingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Suggested profiles navigation (section 3.3): smart suggestions,
 * sortable and filterable list.
 */
final class SuggestController
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

        $filters = $this->filters($params);

        return $this->twig->render($response, 'suggestions/index.html.twig', [
            'profiles' => $this->matching->suggest($userId, $filters, $sort),
            'sort' => $sort,
            'filters' => $filters,
            'all_tags' => $this->tags->all(),
            'me' => $_SESSION['user'],
        ]);
    }

    /** Accepted filters: age_min, age_max, popularity_min, ville, tags[]. */
    private function filters(array $params): array
    {
        return [
            'age_min' => trim((string) ($params['age_min'] ?? '')),
            'age_max' => trim((string) ($params['age_max'] ?? '')),
            'popularity_min' => trim((string) ($params['popularity_min'] ?? '')),
            'ville' => trim((string) ($params['ville'] ?? '')),
            'tags' => (array) ($params['tags'] ?? []),
        ];
    }
}
