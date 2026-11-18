<?php 
require('../init/.forPrint/top.forPrint.php');



require_once(__DIR__ . '/../../data/inscription.session.php');
require_once(__DIR__ . '/../../data/finance.frais.php'); // frais supplementaires du bareme
require_once(__DIR__ . '/../../data/paiement.echeances.php'); // dates limites des tranches, par session
require_once(__DIR__ . '/../../data/work.education.php'); // tache de Work Education, par session (/work-education)

$student_id = $_GET['student_id'] ?? '';

/* Resolution de la session.
   L'ancien repli lisait $_POST['semester'] et $_POST['annee_scolaire'] : la
   fiche est ouverte par un lien <a target="_blank">, donc en GET, et ces deux
   champs n'existaient jamais. Il interrogeait en plus session_semester (un
   entier) avec un nom de semestre (« Premier semestre »). Le repli ne pouvait
   donc pas aboutir, et la fiche partait avec un session_id vide.
   Le socle partage retrouve la session de l'etudiant : celle demandee si elle
   lui appartient, sinon celle de l'annee en cours. */
$session_id = inscSessionIdCourant($dtb, (string) $student_id, $_GET['session_id'] ?? null);

$showSession = false;
if ($session_id !== '') {
	$findSession = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_id = :session_id');
	$findSession->execute(['session_id' => $session_id]);
	$showSession = $findSession->fetch();
}

/* Sans session identifiee la fiche n'a pas de sens : elle imprimait des
   tableaux vides sans le signaler. */
if (!$showSession) {
	echo '<div style="padding:2rem;font-family:sans-serif">'
		. '<h2 style="color:#b45309">Fiche d\'inscription indisponible</h2>'
		. '<p>Aucune session d\'inscription n\'a pu être identifiée pour l\'étudiant '
		. htmlspecialchars((string) $student_id, ENT_QUOTES, 'UTF-8') . '.</p>'
		. '<p>Enregistrez la session à l\'étape 1 du module inscription, puis rouvrez la fiche.</p>'
		. '</div>';
	return;
}

$semester = $showSession['session_semester'];
$session_name = $showSession['session_name'];
$annee_scolaire = $showSession['session_year'];

$now = date('Y-m-d');
$printName = $student_id."-FICHE INSCRIPTION";

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = '.$dtb->quote($student_id).' ORDER BY remove ASC, id ASC LIMIT 1');

$stdA = $searchStd->fetch();

/* Niveau d'etude : 0 remise a niveau, 1 a 3 licence, 4 et 5 master (voir
   baremeNiveaux, data/finance.bareme.php). L'en-tete n'affichait que la
   licence : le niveau d'un etudiant de master restait vide. La fiche du
   master n'a pas non plus de work education, ni de frais de scolarite
   a 0 ar. */
$niveauxEtude = array_column(baremeNiveaux(), null, 'niveau');
$niveauFiche = $niveauxEtude[$stdA['annee_etude'] ?? ''] ?? null;
$ficheMaster = $niveauFiche !== null && $niveauFiche['categorie'] === 'M';
 ?>

 <div class="mb-24" id="exportToExcel">

<center>
	<b class="text-xl">Fiche d'inscription - </b><em class="text-sm"><?=$session_name." ".$annee_scolaire?></em>
</center>
	
	<div class="flex text-xs px-1 py-1" style="border: 1px solid #8e9bb2;">
		<div class="w-10/12" style="display: flex;">
			<div class="text-right w-4/12">
				<label>Matricule - </label><br>
				<label>Noms - </label><br>
				<label>Mention - </label><br>
				<label>Parcours - </label><br>
				<label>Niveau - </label><br>
				<label>Mail / </label>
				<label>Contact - </label><br>
				<label>Adresse - </label>
			</div>
			<div class="w-8/12 pl-1">
				<b><?=$student_id?></b><br>
				<b><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></b><br>
				<b><?=$stdA['etude_envisage']?></b><br>
				<b><?=$stdA['etude_option']?></b><br>
				<b><?=$niveauFiche['libelle'] ?? ''?></b><br>
				<b><?=$stdA['student_email']?></b>
				<b>/ <?=$stdA['student_tel']?></b><br>
				<b><?=$stdA['student_adresse']?></b>
			</div>
		</div>
		<div class="w-2/12">
			<img src="../app/photosetudiants/<?=$stdA['image_student']?>">
		</div>
	</div>
		<b class="text-md">Cours ajouté</b>
	<div>

