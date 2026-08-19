<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dto\RegisterData;
use App\Security\AuthService;
use App\Support\Flash;
use App\Support\Http;
use App\Validation\RegisterValidator;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Registration, email verification, login, forgotten password,
 * password reset, and logout.
 *
 * This controller is a thin HTTP adapter: it extracts request data,
 * delegates business logic to AuthService, and builds the response
 * (Twig render or redirect).
 */
final class AuthController
{
    public function __construct(
        private Twig $twig,
        private UserRepository $users,
        private AuthService $auth,
        private RegisterValidator $registerValidator,
    ) {
    }

    // -------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------

    public function showRegister(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }
        return $this->twig->render($response, 'auth/register.html.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }

        $raw = (array) $request->getParsedBody();
        $data = RegisterData::fromRequest($raw);
        $errors = $this->registerValidator->validate($this->users, $data);

        if ($errors !== []) {
            return $this->twig->render($response, 'auth/register.html.twig', [
                'errors' => $errors,
                'old' => $this->cleanOld($raw),
            ]);
        }

        $result = $this->auth->register($data);

        if ($result['ok']) {
            Flash::set('success', 'Compte créé ! Un e-mail de vérification a été envoyé à ' . $result['email'] . '.');
        } else {
            Flash::set('error', $result['error']);
        }

        return Http::redirect($response, '/auth/login');
    }

    // -------------------------------------------------------------
    // Email verification
    // -------------------------------------------------------------

    public function verify(Request $request, Response $response, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $result = $this->auth->verifyEmail($token);

        return $this->twig->render($response, 'auth/verify.html.twig', [
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    // -------------------------------------------------------------
    // Login / logout
    // -------------------------------------------------------------

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }
        return $this->twig->render($response, 'auth/login.html.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }

        $data = (array) $request->getParsedBody();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $result = $this->auth->authenticate($username, $password);

        if (!$result['ok']) {
            return $this->twig->render($response, 'auth/login.html.twig', [
                'errors' => ['login' => $result['error']],
                'old' => ['username' => $username],
            ]);
        }

        $this->auth->startSession($result['user']);

        Flash::set('success', 'Bonjour ' . $result['user']->prenom . ' !');
        return Http::redirect($response, '/suggestions');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->destroySession();
        return Http::redirect($response, '/');
    }

    // -------------------------------------------------------------
    // Forgotten password / reset
    // -------------------------------------------------------------

    public function showForgot(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }
        return $this->twig->render($response, 'auth/forgot.html.twig');
    }

    public function forgot(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }

        $data = (array) $request->getParsedBody();
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->auth->requestPasswordReset($email);
        }

        // Always the same message: no user enumeration.
        Flash::set('success', 'Si un compte existe avec cette adresse e-mail, un lien de réinitialisation vient d\'être envoyé.');
        return Http::redirect($response, '/auth/forgot');
    }

    public function showReset(Request $request, Response $response, array $args): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }
        $token = (string) ($args['token'] ?? '');
        return $this->twig->render($response, 'auth/reset.html.twig', [
            'token' => $token,
            'token_valid' => $this->auth->validateResetToken($token),
        ]);
    }

    public function reset(Request $request, Response $response, array $args): Response
    {
        if ($this->auth->isLoggedIn()) {
            return Http::redirect($response, '/suggestions');
        }

        $token = (string) ($args['token'] ?? '');
        $data = (array) $request->getParsedBody();
        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirm'] ?? '');

        $result = $this->auth->resetPassword($token, $password, $passwordConfirm);

        if ($result['ok']) {
            Flash::set('success', 'Mot de passe réinitialisé, vous pouvez vous connecter.');
            return Http::redirect($response, '/auth/login');
        }

        return $this->twig->render($response, 'auth/reset.html.twig', [
            'token' => $token,
            'token_valid' => true,
            'errors' => $result['errors'],
        ]);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    /** Never re-populate the password fields in the form. */
    private function cleanOld(array $data): array
    {
        unset($data['password'], $data['password_confirm']);
        return $data;
    }
}
