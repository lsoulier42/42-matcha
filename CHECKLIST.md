# Matcha — Checklist de tests (préparation soutenance)

> À parcourir intégralement avant de dire « c'est fini ». Vérifier **dans Firefox ET Chrome**, en desktop et mobile (DevTools).

## Environnement & zéro erreur
- [ ] Aucune erreur/warning/**notice** dans : logs serveur, console navigateur (F12), onglet Réseau.
- [ ] Layout : en-tête + section principale + pied de page sur toutes les pages.
- [ ] Responsive : affichage correct sur mobile (petits écrans).
- [ ] `.env` local, absent de git.
- [ ] Base de données : `SELECT COUNT(*) FROM users` → **≥ 500 profils distincts**.
- [ ] Requêtes SQL écrites à la main (pas d'ORM) — vérifier le code.

## Inscription & connexion
- [ ] Inscription avec email invalide → refusé.
- [ ] Inscription : nom, prénom, username, email, mot de passe demandés.
- [ ] Mot de passe `password` / `love` / `secret` (mots anglais courants) → **refusé**.
- [ ] Mot de passe faible (courts, sans chiffre) → refusé.
- [ ] E-mail de vérification reçu avec lien unique ; compte inactif tant que non vérifié.
- [ ] Lien de vérification utilisable une seule fois.
- [ ] Connexion username + mdp ; mauvais mdp → refusé.
- [ ] Mot de passe oublié → e-mail de réinitialisation → nouveau mdp fonctionnel.
- [ ] Déconnexion en un clic depuis n'importe quelle page (tester depuis 3 pages différentes).
- [ ] En base : mots de passe hachés, jamais en clair.

## Profil
- [ ] Complétion : genre, préférences sexuelles, biographie.
- [ ] Tags : ajout de `#vegan`, `#geek`… → réutilisables (autocomplétion des tags existants).
- [ ] Photos : jusqu'à 5, une désignée photo de profil.
- [ ] Modification : nom, prénom, email, et toutes les infos profil — reflétées immédiatement.
- [ ] Page « qui a consulté mon profil » : l'historique se remplit.
- [ ] Page « qui m'a liké » : fonctionnelle.
- [ ] Note de popularité publique affichée ; formule cohérente et documentée.
- [ ] Localisation : consentement GPS explicite demandé ; si refus → saisie manuelle ville/quartier **obligatoire** pour le matching.
- [ ] Localisation modifiable depuis le profil.

## Navigation (suggestions)
- [ ] Profil sans orientation → traité comme **bisexuel** (vérifier les suggestions).
- [ ] Femme hétéro → suggestions uniquement masculines ; homme gay → uniquement masculines ; etc.
- [ ] Suggestions priorisent : même zone géographique → tags partagés → popularité.
- [ ] Tri par âge / localisation / popularité / tags communs : fonctionnel.
- [ ] Filtres âge / localisation / popularité / tags : fonctionnels.

## Recherche avancée
- [ ] Recherche par tranche d'âge seule, par plage de popularité, par localisation, par tags.
- [ ] Recherche multi-critères combinés.
- [ ] Résultats triables et filtrables.

## Consultation de profil
- [ ] Toutes les infos affichées **sauf email et mot de passe**.
- [ ] Consultation d'un profil → enregistrée dans l'historique de visites du visiteur.
- [ ] Like possible ; **sans photo de profil, like refusé côté serveur** (tester en manipulant la requête).
- [ ] Like mutuel → statut « connectés » affiché + chat débloqué.
- [ ] Unliker → plus de notifications de cet utilisateur + chat désactivé.
- [ ] Note de popularité d'autrui visible.
- [ ] Statut en ligne visible ; sinon date/heure de dernière connexion.
- [ ] Signalement « faux compte » fonctionnel.
- [ ] Bloquer → l'utilisateur disparaît des recherches, plus de notifications, chat impossible.
- [ ] Le profil consulté montre clairement s'il m'a liké / si on est connectés, avec action unliker/se déconnecter.

## Chat temps réel
- [ ] Chat actif uniquement entre utilisateurs connectés (like mutuel).
- [ ] Message reçu en **≤ 10 secondes** (chronomètre).
- [ ] Nouveau message visible **depuis n'importe quelle page** (badge).
- [ ] Après unlike/blocage : chat coupé, impossible de continuer.

## Notifications temps réel
- [ ] Notification en **≤ 10 s** pour : like reçu, visite de profil, message reçu, like en retour, unlike.
- [ ] Compteur de non lues visible depuis n'importe quelle page.
- [ ] Notifications marquées comme lues après consultation.

## Sécurité (tests manuels)
- [ ] **SQLi** : `' OR 1=1--` dans username/recherche/tags → aucune fuite ni erreur.
- [ ] **XSS** : `<script>alert(1)</script>` dans bio/commentaires/tags → affiché en texte brut.
- [ ] **Uploads** : fichier `.php`/`.exe` refusé ; faux PNG refusé (magic bytes) ; taille limite respectée.
- [ ] **CSRF** : POST forgé sans jeton → refusé.
- [ ] **Accès** : les pages privées exigent une session valide côté serveur (pas juste le bouton caché).
- [ ] Cookies de session `HttpOnly`.
- [ ] `.env`, fichiers sensibles inaccessibles via URL.

## Bonus (seulement si obligatoire parfait)
- [ ] OmniAuth (connexion via réseau social) fonctionnel.
- [ ] Galerie : glisser-déposer + recadrage/rotation/filtres.
- [ ] Carte interactive des utilisateurs.
- [ ] Chat vidéo ou audio.
- [ ] Planification de rendez-vous/événements entre matchés.
- [ ] Chaque bonus : 100 % fonctionnel, sinon il ne compte pas.
