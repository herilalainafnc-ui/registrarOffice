# Version desktop — Infinit Registrar

Enveloppe Electron qui fait tourner l'application PHP existante dans une
fenetre native. Le code PHP est reutilise tel quel : Electron lance le serveur
interne de PHP (`php -S`) sur `127.0.0.1`, et `router.php` (a la racine) rejoue
les regles de `.htaccess` qu'Apache appliquerait normalement.

```
desktop/main.js     processus principal Electron (lance PHP + ouvre la fenetre)
desktop/preload.js  pont sûr page <-> Electron (minimal pour l'instant)
router.php          routeur pour php -S (racine du projet)
forge.config.js     configuration d'empaquetage (racine du projet)
```

## Lancer en developpement

Prerequis : Node.js et un PHP accessible (voir la recherche de binaire dans
`main.js`). Depuis la racine du projet :

```bash
npm run desktop
```

Electron choisit un port libre, demarre PHP dessus, attend qu'il reponde, puis
ouvre l'application. A la fermeture, le serveur PHP est arrete automatiquement.

Pour forcer un binaire PHP precis :

```bash
REGISTRAR_PHP="C:\\chemin\\vers\\php.exe" npm run desktop
```

## PHP portable embarque

L'application distribuee ne doit dependre d'aucun PHP installe sur le poste
cible. Un PHP autonome est donc assemble dans `php/` (a la racine) a partir du
PHP de la machine de build :

```bash
npm run bundle:php
```

Le script (`desktop/bundle-php.js`) copie php.exe, php8.dll, les DLL de support
et le dossier `ext/`, puis applique `desktop/php.ini` (extensions requises,
`extension_dir` relatif, reglages d'upload). Les binaires (~96 Mo) ne sont PAS
suivis par git ; seul `desktop/php.ini` l'est. `main.js` prefere ce PHP embarque
et, quand il le detecte, charge son `php.ini` (`-c`) sans forcer d'extension.

Pour pointer vers un autre PHP source :

```bash
REGISTRAR_PHP="C:\\chemin\\vers\\php.exe" npm run bundle:php
```

## Base de donnees

Actuellement l'app se connecte a la base definie dans `data/config.php`
(`registrar_db` en local). Pour la cible « MySQL central accessible partout »,
il restera a externaliser `DB_HOST` afin de pointer vers le serveur central
sans modifier un fichier suivi par git.

> Securite : ne pas exposer MySQL directement sur internet (port 3306 public).
> Prevoir TLS + utilisateur dedie + liste d'IP autorisees, ou un tunnel.

## Empaqueter

Prerequis : le PHP portable doit etre present (`npm run bundle:php`), car
electron-forge inclut le dossier `php/` du disque dans le paquet.

```bash
npm run package   # assemble l'app dans out/ (sans installeur), pour tester
npm run make      # produit l'installeur + le zip portable dans out/make/
```

`npm run make` genere :
- `out/make/squirrel.windows/x64/InfinitRegistrar-Setup.exe` — installeur Windows ;
- `out/make/zip/win32/x64/Infinit Registrar-win32-x64-<version>.zip` — version portable.

Les donnees (photos, uploads) sont exclues du paquet via `forge.config.js`
(`ignore`) : elles vivent sur le serveur, pas dans l'application.

### Icone

L'icone est `desktop/app-icon.ico` (multi-resolutions 16/32/48/256), generee a
partir de `file/logo-coldbloud.png` (centre sur un canevas carre transparent).
Elle est cablee dans `forge.config.js` : `packagerConfig.icon` (icone de l'app)
et `makers[squirrel].config.setupIcon` (icone de l'installeur). Pour la
regenerer depuis un autre logo, voir le script du scratchpad (pngjs + png-to-ico).

## Etat

- [x] Electron lance PHP et affiche l'application
- [x] Routeur reproduisant les rewrites Apache (statiques, .php, front controller)
- [x] Pages dynamiques et connexion MySQL fonctionnelles dans la fenetre
- [x] PHP portable embarque (autonomie du poste cible) — `npm run bundle:php`
- [x] Makers installes + installeur genere — `npm run make`
- [x] Icone brandee (.ico UAZ)
- [ ] DB_HOST externalise vers le serveur central (prevu avec l'hebergement Hostinger)
