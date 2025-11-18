<?php

	$yearFinance = $_POST['yearFinance'];
	$semestreFinance = $_POST['semestreFinance'];
	$types = $_POST['types'];
	$level = $_POST['level'];

	$printName = "FINANCE_ETUDIANT".$yearFinance."_Sem".$semestreFinance;


	$verifySession = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester ="'.$semestreFinance.'" AND session_year="'.$yearFinance.'"');
	$showSession = $verifySession->fetch();

	$session_id = $showSession['session_id'];
	
 ?>

<div class="">
	<b>État financier de l'étudiant inscrit en année <?=$yearFinance?> - <?=$showSession['session_name']?></b>
	<table class="simpleTbl tbl text-[8px]">
		<thead class="text-center">
			<tr>
				<th rowspan="2">No</th>
				<th rowspan="2">ID</th>
				<th rowspan="2">Nom et prénom</th>
				<th rowspan="2">Mention</th>
				<th rowspan="2">Frais généraux</th>
				<th rowspan="2">Nb crédit</th>
				<th rowspan="2">Ecolage</th>
				<th rowspan="2">Lab</th>
				<th colspan="2">Résidence</th>
				<th rowspan="2">Frais dépôt</th>
				<th colspan="2">Abonement</th>
				<th rowspan="2">Voyage/Colloque</th>
				<?php if ($semestreFinance != 1) { ?>
				<th>Frais Graduation</th>
				<?php } ?>
				<th rowspan="2">TOTAL</th>
				<th colspan="2">Payement</th>
				<th rowspan="2">Sponsor</th>
			</tr>
			<tr>
				<th>Types</th>
				<th>Logement</th>
				<th>Types</th>
				<th>Frais</th>
				<?php if ($semestreFinance != 1) { ?>
				<th>Frais Graduation</th>
				<?php } ?>
				<th>Types</th>
				<th>Tranche</th>
			</tr>
		</thead>
		<tbody>
<?php 

