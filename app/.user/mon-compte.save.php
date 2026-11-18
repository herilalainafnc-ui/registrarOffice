<?php
/* =============================================================================
   Mon compte — enregistrement

   Un seul point d'entree pour les quatre gestes de la page : identite, photo,
   mot de passe, role. Chacun repond en JSON, la page ne se recharge jamais.

   PROPRIETE DE SURETE CENTRALE : ce fichier n'accepte AUCUN identifiant
   d'utilisateur. Il agit toujours sur $_SESSION['user_id'], jamais sur une
   valeur venue de la requete. C'est ce qui rend l'elevation laterale
   impossible — on ne peut pas modifier le compte d'un autre en changeant un
   champ cache, comme le permet app/.user/updateUser.php qui, lui, prend l'id
   dans le formulaire.

   Le role fait exception a « tout est modifiable » : il faut etre superadmin.
   ========================================================================== */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function mcRepondre(array $charge, int $code = 200) : void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    mcRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

/* Le jeton vit une heure et la page ne se recharge plus d'elle-meme : le
   message doit dire quoi faire. Code 400 et non 403 — src/js/session.js
   intercepte les 403 pour annoncer un refus de privileges et rediriger, ce qui
   serait faux ici ; et 419 n'est pas enregistre a l'IANA, ce serveur le
   transforme en 500 (verifie). */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    mcRepondre(['ok' => false, 'csrf_expire' => true,
                'message' => 'Session expirée. Rechargez la page et réessayez.'], 400);
}

$moi = (int) ($_SESSION['user_id'] ?? 0);
if ($moi <= 0) { mcRepondre(['ok' => false, 'message' => 'Session introuvable.'], 401); }

$st = $dtb->prepare('SELECT * FROM compt_utilisateur WHERE id = ? LIMIT 1');
$st->execute([$moi]);
$compte = $st->fetch(PDO::FETCH_ASSOC);
if (!$compte) { mcRepondre(['ok' => false, 'message' => 'Compte introuvable.'], 404); }

$action = (string) ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'identite':   mcIdentite($dtb, $compte);   break;
        case 'avatar':     mcAvatar($dtb, $compte);     break;
        case 'motdepasse': mcMotDePasse($dtb, $compte); break;
        case 'role':       mcRole($dtb, $compte);       break;
        default:
            mcRepondre(['ok' => false, 'message' => 'Action non reconnue.'], 400);
    }
} catch (Throwable $e) {
    /* Le detail part au journal : les messages PDO citent les requetes et les
       noms de colonnes. */
    error_log('Mon compte [' . $action . '] : ' . $e->getMessage());
    mcRepondre(['ok' => false, 'message' => "L'enregistrement n'a pas abouti."], 500);
}


/* =============================================================================
   Identite
   ========================================================================== */

