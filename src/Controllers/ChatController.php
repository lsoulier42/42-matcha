<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entity\Message;
use App\Services\MessageService;
use App\Support\Flash;
use App\Support\Http;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Real-time chat (section 3.6): restricted to matched users
 * (mutual like), messages received ≤ 10 s via 5 s AJAX polling,
 * global unread badge on all pages.
 */
final class ChatController
{
    public function __construct(
        private Twig $twig,
        private MessageService $messages
    ) {
    }

    /** Conversation list (active matches). */
    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        return $this->twig->render($response, 'messages/index.html.twig', [
            'conversations' => $this->messages->conversations($userId),
        ]);
    }

    /** Thread view with a match. */
    public function show(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $otherId = (int) ($args['id'] ?? 0);

        if ($otherId === $userId || !$this->messages->canChat($userId, $otherId)) {
            return $this->twig->render($response, 'messages/blocked.html.twig');
        }

        $this->messages->markRead($userId, $otherId);

        $other = $this->messages->userInfo($otherId);
        $history = $this->messages->history($userId, $otherId);

        return $this->twig->render($response, 'messages/show.html.twig', [
            'other' => $other,
            'other_id' => $otherId,
            'history' => $history,
            'last_id' => count($history) > 0 ? $history[count($history) - 1]->id : 0,
        ]);
    }

    /** Send a message (classic form submission → flash + redirect). */
    public function send(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $otherId = (int) ($args['id'] ?? 0);
        $data = (array) $request->getParsedBody();
        $content = trim((string) ($data['content'] ?? ''));

        $id = $this->messages->send($userId, $otherId, $content);

        if ($id === null) {
            Flash::set('error', 'Message non envoyé : la conversation n\'est plus active.');
        }
        return Http::redirect($response, '/messages/' . $otherId);
    }

    /** AJAX: send a message (returns JSON). */
    public function apiSend(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $otherId = (int) ($args['id'] ?? 0);
        $data = (array) $request->getParsedBody();
        $content = trim((string) ($data['content'] ?? ''));

        $id = $this->messages->send($userId, $otherId, $content);

        return Http::json($response, [
            'ok' => $id !== null,
            'message_id' => $id,
            'error' => $id === null ? 'Message refusé (match requis, aucun blocage, contenu valide).' : null,
        ]);
    }

    /** AJAX polling: new messages since id $after (chat open). */
    public function apiHistory(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $otherId = (int) ($args['id'] ?? 0);
        $after = isset($request->getQueryParams()['after']) ? (int) $request->getQueryParams()['after'] : 0;

        if (!$this->messages->canChat($userId, $otherId)) {
            return Http::json($response, ['ok' => false, 'error' => 'conversation_indisponible']);
        }

        $history = $this->messages->history($userId, $otherId, $after > 0 ? $after : null);
        $payload = array_map(static fn (Message $message): array => $message->toApiArray(), $history);

        return Http::json($response, $payload);
    }
}
