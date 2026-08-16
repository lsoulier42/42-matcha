<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\BlockRepository;
use App\Repository\LikeRepository;
use App\Repository\PhotoRepository;
use App\Repository\ReportRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Repository\VisitRepository;
use App\Services\NotificationService;
use App\Services\PopularityService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Consultation de profil public (section 3.5) : toutes les infos sauf
 * e-mail et mot de passe, historique de visites, like/unlike (refusé
 * côté serveur sans photo de profil), like mutuel = « connectés »,
 * blocage, signalement, statut en ligne. Le SQL vit dans les repositories.
 */
final class UserController
{
    public function __construct(
        private Twig $twig,
        private UserRepository $users,
        private PhotoRepository $photos,
        private TagRepository $tags,
        private LikeRepository $likes,
        private VisitRepository $visits,
        private BlockRepository $blocks,
        private ReportRepository $reports,
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

        $user = $this->users->findActiveById($id);
        if ($user === null) {
            return $this->twig->render($response->withStatus(404), 'user/unavailable.html.twig', [
                'reason' => 'Ce profil n\'existe pas ou n\'est plus actif.',
            ]);
        }

        // Blocage dans un sens ou dans l'autre → profil indisponible.
        if ($this->blocks->isBlocked($me, $id)) {
            return $this->twig->render($response->withStatus(403), 'user/unavailable.html.twig', [
                'reason' => 'Ce profil n\'est pas disponible.',
            ]);
        }

        // La consultation est enregistrée (historique de visites du visiteur).
        $this->visits->record($me, $id);
        $this->notifications->notify($id, 'visit', $me);

        $iLiked = $this->likes->exists($me, $id);
        $likedMe = $this->likes->exists($id, $me);

        $user['age'] = $this->ageOf((string) ($user['birthdate'] ?? ''));
        $user['popularity_display'] = number_format((float) $user['note_popularite'], 1, ',', ' ');
        $user['is_online'] = $this->isOnline((string) ($user['derniere_connexion'] ?? ''));
        $user['last_seen'] = $this->lastSeen((string) ($user['derniere_connexion'] ?? ''));

        return $this->twig->render($response, 'user/show.html.twig', [
            'user' => $user,
            'photos' => $this->photos->listByUser($id),
            'tags' => $this->tags->listByUser($id),
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
        if ($this->blocks->isBlocked($me, $id)) {
            Flash::set('error', 'Action impossible sur ce profil.');
            return $this->redirect($response, $back);
        }

        // Exigence du sujet : sans photo de profil, le like est refusé
        // CÔTÉ SERVEUR (pas seulement masqué dans l'interface).
        if (!$this->photos->hasProfilePhoto($me)) {
            Flash::set('error', 'Vous devez avoir une photo de profil pour liker un autre profil.');
            return $this->redirect($response, $back);
        }

        if (!$this->likes->exists($me, $id)) {
            $this->likes->add($me, $id);
            $this->popularity->recompute($id);

            if ($this->likes->exists($id, $me)) {
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
            $this->likes->remove($me, $id);
            // Un unlike est tracé (formule de popularité).
            $this->likes->recordUnlike($me, $id);
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
            $this->blocks->add($me, $id);
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
            $this->blocks->remove($me, $id);
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
                $this->reports->add($me, $id, $reason);
                Flash::set('success', 'Signalement envoyé. Merci pour votre vigilance.');
            }
        }

        return $this->redirect($response, $back);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

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