function mcIdentite(PDO $dtb, array $compte) : void
{
    $champs = [
        'nom'    => trim((string) ($_POST['nom'] ?? '')),
        'prenom' => trim((string) ($_POST['prenom'] ?? '')),
        'post'   => trim((string) ($_POST['post'] ?? '')),
        'pseudo' => trim((string) ($_POST['pseudo'] ?? '')),
        'mail'   => trim((string) ($_POST['mail'] ?? '')),
    ];

    /* Un champ par champ : la page enregistre section par section, et un refus
       doit nommer le champ fautif pour que l'interface le signale. */
    $erreurs = [];

    if ($champs['nom'] === '')    { $erreurs['nom'] = 'Le nom est obligatoire.'; }
    if ($champs['prenom'] === '') { $erreurs['prenom'] = 'Le prénom est obligatoire.'; }

    if ($champs['pseudo'] === '') {
        $erreurs['pseudo'] = "L'identifiant de connexion est obligatoire.";
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $champs['pseudo'])) {
        $erreurs['pseudo'] = 'Lettres, chiffres, point, tiret et souligné seulement, de 3 à 50 caractères.';
    }

    if ($champs['mail'] === '') {
        $erreurs['mail'] = "L'adresse email est obligatoire.";
    } elseif (!filter_var($champs['mail'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['mail'] = "Cette adresse email n'est pas valide.";
    }

    /* Unicite du pseudo ET de l'email : ce sont les deux identifiants de
       connexion. Sans ce controle, deux comptes homonymes rendaient la
       connexion imprevisible — c'est le premier trouve qui repondait. */
    if (!isset($erreurs['pseudo'])) {
        $q = $dtb->prepare('SELECT id FROM compt_utilisateur WHERE pseudo = ? AND id <> ? LIMIT 1');
        $q->execute([$champs['pseudo'], (int) $compte['id']]);
        if ($q->fetchColumn() !== false) {
            $erreurs['pseudo'] = 'Cet identifiant est déjà pris.';
        }
    }
    if (!isset($erreurs['mail'])) {
        $q = $dtb->prepare('SELECT id FROM compt_utilisateur WHERE mail = ? AND id <> ? LIMIT 1');
        $q->execute([$champs['mail'], (int) $compte['id']]);
        if ($q->fetchColumn() !== false) {
            $erreurs['mail'] = 'Cette adresse email est déjà utilisée par un autre compte.';
        }
    }

    /* L'adresse d'un enseignant ouvre son espace (Middleware::espacesDeLaPersonne : une personne du
       personnel dont l'adresse est celle d'une fiche enseignant choisit son espace) : on ne la prend
       pas pour soi. Reste permise l'adresse qu'on a deja, ou celle de la fiche que le registrariat
       a reliee au compte (teacher_uid). */
    if (!isset($erreurs['mail']) && strtolower($champs['mail']) !== strtolower(trim((string) ($compte['mail'] ?? '')))) {
        $q = $dtb->prepare('SELECT uid FROM teacher WHERE remove = 0 AND LOWER(TRIM(email)) = LOWER(?) LIMIT 1');
        $q->execute([$champs['mail']]);
        $uidFiche = $q->fetchColumn();
        if ($uidFiche !== false && (int) $uidFiche !== (int) ($compte['teacher_uid'] ?? 0)) {
            $erreurs['mail'] = "Cette adresse est celle d'un enseignant. Demandez au registrariat de la rattacher à votre compte.";
        }
    }

    if ($erreurs) {
        mcRepondre(['ok' => false, 'message' => 'Certains champs doivent être corrigés.',
                    'erreurs' => $erreurs], 422);
    }

    $up = $dtb->prepare(
        'UPDATE compt_utilisateur
            SET nom = :nom, prenom = :prenom, post = :post,
                pseudo = :pseudo, mail = :mail, update_user = :moi
          WHERE id = :id LIMIT 1');
    $up->execute($champs + ['moi' => (int) $compte['id'], 'id' => (int) $compte['id']]);

    /* La session porte le nom affiche dans la barre du haut : sans cette mise a
       jour, l'ancien nom y resterait jusqu'a la prochaine connexion. */
    $_SESSION['user_nom']    = $champs['nom'];
    $_SESSION['user_prenom'] = $champs['prenom'];
    $_SESSION['user_pseudo'] = $champs['pseudo'];

    mcRepondre([
        'ok' => true,
        'message' => 'Informations enregistrées.',
        'valeurs' => $champs,
        'nomAffiche' => trim($champs['nom'] . ' ' . $champs['prenom']),
    ]);
}


/* =============================================================================
   Photo de profil
   ========================================================================== */

function mcAvatar(PDO $dtb, array $compte) : void
{
    $f = $_FILES['photo'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $motifs = [
            UPLOAD_ERR_INI_SIZE  => 'Le fichier dépasse la taille autorisée par le serveur.',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux.',
            UPLOAD_ERR_PARTIAL   => "Le transfert s'est interrompu. Réessayez.",
            UPLOAD_ERR_NO_FILE   => 'Aucune image sélectionnée.',
        ];
        $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
        mcRepondre(['ok' => false, 'message' => $motifs[$code] ?? "L'image n'a pas pu être reçue."], 422);
    }

    if ($f['size'] > 5 * 1024 * 1024) {
        mcRepondre(['ok' => false, 'message' => 'Image trop lourde : 5 Mo maximum.'], 422);
    }

    /* Liste blanche d'extensions, VRAI type MIME, puis re-encodage par GD.

       Le re-encodage est ce qui compte : il neutralise toute charge dissimulee
       dans les metadonnees d'une image par ailleurs valide. app/.user/
       updateUser.php, lui, appelle in_array() sans utiliser son resultat — le
       controle d'extension y est donc purement decoratif. */
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        mcRepondre(['ok' => false, 'message' => 'Formats acceptés : JPG, PNG ou WebP.'], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (!in_array($finfo->file($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp'], true)) {
        mcRepondre(['ok' => false, 'message' => "Ce fichier n'est pas une image."], 422);
    }

    $source = @imagecreatefromstring(file_get_contents($f['tmp_name']));
    if (!$source) {
        mcRepondre(['ok' => false, 'message' => 'Image illisible.'], 422);
    }

    /* Recadrage carre centre : l'avatar s'affiche dans un cercle partout dans
       l'application. Redimensionner sans recadrer deformait les photos en
       portrait. */
    $l = imagesx($source);
    $h = imagesy($source);
    $cote = min($l, $h);
    $x = (int) (($l - $cote) / 2);
    $y = (int) (($h - $cote) / 2);

    $taille = 512;
    $carre = imagecreatetruecolor($taille, $taille);
    imagecopyresampled($carre, $source, 0, 0, $x, $y, $taille, $taille, $cote, $cote);
    imagedestroy($source);

    /* Nom deduit de l'identifiant du compte, jamais du fichier recu ni du
       prenom : updateUser.php nommait la photo d'apres le prenom, si bien que
       deux homonymes ecrasaient mutuellement leur portrait. */
    $nom = 'u' . (int) $compte['id'] . '.jpg';
    $dossier = __DIR__ . '/../photosuser/';
    if (!is_dir($dossier)) { @mkdir($dossier, 0775, true); }

    if (!imagejpeg($carre, $dossier . $nom, 88)) {
        imagedestroy($carre);
        mcRepondre(['ok' => false, 'message' => "L'image n'a pas pu être enregistrée."], 500);
    }
    imagedestroy($carre);

    $dtb->prepare('UPDATE compt_utilisateur SET photos = ?, update_user = ? WHERE id = ? LIMIT 1')
        ->execute([$nom, (int) $compte['id'], (int) $compte['id']]);

    mcRepondre([
        'ok' => true,
        'message' => 'Photo de profil mise à jour.',
        // Le nom de fichier ne change pas d'un envoi a l'autre : sans
        // parametre, le navigateur continuerait d'afficher l'ancienne.
        'photo' => $nom,
        'version' => time(),
    ]);
}


/* =============================================================================
   Mot de passe
   ========================================================================== */

function mcMotDePasse(PDO $dtb, array $compte) : void
{
    $actuel  = (string) ($_POST['actuel'] ?? '');
    $nouveau = (string) ($_POST['nouveau'] ?? '');
    $confirm = (string) ($_POST['confirmation'] ?? '');

    $erreurs = [];
    if ($actuel === '')  { $erreurs['actuel'] = 'Saisissez votre mot de passe actuel.'; }
    if ($nouveau === '') { $erreurs['nouveau'] = 'Saisissez le nouveau mot de passe.'; }
    elseif (mb_strlen($nouveau) < 8) { $erreurs['nouveau'] = 'Au moins 8 caractères.'; }
    elseif ($nouveau === $actuel) { $erreurs['nouveau'] = 'Le nouveau mot de passe doit différer de l’actuel.'; }
    if ($confirm !== $nouveau) { $erreurs['confirmation'] = 'La confirmation ne correspond pas.'; }

    if ($erreurs) {
        mcRepondre(['ok' => false, 'message' => 'Certains champs doivent être corrigés.',
                    'erreurs' => $erreurs], 422);
    }

    /* Deux formats coexistent en base : bcrypt pour les comptes recents,
       sha256 sale pour les anciens. On accepte les deux a la verification, et
       l'on ecrit toujours en bcrypt — chaque changement de mot de passe fait
       donc migrer un compte de plus. */
    $stocke = (string) $compte['password'];
    $valide = ($stocke !== '' && password_verify($actuel, $stocke))
           || hash_equals($stocke, hash('sha256', $actuel . 'fixing_password'));

    if (!$valide) {
        mcRepondre(['ok' => false, 'message' => 'Mot de passe actuel incorrect.',
                    'erreurs' => ['actuel' => 'Ce mot de passe ne correspond pas.']], 422);
    }

    $hash = password_hash($nouveau, PASSWORD_DEFAULT);
    $dtb->prepare('UPDATE compt_utilisateur SET password = ? WHERE id = ? LIMIT 1')
        ->execute([$hash, (int) $compte['id']]);

    $_SESSION['infinit_password'] = $hash;
    $_SESSION['password_updated_at'] = time();

    mcRepondre([
        'ok' => true,
        'message' => 'Mot de passe modifié.',
        'changeLe' => date('d/m/Y à H:i'),
    ]);
}


/* =============================================================================
   Role et privilege — superadmin uniquement

   Les trois colonnes qui decrivent un role sont ecrites ENSEMBLE. Les laisser
   se regler separement est ce qui a produit l'incoherence deja en base : un
   compte de niveau 3 portant le privilege « administrator », alors que le
   niveau 3 designe les registraires.
   ========================================================================== */

/** level => [privilege, user_type, libelle] */
function mcRoles() : array
{
    return [
        1 => ['superadmin',    'superadmin',   'Superadmin'],
        2 => ['administrator', 'admin',        'Administrateur'],
        3 => ['registrar',     'registrar',    'Registraire'],
        4 => ['comptabilite',  'comptabilite', 'Comptabilité'],
        5 => ['media',         'media',        'Média'],
        6 => ['chef_mention',  'chef_mention', 'Chef de mention'],
        7 => ['teacher',       'teacher',      'Professeur'],
        8 => ['student',       'student',      'Étudiant'],
        9 => ['work_education', 'work_education', 'Work Education'],
    ];
}

function mcRole(PDO $dtb, array $compte) : void
{
    /* Le verrou est ICI, cote serveur. Le masquage du formulaire dans la page
       n'est qu'un confort : une requete forgee arrive directement. */
    $estSuperadmin = (int) ($compte['level'] ?? 99) === 1
        || (string) ($compte['privilege'] ?? '') === 'superadmin';

    if (!$estSuperadmin) {
        mcRepondre([
            'ok' => false,
            'message' => 'Seul un superadmin peut modifier un rôle. Demandez à un superadmin de le faire pour vous.',
        ], 403);
    }

    $roles = mcRoles();
    $niveau = (int) ($_POST['level'] ?? 0);
    if (!isset($roles[$niveau])) {
        mcRepondre(['ok' => false, 'message' => 'Rôle inconnu.'], 422);
    }

    /* Garde-fou : un superadmin peut se retrograder — il en reste trois autres —
       mais pas s'il est le dernier. Personne ne pourrait alors reattribuer le
       role, et l'administration se refermerait sur elle-meme. */
    if ($niveau !== 1 && (int) $compte['level'] === 1) {
        $restants = (int) $dtb->query(
            "SELECT COUNT(*) FROM compt_utilisateur
              WHERE etat = 1 AND (level = 1 OR privilege = 'superadmin')")->fetchColumn();
        if ($restants <= 1) {
            mcRepondre([
                'ok' => false,
                'message' => 'Vous êtes le dernier superadmin actif. Nommez-en un autre avant de changer votre rôle,'
                           . ' sinon plus personne ne pourrait attribuer ce rôle.',
            ], 409);
        }
    }

    [$privilege, $userType, $libelle] = $roles[$niveau];

    $dtb->prepare(
        'UPDATE compt_utilisateur
            SET level = ?, privilege = ?, user_type = ?, update_user = ?
          WHERE id = ? LIMIT 1')
        ->execute([$niveau, $privilege, $userType, (int) $compte['id'], (int) $compte['id']]);

    /* La session porte le niveau : tous les verrous d'acces le lisent. Sans
       cette mise a jour, le nouveau role ne prendrait effet qu'a la prochaine
       connexion — et l'ancien continuerait d'ouvrir des pages. */
    $_SESSION['user_level'] = $niveau;
    $_SESSION['user_privilege'] = $privilege;

    mcRepondre([
        'ok' => true,
        'message' => 'Rôle modifié : ' . $libelle . '.',
        'libelle' => $libelle,
        'privilege' => $privilege,
        // Se retrograder change ce a quoi l'on a droit : la page doit se
        // recharger pour que le menu reflete les nouveaux acces.
        'rechargerLaPage' => ($niveau !== (int) $compte['level']),
    ]);
}
