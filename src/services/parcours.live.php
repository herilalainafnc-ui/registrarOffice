<?php
/**
 * Parcours d'une mention, pour le formulaire d'ajout d'etudiant.
 *
 * Meme correction que parcours.live.addCours.php : la mention arrivait
 * directement dans la requete et permettait d'en neutraliser la clause
 * WHERE. Requete preparee desormais.
 *
 * Les options restent sans attribut value : c'est le libelle qui est poste,
 * et src/creat.student.php l'attend ainsi.
 */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

require(__DIR__ . '/../../data/backdb.php');

require_once(__DIR__ . '/../../data/mentions.php');

$mention = isset($_POST['mention']) ? (string) $_POST['mention'] : '';
/* Niveau deja transmis par le formulaire (src/creat.student.php) : les
   parcours de master ne sont pas ceux de la licence (colonne cycles,
   reglee dans /settings/mentions). Sans niveau, tout est propose. */
$niveau = $_POST['annee_etude'] ?? null;
$listeParcours = mentionParcoursListe($dtb, $mention, $niveau);
?>
<select id="firstEtd" class="inscInput h-6 text-sm w-full requierd-9 mb-3" name="etude_option" >
<?php foreach ($listeParcours as $showO) { ?>
		<option class="<?=htmlspecialchars(isset($bg_seven_color) ? $bg_seven_color : '', ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($showO['description'], ENT_QUOTES, 'UTF-8')?></option>
<?php } ?>
</select>
