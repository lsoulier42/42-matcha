# Matcha — Site de rencontre (École 42)

Site de rencontre complet : inscription, profils, suggestions intelligentes,
like/match, chat et notifications en temps réel. Implémentation **Slim 4 (PHP)**
conforme aux spécifications du sujet 42 v6.0.

## Fonctionnalités (partie obligatoire)

- **Auth** : inscription (email, username, nom, prénom, mot de passe sécurisé +
  blacklist des mots de passe anglais courants), vérification par e-mail à lien
  unique, connexion, mot de passe oublié, déconnexion en un clic.
- **Profil** : genre, préférences sexuelles, biographie, tags réutilisables avec
  autocomplétion, jusqu'à 5 photos (dont une photo de profil), note de
  popularité publique, localisation GPS avec **consentement explicite** ou
  saisie manuelle obligatoire, « qui a consulté mon profil », « qui m'a liké ».
- **Suggestions intelligentes** : compatibilité d'orientation (bisexuel par
  défaut si non renseigné), même zone géographique prioritaire, tags partagés,
  popularité. Liste triable et filtrable (âge, localisation, popularité, tags).
- **Recherche avancée** : critères combinés (tranche d'âge, plage de
  popularité, localisation, un ou plusieurs tags), résultats triables/filtrables.
- **Consultation de profil** : toutes les infos sauf e-mail/mot de passe,
  historique de visites, like (refusé côté serveur sans photo de profil),
  like mutuel = « connectés », unlike, blocage, signalement, statut en ligne.
- **Chat temps réel** (≤ 10 s) : réservé aux utilisateurs connectés, coupé
  réellement après unlike/blocage, badge global de nouveaux messages.
- **Notifications temps réel** (≤ 10 s) : les 5 événements (like, visite,
  message, like en retour, unlike), compteur global de non-lues.

## Stack

| Composant | Choix |
|---|---|
| Framework | **Slim 4** (micro-framework de la liste officielle : routeur + templating, sans ORM ni gestionnaire de comptes) |
| Templating | **Twig** (échappement HTML automatique) |
| Base de données | **MySQL 8** via **PDO** — requêtes SQL écrites à la main (mini-lib `App\Db\Query`), prepared statements systématiques |
| Auth / validation / CSRF | code maison (`App\Security`, `App\Validation`, `App\Middleware\CsrfMiddleware`) |
| Temps réel | **polling AJAX 5 s** (`/api/poll` + `/api/messages/{id}`) — délai de réception ≤ 10 s garanti |
| Images | extension GD + validation par magic bytes (`getimagesize`) |
| Mail | client SMTP maison → **MailHog** en développement |
| Seed | **Faker** (`fakerphp/faker`) — 520 profils, photos générées localement (GD) |
| Déploiement | **docker-compose** : php-apache + mysql + mailhog |

## Démarrage rapide

```bash
cp .env.example .env        # réglages locaux (jamais commité)
docker compose up -d --build
docker compose exec web php scripts/migrate.php   # schéma (idempotent)
docker compose exec web php scripts/seed.php --force   # 520 profils de démo
```

- Application : <http://localhost:8090>
- MailHog (boîte mail de dev) : <http://localhost:8026>

### Comptes de démonstration

Les profils seed ont tous le mot de passe **`SeedPass123!`** (username affiché
dans les suggestions, ex. `brun.etienne`). Les e-mails de vérification /
réinitialisation sont visibles dans MailHog.

## Note de popularité — formule documentée

> **Popularité = (likes reçus) + 2 × (matchs actifs) − (unlikes reçus)**

- Un **match** = like mutuel encore en vigueur ;
- Un **unlike** est tracé (table `unlikes`) et compte négativement ;
- La note est **plafonnée entre 0 et 10**, arrondie à 2 décimales ;
- Elle est **recalculée à chaque like / unlike** par `PopularityService` et
  identique partout où elle est affichée ou triée (profil, suggestions,
  recherche, cartes).

## Choix techniques

- **Orientation non renseignée = bisexuel** (règle du sujet) : un profil sans
  orientation est suggéré à tous les genres et reçoit des suggestions de tous
  les genres compatibles.
- **Compatible d'orientation (croisée)** : un profil `A` est suggéré à `B` si
  `A` peut être intéressé par `B` **et** `B` par `A` (hétéro → genre opposé,
  homo → même genre, bi → tous, genre « autre » réservé aux profils bi).
