<?php

declare(strict_types=1);

namespace App\Validation;

use App\Dto\AppointmentData;
use App\Services\MessageService;

/**
 * Appointment scheduling rules (bonus): title, future date,
 * matched recipient (mutual like, no block).
 *
 * @return array<string, string> errors (field => message), empty if valid
 */
final class AppointmentValidator
{
    public function validate(MessageService $messages, AppointmentData $data, int $userId): array
    {
        $title = $data->title;
        $description = $data->description;
        $location = $data->location;
        $startAtRaw = $data->startAtRaw;
        $startAt = $data->startAt;
        $otherId = $data->userId;

        $v = new Validator();
        $v->required('title', $title, 'titre')
            ->length('title', $title, 2, 120, 'Titre')
            ->length('description', $description, 0, 500, 'Description')
            ->length('location', $location, 0, 120, 'Lieu');

        if ($otherId <= 0 || $otherId === $userId || !$messages->canChat($userId, $otherId)) {
            $v->add('user_id', 'Choisissez un utilisateur connecté (like mutuel).');
        }

        if ($startAtRaw === '') {
            $v->add('start_at', 'La date et l\'heure du rendez-vous sont obligatoires.');
        } elseif ($startAt === null) {
            $v->add('start_at', 'Date invalide.');
        } elseif ($startAt < new \DateTimeImmutable('now')) {
            $v->add('start_at', 'Le rendez-vous doit être dans le futur.');
        }

        return $v->errors();
    }
}
