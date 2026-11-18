# Prompt pour Claude Code : « Studio UAZ », l'éditeur de visuels à presets de registrar.zurcher.edu.mg

> Copie tout ce fichier dans Claude Code, lancé à la racine du dépôt de registrar.zurcher.edu.mg.
> Mets le dossier `studio-uaz/` (ce kit) dans le dépôt, par exemple dans `docs/studio-uaz/`, avant de lancer la session.

---

## 1. Contexte

Tu travailles sur **registrar.zurcher.edu.mg**, le portail de la scolarité de l'**Université Adventiste Zurcher (UAZ)**, à Madagascar. L'université n'avait pas d'identité visuelle formalisée. Nous en avons défini une à partir d'une affiche réelle (la table ronde scientifique TRS 2026), puis nous en avons tiré **six presets** par type de communication.

L'université a déjà **son propre logiciel de retouche photo**. Nous voulons ajouter un **module de montage** : l'utilisateur choisit un preset, remplit des champs, place ses photos, choisit un format, puis exporte un visuel prêt pour Instagram, Facebook ou l'impression, **sans pouvoir sortir de la charte**.

Nom du module : **Studio UAZ**. Langue de l'interface : **français**.

## 2. Ce que contient le kit (`studio-uaz/`)

- `PROMPT-claude-code.md` : ce fichier.
- `assets/logo-uaz.jpg` : le sceau officiel de l'université (1956×2000, fond blanc).
- `assets/illustration-trs.jpg` : l'illustration de la table ronde (exemple d'image d'événement).
- `reference/table-ronde/*.dc.html` : l'affiche d'origine déclinée en 4 formats (4:5, 1:1, 9:16, A4). **C'est la référence visuelle de la façon dont un preset s'adapte à chaque format.**
- `reference/presets/*.dc.html` : la charte et les 6 presets en 4:5.

Les fichiers `.dc.html` viennent d'un outil de maquette. Lis-les comme du HTML/CSS ordinaire : toutes les valeurs (tailles, couleurs, marges, graisses) sont en `style` inline. Ignore `<x-dc>`, `<helmet>`, `support.js` et le bloc `<script type="text/x-dc">`. Les `src="/_blob/…"` désignent le logo (la petite image ronde de l'en-tête) ou l'illustration : remplace-les par les fichiers de `assets/`. Les textes entre crochets `[…]` sont des **champs à remplir**.

## 3. Avant de coder : explore et propose un plan

1. **Explore le dépôt** : langage, framework, routage, authentification, rôles, base de données, stockage de fichiers, build, déploiement, tests. Résume ce que tu trouves.
2. **Trouve le logiciel de retouche existant** (cherche « retouche », « editor », « photo », « image », « crop », « filter »…). Comprends comment il est appelé, ce qu'il reçoit et ce qu'il renvoie. Le Studio doit **réutiliser** ses briques (upload, recadrage, filtres) au lieu de les dupliquer, et proposer un bouton « Retoucher dans l'éditeur » qui envoie la photo d'un emplacement à ce logiciel et récupère le résultat.
3. **Propose un plan** en étapes livrables (voir §11) et attends ma validation avant d'écrire du code. Si un point du présent prompt contredit l'architecture existante, signale-le et propose une alternative.
4. Respecte les conventions du dépôt (style de code, structure des dossiers, i18n, composants existants). Ne change rien en dehors du module sans me demander.

## 4. Charte graphique : les « design tokens »

Ces valeurs sont **verrouillées**. L'utilisateur ne peut choisir ni d'autres couleurs ni d'autres polices.

