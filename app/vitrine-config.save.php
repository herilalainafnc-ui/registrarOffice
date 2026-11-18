<?php
/* =============================================================================
   Enregistrement des reglages de la vitrine — reponse JSON

   Point d'entree appele par src/js/settings.vitrine.js. La page ne se recharge
   plus a chaque sauvegarde : sur un formulaire de trente-sept champs reparti
   sur huit groupes, le rechargement renvoyait en haut de page et faisait
   perdre l'endroit ou l'on travaillait.

   Place dans app/ et non dans src/ : c'est la convention du projet pour ce qui
   repond des donnees plutot qu'une page — et src/js/transitions.js s'appuie
   dessus pour ne pas jouer de transition sur ces adresses.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/vitrine.save.php');

header('Content-Type: application/json; charset=utf-8');

/* Reponse jamais mise en cache : elle depend de la session et de l'etat de la
   base, et un intermediaire qui la garderait renverrait un succes pour un
   enregistrement qui n'a pas eu lieu. */
header('Cache-Control: no-store');

function repondre(array $charge, int $code = 200) : void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repondre(['ok' => false, 'type' => 'erreur',
              'message' => 'Méthode non autorisée.'], 405);
}

/* Le jeton a une duree de vie d'une heure (CSRF_TOKEN_LIFETIME). Sans
   rechargement de page, une seance de relecture peut la depasser : le message
   doit donc dire quoi faire, pas seulement que c'est refuse.

   Code 400 et non 403 : src/js/session.js intercepte les 403 pour annoncer un
   refus de privileges et rediriger vers la connexion, ce qui serait faux ici.
   Et pas 419 non plus, si tentant soit-il : ce code n'est pas enregistre a
   l'IANA, et ce serveur le transforme en 500 — verifie. Le motif exact voyage
   donc dans la charge utile, pas dans le statut. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    repondre(['ok' => false, 'type' => 'erreur', 'csrf_expire' => true,
              'message' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.'], 400);
}

$agent = trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''));
if ($agent === '') { $agent = $_SESSION['user_pseudo'] ?? null; }

$r = vitrineEnregistrer($dtb, $_POST, $_FILES, $agent);

/* Jeton renvoye pour que le formulaire mette le sien a jour. Sans cela, une
   longue seance d'edition finirait par se heurter a l'expiration ci-dessus, et
   l'AJAX serait alors PIRE que le rechargement qu'il remplace. Le jeton ne
   part que vers une session deja authentifiee, en meme origine : le fournir
   ici n'ouvre rien de plus que la page elle-meme. */
$r['ok'] = ($r['type'] !== 'erreur');
$r['csrf'] = function_exists('csrf_token') ? csrf_token() : null;

repondre($r);
