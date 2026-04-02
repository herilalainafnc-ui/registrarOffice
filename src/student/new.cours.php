
<?php 
	/*::::::::::::::::::::::: CHARGEMENT AUTONOME (AJAX) ::::::::::::::::::::::*/
	
	// Si appelé directement via AJAX, charger les dépendances
	if (!isset($profil) && (isset($_GET['student_id']) || isset($_GET['id']))) {
		require_once('../../data/backdb.php');
		
		// Démarrer la session pour accéder aux variables de couleur et utilisateur
		if (session_status() === PHP_SESSION_NONE) {
			ini_set('session.gc_maxlifetime', 36000);
			ini_set('session.cookie_lifetime', 36000);
			session_start();
		}
		
		// Calculer app_base (même logique que init/head.php)
		$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
		$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
		$app_base = substr($_app_root, strlen($_doc_root));
		if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
		
		// Récupérer l'ID utilisateur depuis la session
		$rg_id = $_SESSION['user_id'] ?? 0;
		
		// Récupérer les couleurs depuis la session
		$bg_one_color = $_SESSION['bg_one_color'] ?? 'bg-[#0a1628]';
		$bg_two_color = $_SESSION['bg_two_color'] ?? 'bg-[#0d1f3c]';
		$bg_three_color = $_SESSION['bg_three_color'] ?? 'bg-[#0f2847]';
		$bg_four_color = $_SESSION['bg_four_color'] ?? 'bg-[#1a3a5c]';
		$bg_five_color = $_SESSION['bg_five_color'] ?? 'bg-[#264d73]';
		
		$student_id = $_GET['student_id'] ?? '';
		$id = $_GET['id'] ?? '';
		$ajax_session_id = $_GET['session_id'] ?? '';
		
		if (!empty($student_id)) {
			$stmt = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id AND remove != 1 LIMIT 1');
			$stmt->execute(['student_id' => $student_id]);
			$profil = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($profil) {
				$id = $profil['id'];
				$student_id = $profil['student_id'];
				$student_nom = $profil['student_nom'];
				$student_prenom = $profil['student_prenom'];
				$level = $profil['annee_etude'];
				$annee_scolaire = $profil['annee_scolaire'];
				$etude_envisage = $profil['etude_envisage'];
				$etude_option = $profil['etude_option'];
			}
		}
	}
	
	// Récupérer le session_id (depuis GET pour AJAX, sinon depuis le formulaire parent)
	$current_session_id = $ajax_session_id ?? '';
?>

<?php 
	/*::::::::::::::::::::::: VÉRIFICATION SUSPENSION ::::::::::::::::::::::*/
	
	// Vérifier si l'étudiant est suspendu
	$isSuspended = false;
	if (isset($profil['suspended']) && $profil['suspended'] == 1) {
		$dateFin = $profil['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			$isSuspended = true;
		}
	}
	
	if ($isSuspended) {
?>
<div class="mt-2 p-4 text-center">
	<div class="bg-orange-500 text-white p-6 rounded-lg shadow-lg">
		<i class="bi bi-exclamation-triangle-fill text-5xl"></i>
		<h3 class="text-xl font-bold mt-3">ÉTUDIANT SUSPENDU</h3>
		<p class="mt-2">Cet étudiant est actuellement suspendu et ne peut pas prendre de nouveaux cours.</p>
		<?php if (!empty($profil['date_fin_suspension'])) { ?>
			<p class="mt-2 text-sm">Fin de suspension prévue: <b><?= date('d/m/Y', strtotime($profil['date_fin_suspension'])) ?></b></p>
		<?php } ?>
		<?php if (!empty($profil['motif_suspension'])) { ?>
			<p class="mt-2 text-sm bg-orange-600 p-2 rounded">Motif: <?= htmlspecialchars($profil['motif_suspension']) ?></p>
		<?php } ?>
	</div>
</div>
<?php
		return; // Arrêter l'exécution du reste de la page
	}
?>

