<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\AppointmentRepository;
use App\Services\MessageService;
use App\Support\Flash;
use App\Validation\AppointmentValidator;
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
        private AppointmentRepository $appointments,
        private MessageService $messages,
        private AppointmentValidator $validator
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];

        return $this->twig->render($response, 'appointments/index.html.twig', [
            'appointments' => $this->appointments->listFor($userId),
            'matches' => $this->messages->conversations($userId),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user_id'];
        $data = (array) $request->getParsedBody();

        $errors = $this->validator->validate($this->messages, $data, $userId);
        if ($errors !== []) {
            Flash::set('error', 'Rendez-vous non créé : ' . implode(' ', $errors));
            return $response->withHeader('Location', '/appointments')->withStatus(302);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $start = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim((string) ($data['start_at'] ?? '')));

        $this->appointments->create([
            'user1_id' => $userId,
            'user2_id' => (int) ($data['user_id'] ?? 0),
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
        $this->appointments->delete((int) ($args['id'] ?? 0), $userId);
        Flash::set('success', 'Rendez-vous supprimé.');
        return $response->withHeader('Location', '/appointments')->withStatus(302);
    }
}
