<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Db\Query;
use App\Security\PasswordPolicy;
use App\Services\MailService;
use App\Support\Flash;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Inscription, vérification d'e-mail, connexion, mot de passe oublié,
 * réinitialisation et déconnexion. Gestionnaire de comptes codé maison
 * (interdit dans le micro-framework).
 */
final class AuthController
{
    public function __construct(
        private Twig $twig,
        private Query $db,
        private MailService $mail,
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

        $data = (array) $request->getParsedBody();
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $v = new Validator();
        $v->required('email', $email, 'adresse e-mail')
            ->email('email', $email, 'Adresse e-mail')
            ->required('username', $username, 'nom d\'utilisateur')
            ->username('username', $username)
            ->required('nom', $data['nom'] ?? null, 'nom de famille')
            ->name('nom', $data['nom'] ?? null, 'Nom de famille')
            ->required('prenom', $data['prenom'] ?? null, 'prénom')
            ->name('prenom', $data['prenom'] ?? null, 'Prénom')
            ->required('password', $password, 'mot de passe')
            ->equals('password_confirm', $data['password_confirm'] ?? null, $password, 'Les deux mots de passe ne correspondent pas.');

        foreach (PasswordPolicy::check($password) as $error) {
            $v->add('password', $error);
        }

        if ($email !== '' && $this->db->value('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            $v->add('email', 'Cette adresse e-mail est déjà utilisée.');
        }
        if ($username !== '' && $this->db->value('SELECT id FROM users WHERE username = ?', [$username]) !== null) {
            $v->add('username', 'Ce nom d\'utilisateur est déjà pris.');
        }

        if ($v->fails()) {
            return $this->twig->render($response, 'auth/register.html.twig', [
                'errors' => $v->errors(),
                'old' => $this->cleanOld($data),
            ]);
        }

        $this->db->beginTransaction();
        try {
            $userId = $this->db->insert('users', [
                'email' => $email,
                'username' => $username,
                'nom' => trim((string) ($data['nom'] ?? '')),
                'prenom' => trim((string) ($data['prenom'] ?? '')),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $token = $this->createToken($userId, 'verify_email');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $link = $this->appUrl . '/auth/verify/' . $token;
        if (!$this->mail->sendVerification($email, $username, $link)) {
            Flash::set('error', 'L\'e-mail de vérification n\'a pas pu être envoyé, réessayez dans un instant.');
        } else {
            Flash::set('success', 'Compte créé ! Un e-mail de vérification a été envoyé à ' . $email . '.');
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

        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return $render(false, 'Ce lien de vérification est invalide.');
        }

        $row = $this->db->fetch(
            'SELECT t.id AS token_id, t.used, t.expires_at, u.id AS user_id
             FROM tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.type = ?',
            [$token, 'verify_email']
        );

        if ($row === null) {
            return $render(false, 'Ce lien de vérification est invalide ou déjà utilisé.');
        }
        if ((int) $row['used'] === 1) {
            return $render(false, 'Ce lien de vérification a déjà été utilisé.');
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return $render(false, 'Ce lien de vérification a expiré.');
        }

        // Usage unique : jeton marqué utilisé + compte activé.
        $this->db->update('tokens', ['used' => 1], 'id = ?', [$row['token_id']]);
        $this->db->update('users', ['email_verifie' => 1], 'id = ?', [$row['user_id']]);

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

        $user = $this->db->fetch(
            'SELECT * FROM users WHERE username = ? AND actif = 1',
            [$username]
        );

        $error = null;
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $error = 'Identifiants invalides.';
        } elseif ((int) $user['email_verifie'] !== 1) {
            $error = 'Ce compte n\'a pas encore été vérifié : consultez votre boîte e-mail.';
        } elseif ($user['bloque_jusqua'] !== null && strtotime((string) $user['bloque_jusqua']) > time()) {
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
        $_SESSION['user_id'] = (int) $user['id'];
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $this->db->run('UPDATE users SET derniere_connexion = NOW() WHERE id = ?', [$user['id']]);

        Flash::set('success', 'Bonjour ' . $user['prenom'] . ' !');
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
            $user = $this->db->fetch('SELECT * FROM users WHERE email = ? AND actif = 1', [$email]);
            if ($user !== null) {
                $token = $this->createToken((int) $user['id'], 'reset_password');
                $link = $this->appUrl . '/auth/reset/' . $token;
                $this->mail->sendPasswordReset($email, (string) $user['username'], $link);
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
            'token_valid' => $this->resetTokenValid($token) !== null,
        ]);
    }

    public function reset(Request $request, Response $response, array $args): Response
    {
        if ($this->isLoggedIn()) {
            return $this->redirectToSuggestions($response);
        }

        $token = (string) ($args['token'] ?? '');
        $row = $this->resetTokenValid($token);

        if ($row === null) {
            return $this->twig->render($response, 'auth/reset.html.twig', [
                'token' => $token,
                'token_valid' => false,
                'errors' => ['token' => 'Ce lien est invalide, déjà utilisé ou expiré.'],
            ]);
        }

        $data = (array) $request->getParsedBody();
        $password = (string) ($data['password'] ?? '');
        $v = new Validator();
        $v->required('password', $password, 'nouveau mot de passe')
            ->equals('password_confirm', $data['password_confirm'] ?? null, $password, 'Les deux mots de passe ne correspondent pas.');
        foreach (PasswordPolicy::check($password) as $error) {
            $v->add('password', $error);
        }

        if ($v->fails()) {
            return $this->twig->render($response, 'auth/reset.html.twig', [
                'token' => $token,
                'token_valid' => true,
                'errors' => $v->errors(),
            ]);
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('tokens', ['used' => 1], 'id = ?', [$row['id']]);
            $this->db->update(
                'users',
                ['password_hash' => password_hash($password, PASSWORD_DEFAULT)],
                'id = ?',
                [$row['user_id']]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

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
        $this->db->insert('tokens', [
            'user_id' => $userId,
            'type' => $type,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 24 * 3600),
        ]);
        return $token;
    }

    /** Retourne la ligne du jeton si valide (non utilisé, non expiré), sinon null. */
    private function resetTokenValid(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, user_id, used, expires_at FROM tokens WHERE token = ? AND type = ?',
            [$token, 'reset_password']
        );
        if ($row === null || (int) $row['used'] === 1 || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return $row;
    }

    /** Ne jamais réafficher le mot de passe dans le formulaire. */
    private function cleanOld(array $data): array
    {
        unset($data['password'], $data['password_confirm']);
        return $data;
    }
}
