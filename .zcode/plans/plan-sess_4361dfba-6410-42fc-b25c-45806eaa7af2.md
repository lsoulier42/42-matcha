# Refactoring : couche Repository + classes de Validation

## Objectif
Sortir le SQL direct et les règles métier des contrôleurs vers une couche **Repository** (sans ORM — la mini-lib `Query` reste la seule couche d'accès, conforme au sujet) et des classes **Validation** par domaine. Refactoring **purement mécanique : aucun changement de comportement**, suivi d'un rejeu complet des tests pour zéro régression.

## 1. Couche Repository — `src/Repository/` (11 classes, `Query` injecté, autowirées par php-di)

| Repository | Méthodes principales (migration des requêtes actuelles) |
|---|---|
| `UserRepository` | findById, findByUsername, findByEmail, emailExists (avec exclusion), usernameExists, create, update, touchLastLogin, setEmailVerified, findWithPosition (carte), count |
| `TokenRepository` | create, findValidVerify (JOIN users), findValidReset, markUsed |
| `TagRepository` | findByName, create, listByUser, attach, detach, countForUser, search (autocomplétion), all, idsForUser |
| `PhotoRepository` | listByUser, countForUser, findOwned, create, setProfile, clearProfile, promoteNext, delete, profilePhoto |
| `LikeRepository` | exists, add, remove, countReceived, countMatches (mutuel), countUnlikesReceived, recordUnlike |
| `VisitRepository` | record (upsert), listVisitors |
| `BlockRepository` | isBlocked (les 2 sens), add, remove, idsInvolving |
| `ReportRepository` | add |
| `MessageRepository` | conversations (grosse requête), history, send, markRead, unreadCount, userInfo |
| `NotificationRepository` | create, unreadCount, markAllRead, list, clearUnreadFrom |
| `AppointmentRepository` | listFor, create, delete (participant) |

## 2. Services migrés en interne (API publique inchangée)
- `PopularityService` → LikeRepository + UserRepository::update
- `NotificationService` → NotificationRepository (wrapper)
- `MessageService` → MessageRepository + LikeRepository + BlockRepository (canChat)
- `PhotoService` → PhotoRepository (garde upload/GD, requêtes déléguées)
- `MatchingService` → UserRepository::suggestCandidates (SQL centralisé) + TagRepository + BlockRepository ; la logique d'orientation/score/tri reste dans le service

## 3. Classes de Validation — `src/Validation/`
`Validator` générique **inchangé**. Nouvelles classes (chacune : `validate(...): array<string,string>`, vide = OK) :
- `RegisterValidator` — email requis/valide/unique, username format/unique, nom/prénom, mot de passe (PasswordPolicy) + confirmation
- `ProfileValidator` — email (unique sauf soi), genre/orientation (enums), bio, birthdate (16–100 ans)
- `LocationValidator` — GPS valide OU ville obligatoire (règle matching du sujet)
- `AppointmentValidator` — titre, date dans le futur, match actif (canChat)

## 4. Contrôleurs allégés
- `AuthController` → UserRepository, TokenRepository, RegisterValidator (~14 requêtes → 0)
- `ProfileController` → PhotoService, TagRepository, ProfileValidator, LocationValidator
- `UserController` → Like/Visit/Block/Report repositories + services
- `AppointmentController` → AppointmentRepository + AppointmentValidator
- `MapController`, `SuggestController`, `SearchController` → repositories

## 5. Vérification (zéro régression) — obligatoire avant commit
1. Smoke test de **toutes** les routes (GET/POST, connecté + anonyme)
2. Rejeu des flux critiques curl : inscription → vérification email → login, blacklist mdp, like → match → chat (coupé après unlike/blocage), recherche multi-critères, uploads refusés, CSRF 403
3. Logs serveur : zéro erreur/warning/notice
4. Test navigateur rapide (login → suggestions → profil → chat)
5. `git status` propre + commit du refactoring

## Contraintes respectées
- **Pas d'ORM** : les repositories wrappent `Query` (prepared statements) — point de contrôle n°1 de la soutenance intact
- Aucun changement de schéma, de route, de template ou de comportement
- Structure de dossiers étendue mais conforme à l'organisation existante