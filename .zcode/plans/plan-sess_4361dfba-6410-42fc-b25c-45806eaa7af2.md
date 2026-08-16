# Refactoring : DTO complets (entités + vues) à la place des tableaux associatifs

## Objectif
Remplacer les tableaux associatifs par des classes typées : **entités** (rows SQL) et **ViewModels** (données affichées). Zéro changement de comportement, rejeu complet des tests après migration.

## 1. Entités — `src/Entity/` (classes `readonly`, propriétés publiques typées, named constructor `fromRow()`)

| Classe | Propriétés |
|---|---|
| `User` | id, email, username, nom, prenom, passwordHash (?string), genre, orientation, bio, birthdate, notePopularite, ville, lat, lng, gpsConsent, emailVerifie, actif, bloqueJusqua, derniereConnexion, createdAt + `withoutPassword()` (session) |
| `Token` | id, userId, type, token, expiresAt, used, createdAt |
| `Photo` | id, userId, path, isProfile, position |
| `Tag` | id, name |
| `Message` | id, fromUserId, content, sentAt, ts + `toApiArray()` (JSON du polling) |
| `Appointment` | id, title, description, location, startLabel, isPast, otherId, otherPrenom |

## 2. ViewModels — `src/ViewModel/` (mêmes conventions)

| Classe | Rôle |
|---|---|
| `ProfileCard` | carte universelle (suggestions, recherche, visites, likes) : id, prenom, age, ville, distanceKm, popularityDisplay, sharedTags, bio, avatar, date (?string) |
| `UserProfile` | profil public consulté + mon profil : id, username, prenom, genre, orientation, bio, age, ville, popularityDisplay, isOnline, lastSeen, avatar |
| `Conversation` | liste des messages : id, prenom, avatar, lastMessage, lastMessageAtLabel, unread |
| `NotificationItem` | id, type, label, createdLabel, actorId, actorPrenom, avatar, read (bool) |
| `MapMarker` + `MapView` | carte interactive (l'exemple cité MapController) : me (?MapMarker) + markers[] ; `toArray()` pour le JSON Twig |
| `GeoPoint` | coordonnées GPS simples (lat, lng) pour les requêtes partielles |

## 3. Repositories migrés (retours typés)
- `UserRepository` : `?User` (findById/findByUsername/findByEmail/findActiveById), bool (emailExists…), `?GeoPoint` (findWithPosition), `MapMarker[]` (findPositionsByIds), `create()/update()` inchangés (array, Query::insert)
- `TagRepository` : `Tag[]` (listByUser), `string[]` (all/search), int[] (idsForUser)
- `PhotoRepository` : `Photo[]`, `?Photo`, bool (hasProfilePhoto)
- `LikeRepository` : `ProfileCard[]` (listLikers), bool/int (counts)
- `VisitRepository` : `ProfileCard[]` (listVisitors)
- `MessageRepository` : `Conversation[]`, `Message[]`, `?UserProfile` (userInfo)
- `NotificationRepository` : `NotificationItem[]`
- `AppointmentRepository` : `Appointment[]`
- `TokenRepository` : `?Token`

## 4. Services et contrôleurs
- `MatchingService` : construit `ProfileCard[]` (le décorateur `decorate()` devient un mapping objet) ; logique d'orientation/score/tri inchangée
- Contrôleurs : plus aucun formatage manuel (`decorate`, `ageOf`, `lastSeen`, `relativeTime`) — les DTO portent ces calculs (`fromRow()`)
- `ChatController` : `Message::toApiArray()` pour l'API JSON (le JS ne change pas)
- Session : `$_SESSION['user']` = objet `User` **sans passwordHash** (`withoutPassword()`)
- Seed : reste en array (outil de dev, inserts bruts dans Query) — documenté

## 5. Templates Twig migrés (propriétés camelCase)
`partials/user_card`, `suggestions/index`, `search/index`, `profile/show`, `profile/visits`, `profile/likes`, `user/show`, `messages/index`, `messages/show`, `notifications/index`, `map/index` (via `toArray()`), `appointments/index`, `base.html.twig` (current_user objet). Twig lit les propriétés publiques nativement.

## 6. Vérification (zéro régression) — obligatoire avant commit
1. Lint PHP de tous les fichiers + `composer dump-autoload` (nouvelles classes PSR-4)
2. Smoke test des 16 routes
3. Rejeu des flux critiques curl (inscription → vérification → login, like → match → chat, blocage, recherche, CSRF, uploads)
4. Vérification des **JSON** : `/api/poll`, `/api/messages/{id}` (clés identiques au JS), `/api/tags`, JSON de la carte
5. Logs serveur zéro erreur + test navigateur (suggestions, profil, chat, carte)
6. Commit + push

## Contraintes
- Toujours pas d'ORM : les repositories wrappent Query (prepared statements)
- `readonly` PHP 8.3 (conteneur OK) — propriétés immuables, mapping via `fromRow()`
- Le JS client ne change **pas** (JSON mappé explicitement via `toApiArray()`/`toArray()`)