<div id="coursListContainer" class="mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">	
<?php
	
	if ($etude_envisage == "Théologie") {
		$eE = 'THEO';
	}elseif ($etude_envisage == "Gestion") {
		$eE = 'GEST';
	}elseif ($etude_envisage == "Informatique") {
		$eE = 'INFO';
	}elseif ($etude_envisage == "Sciences Infirmières") {
		$eE = 'NURS';
	}elseif ($etude_envisage == "Education") {
		$eE = 'EDUC';
	}elseif ($etude_envisage == "Communication") {
		$eE = 'COMM';
	}elseif ($etude_envisage == "Etudes anglophones") {
		$eE = 'LANG';
	}elseif ($etude_envisage == "Droit") {
		$eE = 'DROI';
	}else{
		$eE = '';
	}
 	
/*	if($level<=1) {

 		$init = $level;

 	}elseif($level==2) {

 		if($etude_envisage == "Théologie") {
 			$init = 1;
 			$level = 3;
 		}else{
 			$init = $level-1;
 		}

 	}elseif($level==3) {

 		$init = $level-2;

 	}elseif($level==4) {

 		$init = $level;

 	}elseif($level==5) {

 		$init = $level-1;

 	}*/

 	if ($level <= 3) {
 	
 		$init = 1;
 		$level = 3;
 	
 	}elseif($level > 3) {
 		
 		$init = 4;
 		$level = 5;
 	
 	}

	for ($a=$init; $a <= $level; $a++) {
 ?>
	<div id="year<?=$a?>" class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
<b>

		<?php 
		if($a<=3) {
			echo "NIVEAU Licence ".$a;
		}else{
			echo "NIVEAU Master ".($a-3);
		}
		?>		
</b>
<?php		
		for ($s=1; $s <=2 ; $s++) { 
 ?>

 <!-- DEBUT DU FORMULAIRE -->
 <form action="javascript:void(0);" data-action="<?=$app_base?>/app/.student/checkCours.php" method="post" class="form-newCours">
 	<input type="hidden" name="id" value="<?=htmlspecialchars($id ?? '')?>">
 	<input type="hidden" name="student_id" value="<?=htmlspecialchars($student_id ?? '')?>">
 	<input type="hidden" name="page" value="newCours">
 	<input type="hidden" name="user_id" value="<?=htmlspecialchars($rg_id ?? '')?>">
 	<input type="hidden" name="etude_envisage" value="<?=htmlspecialchars($profil['etude_envisage'] ?? '')?>">
 	<input type="hidden" name="status" value="<?=htmlspecialchars($profil['status'] ?? '')?>">
 	<input type="hidden" name="new_student" value="<?=htmlspecialchars($profil['new_student'] ?? '')?>">
 	<input type="hidden" name="graduated" value="<?=htmlspecialchars($profil['graduated'] ?? '')?>">
 	<input type="hidden" name="student_adresse" value="<?=htmlspecialchars($profil['student_adresse'] ?? '')?>">
 	<input type="hidden" name="etude_option" value="<?=htmlspecialchars($profil['etude_option'] ?? '')?>">
 	<input type="hidden" name="annee_etude" value="<?=htmlspecialchars($profil['annee_etude'] ?? '')?>">
 	<input type="hidden" name="sponsor_nom" value="<?=htmlspecialchars($profil['sponsor_nom'] ?? '')?>">
 	<input type="hidden" name="sponsor_prenom" value="<?=htmlspecialchars($profil['sponsor_prenom'] ?? '')?>">
 	<input type="hidden" name="sponsor_tel" value="<?=htmlspecialchars($profil['sponsor_tel'] ?? '')?>">
 	<input type="hidden" name="sponsor_adresse" value="<?=htmlspecialchars($profil['sponsor_adresse'] ?? '')?>">
 	<input type="hidden" name="situationf" value="<?=htmlspecialchars($profil['situationf'] ?? '')?>">
 	<input type="hidden" name="nom_conjoint" value="<?=htmlspecialchars($profil['nom_conjoint'] ?? '')?>">
 	<input type="hidden" name="nb_enfant" value="<?=htmlspecialchars($profil['nb_enfant'] ?? 0)?>">
 	<input type="hidden" name="abonment" value="<?=htmlspecialchars($profil['abonment'] ?? 0)?>">

		<table class="simpleTbl mb-1 w-full">
			<thead>
				<tr class="text-left bg-gradient-to-r from-green-600">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<th class="w-5"></th>
					<th class="w-20">Sigle</th>
					<th class="">Titre du cours</th>
					<th class="sm:w-2/12 lg:w-3/12">Observations</th>
					<th class="w-20">Crédits</th>
					<th class="w-20">Catégorie</th>
				</tr>	
			</thead>
			<tbody class="<?=$bg_four_color?>">
	<?php
	$parcour = $dtb->query('SELECT * FROM filiere_parcours WHERE description = "'.$etude_option.'"');
	$afparc = $parcour->fetch();
	if (!empty($afparc)) {
		$parcours = $afparc['shortcode'];
	}else{
		$parcours = '';
	}
	
	$tout = 'all';

	$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc ='".$eE."' AND yearlevel='".$a."' AND semester='".$s."' AND (parcours = '".$parcours."' OR parcours = '".$tout."') ORDER BY title");

	/*$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc ='".$eE."' AND yearlevel='".$a."' AND semester='".$s."' ORDER BY title");*/
	
	$nbr = 0;
	$nbrMaj = 0;
	$credit = 0;
	$note = 0;
	$notecredit = 0;

	$tMaj = 0;
	$tTMaj = 0;
	$tcredit = 0;
	$tnote = 0;
	$tnotecredit = 0;
	
	if($cours->rowCount() > 0) {
		while ($crs = $cours->fetch()) {
			$note_id = $crs['id'];
			$annee_scolaire = $crs['yearlevel'];
			$semester = $crs['semester'];

	// Vérifier si le cours existe déjà pour cet étudiant (dans TOUTES les sessions)
	$stmtVerify = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id_cours = :id_cours AND student_id = :student_id AND ajout = 1 AND remove = 0');
	$stmtVerify->execute(['id_cours' => $note_id, 'student_id' => $student_id]);
	$validExisting = $stmtVerify->fetch();
	 ?>
				<tr 
<?php if (empty($validExisting)) { ?>	

				id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black"

<?php }elseif(!empty($validExisting) AND ($validExisting['grade'] >= 10 OR $validExisting['grade'] == -2 OR $validExisting['grade'] == 0)) { ?>

				class="bg-slate-700"

<?php }elseif(!empty($validExisting) AND $validExisting['grade'] > 0 AND $validExisting['grade'] < 10){ ?>

				id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black bg-red-900/30 border-l-2 border-red-500"

<?php } ?>>
					<td class="p-0 text-center" style="height: 15px;">




<?php if (empty($validExisting)) { ?>	

						<input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;">

<?php }elseif(!empty($validExisting) AND $validExisting['grade'] > 0 AND $validExisting['grade'] < 10){ ?>

						<input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;" data-retake="1">	

<?php }elseif(!empty($validExisting) AND ($validExisting['grade'] >= 10 OR $validExisting['grade'] == -2 OR $validExisting['grade'] == 0)){ ?>	

						<i class="bi-check-lg text-green-400"></i>	

<?php } ?>		
					

					</td>
					<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><?=$crs['Sigle']?></td>
					<td><?php
							if ($etude_envisage == "Etudes anglophones") {
								echo $crs['title_english'];
							}else{
								echo $crs['title'];
							}
					?>
					</td>
					<td><?php if (!empty($validExisting) AND $validExisting['grade'] == -2) {

	echo "<em class='text-green-400'><b>OK</b> - Validé</em>";

}elseif (!empty($validExisting) AND $validExisting['grade'] >= 10) {
	
	echo "<em class='text-green-400'><b>".$validExisting['grade']."</b> de moyenne</em>";

}elseif (!empty($validExisting) AND $validExisting['grade'] > 0 AND $validExisting['grade'] < 10) {
	
	echo "<em class='text-red-500'><i class='bi-arrow-repeat'></i> <b>".$validExisting['grade']."</b> de moyenne, en état d'échec. <span class='text-orange-400'>Reprise possible</span></em>";

}elseif (!empty($validExisting) AND $validExisting['grade'] == 0){

	echo "<em class='text-orange-400'>Ajouté le - ".substr($validExisting['date_entry'], 0, 10)."</b></em>";

} ?></td>
					<td><?=$crs['nb_crd']?></td>
					<td><?php 
if ($crs['category'] == 0){
	echo "Général";
}elseif ($crs['category'] == 1) {
	echo "Majeur";
}elseif ($crs['category'] == 2) {
	echo "Selective";
}elseif ($crs['category'] == 3) {
	echo "Additionnel";
}else{
	echo "-";
}
						 ?></td>
					
				</tr>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#cours<?=$a.$s.$nbr;?>').click(function(){
							
							if($('#chk<?=$a.$s.$nbr;?>').prop("checked") == false) {
								$(this).attr("class","bg-blue-500");
								$('#chk<?=$a.$s.$nbr;?>').prop("checked", true);	
							}else{
								$(this).attr("class","hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black");
								$('#chk<?=$a.$s.$nbr;?>').prop("checked", false);	
							}

						});

						$('#chk<?=$a.$s.$nbr;?>').click(function(){
							
							if($(this).prop("checked") == false) {
								$('#cours<?=$a.$s.$nbr;?>').attr("class","bg-blue-500");
								$(this).prop("checked", true);	
							}else{
								$('#cours<?=$a.$s.$nbr;?>').attr("class","hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black");
								$(this).prop("checked", false);	
							}

						});
					});
				</script>

	<?php

