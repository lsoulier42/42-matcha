# Matcha — Refonte visuelle : Design System & Plan d'implémentation

> Positionnement « la rencontre chaleureuse et premium », tokens, typographie, composants, écrans clés —
> puis plan d'implémentation par phases, ancré dans les fichiers réels du projet (Twig + CSS custom, Slim 4).

---

## Partie I — Design System

### 1. Positionnement

**« La rencontre chaleureuse et premium »** : chaud, humain, soigné. L'inverse exact du rendu froid et stérile
des templates par défaut. L'élégance de Hinge/Bumble, avec une chaleur méditerranéenne.

Ce que ça implique concrètement :

| Froid / stérile (état actuel) | Chaud / premium (cible) |
|---|---|
| Rose fuchsia `#e91e63` criard | Terracotta `#E3653F` chaud et profond |
| Noir pur, gris neutres | Bruns chauds `#2E2A26`, ombres teintées |
| Font système uniquement | Contraste serif (Fraunces) / sans (Inter) |
| Angles 10 px, bordures fines | Rayons 20–24 px, surfaces arrondies, ombres douces |
| Marqueurs carte bruts (pin Leaflet) | Pastilles rondes personnalisées |
| Filtres en formulaire scolaire | Chips tactiles, presque un jeu |
| Alertes bleues système | Teintes chaudes (terracotta, sauge, brique) |

**3 mots-clés à garder en tête pour chaque décision** : *chaleur* (palette, ombres, arrondis),
*humain* (typo serif, micro-animations, états vides mignons), *soigné* (détails, cohérence, focus visible).

### 2. Design tokens

Déclarés dans `:root` de `public/assets/css/style.css`. Une seule source de vérité, aucune couleur en dur ailleurs.

```css
:root {
    /* ——— Palette ——— */
    --bg:          #FBF6F1;   /* crème ivoire chaud, fond global */
    --surface:     #FFFFFF;   /* cartes, champs, bulles « autre » */
    --surface-soft:#F6EDE4;   /* panneaux secondaires, inputs sur fond teinté */
    --line:        #EFE3D8;   /* bordures fines */
    --line-strong: #E0CDBB;   /* bordures appuyées (hover, séparateurs) */

    --accent:      #E3653F;   /* terracotta — liens, icônes, marque, teintes, grands textes décoratifs */
    --accent-dark: #C44E2B;   /* surfaces à texte blanc (boutons, chips actives, badges…) — AA 4.7:1 */
    --accent-soft: #F3C9B6;   /* pastilles, tags, fonds décoratifs */
    --accent-tint: #FBE9E0;   /* chips actives, alertes info, sélection */

    --sage:        #8FA98E;   /* en ligne, matches — à utiliser avec parcimonie */
    --sage-soft:   #E6EDE3;   /* fond des alertes succès, badges « en ligne » */
    --sage-dark:   #6E8A6D;   /* texte sur fond sauge */

    --ink:         #2E2A26;   /* texte principal — brun foncé chaud, jamais de noir pur */
    --ink-2:       #7A7168;   /* métadonnées, descriptions */
    --ink-3:       #A99E92;   /* heures, légendes, placeholders */

    --success:     #5F8F5E;
    --danger:      #B4452E;   /* brique chaude, pas de rouge système */
    --danger-soft: #F7E3DD;

    /* ——— Rayons ——— */
    --r-sm: 10px;  --r-md: 14px;  --r-lg: 20px;  --r-xl: 24px;  --r-pill: 999px;

    /* ——— Ombres : chaudes, teintées de brun, très douces ——— */
    --shadow-1: 0 1px 2px rgba(46, 42, 38, 0.05), 0 2px 8px rgba(46, 42, 38, 0.05);
    --shadow-2: 0 4px 16px rgba(46, 42, 38, 0.08);
    --shadow-3: 0 12px 32px rgba(46, 42, 38, 0.12);

    /* ——— Focus visible ——— */
    --ring: 3px solid rgba(227, 101, 63, 0.45);

    /* ——— Typographie ——— */
    --font-serif: "Fraunces", Georgia, "Times New Roman", serif;
    --font-sans:  "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}
```

