<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

/**
 * Planification d'un rendez-vous normalisée (POST /appointments).
 * `startAtRaw` conserve la saisie brute (datetime-local) pour distinguer
 * « champ vide » de « date invalide » à la validation ; `startAt` est la
 * valeur déjà parsée, garantie non-null après validation (toRecord est
 * appelé uniquement sur un DTO validé).
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

    /** Ligne à insérer dans la table appointments ($user1Id vient de la session). */
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
