<!-- 		<tbody>
			
			
			<tr class=" text-xs">
				<td>Frais généraux/semestre</td>

<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="frais_generaux_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['frais_generaux']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="frais_generaux_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['frais_generaux']?>"></td>
<?php
			}
		}

?>
			</tr>
			<tr class=" text-xs">
				<td>Labo INFO/semestre</td>

<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="laboratory_info_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['laboratory_info']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="laboratory_info_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['laboratory_info']?>"></td>
<?php
			}
		}

?>
			</tr>
			<tr class=" text-xs">
				<td>Labo LANGUE/semestre</td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="laboratory_lang_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['laboratory_lang']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="laboratory_lang_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['laboratory_lang']?>"></td>
<?php
			}
		}

?>
			</tr>
			<tr class=" text-xs">
				<td>Dortoir/jour</td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="dortoir_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['dortoir']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="dortoir_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['dortoir']?>"></td>
<?php
			}
		}

?>
			</tr>
			<tr class=" text-xs">
				<td>Cantine/jour</td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="cafeteria_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['cafeteria']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="cafeteria_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['cafeteria']?>"></td>
<?php
			}
		}

?>
			</tr>

			<tr class=" text-xs">
				<td>Fond de dépôt</td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="fond_depot_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['fond_depot']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="fond_depot_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['fond_depot']?>"></td>
<?php
			}
		}

?>
			</tr>
			<tr class=" text-xs">
				<td>Nombre du jour - interne</td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="nb_jours_semestre_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['nb_jours_semestre']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="nb_jours_semestre_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['nb_jours_semestre']?>"></td>
<?php
			}
		}

?>
			</tr>


			<tr class=" text-xs">
				<td>Ecolage <em>(Multiplier par nombre de crédit)</em></td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="ecolage_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['ecolage']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="ecolage_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['ecolage']?>"></td>
<?php
			}
		}

?>
			</tr>
<?php 
if ($i == 2) {
?>
			<tr class=" text-xs">
				<td>Frais de graduation</td>
	<?php
	/* -------------------------------------- LICENCE ------------------------------------ */
		for ($level=1; $level <4 ; $level++) { 
			for ($status=1; $status <3 ; $status++) {			
				if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
					
					$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
		
					$fncL = $financeSemestreL->fetch();
	?>

			<td class="prntInput"><input id="" type="number" name="frais_graduation_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['frais_graduation']?>"></td>
	<?php
				}
			}	

	?>

					
	<?php
	/* ------------------------------------- MASTER -------------------------------------- */
		for ($levelM=4; $levelM <6 ; $levelM++) {
			for ($status=1; $status <3 ; $status++) {			
						if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
							
							$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
				
							$fncM = $financeSemestreM->fetch();
	?>

			<td class="prntInput"><input id="" type="number" name="frais_graduation_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['frais_graduation']?>"></td>
	<?php
				}
			}

	?>
			</tr>
<?php 
}
?>

			<tr class=" text-xs">
				<td><?php if ($f_list['filiere_sigle'] == 'THEO') {
					echo "Colloque";}else{ echo "Voyage d'étude";} ?></td>
<?php
/* -------------------------------------- LICENCE ------------------------------------ */
	for ($level=1; $level <4 ; $level++) { 
		for ($status=1; $status <3 ; $status++) {			
			if ($status==1) { $sttL = "Interne";}else{ $sttL = "Externe";}
				
				$financeSemestreL = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='L' AND std_status = '".$sttL."' AND level ='".$level."' ");
	
				$fncL = $financeSemestreL->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="frais_voyage_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$sttL?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncL['frais_voyage']?>"></td>
<?php
			}
		}	

?>

				
<?php
/* ------------------------------------- MASTER -------------------------------------- */
	for ($levelM=4; $levelM <6 ; $levelM++) {
		for ($status=1; $status <3 ; $status++) {			
					if ($status==1) { $sttM = "Interne";}else{ $sttM = "Externe";}
						
						$financeSemestreM = $dtb->query("SELECT * FROM t_2024_finance_detail_licence WHERE std_mention ='".$filiere_sigle."' AND semester = '".$i."' AND categorie ='M' AND std_status = '".$sttM."' AND level = '".$levelM."' ");
			
						$fncM = $financeSemestreM->fetch();
?>

		<td class="prntInput"><input id="" type="number" name="frais_voyage_<?=$filiere_sigle?>_<?=$levelM?>_<?=$i?>_<?=$sttM?>" class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?>" value="<?=$fncM['frais_voyage']?>"></td>
<?php
			}
		}

?>
			</tr>
		</tbody>


 -->