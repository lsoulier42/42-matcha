<?php

declare(strict_types=1);

namespace App\ViewModel;

/**
 * Conversation (active match) with last message and unread count.
 */
final readonly class Conversation
{
    public function __construct(
        public int $id,
        public string $prenom,
        public ?string $ville,
        public ?string $avatar,
        public ?string $lastMessage,
        public string $lastMessageAtLabel,
        public int $unread,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            prenom: (string) $row['prenom'],
            ville: $row['ville'] ?? null,
            avatar: $row['avatar'] ?? null,
            lastMessage: $row['last_message'] ?? null,
            lastMessageAtLabel: self::relativeTime((string) ($row['last_message_at'] ?? '')),
            unread: (int) ($row['unread'] ?? 0),
        );
    }

    private static function relativeTime(string $datetime): string
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
