<?php 

$mention = $_POST['types'];
$annee_scolaire = $_POST['yearworkedSlip'];
$date_begin = $_POST['date_begin'];
$date_end = $_POST['date_end'];

// Génération d'un code de vérification unique basé sur la date et les paramètres
$verification_base = date('Ymd').$annee_scolaire;

$printName = "WORKED_SLIP_ETUDIANT";
	
	// Exclure les étudiants: diplômés (graduated=1), suspendus (suspended=1), retirés (retrait_universite=2 ou 3)
	$exclusion = " AND (graduated IS NULL OR graduated != 1) AND (suspended IS NULL OR suspended != 1) AND (retrait_universite IS NULL OR retrait_universite = 0)";
	
	if ($mention == "TOUT") {
		$student = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$annee_scolaire.'"'.$exclusion.' ORDER BY student_id');
	}else{
		$student = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$annee_scolaire.'" AND etude_envisage="'.$mention.'"'.$exclusion.' ORDER BY student_id');
	}
?>
<style>
	.worked-slip-card {
		position: relative;
		overflow: hidden;
	}
	.worked-slip-card::before {
		content: 'UAZ OFFICIAL';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%) rotate(-25deg);
		font-size: 28px;
		font-weight: bold;
		color: rgba(0, 100, 180, 0.06);
		white-space: nowrap;
		pointer-events: none;
		z-index: 0;
	}
	.slip-content {
		position: relative;
		z-index: 1;
	}
	.security-badge {
		position: absolute;
		bottom: 2px;
		right: 5px;
		font-size: 6px;
		color: #666;
		font-family: monospace;
	}
	.official-stamp {
		position: absolute;
		top: 8px;
		right: 8px;
		width: 35px;
		height: 35px;
		border: 2px solid rgba(0, 100, 180, 0.3);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 6px;
		color: rgba(0, 100, 180, 0.5);
		text-align: center;
		font-weight: bold;
		transform: rotate(-15deg);
	}
</style>
<div class="w-full grid gap-2 grid-cols-2">
<?php 
	$slip_number = 1;
	while ($afficher = $student->fetch()){
		// Code de vérification unique pour chaque slip
		$verification_code = strtoupper(substr(md5($verification_base.$afficher['student_id']), 0, 8));
?>
	
	<div class="w-full border-1 border-black worked-slip-card">
		<div class="slip-content">
			<!-- Tampon officiel -->
			<div class="official-stamp">UAZ<br>WORK<br>EDU</div>
			
			<center><b style="font-size: 12px;">UNIVERSITE ADVENTISTE ZURCHER<br>Departement Work Education Clearance Slip</b></center>	
			<div style="font-size: 10px; height: 86.5px" class="px-2">
				<em>Matricule : </em><b> <?=$afficher['student_id']?></b><br>
				<em>Nom et prénoms : </em><b> <?=strtoupper($afficher['student_nom'])." ".$afficher['student_prenom']?></b><br>
				<em>Mention : </em><b><?=$afficher['etude_envisage']." - ".$afficher['etude_option']?></b><br>
				<em>Niveau :</em><b> Licence <?=$afficher['annee_etude']?></b><br>
				<em>Année académique :</em><b> <?=$afficher['annee_scolaire']?></b><br>
			</div>
			<div style="text-align: center;font-size: 12px">
				Cet(te) étudiant(e) peut écrire ses examens parce qu'il/elle a terminé toutes les heures requises de son Work Education.		
			</div>
			<hr style="margin: 0px;">
			<div class="h-[40px]">
				<table style="width:100%; font-size: 10px;">
					<tr>
						<td> 
							<em>Date du début : </em> <b><?=$date_begin?></b><br>
							<em>Date d'expiration : </em> <b><?=$date_end?></b><br>
						</td>
						<td>
							<em>Signature</em><br><br>
						</td>
					</tr>
				</table>
			</div>
			<!-- Code de vérification unique -->
			<div class="security-badge">
				REF: <?=$verification_code?> | <?=date('d/m/Y H:i')?>
			</div>
		</div>
	</div>

<?php 
		$slip_number++;
	}
?>	
</div>