### Couleurs
| Jeton | Hex | Usage |
|---|---|---|
| `navy` | `#232A5F` | fond général (visible sous forme de filets), titres, cellules pleines |
| `gold` | `#8B7448` | segment mis en valeur dans le titre |
| `goldDeep` | `#85703F` | bloc vedette plein (invité, date limite, « À retenir ») |
| `goldLight` | `#C8AE72` | labels et puces sur fond marine |
| `bronze` | `#7A6535` | labels en capitales sur fond crème |
| `cream` | `#F4F2ED` | fond des cellules |
| `paper` | `#FBFAF7` | corps de texte long (communiqué) |
| `sand` | `#E4DDCB` | zone image vide ; fond des illustrations |
| `ink` | `#1D2233` | texte courant |
| `rule` | `#8C93B8` | filets fins entre lignes de liste |
| `navySoft` | `#3A4380` | ligne secondaire de l'en-tête |

Contraste : tout texte doit atteindre 4,5:1 (3:1 au-dessus de 24 px). Les combinaisons ci-dessus le respectent ; n'en ajoute pas d'autres.

### Typographie
- Police unique : **Archivo** (Google Fonts, licence OFL), axes `wdth` 62–125 et `wght` 400–800. **Auto-héberge les fichiers** (woff2) : pas d'appel à Google au moment de l'export.
- **Titre** : 800, `font-stretch: 92%`, `letter-spacing: -0.025em`, `line-height: 0.98`. Un seul segment du titre peut être en `gold` (champ « mot-clé en or »).
- **Label** : 800, capitales, `letter-spacing` 0.14–0.16em, `bronze` sur crème, `goldLight` sur marine.
- **Texte** : 500 à 700. Jamais sous 12 px sur un visuel 1080 px, jamais sous 9 pt à l'impression.
- Typographie française : espace insécable avant `: ; ! ?` et à l'intérieur des guillemets « », et pas de veuve sur le dernier mot du titre (insécable avant le dernier mot, comme dans les maquettes).

