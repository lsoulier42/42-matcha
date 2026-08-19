<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dto\AppointmentData;
use App\Repository\AppointmentRepository;
use App\Services\MessageService;
use App\Support\Flash;
use App\Support\Http;
use App\Validation\AppointmentValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Bonus: real-world event/appointment scheduling
 * between matched users (mutual like, no block).
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
        $userId = $request->getAttribute('user_id');

        return $this->twig->render($response, 'appointments/index.html.twig', [
            'appointments' => $this->appointments->listFor($userId),
            'matches' => $this->messages->conversations($userId),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = AppointmentData::fromRequest((array) $request->getParsedBody());

        $errors = $this->validator->validate($this->messages, $data, $userId);
        if ($errors !== []) {
            Flash::set('error', 'Rendez-vous non créé : ' . implode(' ', $errors));
            return Http::redirect($response, '/appointments');
        }

        $this->appointments->create($data->toRecord($userId));

        Flash::set('success', 'Rendez-vous planifié !');
        return Http::redirect($response, '/appointments');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $this->appointments->delete((int) ($args['id'] ?? 0), $userId);
        Flash::set('success', 'Rendez-vous supprimé.');
        return Http::redirect($response, '/appointments');
    }
}