> **Migration** : conserver temporairement les anciens `--c-*` comme alias des nouveaux tokens
> (`--c-primary: var(--accent)` …) pendant la refonte, puis les supprimer en Phase 6.
> Interdiction formelle : toute couleur **bleue** ou autre couleur système.

### 3. Typographie

Le contraste **serif élégante / sans sobre** est la signature « pas un projet d'école ».

- **Fraunces** (Google Fonts, graisses 400–600, italique) — marque, titres, prénom sur les cartes, grands chiffres.
- **Inter** (Google Fonts) — corps, formulaires, métadonnées, UI.
- Fallbacks système propres : `Georgia` pour le serif, `system-ui` pour le sans — le design reste correct hors-ligne.

Échelle :

| Usage | Spec |
|---|---|
| Display (hero / slides onboarding) | `clamp(2rem, 5vw, 3rem)`, Fraunces 500, leading 1.1, `-0.01em` |
| H1 de page | 1.75rem, Fraunces 600 |
| H2 de section | 1.3rem, Fraunces 600 |
| Prénom sur carte profil | 1.6rem, **Fraunces italique** |
| Corps | 0.95–1rem, Inter 400, leading 1.55 |
| Métadonnées | 0.85rem, Inter 400, `--ink-2` |
| Légendes / heures | 0.78rem, `--ink-3` |

**Marque** : le nom « Matcha » s'écrit en **Fraunces italique**, couleur accent, avec un léger `letter-spacing: 0.01em`.

### 4. Principes transverses

1. **Mobile-first** : toutes les grilles partent de la colonne simple, montent en multi-colonnes via `minmax()`.
2. **Focus visible** partout : `:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }`
   (les inputs gardent leur focus mais teinté accent, jamais bleu).
3. **Animations légères** : 150–250 ms, `ease-out`, uniquement `transform`/`opacity` (perf),
   systématiquement désactivées sous `@media (prefers-reduced-motion: reduce)`.
4. **Accessibilité** : contrastes ≥ 4.5:1 pour le texte de taille normale (`--ink-2` sur blanc ≈ 4.6:1 ✓) ;
   `--ink-3` réservé au décoratif. Tous les boutons iconographiques ont un `aria-label`.
5. **Composants réutilisables** : chaque élément répété vit dans un partial Twig (navbar, carte profil,
   bulle chat, état vide, chips) — jamais dupliqué de page en page.
6. **Le HTML sémantique et les formulaires POST (CSRF) ne bougent pas** : on ne change que le style et,
   au pire, on *améliore* avec du JS en enhancement progressif (le site fonctionne sans JS).

### 5. Composants

#### 5.1 Boutons
- **Primary** : fond `--accent`, texte blanc, rayon `--r-pill` (signature chaleureuse), hover `--accent-dark`
  + léger lift (`translateY(-1px)` + `--shadow-2`), padding généreux `0.65rem 1.4rem`.
- **Outline** : bordure `--accent`, texte `--accent-dark`, hover fond `--accent-tint`.
- **Ghost** : bordure `--line`, texte `--ink`, hover bordure `--line-strong`.
- **Danger** : fond `--danger`. Icônes du chat/like : boutons ronds 48 px (accent pour like, line pour nope).
- Tous : `border-radius: var(--r-pill)`, `font-weight: 600`, focus ring.

#### 5.2 Champs de formulaire
- Fond `--surface`, bordure `--line`, rayon `--r-md`, focus : bordure accent + anneau `--ring`.
- Labels : 0.85rem, 600, `--ink`. Erreurs : `--danger` (message sous le champ, pas seulement la bordure).
- **Les inputs deviennent des pilules** (rayon `--r-pill`) pour les recherches et le chat — familier, tactile.

