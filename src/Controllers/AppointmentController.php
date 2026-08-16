<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Db\Query;
use App\Services\MessageService;
use App\Support\Flash;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Bonus : planification de rendez-vous / événements réels
 * entre utilisateurs connectés (like mutuel, sans blocage).
 */
final class AppointmentController
{
    public function __construct(
        private Twig $twig,
        private Query $db,
        private MessageService $messages
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];

        $appointments = $this->db->fetchAll(
            'SELECT a.id, a.title, a.description, a.location, a.start_at, a.user1_id, a.user2_id,
                    CASE WHEN a.user1_id = ? THEN u2.id ELSE u1.id END AS other_id,
                    CASE WHEN a.user1_id = ? THEN u2.prenom ELSE u1.prenom END AS other_prenom,
                    CASE WHEN a.user1_id = ? THEN u2.username ELSE u1.username END AS other_username
             FROM appointments a
             JOIN users u1 ON u1.id = a.user1_id
             JOIN users u2 ON u2.id = a.user2_id
             WHERE a.user1_id = ? OR a.user2_id = ?
             ORDER BY a.start_at ASC',
            [$userId, $userId, $userId, $userId, $userId]
        );

        foreach ($appointments as &$appointment) {
            $appointment['start_display'] = date('d/m/Y à H:i', strtotime((string) $appointment['start_at']));
            $appointment['is_past'] = strtotime((string) $appointment['start_at']) < time();
        }
        unset($appointment);

        $matches = $this->messages->conversations($userId);

        return $this->twig->render($response, 'appointments/index.html.twig', [
            'appointments' => $appointments,
            'matches' => $matches,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $data = (array) $request->getParsedBody();

        $otherId = (int) ($data['user_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $startAt = trim((string) ($data['start_at'] ?? '')); // datetime-local : Y-m-d\TH:i

        $v = new Validator();
        $v->required('title', $title, 'titre')
            ->length('title', $title, 2, 120, 'Titre')
            ->length('description', $description, 0, 500, 'Description')
            ->length('location', $location, 0, 120, 'Lieu');

        if ($otherId <= 0 || $otherId === $userId || !$this->messages->canChat($userId, $otherId)) {
            $v->add('user_id', 'Choisissez un utilisateur connecté (like mutuel).');
        }

        $start = null;
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

        if ($v->fails()) {
            Flash::set('error', 'Rendez-vous non créé : ' . implode(' ', $v->errors()));
            return $response->withHeader('Location', '/appointments')->withStatus(302);
        }

        $this->db->insert('appointments', [
            'user1_id' => $userId,
            'user2_id' => $otherId,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'location' => $location === '' ? null : $location,
            'start_at' => $start->format('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'Rendez-vous planifié !');
        return $response->withHeader('Location', '/appointments')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);

        $this->db->delete(
            'appointments',
            'id = ? AND (user1_id = ? OR user2_id = ?)',
            [$id, $userId, $userId]
        );
        Flash::set('success', 'Rendez-vous supprimé.');
        return $response->withHeader('Location', '/appointments')->withStatus(302);
    }
}
