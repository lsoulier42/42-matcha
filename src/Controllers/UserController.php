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
use App\Support\Http;
use App\ViewModel\UserProfile;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Public profile viewing (section 3.5): all info except email and
 * password, visit history, like/unlike (server-rejected without a
 * profile photo), mutual like = "connected", blocking, reporting,
 * online status. SQL lives in repositories.
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
        $me = $request->getAttribute('user_id');
        $id = (int) ($args['id'] ?? 0);

        if ($id === $me) {
            return Http::redirect($response, '/profile');
        }

        $user = $this->users->findActiveById($id);
        if ($user === null) {
            return $this->twig->render($response->withStatus(404), 'user/unavailable.html.twig', [
                'reason' => 'Ce profil n\'existe pas ou n\'est plus actif.',
            ]);
        }

        // Blocking in either direction -> profile unavailable.
        if ($this->blocks->isBlocked($me, $id)) {
            return $this->twig->render($response->withStatus(403), 'user/unavailable.html.twig', [
                'reason' => 'Ce profil n\'est pas disponible.',
            ]);
        }

        // Record the visit (visitor's browsing history).
        $this->visits->record($me, $id);
        $this->notifications->notify($id, 'visit', $me);

        $iLiked = $this->likes->exists($me, $id);
        $likedMe = $this->likes->exists($id, $me);
        $myPhoto = $this->photos->profilePhoto($me);

        return $this->twig->render($response, 'user/show.html.twig', [
            'user' => UserProfile::fromUser($user),
            'photos' => $this->photos->listByUser($id),
            'tags' => $this->tags->listByUser($id),
            'i_liked' => $iLiked,
            'liked_me' => $likedMe,
            'is_match' => $iLiked && $likedMe,
            'my_avatar' => $myPhoto?->path,
        ]);
    }

    // -------------------------------------------------------------
    // Like / unlike
    // -------------------------------------------------------------

    public function like(Request $request, Response $response, array $args): Response
    {
        $me = $request->getAttribute('user_id');
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);
        $wantsJson = $this->wantsJson($request);

        if ($id === $me) {
            return $this->likeReply($response, $wantsJson, $back, ['ok' => false]);
        }
        if ($this->blocks->isBlocked($me, $id)) {
            Flash::set('error', 'Action impossible sur ce profil.');
            return $this->likeReply($response, $wantsJson, $back, ['ok' => false]);
        }

        // Spec requirement: without a profile photo, the like is rejected
        // SERVER-SIDE (not merely hidden in the UI).
        if (!$this->photos->hasProfilePhoto($me)) {
            Flash::set('error', 'Vous devez avoir une photo de profil pour liker un autre profil.');
            return $this->likeReply($response, $wantsJson, $back, ['ok' => false]);
        }

        $isMatch = false;
        if (!$this->likes->exists($me, $id)) {
            $this->likes->add($me, $id);
            $this->popularity->recompute($id);

            if ($this->likes->exists($id, $me)) {
                // Mutual like: "connected", chat is unlocked.
                $isMatch = true;
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

        return $this->likeReply($response, $wantsJson, $back, [
            'ok' => true,
            'match' => $isMatch,
            'chat_url' => $isMatch ? '/messages/' . $id : null,
        ]);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    /**
     * Like response: classic HTTP redirect (flash kept) or JSON payload
     * for the progressive-enhancement match overlay.
     */
    private function likeReply(Response $response, bool $wantsJson, string $back, array $payload): Response
    {
        $payload['redirect'] = $back;
        return $wantsJson ? Http::json($response, $payload) : Http::redirect($response, $back);
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains($request->getHeaderLine('Accept'), 'application/json');
    }

    public function unlike(Request $request, Response $response, array $args): Response
    {
        $me = $request->getAttribute('user_id');
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $this->likes->remove($me, $id);
            // Unlike is tracked (popularity formula).
            $this->likes->recordUnlike($me, $id);
            // No more notifications from this user + chat cut (no more match).
            $this->notifications->clearUnreadFrom($me, $id);
            $this->notifications->notify($id, 'unlike', $me);
            $this->popularity->recompute($me);
            $this->popularity->recompute($id);
            Flash::set('success', 'Like retiré.');
        }

        return Http::redirect($response, $back);
    }

    // -------------------------------------------------------------
    // Blocking / reporting
    // -------------------------------------------------------------

    public function block(Request $request, Response $response, array $args): Response
    {
        $me = $request->getAttribute('user_id');
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $this->blocks->add($me, $id);
            // Clear pending notifications from the blocked user.
            $this->notifications->clearUnreadFrom($me, $id);
            Flash::set('success', 'Utilisateur bloqué. Il n\'apparaît plus dans vos recherches.');
        }

        return Http::redirect($response, $back);
    }

    public function unblock(Request $request, Response $response, array $args): Response
    {
        $me = $request->getAttribute('user_id');
        $id = (int) ($args['id'] ?? 0);
        $back = $this->backUrl($request, $id);

        if ($id !== $me) {
            $this->blocks->remove($me, $id);
            Flash::set('success', 'Utilisateur débloqué.');
        }

        return Http::redirect($response, $back);
    }

    public function report(Request $request, Response $response, array $args): Response
    {
        $me = $request->getAttribute('user_id');
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

        return Http::redirect($response, $back);
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    /** Redirect back to the profile page (never external redirects). */
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
}
