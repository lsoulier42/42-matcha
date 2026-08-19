<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

/**
 * Normalised appointment scheduling data (POST /appointments).
 * `startAtRaw` keeps the raw input (datetime-local) so that "empty field"
 * can be distinguished from "invalid date" during validation; `startAt`
 * is the parsed value, guaranteed non-null after validation (toRecord is
 * only called on a validated DTO).
 */
final readonly class AppointmentData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $location,
        public string $startAtRaw,
        public ?DateTimeImmutable $startAt,
        public int $userId,
    ) {
    }

    public static function fromRequest(array $body): self
    {
        $description = trim((string) ($body['description'] ?? ''));
        $location = trim((string) ($body['location'] ?? ''));
        $startAtRaw = trim((string) ($body['start_at'] ?? ''));
        $startAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startAtRaw);

        return new self(
            title: trim((string) ($body['title'] ?? '')),
            description: $description === '' ? null : $description,
            location: $location === '' ? null : $location,
            startAtRaw: $startAtRaw,
            startAt: $startAt === false ? null : $startAt,
            userId: (int) ($body['user_id'] ?? 0),
        );
    }

    /** Row to insert into the appointments table ($user1Id comes from the session). */
    public function toRecord(int $user1Id): array
    {
        return [
            'user1_id' => $user1Id,
            'user2_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_at' => $this->startAt->format('Y-m-d H:i:s'),
        ];
    }
}
