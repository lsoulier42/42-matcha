<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Chat temps réel (section 3.6) : réservé aux utilisateurs connectés
 * (like mutuel), messages reçus ≤ 10 s via polling AJAX 5 s, badge
 * global de nouveaux messages sur toutes les pages.
 */
final class ChatController
{
    public function __construct(
        private Twig $twig,
        private MessageService $messages
    ) {
    }

    /** Liste des conversations (matchs actifs). */
    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $conversations = $this->messages->conversations($userId);

        foreach ($conversations as &$conv) {
            $conv['last_message_at_display'] = $this->relativeTime((string) ($conv['last_message_at'] ?? ''));
        }
        unset($conv);

        return $this->twig->render($response, 'messages/index.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    /** Fil de discussion avec un match. */
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $otherId = (int) ($args['id'] ?? 0);

        if ($otherId === $userId || !$this->messages->canChat($userId, $otherId)) {
            return $this->twig->render($response, 'messages/blocked.html.twig');
        }

        $this->messages->markRead($userId, $otherId);

        $other = $this->messages->userInfo($otherId) ?? ['prenom' => '', 'username' => ''];
        $history = $this->messages->history($userId, $otherId);

        return $this->twig->render($response, 'messages/show.html.twig', [
            'other' => $other,
            'other_id' => $otherId,
            'history' => $history,
            'last_id' => count($history) > 0 ? (int) $history[count($history) - 1]['id'] : 0,
        ]);
    }

    /** Envoi d'un message (formulaire classique ou AJAX). */
    public function send(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $otherId = (int) ($args['id'] ?? 0);
        $data = (array) $request->getParsedBody();
        $content = trim((string) ($data['content'] ?? ''));

        $id = $this->messages->send($userId, $otherId, $content);

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            $response->getBody()->write(json_encode([
                'ok' => $id !== null,
                'message_id' => $id,
                'error' => $id === null ? 'Message refusé (match requis, aucun blocage, contenu valide).' : null,
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        if ($id === null) {
            Flash::set('error', 'Message non envoyé : la conversation n\'est plus active.');
        }
        return $response->withHeader('Location', '/messages/' . $otherId)->withStatus(302);
    }

    /** Polling AJAX : nouveaux messages depuis l'id $after (chat ouvert). */
    public function apiHistory(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $otherId = (int) ($args['id'] ?? 0);
        $after = isset($request->getQueryParams()['after']) ? (int) $request->getQueryParams()['after'] : 0;

        if (!$this->messages->canChat($userId, $otherId)) {
            $response->getBody()->write(json_encode(['ok' => false, 'error' => 'conversation_indisponible']));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $history = $this->messages->history($userId, $otherId, $after > 0 ? $after : null);
        foreach ($history as &$msg) {
            $msg['ts'] = strtotime((string) $msg['sent_at']);
        }
        unset($msg);
        $response->getBody()->write(json_encode($history, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function relativeTime(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false || $ts <= 0) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'à l\'instant';
        }
        if ($diff < 3600) {
            return 'il y a ' . (int) floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'il y a ' . (int) floor($diff / 3600) . ' h';
        }
        return date('d/m/Y', $ts);
    }
}