#### 5.3 Chips (tags, filtres)
- Tags : pilule, fond `--accent-tint`, texte `--accent-dark`, croix × pour retirer.
- Filtres : pilule togglable — active = fond `--accent`, texte blanc ; inactive = fond `--surface`, bordure `--line`.
  Implémentation : `<label class="chip"><input type="checkbox" hidden>…</label>` (sémantique, fonctionne sans JS).

#### 5.4 Avatars
- Tailles : 40 (liste), 48 (conversation/notif), 64 (chat header), plein format sur les cartes.
- Round 50 % pour les petits, **rayon `--r-lg` pour les grandes cartes** (carré doux = plus premium).
- Placeholder (profil sans photo, photos GD du seed) : dégradé `--accent-soft → --accent`, initiale en Fraunces blanche.

#### 5.5 Badges
- Compteurs non-lus (messages, notifs) : pastille 18 px, fond `--accent`, texte blanc, bordure 2 px `--surface`.
- « En ligne » : pastille 8 px `--sage` + libellé `--sage-dark` (rare, jamais envahissant).

#### 5.6 Alertes (feedback)
- `info` → fond `--accent-tint`, texte `--accent-dark`, bordure `--accent-soft`.
- `success` → fond `--sage-soft`, texte `--sage-dark`, bordure sauge claire.
- `error` → fond `--danger-soft`, texte `--danger`.
- Rayon `--r-md`. **Aucun bleu.**

#### 5.7 Carte profil grand format (`partials/user_card.html.twig` → `partials/profile_card.html.twig`)
La pièce maîtresse du produit. Format carte de rencontre :

```
┌──────────────────────────────┐
│  ◖photo hero (4/5)◗          │  ← coins haut arrondis, cover
│                              │
│   [8.2]           [en ligne] │  ← popularité : pastille discrète en haut à droite
│                              │      (flamme/soleil SVG + score), pas un gros chiffre
│                              │
│  Léa · 26 ans  (Fraunces     │  ← scrim dégradé bas → nom en italique
│  italique, blanc)            │
│  📍 Lyon · à 3 km            │
│  #yoga #brunch #vinyl        │  ← 2-3 chips tags
└──────────────────────────────┘
```

- Carte : fond `--surface`, rayon `--r-xl` (24 px), `--shadow-2`, hover `--shadow-3` + lift 4 px.
- Photo hero : `aspect-ratio: 4/5`, `object-fit: cover`, `loading="lazy"`, scrim dégradé
  `linear-gradient(180deg, transparent 55%, rgba(46,42,38,.55))` pour la lisibilité du nom.
- Popularité **en teaser discret** : pastille translucide (blanc à 20 %) en haut à droite,
  icône soleil + score arrondi ; un `title` explique « Popularité ».
- **Micro-animation de swipe** (like/nope) : au submit du like/unlike, JS ajoute `.is-liking` /
  `.is-noping` (translation X + rotation + fade, 300 ms) avant navigation. Enhancement progressif :
  sans JS, le formulaire POST fonctionne normalement.

#### 5.8 Bulle de chat (`partials/chat_bubble.html.twig`)
```
moi :   [▣ C'est un match !──] 8:42      → fond accent, texte blanc
autre : [───── 😊──────] 8:41            → fond blanc, bordure line
```
- Rayon 18 px, coin « queue » 6 px côté émetteur, `max-width: 75%`, `word-break: break-word`.
- Bulles consécutives du même auteur : marges réduites (groupement), pas d'espace entre chaque.
- Heure : 0.72rem `--ink-3`, sous la bulle côté aligné.
- Fil de discussion : fond `--bg` (crème), zone de saisie en pilule + bouton d'envoi rond accent.
- Extraire le markup actuel de `messages/show.html.twig` dans le partial avec un flag `mine`.

