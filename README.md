# Infinit Registrar

Infinit Registrar est l’application de gestion des services de scolarité de
l’Université Adventiste Zurcher. Elle regroupe les espaces du registrariat,
des enseignants, des étudiants et des autres services de l’université.

L’application principale est écrite en PHP et s’appuie sur MySQL. Une version
de bureau facultative utilise Electron pour lancer la même application PHP dans
une fenêtre native.

## Fonctionnalités principales

- Admissions, inscriptions et gestion des dossiers étudiants
- Gestion des cours, des enseignants, des horaires et des notes
- Présences et espaces dédiés aux étudiants et aux enseignants
- Services administratifs, financiers, messagerie et notifications
- Authentification, contrôle d’accès par rôle et journalisation d’audit
- Application web installable (PWA) et outils de travail hors ligne pour
  certains modules

## Prérequis

Pour la version web :

- PHP 8 ou supérieur avec PDO MySQL (`pdo_mysql`) ; `mbstring` et `zip` sont
  nécessaires à plusieurs fonctions
- MySQL ou MariaDB, avec une base de développement autorisée
- Composer
- Apache avec `mod_rewrite`, ou le serveur de développement PHP

Node.js et npm ne sont nécessaires que pour travailler sur l’application de
bureau Electron.

## Installation locale

1. Cloner le dépôt et installer les dépendances PHP :

   ```sh
   composer install
   composer install --working-dir=data
   ```

   Le second jeu de dépendances fournit notamment les outils d’export utilisés
   par certaines fonctions.

2. Créer la configuration locale à partir des modèles :

   ```sh
   cp data/config.example.php data/config.local.php
   cp data/config.secret.example.php data/config.secret.php
   ```

   Renseigner dans `data/config.local.php` les paramètres de la base
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Ajouter les secrets nécessaires
   dans `data/config.secret.php` ou dans les variables d’environnement prévues.
   Ces deux fichiers locaux ne doivent jamais être commités.

   Le fichier `data/backdb.php`, requis par les pages et plusieurs tests, est
   lui aussi local et n’est pas fourni dans cette copie publique. Obtenir auprès
   de l’équipe une configuration de développement adaptée ; ne pas publier ce
   fichier ni ses identifiants.

3. Configurer une base de développement et appliquer les migrations SQL
   nécessaires depuis `data/`. Le dépôt ne fournit pas de copie de la base
   réelle : utiliser uniquement un schéma et des données de développement
   autorisés.

4. Depuis la racine du dépôt, démarrer le serveur PHP :

   ```sh
   php -S 127.0.0.1:8000 router.php
   ```

   Puis ouvrir <http://127.0.0.1:8000>. Pour Apache, la configuration de
   réécriture se trouve dans `.htaccess`.

## Organisation du dépôt

| Chemin | Rôle |
| --- | --- |
| `index.php`, `routes.php`, `core/` | Point d’entrée et socle du routeur MVC |
| `controllers/` | Contrôleurs de l’application |
| `src/` | Pages et modules métier, dont des écrans historiques |
| `app/` | Actions applicatives, API et traitements de formulaires |
| `data/` | Accès aux données, configuration, migrations et services |
| `init/` | Éléments communs de mise en page |
| `inscription/`, `landing/` | Parcours d’inscription et pages publiques |
| `tests/` | Tests PHP autonomes |
| `desktop/`, `router.php` | Enveloppe Electron et serveur PHP local |
| `file/` | Ressources statiques du site |

Les routes MVC sont déclarées dans `routes.php`. Les migrations de schéma sont
des fichiers `data/migration_*.sql` ; vérifier leur contenu et leur contexte
avant de les exécuter.

## Version de bureau

Depuis la racine du dépôt :

```sh
npm ci
npm run desktop
```

Les étapes de création du PHP portable et d’empaquetage sont documentées dans
[`desktop/README.md`](desktop/README.md).

## Tests

Les tests sont des scripts PHP indépendants dans `tests/`. Exécuter le test
ciblé après une modification, par exemple :

```sh
php tests/test_hors_ligne.php
```

Plusieurs tests nécessitent une base locale et des comptes de test. Lire le
début du script concerné avant de l’exécuter ; ne jamais lancer des tests qui
écrivent en base contre un environnement de production.

## Contribuer

- Créer une branche de travail et garder les changements ciblés.
- Respecter les contrôles d’accès, les protections CSRF et les conventions
  déjà utilisées par le module modifié.
- Ajouter ou mettre à jour une migration lorsqu’un changement dépend du schéma
  de la base, et un test ciblé lorsque c’est pertinent.
- Ne jamais commiter de mots de passe, clés, données personnelles, exports de
  base, fichiers déposés par les utilisateurs ou journaux.
- Vérifier `git status` avant chaque commit et ne pas ajouter les fichiers
  locaux ignorés par Git.

### Modèle MESUPRES

Le classeur modèle de l’export MESUPRES n’est pas inclus dans cette copie
publique. Le code correspondant reste présent, mais l’export ne peut pas
produire de fichier sans un modèle autorisé placé localement dans
`data/modeles/canevas-mesupres.xlsx`. Ne pas publier de classeur contenant des
données personnelles.
