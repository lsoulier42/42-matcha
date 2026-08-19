<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dto\RegisterData;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;
use App\Services\MailService;
use App\Support\Flash;
use App\Validation\RegisterValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Inscription, vérification d'e-mail, connexion, mot de passe oublié,
 * réinitialisation et déconnexion. Gestionnaire de comptes codé maison
 * (interdit dans le micro-framework) — le SQL vit dans les repositories,
 * les règles de saisie dans RegisterValidator.
 */
final class AuthController
{
    public function __construct(
        private Twig $twig,
        private UserRepository $users,
        private TokenRepository $tokens,
        private MailService $mail,
        private RegisterValidator $registerValidator,
        private string $appUrl
    ) {
    }

    // -------------------------------------------------------------
    // Inscription
    // -------------------------------------------------------------

    public function showRegister(Request $request, Response $response): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        return $this->twig->render($response, 'auth/register.html.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        if ($this->isLoggedIn()) {
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

        $userId = $this->users->create($data->toRecord());
        $token = $this->createToken($userId, 'verify_email');

        $link = $this->appUrl . '/auth/verify/' . $token;
        if (!$this->mail->sendVerification($data->email, $data->username, $link)) {
            Flash::set('error', 'L\'e-mail de vérification n\'a pas pu être envoyé, réessayez dans un instant.');
        } else {
            Flash::set('success', 'Compte créé ! Un e-mail de vérification a été envoyé à ' . $data->email . '.');
        }
        return $response->withHeader('Location', '/auth/login')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Vérification de l'e-mail
    // -------------------------------------------------------------

    public function verify(Request $request, Response $response, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $render = fn (bool $ok, string $message): Response => $this->twig->render(
            $response,
            'auth/verify.html.twig',
            ['ok' => $ok, 'message' => $message]
        );

        $row = $this->tokens->findValidVerify($token);
        if ($row === null) {
            return $render(false, 'Ce lien de vérification est invalide ou déjà utilisé.');
        }
        if ($row->used) {
            return $render(false, 'Ce lien de vérification a déjà été utilisé.');
        }
        if (strtotime($row->expiresAt) < time()) {
            return $render(false, 'Ce lien de vérification a expiré.');
        }

        // Usage unique : jeton marqué utilisé + compte activé.
        $this->tokens->markUsed($row->id);
        $this->users->setEmailVerified($row->userId);

        Flash::set('success', 'Votre compte est vérifié, vous pouvez vous connecter.');
        return $response->withHeader('Location', '/auth/login')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Connexion / déconnexion
    // -------------------------------------------------------------

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        return $this->twig->render($response, 'auth/login.html.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }

        $data = (array) $request->getParsedBody();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = $this->users->findByUsername($username);

        $error = null;
        if ($user === null || !password_verify($password, (string) $user->passwordHash)) {
            $error = 'Identifiants invalides.';
        } elseif (!$user->emailVerifie) {
            $error = 'Ce compte n\'a pas encore été vérifié : consultez votre boîte e-mail.';
        } elseif ($user->bloqueJusqua !== null && strtotime($user->bloqueJusqua) > time()) {
            $error = 'Ce compte est temporairement suspendu.';
        }

        if ($error !== null) {
            return $this->twig->render($response, 'auth/login.html.twig', [
                'errors' => ['login' => $error],
                'old' => ['username' => $username],
            ]);
        }

        // Anti session fixation + données de session (jamais le hash du mdp).
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user'] = $user->withoutPassword();
        $this->users->touchLastLogin($user->id);

        Flash::set('success', 'Bonjour ' . $user->prenom . ' !');
        return $this->redirectToSuggestions($response);
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                (string) $p['path'],
                (string) $p['domain'],
                (bool) $p['secure'],
                (bool) $p['httponly']
            );
        }
        session_destroy();
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Mot de passe oublié / réinitialisation
    // -------------------------------------------------------------

    public function showForgot(Request $request, Response $response): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        return $this->twig->render($response, 'auth/forgot.html.twig');
    }

    public function forgot(Request $request, Response $response): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }

        $data = (array) $request->getParsedBody();
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        // Toujours le même message : pas d'énumération d'utilisateurs.
        Flash::set('success', 'Si un compte existe avec cette adresse e-mail, un lien de réinitialisation vient d\'être envoyé.');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $user = $this->users->findByEmail($email);
            if ($user !== null) {
                $token = $this->createToken($user->id, 'reset_password');
                $link = $this->appUrl . '/auth/reset/' . $token;
                $this->mail->sendPasswordReset($email, $user->username, $link);
            }
        }

        return $response->withHeader('Location', '/auth/forgot')->withStatus(302);
    }

    public function showReset(Request $request, Response $response, array $args): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }
        $token = (string) ($args['token'] ?? '');
        return $this->twig->render($response, 'auth/reset.html.twig', [
            'token' => $token,
            'token_valid' => $this->tokens->findValidReset($token) !== null,
        ]);
    }

    public function reset(Request $request, Response $response, array $args): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }

        $token = (string) ($args['token'] ?? '');
        $tokenEntity = $this->tokens->findValidReset($token);

        if ($tokenEntity === null) {
            return $this->twig->render($response, 'auth/reset.html.twig', [
                'token' => $token,
                'token_valid' => false,
                'errors' => ['token' => 'Ce lien est invalide, déjà utilisé ou expiré.'],
            ]);
        }

        $data = (array) $request->getParsedBody();
        $password = (string) ($data['password'] ?? '');
        $v = new \App\Validation\Validator();
        $v->required('password', $password, 'nouveau mot de passe')
            ->equals('password_confirm', $data['password_confirm'] ?? null, $password, 'Les deux mots de passe ne correspondent pas.');
        foreach (\App\Security\PasswordPolicy::check($password) as $error) {
            $v->add('password', $error);
        }

        if ($v->fails()) {
            return $this->twig->render($response, 'auth/reset.html.twig', [
                'token' => $token,
                'token_valid' => true,
                'errors' => $v->errors(),
            ]);
        }

        $this->tokens->markUsed($tokenEntity->id);
        $this->users->update($tokenEntity->userId, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        Flash::set('success', 'Mot de passe réinitialisé, vous pouvez vous connecter.');
        return $response->withHeader('Location', '/auth/login')->withStatus(302);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    private function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    private function redirectToSuggestions(Response $response): Response
    {
        return $response->withHeader('Location', '/suggestions')->withStatus(302);
    }

    private function createToken(int $userId, string $type): string
    {
        $token = bin2hex(random_bytes(32));
        $this->tokens->create($userId, $type, $token, date('Y-m-d H:i:s', time() + 24 * 3600));
        return $token;
    }

    /** Ne jamais réafficher le mot de passe dans le formulaire. */
    private function cleanOld(array $data): array
    {
        unset($data['password'], $data['password_confirm']);
        return $data;
    }
}
