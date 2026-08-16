<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Message du chat. toApiArray() fournit le format JSON du polling
 * (clés identiques à celles attendues par le client).
 */
final readonly class Message
{
    public function __construct(
        public int $id,
        public int $fromUserId,
        public string $content,
        public string $sentAt,
        public int $ts,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            fromUserId: (int) $row['from_user_id'],
            content: (string) $row['content'],
            sentAt: (string) $row['sent_at'],
            ts: strtotime((string) $row['sent_at']) ?: 0,
        );
    }

    /** Format JSON du polling AJAX (inchangé pour le client). */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'from_user_id' => $this->fromUserId,
            'content' => $this->content,
            'sent_at' => $this->sentAt,
            'ts' => $this->ts,
        ];
    }
}
