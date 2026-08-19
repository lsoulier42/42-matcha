<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\BlockRepository;
use App\Repository\LikeRepository;
use App\Repository\MessageRepository;
use App\ViewModel\UserProfile;

/**
 * Chat restricted to "connected" users (mutual like, section 3.6).
 * An unlike or block genuinely cuts the chat server-side:
 * sending is refused without a mutual like and without a block.
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

    /** Is chat allowed between a and b? (mutual like + no block) */
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

    /** Conversation list (matches) with last message and unread count. */
    public function conversations(int $userId): array
    {
        return $this->messages->conversations($userId);
    }

    /** Thread history (optionally after a given id, for polling). */
    public function history(int $userId, int $otherId, ?int $afterId = null): array
    {
        return $this->messages->history($userId, $otherId, $afterId);
    }

    /** Public info for a user (for the conversation header). */
    public function userInfo(int $userId): ?UserProfile
    {
        return $this->messages->userInfo($userId);
    }

    /**
     * Sends a message. Returns the created id, or null if rejected
     * (no mutual like, blocked, invalid content).
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

    /** Marks messages received from $otherId as read. */
    public function markRead(int $userId, int $otherId): void
    {
        $this->messages->markRead($userId, $otherId);
    }

    /** Total unread message count. */
    public function unreadCount(int $userId): int
    {
        return $this->messages->unreadCount($userId);
    }
}