<!-- 
AFFICHAGE DE LISTE DE COURS SANS FINANCEMENT

		<table class="tbl mb-2">
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">Sigle</th>
					<th style="">Titre du cours</th>
					<th style="width: 60px">Crédits</th>
					<th style="width: 80px">Catégorie</th>
					<th style="width: 80px; text-align: left; padding-right: 6px;">Labo</th>
				</tr>
			</thead>
			<tbody>
				
	<?php 
		$findCours = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id='.$dtb->quote($student_id).' AND session_id='.$dtb->quote($session_id).' AND remove != 1');
		$nbr = 0;
		$tCredit = 0;
		$tCout = 0;
		$tLab = 0;
		$n_lab = 0;
		$somm_lab = 0;
		while($showC = $findCours->fetch()) {
	 ?>
	 			<tr>
	 				<td><?=$showC['Sigle']?></td>
	 				<td><?=$showC['title_cours']?></td>
	 				<td><?=$showC['credit']?></td>
	 				<td><?php 
if ($showC['cours_category'] == 0){
	echo "Général";
}elseif ($showC['cours_category'] == 1) {
	echo "Majeur";
}elseif ($showC['cours_category'] == -1 OR $showC['cours_category'] == 2) {
	echo "Selective";
}elseif ($showC['cours_category'] == 3) {
	echo "Additionnel";
}elseif ($showC['cours_category'] == 5) {
	echo "``";
}else{
	echo "-";
}

	 				?></td>
	 				<td><?php 
	 if ($showC['lab']==0) {
	 	echo "";
	 	}else{
	 		echo "Labo". $showC['lab'];
	 	} 				
	 			?></td>
	 			</tr>
	<?php
		$nbr++;
		$tCredit =+ $tCredit + $showC['credit'];
		}
	 ?>
 				
			</tbody>
			<tfoot>
				<tr class="bg-slate-200">
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?=$tCredit?></th>
					<th></th>
					<th class="text-right"><?=$tCout.' ar'?></th>
					<th class="text-right"><?=$tLab.' ar'?></th> 
				</tr>
			</tfoot>
		</table>
 -->		
 		
<!-- $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$ -->
<!-- AFFICHAGE AVEC FINANCEMENT DE LISTE DE COURS -->
 		<table class="tbl mb-2">
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">Sigle</th>
					<th style="">Titre du cours</th>
					<th style="width: 60px">Crédits</th>
					<th style="width: 80px">Catégorie</th>
					<th style="width: 80px; text-align: right; padding-right: 6px;">coût</th>
					<th style="width: 80px; text-align: right; padding-right: 6px;">Labo</th>
				</tr>	
			</thead>
			<tbody>
