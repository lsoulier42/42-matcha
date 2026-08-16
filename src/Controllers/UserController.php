<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Db\Query;
use App\Services\NotificationService;
use App\Services\PhotoService;
use App\Services\PopularityService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Consultation de profil public (section 3.5) : toutes les infos sauf
 * e-mail et mot de passe, historique de visites, like/unlike (refusé
 * côté serveur sans photo de profil), like mutuel = « connectés »,
 * blocage, signalement, statut en ligne.
 */
final class UserController
{
    public function __construct(
        private Twig $twig,
        private Query $db,
        private PhotoService $photos,
        private PopularityService $popularity,
        private NotificationService $notifications
    ) {
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $me = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);

        if ($id === $me) {
            return $response->withHeader('Location', '/profile')->withStatus(302);
        }

        $user = $this->db->fetch('SELECT * FROM users WHERE id = ? AND actif = 1', [$id]);
        if ($user === null) {
            return $this->twig->render($response->withStatus(404), 'user/unavailable.html.twig', [
                'reason' => 'Ce profil n\'existe pas ou n\'est plus actif.',
            ]);
        }

        // Blocage dans un sens ou dans l'autre → profil indisponible.
        if ($this->isBlocked($me, $id)) {
            return $this->twig->render($response->withStatus(403), 'user/unavailable.html.twig', [
                'reason' => 'Ce profil n\'est pas disponible.',
            ]);
        }

        // La consultation est enregistrée (historique de visites du visiteur).
        $this->db->run(
            'INSERT INTO visits (visitor_id, visited_id, viewed_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = NOW()',
            [$me, $id]
        );
        $this->notifications->notify($id, 'visit', $me);

        $photos = $this->db->fetchAll(
            'SELECT * FROM photos WHERE user_id = ? ORDER BY position ASC',
            [$id]
        );
        $tags = $this->db->fetchAll(
            'SELECT t.id, t.name FROM tags t
             JOIN user_tags ut ON ut.tag_id = t.id
             WHERE ut.user_id = ? ORDER BY t.name ASC',
            [$id]
        );

        $iLiked = $this->db->value('SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?', [$me, $id]) !== null;
        $likedMe = $this->db->value('SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?', [$id, $me]) !== null;

        $user['age'] = $this->ageOf((string) ($user['birthdate'] ?? ''));
        $user['popularity_display'] = number_format((float) $user['note_popularite'], 1, ',', ' ');
        $user['is_online'] = $this->isOnline((string) ($user['derniere_connexion'] ?? ''));
        $user['last_seen'] = $this->lastSeen((string) ($user['derniere_connexion'] ?? ''));