/* --- CALCULE DES NOTES MAJEURS --- */
 
$tcredit+= $credit + $crs['nb_crd'];
		$nbr++;
		}
	}
	 ?>			
			</tbody>
			<tfoot class="<?=$bg_one_color?> text-white">
				<tr>
					<th colspan="2"></th>
					<th><?=$nbr?> cours</th>
					<th></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo $tcredit;}?></th>
					<th></th>
				</tr>
				<tr>
					<td colspan="6" class="<?=$bg_four_color?>">
						<div class="flex">
							<div class="w-40 pt-1">
								<a href="#" id="selectAll<?=$a.$s;?>" class="px-2 py-0 m-1"><i class="bi-arrow-90deg-up"></i> Cocher tout</a>
								<a href="#" id="deselectAll<?=$a.$s;?>" class="px-2 py-0 m-1 hidden"><i class="bi-arrow-90deg-up"></i> Décocher tout</a>
							</div>
							<div>
								<b>Session :</b>
								<select name="semesterSession" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarSs<?=$a.$s;?>">
									<option <?php if (date('m')>=7) {echo "selected";} ?>>Premier semestre</option>
									<option>Semestre d'été</option>
									<option <?php if (date('m')<7) {echo "selected";} ?>>Deuxième semestre</option>
									<option>Semestre d'hiver</option>
								</select>

								<b>Année du cours :</b>
								<select name="annee_scolaire" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarA<?=$a.$s;?>">
									<?php
										$mois = date('m');
										if (intval($mois) < 7){
											$z = date('Y');
										}else{
											
											$z = date('Y') + 1;
										}
										$yn = 1;
						        			for ($i=1; $i < 6; $i++) { 
						        			?>
						        				<option><?=($z-1)." - ".$z?></option>
						        			<?php
						        			$z = $z-$yn;
						        		}
					        		 ?>
								</select>
							</div>
						
							
						
						<button id="submit<?=$a.$s;?>" type="submit" class="submiting px-2 text-center py-0 m-1 text-slate-400 bg-black">Ajouter au transcript</button>
						</div>
					</td>
				</tr>
												<script type="text/javascript">
													$(document).ready(function(){
													
														var nbr = <?=$nbr;?>;
														
														$('#selectAll<?=$a.$s;?>').click(function() {
															
															$(this).attr('class','px-2 py-0 m-1 hidden');
															$('#deselectAll<?=$a.$s;?>').attr('class','px-2 py-0 m-1');

															for (var i = 0; i < nbr ; i++) {
																$('#chk<?=$a.$s;?>'+i).prop("checked", true);
																$('#cours<?=$a.$s;?>'+i).attr("class","bg-blue-500");
															}

														});

														$('#deselectAll<?=$a.$s;?>').click(function() {
															
															$(this).attr('class','px-2 py-0 m-1 hidden');
															$('#selectAll<?=$a.$s;?>').attr('class','px-2 py-0 m-1');

															for (var i = 0; i < nbr ; i++) {
																$('#chk<?=$a.$s;?>'+i).prop("checked", false);
																$('#cours<?=$a.$s;?>'+i).attr("class","hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black");
															}

														});

														$('#scolarA<?=$a.$s;?>').on('change', function(){
															var scolarA<?=$a.$s;?> = $(this).val();
															var scolarSs<?=$a.$s;?> = $('#scolarSs<?=$a.$s;?>').val();
															
															if (scolarA<?=$a.$s;?> != '' && scolarSs<?=$a.$s;?> !='') {
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-white bg-black');
															}/*else{
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-slate-400 bg-slate-700 toolInactive');
															}*/

														});

														$('#scolarSs<?=$a.$s;?>').on('change', function(){
															var scolarSs<?=$a.$s;?> = $(this).val();
															var scolarA<?=$a.$s;?> = $('#scolarA<?=$a.$s;?>').val();
															
															if (scolarA<?=$a.$s;?> != '' && scolarSs<?=$a.$s;?> !='') {
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-white bg-black');
															}/*else{
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-slate-400 bg-slate-700 toolInactive');
															}*/

														});

													});
												</script>

		</table>