if ($types == 'TOUT') {
	if ($level == 'TOUT') {
		//$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE session_id = "'.$session_id.'" ORDER BY student_id');
		$findFinance = $dtb->query('
    SELECT f.*
    FROM t_2024_etudiant_finace f
    INNER JOIN (
        SELECT student_id, session_id, MAX(id) AS id_ref
        FROM t_2024_etudiant_finace
        WHERE session_id = "'.$session_id.'"
        GROUP BY student_id, session_id) x ON f.id = x.id_ref ORDER BY f.student_id');
	}else{
		//$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE session_id ="'.$session_id.'" AND level = "'.$level.'" ORDER BY student_id');
		$findFinance = $dtb->query('
    SELECT f.*
    FROM t_2024_etudiant_finace f
    INNER JOIN (
        SELECT student_id, session_id, MAX(id) AS id_ref
        FROM t_2024_etudiant_finace
        WHERE session_id = "'.$session_id.'"
		AND level = "'.$level.'"
        GROUP BY student_id, session_id) x ON f.id = x.id_ref ORDER BY f.student_id');	
	}
}else{
	if ($level == 'TOUT') {
		//$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE session_id ="'.$session_id.'" AND mention = "'.$types.'" ORDER BY student_id');
		$findFinance = $dtb->query('
    SELECT f.*
    FROM t_2024_etudiant_finace f
    INNER JOIN (
        SELECT student_id, session_id, MAX(id) AS id_ref
        FROM t_2024_etudiant_finace
        WHERE  session_id ="'.$session_id.'" 
		AND mention = "'.$types.'"
        GROUP BY student_id, session_id) x ON f.id = x.id_ref ORDER BY f.student_id');
	}else{
		//$findFinance = $dtb->query('SELECT * FROM t_2024_etudiant_finace WHERE session_id ="'.$session_id.'" AND mention = "'.$types.'"  AND level = "'.$level.'" ORDER BY student_id');
		$findFinance = $dtb->query('
    SELECT f.*
    FROM t_2024_etudiant_finace f
    INNER JOIN (
        SELECT student_id, session_id, MAX(id) AS id_ref
        FROM t_2024_etudiant_finace
        WHERE  session_id ="'.$session_id.'" 
		AND mention = "'.$types.'"  
		AND level = "'.$level.'"
        GROUP BY student_id, session_id) x ON f.id = x.id_ref ORDER BY f.student_id');
	}
}
	$nbrF = 1;
	$gttl = 0;
	while ($showF = $findFinance->fetch()) {
	$student_id = $showF['student_id'];
	
	$findStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');
	$showStd = $findStd->fetch();

	$findCours = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id ="'.$student_id.'" AND session_id = "'.$session_id.'" AND remove != 1');

		$nb_crd = 0;
		$ttl_cout = 0;
		$ttl_lab = 0;
		$n_lab = 0;
		while ($showCrs = $findCours->fetch()) {
			$idCours = $showCrs['id_cours'];
		
			$findSource = $dtb->query('SELECT * FROM t_2023_cours WHERE id = "'.$idCours.'"');
			$showSrc = $findSource->fetch();
			
			$nb_crd += $showCrs['credit'];
			$ttl_cout =+ $ttl_cout + $showSrc['cout'];

			if (!empty($showSrc['cout_lab']) AND $showSrc['cout_lab'] != 0) {							
				$n_lab++;
				
				if ($n_lab <= 2) {
					$ttl_lab += $showSrc['cout_lab'];
				}

			}
		}	

 ?>
			<tr style="<?php if ($ttl_cout == 0) { echo "background-color: #e87c68"; }?>">
				<td><?=$nbrF?></td>
				<td><?=$student_id?></td>
				<td><?=$showStd['student_nom']." ".$showStd['student_prenom']?></td>
				<td><?=$showF['mention']?></td>
				<td><?=$showF['cout_fraix_generaux']?></td>
				<td><?=$nb_crd?></td>
				<td><?=$ttl_cout?></td>
				<td><?=$ttl_lab?></td>
				<td><?=$showF['status']?></td>
				<td><?=$showF['cout_logement']?></td>
				<td><?=$showF['cout_fondDepot_dortoir']?></td>
				<td><?php if($showStd['abonment']==1){ echo "Abonné";}?></td>
				<td><?=$showF['cout_abonment']?></td>
				<td><?=$showF['cout_voyage']?></td>
				<?php if ($semestreFinance != 1) { ?>
				<td><?=$showF['cout_frais_graduation']?></td>
				<?php } ?>
				<td><?=$ttl = $showF['cout_fraix_generaux']+$ttl_cout+$ttl_lab+$showF['cout_logement']+$showF['cout_fondDepot_dortoir']+$showF['cout_abonment']+$showF['cout_voyage']+$showF['cout_frais_graduation']?></td>
				<td><?=$showF['mode_payement']?></td>
				<td><?php
if ($showF['mode_payement']== 'A') {
	echo ' <em class="text-[5px]">100%</em>';
}elseif ($showF['mode_payement']== 'B') {
	echo ' <em class="text-[5px]">50%,50%</em>';
}elseif ($showF['mode_payement']== 'C') {
	echo ' <em class="text-[5px]">75%,25%</em>';
}elseif ($showF['mode_payement']== 'D') {
	echo ' <em class="text-[5px]">40%,30%,30%</em>';
}elseif ($showF['mode_payement']== 'E') {
	echo ' <em class="text-[5px]">25%,25%,25%,25%</em>';
}			
			?>	</td>
				<td><?=$showStd['sponsor_nom']?></td>
			</tr>
<?php
	$gttl =+ $gttl + $ttl;
	$nbrF++;
	}
 ?>
		</tbody>
		<tfoot>
			<tr>
				<th colspan="4"></th>
				<th>FG</th>
				<th></th>
				<th>ECO</th>
				<th colspan="2"></th>
				<th>Dortoir</th>
				<th>FD</th>
				<th></th>
				<th colspan="2" class="bg-slate-200">Total général</th>
				<th colspan="4"><?=number_format($gttl, 0,'', ' ')?> ar</th>
			</tr>
		</tfoot>
	</table>
</div>
<?php require('../init/.forPrint/foot.forPrint.php');?>