#### 5.9 Carte géolocalisation (Leaflet)
- Conteneur : rayon `--r-xl`, bordure `--line`, `--shadow-2`.
- **Tuiles réchauffées** : `.leaflet-tile-pane { filter: saturate(.9) sepia(.12) hue-rotate(-10deg); }`
  (la carte OSM devient crème/chaude au lieu de grise/bleutée).
- **Pastilles personnalisées** (`L.divIcon`) au lieu des pins bruts :
  rond 36–48 px, fond `--accent-soft`, bordure 2 px `--surface`, initiale du prénom en Fraunces italic.
  Position de l'utilisateur : fond `--accent`, texte blanc.
- **Brouillard de popularité** : taille et halo proportionnels à la note —
  popularité ≥ 7 : pastille agrandie + halo doux (`box-shadow: 0 0 0 8px rgba(227,101,63,.18)`) ;
  populaire faible : pastille plus petite et légèrement translucide.
- Popups : fond blanc, rayon 16 px, prénom en Fraunces, métadonnées en `--ink-2`.

#### 5.10 État vide illustré (`partials/empty_state.html.twig`)
- Remplace tous les `alert alert-info` « aucune conversation / aucun match / aucun résultat ».
- Illustration inline SVG (aucun asset externe) par type : cœur (+ cœur brisé), bulles de chat,
  loupe, carte, calendrier — traits ronds, palette terracotta/sauge sur fond `--accent-tint`.
- Titre en Fraunces, texte en `--ink-2`, CTA optionnel (bouton primary vers la page utile).
- Usage : `{% include 'partials/empty_state.html.twig' with { icon: 'chat', title: '…', text: '…', cta: { label: 'Voir les suggestions', href: '/suggestions' } } %}`.

#### 5.11 Navigation
- Header sticky, fond `--surface`, bordure basse `--line`. Marque en Fraunces italic accent.
- Liens : pilules hover `--accent-tint` ; page active = texte `--accent-dark` + pastille douce.
- Mobile : **barre d'onglets basse** (5 icônes : suggestions, recherche, carte, messages, profil) —
  le pattern natif des apps de rencontre, avec badge non-lus ; le menu hamburger actuel disparaît.
- `base.html.twig` : extraire le header dans `partials/navbar.html.twig`.

### 6. Écrans clés

#### 6.1 Onboarding (page d'accueil, `home/index.html.twig`)
3–4 slides de valeur, en scroll-snap horizontal + pastilles de navigation (dots) :

1. **« Des rencontres qui vous ressemblent »** — suggestions intelligentes (icône cœur/compas).
2. **« Des conversations qui commencent bien »** — chat en temps réel (icône bulles).
3. **« Votre ville, vos gens »** — carte géolocalisée (icône pastille).
4. **« La popularité, sans pression »** — teaser discret (icône soleil).

Chaque slide : visuel SVG large + titre Fraunces + texte `--ink-2` + (slide 1) CTA
« Créer un compte » (primary) / « Se connecter » (outline). Si l'utilisateur est connecté :
variante compacte avec CTA « Voir les suggestions » et « Mes messages ».

#### 6.2 Suggestions (`suggestions/index.html.twig`)
- Grille de **cartes profil grand format** (5.7) : 1 colonne mobile, 2 tablette, 3 desktop.
- Filtres en chips (5.3), tri en chips déroulées sous le titre.
- État vide : illustration + conseil « complétez votre profil » avec liens vers `/profile`.
- Boutons like/nope sur la carte (enhancement progressif, POST préservé) → swipe animation.

#### 6.3 Profil public (`user/show.html.twig`)
- **Photo hero en grand** (pleine largeur de la colonne, rayon bas `--r-lg`, hauteur ~55vh mobile /
  ratio 4/5 desktop), nom en Fraunces italic par-dessus, teaser popularité, statut (sage « En ligne »).
- Bio en texte aéré, tags en chips, galerie en grille arrondie.
- Zone d'action : boutons ronds géants — **like** (cœur, accent, micro-animation « pop »),
  **nope/retirer** (ghost), **bloquer/signaler** discrets en bas.