<?php 
	$findCoursFinance = $dtb->query('SELECT * FROM t_2024_cours_finance WHERE student_id='.$dtb->quote($student_id).' AND session_id='.$dtb->quote($session_id).' AND remove != 1');
	$nbr = 0;
	$tCredit = 0;
	$tCout = 0;
	$tLab = 0;
	$n_lab = 0;
	$somm_lab = 0;
	// $session_id n'est plus reaffecte depuis les lignes lues : il sert ensuite
	// a chercher les frais, il ne doit pas dependre du contenu du tableau.
	while($showCF = $findCoursFinance->fetch()) {
 ?>
				<tr>
					<td><?=$showCF['cours_sigle']?></td>
					<td><?=$showCF['cours_title']?></td>
					<td><?=$showCF['cours_credit']?></td>
					<td><?php 
$findCat = $dtb->query('SELECT * FROM t_2023_cours WHERE id = '.$dtb->quote($showCF['cours_id']).'');
$showCat = $findCat->fetch();
if ($showCat['category'] == 0){
	echo "Général";
}elseif ($showCat['category'] == 1) {
	echo "Majeur";
}elseif ($showCat['category'] == -1 OR $showCat['category'] == 2) {
	echo "Selective";
}elseif ($showCat['category'] == 3) {
	echo "Additionnel";
}elseif ($showCat['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
				?></td>
					<td class="text-right"><?=number_format($showCF['cours_cout'], 0, '', ' ').' ar'?></td>
					<td class="text-right"><?php 
					
					if ($showCat['lab'] != 0) {						
					
						$n_lab =+ $n_lab + 1;

						if ($n_lab <= 2) {

							echo number_format($showCat['cout_lab'], 0, '', ' ');
							$somm_lab =+ $somm_lab + $showCat['cout_lab'];
					
						}else{
						
							echo "<a style='text-decoration: line-through; color: orange;'>".number_format($showCat['cout_lab'], 0, '', ' ')."</a>";
						
						}

					}


					echo ' ar';?></td>
				</tr>	
<?php
	$nbr++;
	$tCredit =+ $tCredit + $showCF['cours_credit'];
	$tCout =+ $tCout + $showCF['cours_cout'];
	$tLab =+ $tLab + $showCF['lab_cout'];
	}
 ?>
			</tbody>
			<tfoot>
				<tr class="bg-slate-200">
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?=$tCredit?></th>
					 <th></th>
					<th class="text-right"><?=number_format($tCout, 0, '', ' ').' ar'?></th>
					<th class="text-right"><?=number_format($somm_lab, 0, '', ' ').' ar'?></th>
				</tr>
			</tfoot>
		</table>

		
<!-- <table class="tbl">
<tr>
<?php
	$findCours = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id='.$dtb->quote($student_id).' AND session_id='.$dtb->quote($session_id).' AND remove != 1');
	while($showC = $findCours->fetch()) {
?>
	
		<td><?php echo $showC['Sigle'];?></td>
	
<?php 
	}
 ?>
 </tr>
</table> -->

<?php if (!$ficheMaster) { ?>
<em class="text-xs">Résidence : <b><?=$stdA['status']?></b></em><br>
		<em class="text-xs">Cafétéria : <b><?php if ($stdA['abonment'] == 1) { echo "Abonnée"; }else{ echo "Non abonnée"; }?></b></em><br><br>
<?php } ?>

<!-- AFFICHAGE DES FRAIS A PAYER -->		
 
<?php 

	// Requete preparee et choix deterministe parmi les doublons de
	// t_2024_etudiant_finace : voir inscFinanceSession().
	$showFin = inscFinanceSession($dtb, (string) $student_id, $session_id);

	/* Frais supplementaires du bareme (Alliance francaise, etc.) : ils
	   s'ajoutent aux frais divers et au total general, comme le voyage
	   d'etude et le costume. Voir data/finance.frais.php. */
	$fraisPersoDus = fraisPersoEtudiant($dtb, (string) $student_id, $session_id);
	$fraisPersoMontant = fraisPersoTotal($fraisPersoDus);

	if (empty($showFin)) {
		/* C'est ici que la fiche devenait fausse.
		   La condition precedente etait :
		       if (!empty($showFin['session_id']) OR $showFin['session_id'] != 0)
		   Sans ligne de frais, $showFin vaut false : le premier membre est faux,
		   et le second compare null a 0 — egaux en PHP — donc faux aussi. Toute
		   la section des frais disparaissait, la fiche s'imprimait complete en
		   apparence et sans frais de scolarite, et l'acces a un index sur false
		   emettait deux avertissements au milieu du PDF.
		   Releve en base : 140 couples (etudiant, session) de 2025-2026 sont
		   dans ce cas. L'absence est donc nommee, pas escamotee. */
	?>
	<div style="border:1px solid #b45309;background:#fffbeb;color:#7c2d12;padding:8px;margin:8px 0;font-size:11px">
		<b>Frais de scolarité non disponibles</b> — aucune ligne financière n'existe
		pour la session <?= htmlspecialchars((string) $session_name, ENT_QUOTES, 'UTF-8') ?>
		<?= htmlspecialchars((string) $annee_scolaire, ENT_QUOTES, 'UTF-8') ?>.
		Cette fiche ne peut pas servir de justificatif de paiement.
	</div>
	<?php
	} else {
 ?>
 <div class="flex gap-5">
 	<div class="w-6/12">
 <em class="text-xs"><b>NB :</b> Le frais généraux doivent être payés le jour de l'inscription.</em>
 		<table class="w-full tbl mb-2">
 			<thead class="bg-sky-200">
 				<tr class="text-center">
 					<th colspan="2">Frais divers</th>
 				</tr>
 			</thead>
 			<tbody>
 				<tr>
 					<td>Frais Généraux</td>
 					<td class="text-right"><?=number_format($showFin['cout_fraix_generaux'], 0, '', ' ')?> ar</td>
 				</tr>
 				<tr>
 					<?php if ($stdA['etude_envisage'] == "Théologie" AND $stdA['annee_etude'] == 1) { ?>
					<td>Frais Costume</td>
					<?php }?>
 					
 					<?php if ($stdA['etude_envisage'] == "Théologie" AND $stdA['annee_etude'] == 1) { ?>
						<td class="text-right"><?=number_format($showFin['cout_costume'], 0, '', ' ')?> ar</td>
					<?php }?>
 				</tr>
 				<tr>
 					<td>
<?php 
	if ($stdA["etude_envisage"] == "Théologie") {
		echo "Colloque";
	}else{
		echo "Voyage d'étude";
	}

 ?>
					</td>
					<td class="text-right"><?=number_format($showFin['cout_voyage'], 0, '', ' ')?> ar</td>
 				</tr>
 				<?php /* Frais supplementaires factures a cet etudiant pour cette
 				         session : lignes de t_2024_etudiant_frais_perso, avec le
 				         libelle et le montant figes a l'inscription. */ ?>
 				<?php foreach ($fraisPersoDus as $fp) { ?>
 				<tr>
 					<td><?=htmlspecialchars($fp['libelle'], ENT_QUOTES, 'UTF-8')?></td>
 					<td class="text-right"><?=number_format($fp['montant'], 0, '', ' ')?> ar</td>
 				</tr>
 				<?php } ?>
 				<!-- <tr>
 					<td>Fond de dépôt</td>
 					<td class="text-right"><?=number_format($showFin['cout_fondDepot_dortoir'], 0, '', ' ')?> ar</td>
 				</tr> -->
 			</tbody>
 		</table>

<?php
/* Deuxieme semestre et etudiant diplomant, comme au calcul
   (baremeFraisGraduation, data/finance.bareme.php). Des dossiers
   anciens portent un montant pose hors de ces conditions : il ne
   doit pas etre facture ici. */
$frais_graduation = baremeFraisGraduation($showFin['cout_frais_graduation'], $semester, $stdA['graduated']);
$Montant = $showFin['cout_fraix_generaux'] +
							$showFin['cout_logement'] +
							$showFin['cout_fondDepot_dortoir'] +
							$showFin['cout_abonment'] +
							$frais_graduation +
							$tCout +
							$somm_lab;
$pay_inscription = $showFin['cout_fraix_generaux'] + $showFin['cout_fondDepot_dortoir'];
$Montant_sans_fraix_Generaux = $Montant - $pay_inscription;

/* Master : les lignes a 0 ar se taisent ; si tout est a 0, le tableau des
   frais de scolarite et le mode de paiement disparaissent. */
$afficherScolarite = !$ficheMaster || $Montant_sans_fraix_Generaux != 0;
$ligneScolarite = function ($montant) use ($ficheMaster) { return !$ficheMaster || $montant != 0; };

if ($afficherScolarite) {
?>
 		<table class="w-full tbl mb-2">
 			<thead class="bg-sky-200">
 				<tr>
 					<th colspan="2" class="text-center">Frais de scolarité</th>
 				</tr>
 			</thead>
 			<tbody>
 				<?php if ($ligneScolarite($tCout)) { ?>
 				<tr>
 					<td>Total du cours</td>
 					<td class="text-right"><?=number_format($tCout, 0, '', ' ')?> ar</td>
 				</tr>
 				<?php } if ($ligneScolarite($somm_lab)) { ?>
 				<tr>
 					<td>Total de Laboratoire</td>
 					<td class="text-right"><?=number_format($somm_lab, 0, '', ' ')?> ar</td>
 				</tr>
 				<?php } if ($ligneScolarite($showFin['cout_logement'])) { ?>
 				<tr>
 					<td>Frais du logement</td>
 					<td class="text-right"><?=number_format($showFin['cout_logement'], 0, '', ' ')?> ar</td>
 				</tr>
 				<?php } if ($ligneScolarite($showFin['cout_abonment'])) { ?>
 				<tr>
 					<td>Frais de caféteria</td>
 					<td class="text-right"><?=number_format($showFin['cout_abonment'], 0, '', ' ')?> ar</td>
 				</tr>
 				<?php } if ($ligneScolarite($frais_graduation)) { ?>
 				<tr>
 					<td>Frais de Graduation</td>
 					<td class="text-right"><?=number_format($frais_graduation, 0, '', ' ')?> ar</td>
 				</tr>
 				<?php } ?>
 			</tbody>
 			<tfoot>
 				<tr>
 					<th>Sous total frais de scolarité</th>
 					<th class="text-right"><?=number_format($Montant_sans_fraix_Generaux, 0, '', ' ')?> ar</th>
 				</tr>
 			</tfoot>
 		</table>
<?php
}

/* Le mode de paiement etait relu par une SECONDE requete sur la meme table,
   sans « remove != 1 » et sans ORDER BY. Faute d'index unique la table
   contient 133 couples (etudiant, session) en double : cette requete pouvait
   donc renvoyer une autre ligne que $showFin, et la fiche imprimait un mode de
   paiement appartenant a une ligne differente de celle des montants affiches
   juste au-dessus. La valeur est desormais prise sur la ligne deja retenue. */
$modeP = $showFin['mode_payement'];
?>
 	</div>
<div class="w-6/12">
<?php 
	
	if ($stdA['sponsor_nom'] != "") {
?>	
	<div>
		<b class="text-xs">Boursié(e) par : <?=$stdA['sponsor_nom']." ".$stdA['sponsor_prenom']?></b>
	</div>
<?php
	}
 ?>
 <!-- __________________________________________________________________ -->

<!-- Mode de paiement -->
<?php if ($afficherScolarite) { ?>
<div>
	<b class="text-md">Mode de paiement choisi :</b>

	<table class="tbl mb-2">
			<thead>
				<tr>
					<th colspan="3" class="text-center text-bold">
<?=number_format($Montant_sans_fraix_Generaux, 0, '', ' ')?> ar
					</th>
				</tr>
				<tr>
					<th colspan="3" class="text-center text-bold text-[20px]">Tranche <?=$modeP?></th>
				</tr>

			</thead>
			<thead>
				<tr>
					<th>Pourcentage</th>
					<th>Montant</th>
					<th>Date de paiement</th>
				</tr>
			</thead>
			<tbody>
				<?php /* Les parts de chaque type et leurs dates : data/paiement.echeances.php,
				         dates reglees par session sur /finance/echeances. Elles etaient
				         ecrites ici en dur, les memes pour toutes les sessions. La case
				         reste modifiable a l'ecran avant l'impression. */
				$typeFiche = echeancesTypes()[$modeP] ?? null;
				$datesFiche = $typeFiche ? (echeancesSession($dtb, $session_id)[$modeP] ?? []) : [];
				foreach (($typeFiche['parts'] ?? []) as $k => $part) {
					$dateFiche = $datesFiche[$k + 1] ?? null; ?>
					<tr>
						<td><?= $part ?> %</td>
						<td class="text-right"><?=number_format(($Montant_sans_fraix_Generaux * $part) / 100, 0, '', ' ') ?> ar</td>
						<td><input type="text" class="h-4 p-0 border-0 text-xs w-full" value="<?= $dateFiche ? htmlspecialchars(echeanceDate($dateFiche)) : '' ?>"></td>
					</tr>
				<?php } ?>
			</tbody>
	</table>
	<?php /* Dates manquantes : a l'ecran seulement (data-html2canvas-ignore,
	         hors du PDF), pour le personnel ; le lien pour qui peut les regler. */
	if ($typeFiche && count($datesFiche) < count($typeFiche['parts']) && !defined('IMPRESSION_ETUDIANT')) { ?>
	<p data-html2canvas-ignore="true" style="margin:4px 0 0;padding:6px 8px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:11px;line-height:1.4;">
		Échéances du type <?= htmlspecialchars((string) $modeP) ?> non définies pour <?= htmlspecialchars($session_name . ' ' . $annee_scolaire) ?> : les cases restent à remplir à la main.
		<?php if ((int) ($_SESSION['user_level'] ?? 99) <= 3) { ?><a href="<?= $app_base ?>/finance/echeances?session_id=<?= (int) $session_id ?>" target="_blank" rel="noopener" style="color:#92400e;font-weight:bold;">Les définir</a><?php } else { ?>À définir par le registrariat.<?php } ?>
	</p>
	<?php } ?>
</div>
<?php } ?>

<div style="background-color: #1e293b; color: #fff; padding: 6px 10px; border-radius: 4px; margin-top: 6px;">
	<div style="font-size: 11px; font-weight: bold;">Total Général (<?= $afficherScolarite ? 'frais divers + frais de scolarité' : 'frais divers' ?>)</div>
	<div style="font-size: 18px; font-weight: bold;"><?=number_format($showFin['cout_fraix_generaux']+$showFin['cout_costume']+$showFin['cout_voyage']+$fraisPersoMontant+$Montant_sans_fraix_Generaux, 0, '', ' ')?> ar</div>
</div>
</div>

</div>
	
<div class="w-full gap-3 flex">



<?php  } /* fin du else : une ligne de frais existe */ ?>

<!-- _______________________________________________________________________________________ -->


<div class="w-full pb-3">
	<hr>
	<?php if (!$ficheMaster) { ?>
	<div class="flex gap-5 pt-3">
		<b class="text-sm w-3/12">Work education :</b>
		<?php /* La tache attribuee par le responsable du Work Education
		         (/work-education) ; le champ reste modifiable avant l'impression. */ ?>
		<input type="text" class="p-0 text-mg border-1 w-9/12" value="<?= htmlspecialchars(weTache($dtb, (string) $student_id, $session_id), ENT_QUOTES, 'UTF-8') ?>">
	</div>
	<?php } ?>
	<b class="text-sm"<?= $ficheMaster ? ' style="display:inline-block;margin-top:12px"' : '' ?>>ENGAGEMENT</b><br>

<p class="text-[10px]" style="">Je sousigné(e) <?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?><br>m'engage, durant mon séjour à l'Université Adventiste Zurcher, à maintenir en tout temps une conduite et attitude exemplaire, et en harmonie avec
la philosophie chrétienne de cette institution qui m'acceuille; à contribuer positivement à la vie de l'université et à vivre en tout temps en conformité
avec ses principes et règlements. Le non-respect de cet engagement pourrait entrainer une sanction ou même un renvoi temporaire.</p>
</div>
</div>
	</div>
	

<hr>
<div>
			<em class="text-[10px] mt-1">Sambaina, le <?php
				 echo date('d')." ";
				 $volana = date('m');
				 if($volana == '01'){echo('Janvier ');}
				 else if($volana == '02'){echo('Février ');}
				 else if($volana == '03'){echo('Mars ');}
				 else if($volana == '04'){echo('Avril ');}
				 else if($volana == '05'){echo('Mai ');}
				 else if($volana == '06'){echo('Juin ');}
				 else if($volana == '07'){echo('Juillet ');}
				 else if($volana == '08'){echo('Août ');}
				 else if($volana == '09'){echo('Septembre ');}
				 else if($volana == '10'){echo('Octobre ');}
				 else if($volana == '11'){echo('Novembre ');}
				 else if($volana == '12'){echo('Decembre ');}
				 echo date('Y')
				 ?></em>
				 <b class="text-[10px]"> - Approuvée par :</b>
		</div>
		
		<table class="text-sm text-[10px]" style="width: 100%; page-break-inside: avoid;">
			<tbody>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<!-- <td></td> -->
					<td></td>
					<td></td>
					<?php /* La place de la signature, au-dessus de chaque nom : 30 px ne
					         laissaient pas de quoi signer. */ ?>
					<td style="height: 64px;"></td>
				</tr>
				<tr style="font-size : 10px; line-height: 10px;">
					<td style="border-top: 1px solid black; text-align: center;">Étudiant(e)</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Chef de mention</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur aux affaires financières <br>/ Controleur</td>
					<td style="width: 20px"></td>
					<!-- <td style="border-top: 1px solid black; text-align: center;">Vice-Recteur<br>académique</td>
					<td style="width: 20px"></td> -->
					<td style="border-top: 1px solid black; text-align: center;">Vice-Recteur aux Affaires Estudiantines</td>
					<td style="width: 20px"></td>
					<td style="border-top: 1px solid black; text-align: center;">Registraire</td>
				</tr>
			</tbody>
		</table>
<div class="text-[10px] mt-1" style="border-top:1px solid black; width: 100%;">
	<em class="text-[10px]">Université Adventiste Zurcher</em>
</div>

</div>