### Grille et signature
- Le fond du visuel est `navy`. Le contenu est une **pile de rangées** séparées par un **espace de 4 px** (3 px en A4) : le marine apparaît en filets. C'est la signature de la marque.
- Chaque rangée est découpée en cellules (crème, marine ou or), elles aussi séparées par 4 px.
- **Une seule cellule pleine de couleur (marine ou or) par rangée.**
- Marge intérieure des cellules : 40 px minimum sur les bords du visuel (sur l'A4 : 40 px soit ≈ 10 mm).
- **En-tête identique sur tous les presets** : logo rond, « Université Adventiste Zurcher » + service émetteur à gauche ; bloc marine à droite avec le **code catégorie** (`goldLight`) et l'**année** (blanc).
- **Pied de page** marine : une mention pratique à gauche (avec une pastille `goldLight`), `zurcher.edu.mg` à droite.

## 5. Les presets

Chaque preset est une **définition de données** (JSON ou équivalent) : catégorie, champs, emplacements d'image et un **layout par format**. Les références visuelles en 4:5 sont dans `reference/presets/`.

Règles communes à tous les champs : longueur maximale imposée (compteur visible), texte qui **s'ajuste automatiquement** (on réduit la taille par pas de 2 px jusqu'à un minimum défini par le preset, puis on affiche une alerte « Texte trop long » et on bloque l'export), champs facultatifs qui **masquent proprement** leur cellule ou la rangée.

### ANN : Annonce (`Preset-Annonce.dc.html`)
- Champs : service émetteur (40), titre (70) + mot-clé en or (25), texte d'accompagnement (220), quand : jour (20) et mois/année (20), lieu (30) + précision (35), contact : nom (30) + téléphone/e-mail (40), « À retenir » (70, facultatif), mention pratique du pied de page (50).
- Image : 1 emplacement **facultatif** (colonne droite du bloc titre). Sans photo, le bloc titre prend toute la largeur.

### EVT : Événement (`Preset-Evenement.dc.html`, et la série `table-ronde/` comme exemple rempli)
- Champs : organisateur (40), type d'événement (35), titre (90) + mot-clé en or, sous-titre (60), date (jour + mois/année), heure (`HH` ou `HHhMM`), lieu + précision, intervenants (**liste de 0 à 4**, 32 car. chacun), invité (nom 30 + fonction 40, facultatif), modération (70, facultatif), mention d'accès (20) + diffusion/inscription (60).
- Image : 1 emplacement (illustration ou photo). Les illustrations à fond blanc se fondent dans le fond `sand` grâce à `mix-blend-mode: multiply`. Fais-en une option « Fondre le fond blanc » (activée par défaut pour les illustrations).
- Sans invité, la liste des intervenants prend toute la largeur. Sans intervenants ni invité, la rangée disparaît et le bloc titre s'agrandit.

### REC : Recrutement (`Preset-Recrutement.dc.html`)
- Champs : service (40), intitulé du poste (45), faculté/département (50), type de contrat (25), lieu de travail (25), date limite (jour + mois, année), profil recherché (**liste de 2 à 5**, 40 car.), pièces et adresse pour postuler (e-mail 45), objet de la candidature (reprend l'intitulé par défaut), mention de pied de page.
- Image : 1 emplacement « photo du campus ».
- Le bloc titre est marine, avec l'étiquette « NOUS RECRUTONS » sur fond `goldLight`.

### COM : Communiqué (`Preset-Communique.dc.html`)
- Champs : service, référence (N°), date, objet (80) + mot-clé en or, **3 paragraphes** (le 1er en gras, 250 car. max chacun), signataire (nom + fonction), renseignements (60).
- Pas d'image. Le preset est **sobre** : c'est le seul où le corps de texte est sur fond `paper`.
- En A4, c'est une vraie page de communiqué : prévois un mode « texte long » qui passe sur 2 pages A4 si nécessaire, avec en-tête et pied répétés.

### INS : Inscriptions (`Preset-Inscriptions.dc.html`)
- Champs : année universitaire, titre (par défaut « Inscriptions ouvertes », mot-clé « ouvertes »), date limite, **filières** (grille de 3 à 6 cellules, 30 car.), pièces à fournir (**liste de 2 à 5**), texte « Où s'inscrire », horaires, mention de pied (frais, etc.).
- Le service émetteur est fixé à « Service de la scolarité ». Le bloc « Où s'inscrire » affiche `registrar.zurcher.edu.mg`. **Option** : générer un QR code vers une URL saisie (marine sur crème, avec une zone de silence suffisante), en remplacement de l'URL écrite dans les formats 9:16 et A4.

### FEL : Félicitations (`Preset-Felicitations.dc.html`)
- Champs : faculté/service, prénom (20) + NOM (25, en or), motif (110), message de l'université (160), date, filière/diplôme.
- Image : 1 emplacement **portrait**, obligatoire (recadrage guidé centré sur le visage).
- Pied de page : la devise du sceau « Spiritus · Mens · Manus — Service ».

### Évolutions prévues (structure à prévoir, pas à implémenter maintenant)
Avis de soutenance, résultats d'examens, calendrier académique, avis de décès / condoléances (palette réduite, sans or), citation / verset du jour. Le moteur doit permettre d'**ajouter un preset sans toucher au code de l'éditeur**, seulement en ajoutant une définition et ses layouts.

## 6. Formats de sortie

| Format | Taille | Usage |
|---|---|---|
| Post 4:5 | 1080×1350 | Instagram et Facebook (format par défaut) |
| Carré 1:1 | 1080×1080 | Instagram et Facebook |
| Story 9:16 | 1080×1920 | Stories Instagram et Facebook |
| A4 portrait | 210×297 mm | impression |

- Chaque preset définit **son layout pour chaque format**. Prends la série `reference/table-ronde/` comme modèle d'adaptation : en 1:1, on compresse et on fusionne des rangées (modération et diffusion dans le pied) ; en 9:16, on empile (image en haut, puis titre, infos, invité, liste) ; en A4, on reprend le 4:5 à l'échelle avec des marges d'impression.
- **Story** : garde les informations clés hors des 250 px du haut et des 250 px du bas (zones couvertes par l'interface d'Instagram). Affiche ces zones en surimpression dans l'aperçu, avec un interrupteur.
- **Export** :
  - PNG en 1080 px de large (et une option ×2) ; JPG qualité 90 pour Facebook.
  - PDF A4 vectoriel (texte sélectionnable, polices incorporées), avec une option **fond perdu de 3 mm** et traits de coupe pour l'imprimeur.
  - Nom de fichier : `UAZ_{CODE}_{AAAA-MM-JJ}_{titre-slug}_{format}.{ext}`.
  - Un bouton « Tout exporter (.zip) » génère les 4 formats d'un coup.

## 7. Moteur de rendu (recommandation, à confirmer après exploration)

- **Une seule source de rendu** pour l'aperçu et l'export, sinon l'export ne ressemble pas à l'aperçu. Recommandation : chaque layout est un template HTML/CSS (ou un composant du framework du dépôt) rendu à taille réelle. L'aperçu le met à l'échelle avec `transform: scale()`, et l'export passe par un **Chromium headless côté serveur** (Playwright ou Puppeteer) : `screenshot` pour PNG/JPG, `page.pdf` pour l'A4.
- Si le dépôt est en PHP ou dans un autre langage sans navigateur headless, propose un petit service de rendu Node séparé (une file d'attente simple, un timeout, des limites de taille) plutôt qu'un rendu en canvas côté client, qui gère mal la typographie.
- Les définitions de presets sont **validées par un schéma** (JSON Schema, Zod ou équivalent) au chargement. Un preset invalide est refusé avec un message clair.
- Le moteur mesure le texte réellement rendu pour l'ajustement automatique et le débordement (pas d'estimation au nombre de caractères).

## 8. Emplacements d'image

- Upload (JPG, PNG, WebP ; 15 Mo max) ou choix dans la **médiathèque** du module (images déjà utilisées et banque de photos du campus, alimentée par les administrateurs).
- Recadrage **dans le cadre** : déplacer et zoomer avec le ratio du cadre verrouillé, point focal mémorisé pour que le recadrage suive quand on change de format.
- Réglages rapides **limités** et cohérents avec la marque : luminosité, contraste, saturation, noir et blanc, et un **duotone marine/crème**. Pour tout le reste : bouton « Retoucher dans l'éditeur » (le logiciel existant), puis retour automatique dans l'emplacement.
- Alerte de **résolution insuffisante** quand l'image sera agrandie au-delà de 100 % à l'export (et au-delà de 150 dpi pour l'A4).
- Texte alternatif obligatoire pour chaque image, réutilisé dans la légende suggérée (§9).

## 9. Fonctions de l'application

1. **Accueil** : grille des 6 presets avec aperçu, filtre par catégorie, bouton « Nouveau visuel ». Liste « Mes visuels » (brouillons, exportés) et « Visuels de mon service ».
2. **Éditeur** en trois zones : panneau de champs à gauche (dans l'ordre de lecture du visuel), aperçu au centre (avec les onglets de format 4:5 / 1:1 / 9:16 / A4), actions à droite (export, enregistrer, dupliquer, historique).
3. **Édition directe** : un clic sur un texte de l'aperçu met le focus sur le champ correspondant.
4. **Brouillons** enregistrés automatiquement, **dupliquer** un visuel (pour une série), **historique** des versions (au moins les 20 dernières), annuler/rétablir.
5. **Légende suggérée** pour la publication : générée à partir des champs (titre, date, lieu, lien), modifiable, avec le texte alternatif. Bouton copier.
6. **Rôles** (rattachés à l'authentification existante) :
   - *Éditeur* : crée et exporte des visuels pour son service.
   - *Validateur* (communication) : facultatif, active un circuit « soumettre pour validation » avant l'export définitif, obligatoire pour les presets COM et REC.
   - *Administrateur* : gère les presets (activer/désactiver, valeurs par défaut, liste des services émetteurs), la médiathèque et les rôles.
7. **Journal** : qui a exporté quoi, quand, dans quel format (utile pour les communiqués officiels).
8. Les champs « service émetteur », « année » et « filières » proposent des **listes** gérées par l'administrateur, avec saisie libre possible sauf pour le service émetteur.

## 10. Qualité, accessibilité, sécurité

- L'interface de l'éditeur respecte le WCAG 2.1 AA : navigation au clavier, labels de formulaire, focus visible, cibles de 44 px minimum.
- Fonctionne sur un ordinateur portable 1366×768 et reste **utilisable sur tablette**. Sur téléphone : consultation et export seulement.
- Uploads : vérification du type réel du fichier, suppression des métadonnées EXIF (localisation), redimensionnement à 4000 px max à l'enregistrement.
- Le moteur de rendu ne charge **aucune ressource externe** (polices, images et scripts sont locaux) et n'exécute **aucun HTML saisi par l'utilisateur** : tous les champs sont échappés.
- Performances : aperçu mis à jour en moins de 150 ms à la frappe, export PNG en moins de 5 s, export ZIP des 4 formats en moins de 20 s.

## 11. Étapes de livraison

1. **Fondations** : tokens, police auto-hébergée, schéma de preset, moteur de rendu, preset **EVT en 4:5**. Critère de réussite : en remplissant EVT avec les données de la table ronde (voir `reference/table-ronde/Main.dc.html`), l'export PNG est visuellement identique à la référence (test de régression visuelle avec une tolérance de 1 %).
2. **Tous les presets en 4:5**, avec ajustement automatique, champs facultatifs et listes variables.
3. **Formats 1:1, 9:16 et A4**, avec les zones de sécurité de la story, le PDF avec fond perdu et l'export ZIP.
4. **Emplacements d'image** : recadrage, point focal, réglages rapides, pont vers le logiciel de retouche.
5. **Application** : brouillons, duplication, historique, rôles, validation, journal, légende suggérée.
6. **Administration des presets** et de la médiathèque.

À chaque étape : tests unitaires (validation des presets, ajustement du texte, nommage des fichiers), tests de régression visuelle des 6 presets × 4 formats avec des données de test, et une courte note de ce qui a changé.

## 12. Données de test

Utilise ces valeurs réelles pour les tests du preset EVT (issues de l'affiche TRS 2026) :

```json
{
  "preset": "EVT",
  "organisateur": "Zurcher Research Center",
  "annee": "2026",
  "type": "TABLE RONDE SCIENTIFIQUE",
  "titre": "La préconnaissance et la prédestination divines dans la doctrine du Salut",
  "motCleOr": "prédestination divines",
  "sousTitre": "Enjeux théologiques et perspectives bibliques",
  "date": "2026-10-22",
  "heure": "15",
  "lieu": "Zurcher",
  "lieuPrecision": "Research Center",
  "intervenants": ["Dr Daniel Kancel", "Dr Rabezanaka Pamphile", "Dr Ratsimbason Jacques"],
  "invite": { "nom": "Dr Patrick Anani", "fonction": "Conférencier invité" },
  "moderation": "Pasteurs Ranala Marc Arthur et Irina Andriamandranto",
  "acces": "Ouvert à tous",
  "diffusion": "Diffusé en direct sur la page Facebook de l'UAZ",
  "image": "assets/illustration-trs.jpg"
}
```

Pour les autres presets, crée des données de test **visiblement fictives** (par ex. « Poste de test », « test@exemple.mg ») et ne publie jamais de vraies données personnelles dans les fixtures.

## 13. Ce que j'attends de toi maintenant

1. L'exploration du dépôt et du logiciel de retouche (§3), résumée.
2. Le plan détaillé de l'étape 1 et les choix techniques (§7), avec les fichiers que tu comptes créer.
3. Tes questions, s'il en reste, regroupées en une seule liste.

Ensuite, attends mon feu vert avant d'implémenter.
