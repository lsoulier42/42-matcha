# Plan d'implémentation — Matcha (Option A : un seul agent, tout le projet)

## Contexte
Repo vide (README.md, SPEC.md, CHECKLIST.md, .gitignore). À implémenter intégralement : **Slim 4 (PHP 8.x) + Twig + PDO/MySQL sans ORM**, sessions PHP natives, auth/validation/CSRF maison, polling AJAX 5 s pour le temps réel, seed Faker 500+ profils, docker-compose (php-apache + mysql + mailhog). UI en **français** (sujet français). `.env` hors git.

## Décisions techniques (issues de la SPEC)
| Sujet | Choix |
|---|---|
| Temps réel | Polling AJAX 5 s (fetch) — satisfait ≤ 10 s ; un seul endpoint `/api/poll` renvoyant notifs non lues + nouveaux messages ; badge global dans `base.html.twig` (visible sur toutes les pages) |
| Popularité | `(likes reçus) + 2×(matchs) − (unlikes reçus)`, plafonnée 0–10, recalculée par `PopularityService` à chaque événement, formule documentée dans le README |
| Matching | Orientation croisée (cible : genre de la cible ∩ orientation de moi, orientation de la cible ∩ mon genre ; **orientation NULL = bisexuel**) ; score = même zone (Haversine < 10 km, priorité) + nb tags partagés + popularité ; exclusion self + bloqués |
| Géolocalisation | `navigator.geolocation` (consentement explicite) sinon saisie manuelle ville obligatoire ; modifiable |
| Uploads | GD + `getimagesize()` (magic bytes), renommage, limite taille/poids, stockés dans `public/assets/uploads/` protégé par `.htaccess` (exécution PHP désactivée) |
| Mail | `mail()` → MailHog (docker, port 1025/8025) |
| Sécurité | Prepared statements systématiques (mini-lib `Query`), Twig autoescape (jamais `|raw`), CSRF maison sur tout POST, `session_regenerate_id()` + cookie HttpOnly, `password_hash()/verify()`, blacklist ~100 mots de passe anglais + complexité (8+, majuscule, chiffre, symbole) |
| Seed | Faker ; photos de démo générées par GD (script PHP) pour que les likes/matchs soient démontrables |

## Structure cible (SPEC 2.2)
```
public/index.php, public/.htaccess, public/assets/{css,js,uploads/}
src/Controllers/ (Auth, Profile, Suggest, Search, User, Chat, Notification) + ApiController
src/Middleware/ (AuthMiddleware, CsrfMiddleware, SessionMiddleware)
src/Services/ (PopularityService, MatchingService, LocationService, NotificationService, MailService)
src/Db/ (Query.php, ConnectionFactory.php)
src/Validation/ (Validator maison), src/Security/ (PasswordPolicy, blacklist)
templates/ (base.html.twig + sections), routes.php, config/, scripts/seed.php, database/schema.sql
docker-compose.yml, .env, .env.example, README.md (formule popularité documentée)
```
Tables (SPEC 2.3) : users, tags, user_tags, photos, likes, visits, blocks, reports, messages, notifications, password_resets.

## Phases d'implémentation (chaque phase = livrable testé avant la suivante)

**Phase 0 — Squelette** : vérifier l'environnement (docker dispo ? sinon fallback PHP local + MySQL local — je m'adapte) → `composer require slim/slim slim/psr7 slim/twig-view vlucas/phpdotenv fakerphp/faker` → docker-compose (php-apache, mysql, mailhog) → `.env`/`.env.example` → `schema.sql` → `ConnectionFactory` + `Query` (wrap PDO préparé) → bootstrap `index.php` + routes vides → layout Twig responsive (en-tête/main/pied) + CSS + JS de base → vérif : page d'accueil OK, zéro erreur/notice, logs propres.

**Phase 1 — Auth (SPEC 3.1)** : inscription (email, username, nom, prénom, mdp sécurisé + blacklist anglais), email de vérification à lien unique (jeton, usage unique), connexion username+mdp, reset mdp par email, déconnexion 1 clic partout, `AuthMiddleware` (pages privées côté serveur), `CsrfMiddleware` (jeton session + champ caché sur tout POST). Vérif : flux complet + refus des mdp faibles + mdp hachés en base.

**Phase 2 — Profil (SPEC 3.2)** : genre, orientation, bio, tags réutilisables (autocomplétion AJAX des tags existants), jusqu'à 5 photos dont photo de profil (upload GD validé), édition complète (dont nom/prénom/email), « qui m'a consulté », « qui m'a liké », note de popularité publique, localisation (consentement GPS ou saisie manuelle obligatoire, modifiable).

**Phase 3 — Suggestions (SPEC 3.3)** : `MatchingService` (orientation/bisexuel par défaut, proximité prioritaire, tags partagés, popularité), liste triable (âge/localisation/popularité/tags) et filtrable (mêmes critères) — tri/filtres en SQL paramétré.

**Phase 4 — Recherche avancée (SPEC 3.4)** : critères combinés tranche d'âge + plage popularité + localisation + 1..n tags, résultats triables/filtrables.

**Phase 5 — Consultation de profil (SPEC 3.5)** : affichage complet sauf email/mdp, enregistrement des visites, like/unlike (like refusé côté serveur si pas de photo de profil), like mutuel → « connectés » + chat débloqué, unlike → notifs coupées + chat désactivé (côté serveur, pas juste masqué), blocage (disparaît des recherches, plus de notifs, chat impossible), signalement « faux compte », statut en ligne / dernière connexion, état « il m'a liké / on est connectés » avec action.

**Phase 6 — Chat + Notifications (SPEC 3.6–3.7)** : chat réservé aux matchés, polling AJAX 5 s, messages + notifs reçus ≤ 10 s, badge global messages + compteur notifs non lues dans le layout, marquage lu après consultation, les 5 événements de notif (like reçu, visite, message, like en retour, unlike).

**Phase 7 — Seed** : `scripts/seed.php` — 500+ profils Faker cohérents (genre/orientation/ville/tags/popularité), photos générées GD, likes/visits/messages entre profils. Vérif : `SELECT COUNT(*) >= 500`.

**Phase 8 — Nettoyage & vérification CHECKLIST.md** : `error_reporting(E_ALL)` sans aucune erreur/warning/notice (logs serveur, console navigateur, onglet Réseau) ; tests sécurité (SQLi, XSS, uploads, CSRF, accès privés, HttpOnly) ; responsive mobile ; test navigateur automatisé (browser) de tous les flux dans le navigateur ; correction de tout ce qui ne passe pas. Commit par phase.

**Phase 9 — Bonus** (uniquement si obligatoire 100 % parfait, ordre de la SPEC) : OmniAuth (OAuth Google/42/GitHub) → galerie drag&drop + édition GD (recadrage/rotation/filtres) → carte interactive → chat vidéo/audio → rendez-vous entre matchés.

## Vérification finale
- Parcourir CHECKLIST.md intégralement ; chaque case validée dans le navigateur (desktop + mobile) avec chronomètre pour les ≤ 10 s.
- `.env` hors git confirmé, `vendor/` ignoré, README complété (formule popularité, lancement docker, comptes de démo).

## Risques / parades
- Docker indisponible → fallback PHP built-in server + MySQL/MariaDB local (mêmes requêtes, .env adapté).
- Polling > 10 s → réduire l'intervalle et vérifier le badge sur toutes les pages.
- Zéro notice PHP → dev avec `error_reporting(E_ALL)` et `display_errors=1` en dev, `0` en prod.