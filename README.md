<div align="center">

# 💘 Matcha

**Site de rencontre complet** — profils, suggestions intelligentes, like/match, chat et notifications en temps réel.

<sub>Projet de fin d'études — École 42</sub>

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Slim 4](https://img.shields.io/badge/Slim-4-000000?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Twig](https://img.shields.io/badge/Twig-3-339933?logo=twig&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

</div>

---

## ✨ Fonctionnalités

| Domaine | Détails |
|---|---|
| **Authentification** | Inscription (email, username, nom, prénom), vérification par e-mail à lien unique, connexion, mot de passe oublié, déconnexion en un clic. Mots de passe anglais courants refusés (blacklist) + exigence de complexité |
| **Profils** | Genre, préférences sexuelles, biographie, tags réutilisables avec autocomplétion, jusqu'à 5 photos (une photo de profil), note de popularité publique, localisation GPS avec **consentement explicite** (RGPD) ou saisie manuelle |
| **Suggestions intelligentes** | Compatibilité d'orientation croisée (bisexuel par défaut), même zone géographique prioritaire, tags partagés, popularité — liste triable et filtrable (âge, localisation, popularité, tags) |
| **Recherche avancée** | Critères combinés : tranche d'âge, plage de popularité, localisation, un ou plusieurs tags |
| **Consultation de profil** | Toutes les infos sauf email/mot de passe, historique de visites, like (refusé côté serveur sans photo de profil), like mutuel = « connectés », unlike, blocage, signalement, statut en ligne |
| **Chat temps réel** | Réservé aux utilisateurs connectés, réception **≤ 10 s** (polling 5 s), badge global de nouveaux messages sur toutes les pages |
| **Notifications temps réel** | Les 5 événements (like, visite, message, like en retour, unlike), compteur global de non-lues |

## 🖼️ Aperçu

| | |
|---|---|
| ![Page d'accueil](gui-test-screenshots/p01-landing.png) | ![Suggestions](gui-test-screenshots/p02-suggestions.png) |
| ![Chat temps réel](gui-test-screenshots/p03-chat-temps-reel.png) | ![Carte interactive](gui-test-screenshots/p04-carte-bonus.png) |

## 🛠️ Stack

- **Backend** : [Slim 4](https://www.slimframework.com/) (micro-framework : routeur + templating, sans ORM ni gestionnaire de comptes) — PHP 8.3
- **Templating** : Twig (échappement HTML automatique)
- **Base de données** : MySQL 8 via PDO — requêtes SQL écrites à la main (mini-lib `App\Db\Query`, prepared statements systématiques)
- **Architecture** : couche `Repository` (SQL centralisé) + `Entity`/`ViewModel` typés + services métier + contrôleurs fins
- **Temps réel** : polling AJAX 5 s (badges globaux + fil de chat)
- **Images** : extension GD, validation par magic bytes
- **E-mails** : client SMTP maison → MailHog en développement
- **Données de démo** : Faker (520 profils, photos générées en GD)
- **Déploiement** : Docker Compose (php-apache + MySQL + MailHog)

## 🚀 Démarrage rapide

```bash
# 1. Copier la configuration (jamais commitée)
cp .env.example .env

# 2. Lancer l'environnement
docker compose up -d --build

# 3. Appliquer le schéma de base de données
docker compose exec web php scripts/migrate.php

# 4. Générer 520 profils de démonstration
docker compose exec web php scripts/seed.php --force
```

- **Application** : <http://localhost:8090>
- **MailHog** (boîte mail de dev) : <http://localhost:8026>

> ⚠️ Après un `scripts/seed.php`, les fichiers d'images créés par le script appartiennent à `root` : relancez
> `docker compose exec web chown -R www-data:www-data public/assets/uploads`
> pour que l'édition GD (bonus galerie) puisse les réécrire.

## 🔑 Comptes de démonstration

Les 520 profils seed utilisent tous le mot de passe **`SeedPass123!`**
(username visible dans les suggestions, ex. `brun.etienne`).

Comptes de test dédiés au chat : `testeur_a` / `testeur_b` (mot de passe `Azerty123!`, déjà matchés entre eux).

## 📊 Note de popularité

> **Popularité = (likes reçus) + 2 × (matchs actifs) − (unlikes reçus)**

- Un **match** = like mutuel encore en vigueur ; un **unlike** est tracé et compte négativement.
- Note plafonnée entre **0 et 10**, recalculée à chaque like/unlike par `PopularityService`.
- Identique partout où elle est affichée ou triée (profil, suggestions, recherche, cartes).

## 🗂️ Structure du projet

```
public/            # Document root (bootstrap Slim, assets, uploads protégés)
src/
├── Controllers/   # Auth, Profile, Suggest, Search, User, Chat, Notification, Api, Map, Appointment
├── Middleware/    # Session, Csrf, Auth, ViewGlobals
├── Services/      # Matching, Popularity, Photo, Mail, Message, Notification
├── Repository/    # Accès aux données (SQL centralisé, prepared statements)
├── Entity/        # Entités readonly (User, Photo, Tag, Message, Token, Appointment)
├── ViewModel/     # Données typées pour les vues (ProfileCard, UserProfile, Conversation…)
├── Validation/    # Validators par domaine (Register, Profile, Location, Appointment)
├── Security/      # PasswordPolicy (blacklist + complexité)
└── Db/            # Query (mini-lib PDO maison) + ConnectionFactory
templates/         # Twig (layout responsive en-tête/main/pied)
database/          # Schéma MySQL (idempotent)
scripts/           # migrate.php, seed.php
docker/            # Dockerfile php-apache, php.ini, vhost
```

## 🎁 Bonus

1. **Galerie photo** : téléchargement par glisser-déposer + édition d'image GD (rotation, filtres N&B/sépia/négatif/flou, recadrage)
2. **Carte interactive** : Leaflet + OpenStreetMap, marqueurs des profils suggérés positionnés
3. **Rendez-vous** : planification d'événements réels entre utilisateurs connectés

## 🔒 Sécurité

- Mots de passe **jamais en clair** (`password_hash` / bcrypt)
- **Anti-SQLi** : prepared statements sur 100 % des requêtes
- **Anti-XSS** : échappement Twig systématique
- **Anti-CSRF** : jeton en session exigé sur tout POST
- **Sessions** : `session_regenerate_id()` à la connexion, cookies `HttpOnly` + `SameSite=Lax`
- **Uploads** : magic bytes, renommage, dossier sans exécution de scripts
- `.env` local exclu de git

## 🧪 Tests

Checklist complète des scénarios de test (soutenance) : **[CHECKLIST.md](CHECKLIST.md)**.

## 📄 Licence

Projet pédagogique — École 42. Aucune licence d'utilisation publique.