</form>

<!-- FIN DU FORMULAIRE -->
<?php
		}
	echo "</div>";
	}
?>
</div>
<script type="text/javascript">
	$(document).ready(function(){

	    window.onload = function() {
	        var a = '<?=$a-1?>';
	        window.location.hash = '#year'+a;
	    };

		// Attacher les événements du formulaire au chargement
		bindFormEvents();
		bindCoursEvents();
	});
	
	var hoverClass = "hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black";
	
	// Délégation d'événements pour les clics sur les lignes et checkboxes de cours
	// Ces événements survivent au remplacement du DOM via AJAX
	function bindCoursEvents() {
		var container = $('#coursListContainer');
		
		// Clic sur une ligne de cours (tr avec id commençant par "cours")
		container.off('click', 'tr[id^="cours"]').on('click', 'tr[id^="cours"]', function(e) {
			// Ignorer si le clic est directement sur la checkbox
			if ($(e.target).is('input[type="checkbox"]')) return;
			var id = $(this).attr('id');
			var chkId = id.replace('cours', 'chk');
			var chk = $('#' + chkId);
			if (chk.prop("checked") == false) {
				$(this).attr("class", "bg-blue-500");
				chk.prop("checked", true);
			} else {
				$(this).attr("class", hoverClass);
				chk.prop("checked", false);
			}
		});
		
		// Clic sur une checkbox (change la couleur de la ligne)
		container.off('change', 'input[id^="chk"]').on('change', 'input[id^="chk"]', function() {
			var id = $(this).attr('id');
			var rowId = id.replace('chk', 'cours');
			var row = $('#' + rowId);
			if ($(this).prop("checked")) {
				row.attr("class", "bg-blue-500");
			} else {
				row.attr("class", hoverClass);
			}
		});
		
		// Cocher tout
		container.off('click', 'a[id^="selectAll"]').on('click', 'a[id^="selectAll"]', function(e) {
			e.preventDefault();
			var suffix = $(this).attr('id').replace('selectAll', '');
			$(this).addClass('hidden');
			$('#deselectAll' + suffix).removeClass('hidden');
			$('input[id^="chk' + suffix + '"]').each(function() {
				$(this).prop("checked", true);
				var rowId = $(this).attr('id').replace('chk', 'cours');
				$('#' + rowId).attr("class", "bg-blue-500");
			});
		});
		
		// Décocher tout
		container.off('click', 'a[id^="deselectAll"]').on('click', 'a[id^="deselectAll"]', function(e) {
			e.preventDefault();
			var suffix = $(this).attr('id').replace('deselectAll', '');
			$(this).addClass('hidden');
			$('#selectAll' + suffix).removeClass('hidden');
			$('input[id^="chk' + suffix + '"]').each(function() {
				$(this).prop("checked", false);
				var rowId = $(this).attr('id').replace('chk', 'cours');
				$('#' + rowId).attr("class", hoverClass);
			});
		});
		
		// Changement de session/année → activer le bouton submit
		container.off('change', 'select[id^="scolarA"], select[id^="scolarSs"]').on('change', 'select[id^="scolarA"], select[id^="scolarSs"]', function() {
			var id = $(this).attr('id');
			// Extraire le suffixe (ex: "11", "12", "21", etc.)
			var suffix = id.replace('scolarA', '').replace('scolarSs', '');
			var scolarA = $('#scolarA' + suffix).val();
			var scolarSs = $('#scolarSs' + suffix).val();
			if (scolarA != '' && scolarSs != '') {
				$('#submit' + suffix).attr('class', 'submiting px-2 text-center py-0 m-1 text-white bg-black');
			}
		});
	}
	
	// Déterminer le chemin de base selon le contexte
	var basePath = window.location.pathname.includes('/inscription/') ? '<?=$app_base?>/app/.student/' : '<?=$app_base?>/app/.student/';
	
	// Fonction pour rafraîchir uniquement la liste des cours
	function refreshCoursList() {
		var scrollTop = $('#coursListContainer').scrollTop();
		var currentHash = window.location.hash;
		
		// Construire l'URL relative depuis la page actuelle
		var ajaxUrl = '<?=$app_base?>/app/.student/get-cours-list.php';
		
		$.ajax({
			url: ajaxUrl,
			method: 'GET',
			data: {
				id: <?=json_encode($id ?? '')?>,
				student_id: <?=json_encode($student_id ?? '')?>,
				etude_envisage: <?=json_encode($profil['etude_envisage'] ?? '')?>,
				etude_option: <?=json_encode($profil['etude_option'] ?? '')?>,
				level: <?=json_encode($profil['level'] ?? 1)?>
			},
			beforeSend: function() {
				$('#coursListContainer').css('opacity', '0.5');
			},
			success: function(data) {
				$('#coursListContainer').html(data).css('opacity', '1');
				// Restaurer la position du scroll
				$('#coursListContainer').scrollTop(scrollTop);
				// Réattacher les événements du formulaire
				bindFormEvents();
			},
			error: function() {
				$('#coursListContainer').css('opacity', '1');
				Toast.error('Erreur lors du rafraîchissement de la liste');
			}
		});
	}
	
	// Fonction pour réattacher les événements après le rafraîchissement AJAX
	function bindFormEvents() {
		$(".form-newCours").off('submit').on('submit', function (e) {
			e.preventDefault();
			 
			var form = $(this);
			var submitBtn = form.find('button[type="submit"]');
			var originalText = submitBtn.text();
			 
			// Vérifier si au moins une case est cochée
			if (form.find('input[type="checkbox"]:checked').length === 0) {
				Toast.warning('Veuillez sélectionner au moins un cours');
				return;
			}
			 
			// Désactiver le bouton pendant l'envoi
			submitBtn.prop('disabled', true).text('Enregistrement...');

			var url = form.data('action');
			
			if (!url) {
				Toast.error('URL du formulaire non trouvée');
				submitBtn.prop('disabled', false).text(originalText);
				return;
			}
			
			var data = $(this).serialize();

			$.post(url, data, function(response){
				Toast.success('Cours ajouté au transcript avec succès!');
				
				submitBtn.prop('disabled', false).text(originalText);
				refreshCoursList();
				
			}).fail(function(){
				Toast.error('Erreur lors de l\'ajout du cours');
				submitBtn.prop('disabled', false).text(originalText);
			});
		});
	}

</script>