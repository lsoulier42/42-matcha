<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\BlockRepository;
use App\Repository\LikeRepository;
use App\Repository\MessageRepository;

/**
 * Chat réservé aux utilisateurs « connectés » (like mutuel, section 3.6).
 * Un unlike ou un blocage coupe réellement le chat côté serveur :
 * l'envoi est refusé sans like mutuel et sans blocage.
 */
final class MessageService
{
    public function __construct(
        private MessageRepository $messages,
        private LikeRepository $likes,
        private BlockRepository $blocks,
        private NotificationService $notifications
    ) {
    }

    /** Le chat est-il autorisé entre a et b ? (like mutuel + aucun blocage) */
    public function canChat(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }
        if ($this->blocks->isBlocked($a, $b)) {
            return false;
        }
        return $this->likes->exists($a, $b) && $this->likes->exists($b, $a);
    }

    /** Liste des conversations (matchs) avec dernier message et non-lus. */
    public function conversations(int $userId): array
    {
        return $this->messages->conversations($userId);
    }

    /** Historique du fil (optionnellement après un id, pour le polling). */
    public function history(int $userId, int $otherId, ?int $afterId = null): array
    {
        return $this->messages->history($userId, $otherId, $afterId);
    }

    /** Infos publiques d'un utilisateur (pour l'en-tête de discussion). */
    public function userInfo(int $userId): ?array
    {
        return $this->messages->userInfo($userId);
    }

    /**
     * Envoie un message. Retourne l'id créé, ou null si refusé
     * (pas de like mutuel, blocage, contenu invalide).
     */
    public function send(int $from, int $to, string $content): ?int
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 2000) {
            return null;
        }
        if (!$this->canChat($from, $to)) {
            return null;
        }

        $id = $this->messages->send($from, $to, $content);
        $this->notifications->notify($to, 'message', $from);
        return $id;
    }

    /** Marque comme lus les messages reçus de $otherId. */
    public function markRead(int $userId, int $otherId): void
    {
        $this->messages->markRead($userId, $otherId);
    }

    /** Nombre total de messages non lus. */
    public function unreadCount(int $userId): int
    {
        return $this->messages->unreadCount($userId);
    }
}
