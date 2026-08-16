# Matcha — Kit pour agents de coding

Fichiers du kit :
- **`SPEC.md`** — les specs complètes (transcription fidèle du PDF v6.0 + recommandations). À donner à l'agent.
- **`CHECKLIST.md`** — la checklist de tests pour vérifier le travail et préparer la soutenance.

## Stack validée : Slim 4 / PHP
- **Backend** : **Slim 4** (PHP 8.x) — micro-framework conforme (routeur + middleware, pas d'ORM).
- **Templating** : **Twig** (`slim/twig-view`).
- **Base** : **MySQL / MariaDB** via **PDO**, requêtes SQL écrites à la main (mini-lib `src/Db/Query.php` maison).
- **Auth/sessions/validation/CSRF** : code maison (interdit dans le micro-framework → on le code soi-même).
- **Temps réel** : **polling AJAX 5 s** (suffit pour la contrainte ≤ 10 s), WebSocket Ratchet en option.
- **Images** : extension **GD** + validation magic bytes.
- **Seed** : **Faker** (`fakerphp/faker`) pour 500+ profils.
- **Déploiement** : docker-compose (php-apache + mysql + mailhog).

❌ Interdits : Symfony, Laravel, Doctrine, Eloquent, gestionnaires de comptes, validateurs intégrés, Silex.
✅ Autorisés : React, Vue, Angular, Bootstrap, et toutes les bibliothèques nécessaires (Faker, phpdotenv…).

## Comment l'utiliser avec un agent de coding

### Option A — un seul agent, tout le projet (recommandé pour démarrer)
```
Lis le fichier SPEC.md du projet Matcha et implémente-le intégralement avec Slim 4 (PHP) :
- stack imposée : Slim 4 + Twig + PDO/MySQL (requêtes SQL à la main, PAS d'ORM), sessions
  PHP natives, auth/validation/CSRF codés maison, polling AJAX 5s pour le temps réel, seed Faker
- partie obligatoire : auth (inscription + vérification email + reset + déconnexion, blacklist
  des mots de passe anglais), profil complet (tags, 5 photos, popularité, géolocalisation avec
  consentement), suggestions intelligentes (orientation, proximité, tags, popularité),
  recherche avancée, consultation de profil (like/unlike, blocage, signalement), chat temps
  réel ≤ 10s, notifications temps réel ≤ 10s (les 5 événements)
- puis les bonus, seulement une fois l'obligatoire parfait
Contraintes absolues : zéro erreur/warning/notice serveur et client, sécurité sans faille
(mdp hachés, anti-SQLi, anti-XSS, validation uploads), .env hors git, seed de 500+ profils.
Vérifie ton travail avec CHECKLIST.md et corrige tout ce qui ne passe pas.
```

### Option B — un agent par partie (projet par étapes)
1. Agent 1 : « Squelette Slim 4 : composer require slim/slim slim/psr7 slim/twig-view, bootstrap public/index.php, layout Twig en-tête/main/pied responsive, docker-compose (apache+php, mysql, mailhog), .env, connexion PDO + mini-lib Query (sections 1-2 de SPEC.md). »
2. Agent 2 : « Auth complète (section 3.1) : inscription, email de vérification, connexion, reset mdp, déconnexion, blacklist mots de passe anglais, middleware Auth + CSRF maison. »
3. Agent 3 : « Profil (section 3.2) : genre, préférences, bio, tags réutilisables, 5 photos, note de popularité, géolocalisation consentement/manuelle. »
4. Agent 4 : « Suggestions intelligentes + tri/filtres + recherche avancée (sections 3.3-3.4). »
5. Agent 5 : « Consultation de profil (section 3.5) : like/unlike, historique de visites, blocage, signalement, statut en ligne. »
6. Agent 6 : « Chat + notifications temps réel ≤ 10 s (sections 3.6-3.7) : polling AJAX 5 s + badge global dans le layout Twig. »
7. Agent 7 : « Seed 500+ profils (Faker) + passe la CHECKLIST.md intégralement et corrige tout. »
8. Agent bonus : « Bonus dans cet ordre : OmniAuth, galerie drag&drop + édition GD, carte interactive, chat vidéo/audio, rendez-vous. »

## Rappels
- La **note de popularité** doit avoir une formule documentée et cohérente (proposition dans SPEC.md section 4).
- **Orientation non renseignée = bisexuel** — sinon l'algorithme de suggestion est faux.
- Sans photo de profil, **le like doit être refusé côté serveur**.
- Tester la version finale avec CHECKLIST.md **dans Firefox et Chrome**, mobile et desktop, avec un chronomètre pour les 10 s.
- **Pas d'ORM** : c'est le point de contrôle n°1 de la définition du micro-framework à la soutenance.
