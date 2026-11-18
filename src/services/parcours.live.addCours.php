<?php
/**
 * Parcours d'une mention, pour la modale « Ajouter un cours ».
 *
 * La mention arrivait auparavant directement dans la requete :
 *     WHERE departement = "'.$_POST['mentionSelect'].'"
 * Une valeur telle que INFO" OR "1"="1 neutralisait la clause et renvoyait
 * toute la table. Requete preparee desormais.
 *
 * Le fragment est produit apres la garde : celle-ci envoie des en-tetes et
 * ne peut donc rien emettre avant.
 */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

require(__DIR__ . '/../../data/backdb.php');

require_once(__DIR__ . '/../../data/mentions.php');

$mention = isset($_POST['mentionSelect']) ? (string) $_POST['mentionSelect'] : '';
/* Niveau du cours, quand le formulaire le transmet : les parcours de master
   ne sont pas ceux de la licence (colonne cycles). Sans niveau, tout est
   propose, comme avant. */
$niveauCours = $_POST['yearlevel'] ?? $_POST['niveau'] ?? null;
$listeParcours = mentionParcoursListe($dtb, $mention, $niveauCours);
?>
<select class="input w-full" name="parcours" id="parcours">
	<option value="all">TRONC COMMUN</option>
<?php foreach ($listeParcours as $showO) { ?>
		<option value="<?=htmlspecialchars($showO['shortcode'], ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($showO['description'], ENT_QUOTES, 'UTF-8')?></option>
<?php } ?>
</select>
