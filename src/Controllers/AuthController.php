<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dto\RegisterData;
use App\Security\AuthService;
use App\Support\Flash;
use App\Validation\RegisterValidator;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Inscription, vérification d'e-mail, connexion, mot de passe oublié,
 * réinitialisation et déconnexion.
 *
 * Ce contrôleur est un adaptateur HTTP fin : il extrait les données
 * de la requête, délègue la logique métier à AuthService et construit
 * la réponse (rendu Twig ou redirection).
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
    // Inscription
    // -------------------------------------------------------------

    public function showRegister(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        return $this->twig->render($response, 'auth/register.html.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
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

        return $response->withHeader('Location', '/auth/login')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Vérification de l'e-mail
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
    // Connexion / déconnexion
    // -------------------------------------------------------------

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        return $this->twig->render($response, 'auth/login.html.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
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
        return $this->redirectToSuggestions($response);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->destroySession();
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Mot de passe oublié / réinitialisation
    // -------------------------------------------------------------

    public function showForgot(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        return $this->twig->render($response, 'auth/forgot.html.twig');
    }

    public function forgot(Request $request, Response $response): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }

        $data = (array) $request->getParsedBody();
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->auth->requestPasswordReset($email);
        }

        // Toujours le même message : pas d'énumération d'utilisateurs.
        Flash::set('success', 'Si un compte existe avec cette adresse e-mail, un lien de réinitialisation vient d\'être envoyé.');
        return $response->withHeader('Location', '/auth/forgot')->withStatus(302);
    }

    public function showReset(Request $request, Response $response, array $args): Response
    {
        if ($this->auth->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
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
            return $this->redirectToSuggestions($response);
        }

        $token = (string) ($args['token'] ?? '');
        $data = (array) $request->getParsedBody();
        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirm'] ?? '');

        $result = $this->auth->resetPassword($token, $password, $passwordConfirm);

        if ($result['ok']) {
            Flash::set('success', 'Mot de passe réinitialisé, vous pouvez vous connecter.');
            return $response->withHeader('Location', '/auth/login')->withStatus(302);
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

    private function redirectToSuggestions(Response $response): Response
    {
        return $response->withHeader('Location', '/suggestions')->withStatus(302);
    }

    /** Ne jamais réafficher le mot de passe dans le formulaire. */
    private function cleanOld(array $data): array
    {
        unset($data['password'], $data['password_confirm']);
        return $data;
    }
}
