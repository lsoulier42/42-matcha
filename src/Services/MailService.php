<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Email sending via raw SMTP socket, no external dependency.
 * In development the server is MailHog (docker-compose): no real
 * emails sent, just captured in the web UI (port 8026).
 */
final class MailService
{
    public function __construct(private array $config)
    {
    }

    /** Account verification email (unique link). */
    public function sendVerification(string $to, string $username, string $link): bool
    {
        $body = $this->layout(
            'Vérification de votre compte',
            '<p>Bonjour <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Bienvenue sur Matcha ! Pour activer votre compte, cliquez sur le bouton ci-dessous :</p>'
            . $this->button($link, 'Vérifier mon compte')
            . '<p>Ce lien est valable 24 heures et ne peut être utilisé qu\'une seule fois.</p>'
            . '<p>Si vous n\'êtes pas à l\'origine de cette inscription, ignorez cet e-mail.</p>'
        );
        return $this->send($to, 'Vérification de votre compte Matcha', $body);
    }

    /** Password reset email (unique link). */
    public function sendPasswordReset(string $to, string $username, string $link): bool
    {
        $body = $this->layout(
            'Réinitialisation de votre mot de passe',
            '<p>Bonjour <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous :</p>'
            . $this->button($link, 'Réinitialiser mon mot de passe')
            . '<p>Ce lien est valable 24 heures et ne peut être utilisé qu\'une seule fois.</p>'
            . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.</p>'
        );
        return $this->send($to, 'Réinitialisation de votre mot de passe Matcha', $body);
    }

    private function send(string $to, string $subject, string $html): bool
    {
        $host = (string) $this->config['host'];
        $port = (int) $this->config['port'];

        $fp = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 10);
        if ($fp === false) {
            return false;
        }
        stream_set_timeout($fp, 10);

        $ok = $this->expect($fp, 220)
            && $this->cmd($fp, 'EHLO matcha.local', 250)
            && $this->auth($fp)
            && $this->cmd($fp, 'MAIL FROM:<' . $this->config['from'] . '>', 250)
            && $this->cmd($fp, 'RCPT TO:<' . $to . '>', 250)
            && $this->cmd($fp, 'DATA', 354);

        if ($ok) {
            $headers = implode("\r\n", [
                'From: ' . $this->config['from_name'] . ' <' . $this->config['from'] . '>',
                'To: <' . $to . '>',
                'Subject: ' . $this->mimeSubject($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: 8bit',
            ]);
            // Anti dot-stuffing: a line starting with a dot is doubled.
            $body = str_replace("\r\n.", "\r\n..", $html);
            @fwrite($fp, $headers . "\r\n\r\n" . $body . "\r\n.\r\n");
            $ok = $this->expect($fp, 250);
        }

        $this->cmd($fp, 'QUIT', 221);
        @fclose($fp);
        return $ok;
    }

    private function auth($fp): bool
    {
        if ($this->config['user'] === '') {
            return true; // MailHog: no authentication needed
        }
        return $this->cmd($fp, 'AUTH LOGIN', 334)
            && $this->cmd($fp, base64_encode((string) $this->config['user']), 334)
            && $this->cmd($fp, base64_encode((string) $this->config['pass']), 235);
    }

    private function cmd($fp, string $line, int $expected): bool
    {
        @fwrite($fp, $line . "\r\n");
        return $this->expect($fp, $expected);
    }

    /**
     * Reads a full SMTP response: multi-line replies
     * ("250-…" continuations) are consumed until the final line.
     */
    private function expect($fp, int $expected): bool
    {
        $code = '0';
        while (true) {
            $line = @fgets($fp);
            if ($line === false) {
                return false;
            }
            $code = substr($line, 0, 3);
            // Last line of the response: no dash after the code.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $code === (string) $expected;
    }

    private function mimeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private function button(string $url, string $label): string
    {
        return '<p style="margin:24px 0"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;background:#e91e63;color:#ffffff;padding:12px 24px;'
            . 'border-radius:8px;text-decoration:none;font-weight:600;">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }

    private function layout(string $title, string $content): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>'
            . '<body style="margin:0;background:#faf7f5;font-family:system-ui,Roboto,sans-serif;color:#2d2a2e;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr><td align="center" style="padding:32px 12px;">'
            . '<div style="max-width:520px;background:#ffffff;border:1px solid #e8e2dd;border-radius:12px;'
            . 'padding:28px;text-align:left;">'
            . '<p style="margin:0 0 18px;font-size:22px;font-weight:800;color:#e91e63;">Matcha</p>'
            . '<h1 style="font-size:18px;margin:0 0 14px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . $content
            . '<p style="margin-top:26px;font-size:12px;color:#6b6570;">Ce message a été envoyé automatiquement par Matcha.</p>'
            . '</div></td></tr></table></body></html>';
    }
}
