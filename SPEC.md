# Matcha — Spécifications (sujet 42 v6.0)

> Objectif : créer un **site de rencontre** (type dating app) couvrant tout le processus, de l'inscription à la rencontre : profils, matching intelligent, likes, chat temps réel, notifications.
> Ces specs sont une transcription fidèle du PDF officiel (`fr.subject.pdf` v6.0), enrichies de recommandations pour l'agent de coding. Ce qui est **obligatoire** vient du PDF ; ce qui est marqué **[RECO]** est un conseil.

---

## 1. Règles strictes du sujet (ne pas enfreindre)

| Règle | Détail |
|---|---|
| **Zéro erreur** | Aucune erreur, **warning ou notice**, côté serveur ET côté client (console web). |
| **Langage serveur** | **Libre** (PHP, Node, Python, Ruby…). |
| **Micro-frameworks autorisés** | Oui, MAIS avec une définition stricte qui fait foi à la soutenance : un micro-framework = **routeur + éventuellement templating**, qui **n'inclut PAS d'ORM, de validateurs ni de gestionnaire de comptes utilisateurs**. |
| **Exemples valides** | Sinatra (Ruby), **Express (Node)**, **Flask (Python)**, **Slim (PHP)**, Scalatra, Nickel (Rust), Goji (Go), Spark (Java), Crow (C++). ⚠️ **Silex interdit** (intégration Doctrine). ⚠️ **Symfony/Laravel/Django/Rails interdits** (ORM + gestionnaires de comptes intégrés). |
| **UI / front** | Bibliothèques d'interface **autorisées** : React, Angular, Vue, Bootstrap, Semantic, ou combinaisons. JS natif OK aussi. |
| **Base de données** | Relationnelle ou orientée graphe, **gratuite** : MySQL, MariaDB, PostgreSQL, Cassandra, InfluxDB, Neo4j… **Requêtes écrites à la main** (pas d'ORM). Libre de créer sa propre mini-bibliothèque de requêtes. |
| **Données d'évaluation** | La base doit contenir **un minimum de 500 profils distincts** au moment de l'évaluation. |
| **Serveur web** | Libre : Apache, Nginx, ou serveur web intégré. |
| **Compatibilité** | Dernières versions de **Firefox** et **Chrome** au minimum. |
| **Layout** | En-tête + section principale + pied de page, adapté **mobile** et petits écrans. |
| **Sécurité** | Aucune vulnérabilité tolérée : **toute faille = note 0**. Minimum : mots de passe jamais en clair, anti-SQLi, validation de toutes les entrées et uploads, anti-XSS. |
| **Credentials** | Tous identifiants, clés API, variables d'environnement dans un **`.env` local, exclu de Git**. Stockage public = échec du projet. |
| **Bonus** | Évalué **uniquement si la partie obligatoire est parfaite** (100 % des fonctionnalités obligatoires, sans dysfonctionnement). |
| **Règle d'or évaluation** | « Tout ce qui n'est pas explicitement autorisé est strictement interdit. » |

---

## 2. Stack recommandée [RECO]

- **Backend** : **Slim 4 (PHP)** ou **Flask (Python)** ou **Express (Node)** — conforme micro-framework (routeur + templating, pas d'ORM).
- **Base** : **MySQL / MariaDB** via **PDO** (PHP) avec requêtes SQL écrites à la main.
- **Temps réel (chat + notifications)** : WebSocket (ou polling ≤ 10 s) ; délai maximum **10 secondes** imposé par le sujet.
- **Front** : React ou Vue pour l'app, ou vanilla JS + CSS si on préfère la légèreté (les deux sont autorisés).
- **Seed** : script de génération de **500+ profils factices** (avec Faker ou équivalent — les bibliothèques sont autorisées) pour l'évaluation.
- **Déploiement** : docker-compose recommandé (pas obligatoire pour Matcha, contrairement à Camagru, mais pratique).

---

## 3. Partie obligatoire

### 3.1. Inscription et connexion
- [ ] **Inscription** avec au minimum : adresse e-mail, nom d'utilisateur, **nom de famille**, **prénom**, mot de passe sécurisé.
- [ ] **Mots de passe** : les **mots anglais couramment utilisés ne doivent pas être acceptés** (ex. `password`, `love`, `secret`, `admin`… → blacklist + exigence de complexité). [RECO] blacklist de ~100 mots courants + min 8 caractères + majuscule/chiffre/symbole.
- [ ] **Vérification de compte** : après inscription, e-mail avec **lien unique** pour vérifier le compte.
- [ ] **Connexion** : nom d'utilisateur + mot de passe.
- [ ] **Mot de passe oublié** : e-mail de réinitialisation.
- [ ] **Déconnexion en un clic** depuis n'importe quelle page.

### 3.2. Profil utilisateur
Une fois connecté, l'utilisateur complète son profil :
- [ ] **Genre** et **préférences sexuelles**.
- [ ] **Biographie**.
- [ ] **Liste d'intérêts via tags** réutilisables (ex. `#vegan`, `#geek`, `#piercing`) — les tags existants doivent être réutilisables (autocomplétion des tags déjà utilisés).
- [ ] **Jusqu'à 5 photos**, dont une désignée **photo de profil**.
- [ ] Modification à tout moment de ces infos + **nom de famille, prénom, adresse e-mail**.
- [ ] Voir **qui a consulté son profil**.
- [ ] Voir **qui l'a « liké »**.
- [ ] **Note de popularité publique** : définition libre mais **critères cohérents et documentés** [RECO : voir section 4].
- [ ] **Localisation GPS** jusqu'au quartier, avec **consentement explicite** (RGPD). Si refus du GPS → **saisie manuelle obligatoire** de la localisation approximative (ville ou quartier) pour utiliser le matching. Localisation modifiable à tout moment dans le profil.

### 3.3. Navigation (profils suggérés)
- [ ] Accès facile à une liste de **profils suggérés** correspondant aux préférences.
- [ ] Suggestion « intelligente » : une femme hétérosexuelle ne voit que des profils masculins ; **gérer la bisexualité** ; **orientation non renseignée = bisexuel par défaut**.
- [ ] Correspondances déterminées par **plusieurs critères combinés** :
  1. **Proximité** avec la localisation géographique de l'utilisateur (**priorité à la même zone géographique**),
  2. **Plus grand nombre de tags partagés**,
  3. **Note de popularité la plus élevée**.
- [ ] Liste **triable** par : âge, localisation, note de popularité, tags communs.
- [ ] Liste **filtrable** par : âge, localisation, note de popularité, tags communs.

### 3.4. Recherche avancée
- [ ] Recherche avec un ou plusieurs critères : **tranche d'âge**, **plage de note de popularité**, **localisation**, **un ou plusieurs tags** d'intérêt.
- [ ] Résultats **triables et filtrables** par âge, localisation, note de popularité, tags.

### 3.5. Consultation de profil
- [ ] Consultation des profils des autres utilisateurs, affichant **toutes les infos sauf e-mail et mot de passe**.
- [ ] Chaque consultation **enregistrée dans l'historique de visites** (du visiteur).
- [ ] **Liker la photo de profil** d'un autre utilisateur. Like mutuel = **« connectés »** → chat possible. ⚠️ **Sans photo de profil, l'utilisateur ne peut pas liker.**
- [ ] **Retirer un like** → plus de notifications de cet utilisateur, **chat désactivé** entre eux.
- [ ] Consulter la **note de popularité** d'un autre utilisateur.
- [ ] Voir si l'utilisateur est **en ligne** ; sinon **date et heure de sa dernière connexion**.
- [ ] **Signaler** un utilisateur comme « faux compte ».
- [ ] **Bloquer** un utilisateur : n'apparaît plus dans les recherches, ne génère plus de notifications, chat impossible.
- [ ] Afficher clairement si le profil consulté **l'a liké** ou s'ils sont **déjà connectés** ; bouton pour **unliker / se déconnecter** de ce profil.

### 3.6. Chat
- [ ] Quand deux utilisateurs sont **connectés** (like mutuel) → chat **en temps réel** (délai max **10 secondes**).
- [ ] Nouveau message visible **depuis n'importe quelle page** (badge / compteur global).

### 3.7. Notifications
- [ ] Notifications **temps réel** (délai max **10 secondes**) pour :
  1. Réception d'un **like**,
  2. **Consultation de son profil**,
  3. Réception d'un **message**,
  4. Un utilisateur liké **like en retour**,
  5. Un utilisateur connecté **unlike**.
- [ ] Compteur de **notifications non lues visible depuis n'importe quelle page**.

---

## 4. Note de popularité — définition proposée [RECO]

Le sujet laisse la définition libre mais exige des **critères cohérents**. Proposition simple et défendable :

> `Popularité = (likes reçus) + 2 × (matchs/connections actifs) − (unlikes reçus)`, plafonnée et affichée sur 10 (ou échelle 0–100).
> Variante plus riche : normaliser par le nombre de consultations de profil (`likes reçus / vues du profil`) pour récompenser le « taux de conversion » réel.

**Exigence** : la formule doit être **documentée** (dans le README du projet) et **identique partout** où la note est affichée/triée.

---

## 5. Sécurité — points MANDATOIRES

- [ ] **Mots de passe jamais en clair** (`password_hash` / bcrypt / argon2, `password_verify`).
- [ ] **Anti-SQLi** : requêtes préparées systématiquement (PDO prepared statements).
- [ ] **Validation de toutes les entrées de formulaire** (serveur + client) : email, username, mdp, champs profil…
- [ ] **Validation des uploads** : type MIME + extension + `getimagesize()`/magic bytes, renommage, restrictions de taille et de dossier.
- [ ] **Anti-XSS** : échappement de toute sortie (`htmlspecialchars` ou échappement du framework).
- [ ] **Anti-CSRF** [RECO] : jeton CSRF en session sur tous les formulaires POST.
- [ ] **Sessions sécurisées** [RECO] : cookies `HttpOnly`, `Secure`, `session_regenerate_id()` après connexion.
- [ ] **RGPD / consentement** : la géolocalisation exige un consentement explicite ; refus → saisie manuelle (pas de tracking silencieux).

---

## 6. Partie bonus

> Le bonus n'est évalué que si la partie obligatoire est **PARFAITE** (intégrale, sans dysfonctionnement). Sinon, il n'est **pas évalué du tout**.

1. [ ] **OmniAuth** : authentification via réseaux sociaux (OAuth Google / 42 / GitHub…).
2. [ ] **Galerie photo personnelle** : téléchargement par **glisser-déposer** + édition d'image de base (recadrer, pivoter, filtres).
3. [ ] **Carte interactive des utilisateurs** (localisation GPS plus précise via JavaScript).
4. [ ] **Chat vidéo ou audio** pour les utilisateurs connectés.
5. [ ] **Planification de rendez-vous / événements réels** pour les utilisateurs matchés.

---

## 7. Ordre d'implémentation conseillé [RECO]

1. **Squelette** : micro-framework (routeur + templating), base de données, layout en-tête/main/pied responsive, `.env`, connexion DB, mini-bibliothèque de requêtes SQL.
2. **Auth** : inscription (email, username, nom, prénom, mdp sécurisé + blacklist mots anglais), vérification par email (lien unique), connexion, reset mdp, déconnexion, session sécurisée + CSRF.
3. **Profil** : genre, préférences, bio, tags réutilisables, 5 photos (dont profil), édition, note de popularité, localisation (GPS consentement / saisie manuelle).
4. **Matching & navigation** : algorithme de suggestion (orientation sexuelle, proximité, tags partagés, popularité), tri + filtres.
5. **Recherche avancée** : critères combinés, tri + filtres.
6. **Consultation de profil** : affichage, historique de visites, like/unlike, blocage, signalement, statut en ligne.
7. **Chat temps réel** : WebSocket/polling ≤ 10 s, badge global de nouveaux messages.
8. **Notifications temps réel** : les 5 événements, compteur global non lues.
9. **Seed** : script générant **500+ profils** cohérents (avec photos de démo).
10. **Nettoyage** : zéro erreur/warning/notice (PHP, serveur, console navigateur), tests sécurité, tests mobiles.
11. **Bonus** (seulement si obligatoire parfait) : OmniAuth → galerie drag&drop → carte interactive → chat vidéo → rendez-vous.

---

## 8. Pièges connus (à vérifier en fin de projet)

- ⚠️ **500 profils minimum** : sans seed, l'évaluation est impossible — vérifier `SELECT COUNT(*)` avant la soutenance.
- ⚠️ **Le chat/notifications doivent être temps réel** : un délai > 10 s = non conforme (tester avec chronomètre).
- ⚠️ **Blacklist des mots de passe anglais courants** : oubliée par 90 % des projets, pourtant explicite dans le sujet.
- ⚠️ **Like sans photo de profil** : doit être refusé côté serveur, pas seulement caché côté client.
- ⚠️ **Unblock/unlike** : le chat et les notifications doivent être **réellement coupés**, pas juste masqués.
- ⚠️ **Orientation par défaut** : profil sans orientation = bisexuel (sinon les suggestions sont fausses).
- ⚠️ **Zéro notice PHP** : une simple variable non définie dans une vue peut faire perdre des points — `error_reporting(E_ALL)` en dev, logs propres.
- ⚠️ **Géolocalisation** : pas de consentement explicite = faille RGPD évoquée à la soutenance.
- ⚠️ **Requêtes SQL à la main** : pas d'ORM (Doctrine, Eloquent, SQLAlchemy…) — c'est un point de contrôle de la définition du micro-framework.
