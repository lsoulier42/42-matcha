<?php

declare(strict_types=1);

namespace App\Validation;

use App\Services\MessageService;

/**
 * Règles de planification d'un rendez-vous (bonus) : titre, date dans
 * le futur, destinataire connecté (like mutuel, sans blocage).
 *
 * @return array<string, string> erreurs (champ => message), vide si valide
 */
final class AppointmentValidator
{
    public function validate(MessageService $messages, array $data, int $userId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $startAt = trim((string) ($data['start_at'] ?? '')); // datetime-local : Y-m-d\TH:i
        $otherId = (int) ($data['user_id'] ?? 0);

        $v = new Validator();
        $v->required('title', $title, 'titre')
            ->length('title', $title, 2, 120, 'Titre')
            ->length('description', $description, 0, 500, 'Description')
            ->length('location', $location, 0, 120, 'Lieu');

        if ($otherId <= 0 || $otherId === $userId || !$messages->canChat($userId, $otherId)) {
            $v->add('user_id', 'Choisissez un utilisateur connecté (like mutuel).');
        }

        if ($startAt === '') {
            $v->add('start_at', 'La date et l\'heure du rendez-vous sont obligatoires.');
        } else {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startAt);
            if ($start === false) {
                $v->add('start_at', 'Date invalide.');
            } elseif ($start < new \DateTimeImmutable('now')) {
                $v->add('start_at', 'Le rendez-vous doit être dans le futur.');
            }
        }

        return $v->errors();
    }
}