- **Suggestions** : score = `+100` si même zone (distance < 10 km ou même
  ville) + `2 × tags partagés` + note de popularité ; tri par score par défaut.
- **Temps réel** : le polling 5 s rafraîchit les badges (messages, notifications)
  sur toutes les pages et le fil de chat ouvert — délai observé < 10 s.
- **Blocage / unlike** : coupe réellement côté serveur — le chat refuse l'envoi
  sans like mutuel, le profil bloqué disparaît des recherches et ne génère plus
  de notifications.
- **Uploads** : validation par magic bytes, renommage systématique,
  `public/assets/uploads/.htaccess` interdit toute exécution de script.

## Sécurité

- Mots de passe **jamais en clair** : `password_hash()` / `password_verify()` (bcrypt).
- **Anti-SQLi** : prepared statements sur 100 % des requêtes.
- **Anti-XSS** : échappement Twig systématique (jamais `|raw`).
- **Anti-CSRF** : jeton en session exigé sur tout POST (champ ou en-tête `X-CSRF-Token`).
- **Sessions** : `session_regenerate_id()` à la connexion, cookie `HttpOnly`,
  `SameSite=Lax`, `use_strict_mode`.
- **Validation des entrées et des uploads** côté serveur uniquement.
- `.env` local exclu de git (voir `.gitignore`).

## Scripts

| Script | Usage |
|---|---|
| `scripts/migrate.php` | Applique `database/schema.sql` (idempotent) |
| `scripts/seed.php` | Génère 520 profils Faker + interactions (`--force` pour régénérer) |

> ⚠️ Les fichiers d'images créés par les scripts CLI (seed, tests) appartiennent à
> root : pour que l'édition GD (bonus galerie) puisse les réécrire, relancer
> `docker compose exec web chown -R www-data:www-data public/assets/uploads`
> (fait automatiquement au démarrage du conteneur pour les dossiers existants).

## Structure

```
public/            # document root (bootstrap Slim, assets, uploads protégés)
src/Controllers/   # Auth, Profile, Suggest, Search, User, Chat, Notification, Api
src/Middleware/    # Session, Csrf, Auth, ViewGlobals
src/Services/      # Matching, Popularity, Photo, Mail, Message, Notification
src/Db/            # Query (mini-lib PDO maison) + ConnectionFactory
src/Validation/    # Validator maison
src/Security/      # PasswordPolicy (blacklist + complexité)
templates/         # Twig (layout responsive en-tête/main/pied)
config/            # settings + définitions du conteneur php-di
database/schema.sql
docker/            # Dockerfile php-apache, php.ini, vhost
```

## Bonus implémentés

1. **Galerie photo personnelle** : téléchargement par glisser-déposer (zone de
   dépôt) + édition d'image de base avec GD (rotation 90°/180°/270°, filtres
   N&B/sépia/négatif/flou, recadrage par cadre déplaçable côté client).
2. **Carte interactive des utilisateurs** : Leaflet + tuiles OpenStreetMap,
   marqueurs des profils suggérés positionnés (GPS), popup vers le profil.
   Nécessite une connexion Internet (fallback affiché sinon).
3. **Planification de rendez-vous** entre utilisateurs connectés (like mutuel) :
   liste partagée, création (titre, date, lieu, description), suppression.

Non implémentés (prérequis externes) : OmniAuth (clés OAuth à fournir),
chat vidéo/audio (WebRTC + serveur de signalisation).

## Spécifications et tests

- `SPEC.md` — transcription du sujet v6.0 (obligatoire + bonus).
- `CHECKLIST.md` — checklist de tests pour la soutenance.

---

## Annexe — kit pour agents de coding

Fichiers du kit : **`SPEC.md`** (specs complètes à donner à l'agent),
**`CHECKLIST.md`** (checklist de tests).

❌ Interdits : Symfony, Laravel, Doctrine, Eloquent, gestionnaires de comptes,
validateurs intégrés, Silex. ✅ Autorisés : React, Vue, Angular, Bootstrap, et
toutes les bibliothèques nécessaires (Faker, phpdotenv…).

Rappels : popularité documentée et cohérente ; orientation non renseignée =
bisexuel ; sans photo de profil, le like doit être refusé côté serveur ; tester
la version finale avec CHECKLIST.md dans Firefox et Chrome, mobile et desktop,
chronomètre pour les 10 s ; pas d'ORM (point de contrôle n°1 de la soutenance).
