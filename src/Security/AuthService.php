<?php

declare(strict_types=1);

namespace App\Security;

use App\Dto\RegisterData;
use App\Entity\User;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;
use App\Services\MailService;

/**
 * Authentication business logic: credential verification,
 * session lifecycle, single-use tokens, password reset flow.
 *
 * This service does not handle HTTP transport — the controller
 * remains responsible for extracting request data and building
 * the response.
 */
final class AuthService
{
    public function __construct(
        private UserRepository $users,
        private TokenRepository $tokens,
        private MailService $mail,
        private string $appUrl,
    ) {
    }

    // -------------------------------------------------------------
    // Login
    // -------------------------------------------------------------

    /**
     * Verifies credentials and account authorization rules.
     *
     * @return array{ok: bool, user?: User, error?: string}
     */
    public function authenticate(string $username, string $password): array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null || !password_verify($password, (string) $user->passwordHash)) {
            return ['ok' => false, 'error' => 'Identifiants invalides.'];
        }
        if (!$user->emailVerifie) {
            return ['ok' => false, 'error' => 'Ce compte n\'a pas encore été vérifié : consultez votre boîte e-mail.'];
        }
        if ($user->bloqueJusqua !== null && strtotime($user->bloqueJusqua) > time()) {
            return ['ok' => false, 'error' => 'Ce compte est temporairement suspendu.'];
        }

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Completes login: regenerates the session id,
     * populates $_SESSION and updates the last login timestamp.
     */
    public function startSession(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user'] = $user->withoutPassword();
        $this->users->touchLastLogin($user->id);
    }

    // -------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------

    /**
     * Destroys the PHP session and clears the associated cookie.
     */
    public function destroySession(): void
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
                (bool) $p['httponly'],
            );
        }
        session_destroy();
    }

    // -------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------

    /**
     * Creates the account, generates the verification token and sends the email.
     *
     * @return array{ok: bool, email?: string, username?: string, error?: string}
     */
    public function register(RegisterData $data): array
    {
        $userId = $this->users->create($data->toRecord());
        $token = $this->createToken($userId, 'verify_email');

        $link = $this->appUrl . '/auth/verify/' . $token;
        $sent = $this->mail->sendVerification($data->email, $data->username, $link);

        if ($sent) {
            return ['ok' => true, 'email' => $data->email, 'username' => $data->username];
        }

        return ['ok' => false, 'error' => 'L\'e-mail de vérification n\'a pas pu être envoyé, réessayez dans un instant.'];
    }

    // -------------------------------------------------------------
    // Email verification
    // -------------------------------------------------------------

    /**
     * Validates the verification token and activates the account.
     *
     * @return array{ok: bool, message: string}
     */
    public function verifyEmail(string $token): array
    {
        $row = $this->tokens->findValidVerify($token);

        if ($row === null) {
            return ['ok' => false, 'message' => 'Ce lien de vérification est invalide ou déjà utilisé.'];
        }
        if ($row->used) {
            return ['ok' => false, 'message' => 'Ce lien de vérification a déjà été utilisé.'];
        }
        if (strtotime($row->expiresAt) < time()) {
            return ['ok' => false, 'message' => 'Ce lien de vérification a expiré.'];
        }

        $this->tokens->markUsed($row->id);
        $this->users->setEmailVerified($row->userId);

        return ['ok' => true, 'message' => 'Votre compte est vérifié, vous pouvez vous connecter.'];
    }

    // -------------------------------------------------------------
    // Forgotten password
    // -------------------------------------------------------------

    /**
     * Sends a reset email if the account exists.
     * Always returns the same message to prevent user enumeration.
     */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }

        $token = $this->createToken($user->id, 'reset_password');
        $link = $this->appUrl . '/auth/reset/' . $token;
        $this->mail->sendPasswordReset($email, $user->username, $link);
    }

    // -------------------------------------------------------------
    // Password reset
    // -------------------------------------------------------------

    /**
     * Checks whether a reset token is valid.
     */
    public function validateResetToken(string $token): bool
    {
        return $this->tokens->findValidReset($token) !== null;
    }

    /**
     * Validates the new password and performs the reset.
     *
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function resetPassword(string $token, string $password, string $passwordConfirm): array
    {
        $tokenEntity = $this->tokens->findValidReset($token);

        if ($tokenEntity === null) {
            return ['ok' => false, 'errors' => ['token' => 'Ce lien est invalide, déjà utilisé ou expiré.']];
        }

        $v = new \App\Validation\Validator();
        $v->required('password', $password, 'nouveau mot de passe')
            ->equals('password_confirm', $passwordConfirm, $password, 'Les deux mots de passe ne correspondent pas.');

        foreach (PasswordPolicy::check($password) as $error) {
            $v->add('password', $error);
        }

        if ($v->fails()) {
            return ['ok' => false, 'errors' => $v->errors()];
        }

        $this->tokens->markUsed($tokenEntity->id);
        $this->users->update($tokenEntity->userId, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return ['ok' => true];
    }

    // -------------------------------------------------------------
    // Session (read)
    // -------------------------------------------------------------

    /**
     * Checks whether a user is logged in.
     */
    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    // -------------------------------------------------------------
    // Tokens (internal)
    // -------------------------------------------------------------

    /**
     * Generates a single-use token and stores it in the database.
     */
    private function createToken(int $userId, string $type): string
    {
        $token = bin2hex(random_bytes(32));
        $this->tokens->create($userId, $type, $token, date('Y-m-d H:i:s', time() + 24 * 3600));
        return $token;
    }
}
