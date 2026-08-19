<?php

declare(strict_types=1);

namespace App\ViewModel;

/**
 * Displayed notification (French label, date, actor).
 */
final readonly class NotificationItem
{
    /** French labels by event type. */
    public const LABELS = [
        'like' => 'vous a liké',
        'visit' => 'a consulté votre profil',
        'message' => 'vous a envoyé un message',
        'match' => 'vous a liké en retour — c\'est un match !',
        'unlike' => 'a retiré son like',
    ];

    public function __construct(
        public int $id,
        public string $type,
        public string $label,
        public string $createdLabel,
        public ?int $actorId,
        public ?string $actorPrenom,
        public ?string $actorUsername,
        public ?string $avatar,
        public bool $read,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            type: (string) $row['type'],
            label: self::LABELS[$row['type']] ?? 'nouvelle activité',
            createdLabel: date('d/m/Y à H:i', strtotime((string) $row['created_at']) ?: time()),
            actorId: isset($row['actor_id']) ? (int) $row['actor_id'] : null,
            actorPrenom: $row['prenom'] ?? null,
            actorUsername: $row['username'] ?? null,
            avatar: $row['avatar'] ?? null,
            read: $row['read_at'] !== null,
        );
    }
}