        return $this->twig->render($response, 'user/show.html.twig', [
            'user' => $user,
            'photos' => $photos,
            'tags' => $tags,
            'i_liked' => $iLiked,
            'liked_me' => $likedMe,
            'is_match' => $iLiked && $likedMe,
        ]);
    }

    // -------------------------------------------------------------
    // Like / unlike
    // -------------------------------------------------------------

    public function like(Request $request, Response $response, array $args): Response
    {
        $me = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id === $me) {
            return $this->redirect($response, $back);
        }
        if ($this->isBlocked($me, $id)) {
            Flash::set('error', 'Action impossible sur ce profil.');
            return $this->redirect($response, $back);
        }

        // Exigence du sujet : sans photo de profil, le like est refusé
        // CÔTÉ SERVEUR (pas seulement masqué dans l'interface).
        $hasProfilePhoto = $this->db->value(
            'SELECT id FROM photos WHERE user_id = ? AND is_profile = 1 LIMIT 1',
            [$me]
        ) !== null;
        if (!$hasProfilePhoto) {
            Flash::set('error', 'Vous devez avoir une photo de profil pour liker un autre profil.');
            return $this->redirect($response, $back);
        }

        $already = $this->db->value('SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?', [$me, $id]) !== null;

        if (!$already) {
            $this->db->insert('likes', ['from_user_id' => $me, 'to_user_id' => $id]);
            $this->popularity->recompute($id);

            $likedMe = $this->db->value('SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?', [$id, $me]) !== null;
            if ($likedMe) {
                // Like mutuel : « connectés », le chat est débloqué.
                $this->notifications->notify($id, 'match', $me);
                $this->notifications->notify($me, 'match', $id);
                $this->popularity->recompute($me);
                Flash::set('success', 'C\'est un match ! 🎉 Vous pouvez discuter.');
            } else {
                $this->notifications->notify($id, 'like', $me);
                Flash::set('success', 'Like envoyé !');
            }
        } else {
            Flash::set('info', 'Vous avez déjà liké ce profil.');
        }

        return $this->redirect($response, $back);
    }

    public function unlike(Request $request, Response $response, array $args): Response
    {
        $me = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $this->db->delete('likes', 'from_user_id = ? AND to_user_id = ?', [$me, $id]);
            // Un unlike est tracé (formule de popularité).
            $this->db->run(
                'INSERT INTO unlikes (from_user_id, to_user_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE created_at = NOW()',
                [$me, $id]
            );
            // Plus de nouvelles notifications de cet utilisateur + chat coupé (plus de match).
            $this->notifications->clearUnreadFrom($me, $id);
            $this->notifications->notify($id, 'unlike', $me);
            $this->popularity->recompute($me);
            $this->popularity->recompute($id);
            Flash::set('success', 'Like retiré.');
        }

        return $this->redirect($response, $back);
    }

    // -------------------------------------------------------------
    // Blocage / signalement
    // -------------------------------------------------------------

    public function block(Request $request, Response $response, array $args): Response
    {
        $me = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $this->db->run(
                'INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)',
                [$me, $id]
            );
            // Plus de notifications de l'utilisateur bloqué.
            $this->notifications->clearUnreadFrom($me, $id);
            Flash::set('success', 'Utilisateur bloqué. Il n\'apparaît plus dans vos recherches.');
        }

        return $this->redirect($response, $back);
    }

    public function unblock(Request $request, Response $response, array $args): Response
    {
        $me = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $this->db->delete('blocks', 'blocker_id = ? AND blocked_id = ?', [$me, $id]);
            Flash::set('success', 'Utilisateur débloqué.');
        }

        return $this->redirect($response, $back);
    }

    public function report(Request $request, Response $response, array $args): Response
    {
        $me = (int) $_SESSION['user_id'];
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $reason = trim((string) (($request->getParsedBody() ?? [])['reason'] ?? ''));
            if ($reason === '' || mb_strlen($reason) > 255) {
                Flash::set('error', 'Veuillez préciser un motif (255 caractères max).');
            } else {
                $this->db->run(
                    'INSERT IGNORE INTO reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)',
                    [$me, $id, $reason]
                );
                Flash::set('success', 'Signalement envoyé. Merci pour votre vigilance.');
            }
        }

        return $this->redirect($response, $back);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    private function isBlocked(int $me, int $other): bool
    {
        return $this->db->value(
            'SELECT id FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)',
            [$me, $other, $other, $me]
        ) !== null;
    }

    private function isOnline(string $lastSeen): bool
    {
        if ($lastSeen === '') {
            return false;
        }
        $ts = strtotime($lastSeen);
        return $ts !== false && (time() - $ts) < 300; // en ligne depuis moins de 5 minutes
    }

    private function lastSeen(string $lastSeen): string
    {
        $ts = strtotime($lastSeen);
        if ($ts === false || $ts <= 0) {
            return 'jamais';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'à l\'instant';
        }
        if ($diff < 3600) {
            return 'il y a ' . (int) floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'il y a ' . (int) floor($diff / 3600) . ' h';
        }
        return 'le ' . date('d/m/Y à H:i', $ts);
    }

    private function ageOf(string $birthdate): ?int
    {
        if ($birthdate === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
        return $dt === false ? null : (int) $dt->diff(new \DateTimeImmutable('now'))->y;
    }

    /** Retour sur la page du profil (jamais de redirection externe). */
    private function backUrl(Request $request, int $id): string
    {
        $referer = $request->getHeaderLine('Referer');
        $appUrl = $this->appUrl($request);
        if ($referer !== '' && str_starts_with($referer, $appUrl)) {
            return $referer;
        }
        return '/user/' . $id;
    }

    private function appUrl(Request $request): string
    {
        $uri = $request->getUri();
        return $uri->getScheme() . '://' . $uri->getHost() . ($uri->getPort() !== null ? ':' . $uri->getPort() : '');
    }

    private function redirect(Response $response, string $url): Response
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