- **Écran « C'est un match ! »** : overlay plein écran (fond crème), deux avatars qui se rejoignent
  (animation de rapprochement 400 ms), « C'est un match ! » en Fraunces, boutons
  « 💬 Discuter » et « Continuer ». Déclenché par enhancement JS sur le POST du like
  (le contrôleur renvoie un indicateur de match — petite branche JSON à ajouter, optionnelle).

#### 6.4 Chat (`messages/show.html.twig`)
- Header : avatar + prénom (Fraunces) + ville, statut en ligne en sauge.
- Bulles 5.8, fil crème, saisie pilule + bouton rond.
- Liste des conversations : avatars ronds, aperçu, heure, badge non-lu accent,
  item actif avec bordure gauche accent.

#### 6.5 Recherche (`search/index.html.twig`)
- **Chips de filtres, pas un formulaire** : tri (chips), tranche d'âge (2 mini-pilules numériques),
  popularité (pilule), ville (pilule avec icône), tags (chips togglables). Bouton « Filtrer »
  en primary + « Réinitialiser » en ghost. Le GET reste identique côté serveur.
- Résultats : grille de cartes grand format + état vide illustré si zéro résultat.

#### 6.6 Notifications & messages (listes)
- Items : avatar rond, texte, date `--ink-3`, non-lu = pastille accent + fond `--accent-tint` très léger.
- CTA « Voir » en ghost → pilule.

#### 6.7 Carte (`map/index.html.twig`)
- 5.9 : tuiles réchauffées + pastilles + halo popularité + popups stylés.
- Compteur « N profils autour de vous » en `--ink-2`, lien fallback conservé.

#### 6.8 Mon profil (`profile/show.html.twig`) et pages secondaires
- Même langage (form-card rayon `--r-lg`, ombre douce, chips tags, photo-grid arrondie,
  drop-zone réchauffée). Aucune refonte structurelle, uniquement l'application des tokens.

### 7. Qualité, accessibilité, performance

