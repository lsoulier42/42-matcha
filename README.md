# Matcha — Kit pour agents de coding

Fichiers du kit :
- **`SPEC.md`** — les specs complètes (transcription fidèle du PDF v6.0 + recommandations). À donner à l'agent.
- **`CHECKLIST.md`** — la checklist de tests pour vérifier le travail et préparer la soutenance.

## Rappel du cadre (différent de Camagru !)
- **Langage libre** mais **micro-framework uniquement** : routeur + templating, **sans ORM, sans validateurs, sans gestionnaire de comptes** (définition du sujet, fait foi à la soutenance).
  - ✅ Slim (PHP), Flask (Python), Express (Node), Sinatra (Ruby)…
  - ❌ Symfony, Laravel, Django, Rails, Silex.
- **UI libs autorisées** : React, Vue, Angular, Bootstrap…
- **SQL écrit à la main** (pas d'ORM), base gratuite (MySQL/MariaDB/PostgreSQL/Neo4j…).
- **500+ profils** dans la base pour l'évaluation (prévoir un script de seed).
- Chat + notifications **temps réel ≤ 10 s**.
- **Toute faille de sécurité = note 0.** Zéro erreur/warning/notice serveur et client.

## Comment l'utiliser avec un agent de coding

### Option A — un seul agent, tout le projet (recommandé pour démarrer)
```
Lis le fichier SPEC.md du projet Matcha et implémente-le intégralement :
- partie obligatoire : auth (inscription + vérification email + reset + déconnexion),
  profil complet (tags, 5 photos, popularité, géolocalisation avec consentement),
  suggestions intelligentes (orientation, proximité, tags, popularité), recherche avancée,
  consultation de profil (like/unlike, blocage, signalement), chat temps réel ≤ 10s,
  notifications temps réel ≤ 10s (les 5 événements)
- puis les bonus, seulement une fois l'obligatoire parfait
Contraintes absolues : micro-framework SANS ORM (requêtes SQL à la main), zéro erreur/
warning/notice serveur et client, sécurité sans faille (mdp hachés, anti-SQLi, anti-XSS,
validation uploads), .env hors git, seed de 500+ profils.
Vérifie ton travail avec CHECKLIST.md et corrige tout ce qui ne passe pas.
```

### Option B — un agent par partie (projet par étapes)
1. Agent 1 : « Squelette micro-framework + DB + layout responsive + .env + mini-lib de requêtes SQL (sections 1-2 de SPEC.md). »
2. Agent 2 : « Auth complète (section 3.1) : inscription, email de vérification, connexion, reset mdp, déconnexion, blacklist mots de passe anglais. »
3. Agent 3 : « Profil (section 3.2) : genre, préférences, bio, tags réutilisables, 5 photos, note de popularité, géolocalisation consentement/manuelle. »
4. Agent 4 : « Suggestions intelligentes + tri/filtres + recherche avancée (sections 3.3-3.4). »
5. Agent 5 : « Consultation de profil (section 3.5) : like/unlike, historique de visites, blocage, signalement, statut en ligne. »
6. Agent 6 : « Chat + notifications temps réel ≤ 10 s (sections 3.6-3.7) avec badge global. »
7. Agent 7 : « Seed 500+ profils + passe la CHECKLIST.md intégralement et corrige tout. »
8. Agent bonus : « Bonus dans cet ordre : OmniAuth, galerie drag&drop + édition, carte interactive, chat vidéo/audio, rendez-vous. »

## Rappels
- La **note de popularité** doit avoir une formule documentée et cohérente (proposition dans SPEC.md section 4).
- **Orientation non renseignée = bisexuel** — sinon l'algorithme de suggestion est faux.
- Sans photo de profil, **le like doit être refusé côté serveur**.
- Tester la version finale avec CHECKLIST.md **dans Firefox et Chrome**, mobile et desktop, avec un chronomètre pour les 10 s.