- **Contrastes** : les surfaces à texte blanc (boutons primary, chips actives, badges, pastille « moi »,
  bouton envoyer/like) utilisent **`--accent-dark`** (#C44E2B, blanc AA 4.7:1 — le terracotta clair #E3653F
  ne passe pas 4.5:1). `--accent` reste pour les liens, icônes, teintes et les grands textes décoratifs
  (tampon LIKE, titre « C'est un match ! » — AA large ≥ 3). Tout **texte sur fond teinté** (`--accent-tint`) —
  lien de nav actif, alertes info, drop-zone, pilule popularité, tags (`.tag-chip`) — utilise le token
  **`--accent-ink`** (#B94523, brun-rouge, AA 4.53:1 — `--accent-dark` ne ferait que 3.99:1).
- **Reduced motion** : `@media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }`.
- **Perf** : les seuls ajouts externes sont les deux polices (preconnect + `display=swap`) ;
  Leaflet reste en CDN comme aujourd'hui. Pas de framework JS.
- **Validation** : parcourir `CHECKLIST.md` en parallèle ; les pages doivent passer Firefox + Chrome,
  desktop + mobile, avec et sans JS (les formulaires POST restent fonctionnels).

### 8. Interdits

- ❌ Couleur bleue ou « système » par défaut (boutons, liens, focus, alertes, map).
- ❌ Noir pur (`#000`) pour le texte et ombres neutres grises.
- ❌ Bordures/pins Leaflet par défaut, alertes `alert-info` bleues.
- ❌ Changer le comportement backend (routes, CSRF, GET des filtres, POST des likes).
- ❌ Dupliquer du markup entre pages : tout composant répété → partial.

---

## Partie II — Plan d'implémentation

Phases ordonnées, chacune livrant un état **visible et testable** dans Docker (`docker compose up -d`,
<http://localhost:8090>). Chaque phase se termine par une vérification visuelle + `CHECKLIST.md` à jour.

> **Statut (août 2026)** : Phases 0–6 implémentées et vérifiées dans Docker (tokens, typo, atomes, navbar,
> carte profil, bulles chat, états vides, filtres chips, onboarding, profil public + match, carte Leaflet,
> messages/notifs, profil perso, animations, nettoyage `--c-*`). **Phase 7 terminée** : QA finale passée
> (parcours CHECKLIST Firefox/Chrome desktop + mobile, sans JS, contrastes AA, débordements, console),
> captures `gui-test-screenshots/` et tableau « Aperçu » du README rafraîchis.

### Phase 0 — Fondations : tokens, typo, base (1 session)
**Fichiers** : `public/assets/css/style.css`, `templates/base.html.twig`

- [x] `:root` : remplacer les tokens par la palette du §2 (alias `--c-*` conservés temporairement).
- [x] Charger Fraunces + Inter dans `base.html.twig` (preconnect + stylesheet Google Fonts, `display=swap`).
- [x] Typo de base : `body { font: var(--font-sans); background: var(--bg); color: var(--ink); }`,
      titres `h1–h3 { font-family: var(--font-serif) }`, marque en Fraunces italic.
- [x] `:focus-visible` global (ring accent), `::selection` en `--accent-tint`.
- [x] Ombres, rayons, container, `main` : appliquer les tokens.
- [x] ✅ Vérif : la page d'accueil passe au crème/terracotta, titres en serif, focus visible orange.

### Phase 1 — Atomes : boutons, champs, alertes, chips, avatars, badges (1–2 sessions)
**Fichiers** : `style.css` (sections Boutons, Formulaires, Alertes, Tags, Avatars)

- [x] Boutons : 4 variantes en pilules, hovers avec lift, `--danger` brique.
- [x] Champs : rayon `--r-md`, focus accent, erreurs en `--danger` (message sous champ).
- [x] Alertes : 3 variantes chaudes (accent-tint / sage / brique) — zéro bleu.
- [x] Chips tags + avatars (dégradé terracotta, tailles 40/48/64) + badges accent.
- [x] ✅ Vérif : formulaire de connexion, profil perso, notifications.

### Phase 2 — Layout & navigation (1 session)
**Fichiers** : `base.html.twig`, `partials/navbar.html.twig` (nouveau), `style.css` (En-tête)

- [x] Extraire le header dans `partials/navbar.html.twig`, include depuis `base.html.twig`.
- [x] Marque Fraunces italic accent, liens pilules + état actif.
- [x] **Mobile** : barre d'onglets basse (5 items + badges) ; supprimer le hamburger.
- [x] Footer : texte `--ink-2`, bordure `--line`.
- [x] ✅ Vérif : navigation sur toutes les pages, mobile (DevTools).

### Phase 3 — Composants partagés (2 sessions)
**Fichiers** : `partials/profile_card.html.twig` (nouveau, remplace `user_card.html.twig`),
`partials/chat_bubble.html.twig` (nouveau), `partials/empty_state.html.twig` (nouveau),
`partials/filters.html.twig`, `style.css`

- [x] **Carte profil grand format** (§5.7) : photo hero 4/5, scrim, nom Fraunces italic,
      teaser popularité (pastille), ville/distance, chips tags, boutons like/nope (enhancement).
- [x] **Bulle chat** (§5.8) : extraire de `messages/show.html.twig`, flag `mine`, groupement.
- [x] **État vide illustré** (§5.10) : 6 icônes SVG inline (cœur, chat, loupe, carte, calendrier, soleil).
- [x] **Filtres → chips** (§5.3/6.5) : refondre `filters.html.twig` sans changer le GET ni les noms de champs.
- [x] ✅ Vérif : suggestions (cartes), chat (bulles), états vides (messages, recherche), filtres en chips.

### Phase 4 — Écrans principaux (3–4 sessions)
**Fichiers** : `home/index.html.twig`, `suggestions/index.html.twig`, `user/show.html.twig`,
`messages/index.html.twig`, `messages/show.html.twig`, `notifications/index.html.twig`,
`search/index.html.twig`, `map/index.html.twig`, `app.js`, `style.css`

- [x] **Onboarding** (§6.1) : slides scroll-snap + dots + CTA (variante connecté/déconnecté).
- [x] **Suggestions** : grille de cartes grand format, état vide illustré, tri en chips.
- [x] **Profil public** : photo hero, teaser popularité, statut sauge, boutons ronds,
      micro-animation like (`.is-liking`/`.is-noping` dans `app.js`), overlay « C'est un match ! » (optionnel).
- [x] **Chat** : bulles + fil crème + saisie pilule ; liste conversations restylée.
- [x] **Notifications** : items + non-lu + état vide.
- [x] **Recherche** : chips filtres actives, grille résultats, état vide.
- [x] **Carte** : pastilles `divIcon` + halo popularité + tuiles réchauffées + popups stylés + état vide.
- [x] ✅ Vérif : parcours complet du seed (like → match → chat), carte, recherche, mobile.

### Phase 5 — Mon profil & secondaires (1 session)
**Fichiers** : `profile/show.html.twig`, `profile/likes.html.twig`, `profile/visits.html.twig`,
`appointments/index.html.twig`, `auth/*.html.twig`, `style.css`

- [x] Photo-grid arrondie, upload/drop-zone réchauffés, tags en chips, popularité en teaser.
- [x] Likes/visites : cartes grand format + états vides illustrés.
- [x] Auth : pages centrées, cartes `--r-lg`, pilules — cohérence totale.

### Phase 6 — Animations & nettoyage (1–2 sessions)
**Fichiers** : `style.css`, `app.js`

- [x] Micro-animations : lift des cartes, pop des cœurs, fade-up des sections (200 ms, respect reduced-motion).
- [x] Supprimer les alias `--c-*` et toute couleur/var morte (`grep -r "#e91e63\|--c-" public/assets/css`).
- [x] Passer au peigne fin : focus visible, contrastes (§7), surcharges Leaflet, états hover/touch.

### Phase 7 — QA & documentation (1 session)
- [x] Parcourir `CHECKLIST.md` en entier (Firefox + Chrome, desktop + mobile).
- [x] Retester sans JS : POST likes/chat/filtres fonctionnels (formulaires bruts conservés).
- [x] Rafraîchir les captures `gui-test-screenshots/` et le tableau « Aperçu » du README.
- [x] Tenir `DESIGN.md` à jour si des décisions divergent.

### Récapitulatif

| Phase | Contenu | Fichiers principaux | Sessions | Statut |
|---|---|---|---|---|
| 0 | Tokens, typo, base | `style.css`, `base.html.twig` | 1 | ✅ |
| 1 | Atomes (boutons, champs, alertes, chips) | `style.css` | 1–2 | ✅ |
| 2 | Navbar + barre mobile | `base.html.twig`, `partials/navbar.html.twig` | 1 | ✅ |
| 3 | Cartes profil, bulles, états vides, filtres chips | `partials/*` | 2 | ✅ |
| 4 | Écrans clés (onboarding, suggestions, profil, chat, carte, recherche) | `templates/**`, `app.js` | 3–4 | ✅ |
| 5 | Profil perso, auth, secondaires | `profile/*`, `auth/*` | 1 | ✅ |
| 6 | Animations, nettoyage tokens | `style.css`, `app.js` | 1–2 | ✅ |
| 7 | QA (parcours Firefox/Chrome) + captures + README | `CHECKLIST.md`, `README.md` | 1 | ✅ |

**Ordre de valeur** : Phases 0–3 donnent 80 % de l'impact visuel (palette + typo + cartes profil).
La Phase 4 (écrans) apporte le « wow » (onboarding, match, carte). Les Phases 5–7 sont la finition qui
transforme le résultat en « pas un projet d'école ».
