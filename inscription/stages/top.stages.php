<?php 
	require('../../data/backdb.php');

	$student_id = $_POST['student_id'];

	$y = date('Y');

	$findStudent_Session = $dtb->query('SELECT * FROM t_2024_inscription_session WHERE student_id="'.$student_id.'" ORDER BY id DESC');

		$show_stape = $findStudent_Session->fetch();

if (!empty($show_stape)) {
	
	$aSem = $y." - ".($y+1);
	$aSem_ = ($y-1)." - ".$y;

	if (date('m') >= 7) {
		if ($show_stape['annee_scolaire'] == $aSem) {
 ?>
			<span target="_blank" class="text-xs p-1 <?php if ($show_stape['data_completion'] != 1) { echo "toolInactive"; $stage = 0;} else { $stage = 1;}?>">
				<center>
					<i class="bi-1-circle-fill text-2xl"></i><br>
						Informations enrégistrée
				</center>
			</span>

			<span target="_blank" id="stageMark_2" class="text-xs p-1 <?php if ($show_stape['data_verification'] != 1) { echo "toolInactive"; } else { $stage = 2;}?> ?>">
				<center>
					<i class="bi-2-circle-fill text-2xl"></i><br>
						Informations vérifiée
				</center>
			</span>

			<span target="_blank" id="stageMark_3" class="text-xs p-1 <?php if ($show_stape['cours_selected'] != 1) { echo "toolInactive"; } else { $stage = 3;}?> ?>">
				<center>
					<i class="bi-3-circle-fill text-2xl"></i><br>
						Cours ajouté
				</center>
			</span>

			<span target="_blank" id="stageMark_4" class="text-xs p-1 <?php if ($show_stape['mode_payement'] != 1) { echo "toolInactive"; } else { $stage = 4;}?> ?>">
				<center>
					<i class="bi-4-circle-fill text-2xl"></i><br>
						Mode de payement
				</center>
			</span>

			<span target="_blank" id="stageMark_5" class="text-xs p-1 <?php if ($show_stape['verification_signatures'] != 1) { echo "toolInactive"; } else { $stage = 5;}?> ?>">
				<center>
					<i class="bi-5-circle-fill text-2xl"></i><br>
						Impression, signatures
				</center>
			</span>

			<span target="_blank" id="stageMark_6" class="text-xs p-1 <?php if ($show_stape['depot_list'] != 1) { echo "toolInactive"; } else { $stage = 6;}?> ?>">
				<center>
					<i class="bi-6-circle-fill text-2xl"></i><br>
						Dépôt de la liste
				</center>
			</span>	
<?php
		}
	}elseif (date('m') < 7) {
		if ($show_stape['annee_scolaire'] == $aSem_) {
 ?>
			<span target="_blank" class="text-xs p-1 <?php if ($show_stape['data_completion'] != 1) { echo "toolInactive"; $stage = 0;} else { $stage = 1;}?>">
				<center>
					<i class="bi-1-circle-fill text-2xl"></i><br>
						Information bien enrégistrée
				</center>
			</span>

			<span target="_blank" id="stageMark_2" class="text-xs p-1 <?php if ($show_stape['data_verification'] != 1) { echo "toolInactive"; } else { $stage = 2;}?> ?>">
				<center>
					<i class="bi-2-circle-fill text-2xl"></i><br>
						Vérification de l'Information
				</center>
			</span>

			<span target="_blank" id="stageMark_3" class="text-xs p-1 <?php if ($show_stape['cours_selected'] != 1) { echo "toolInactive"; } else { $stage = 3;}?> ?>">
				<center>
					<i class="bi-3-circle-fill text-2xl"></i><br>
						Cours ajouté
				</center>
			</span>

			<span target="_blank" id="stageMark_4" class="text-xs p-1 <?php if ($show_stape['mode_payement'] != 1) { echo "toolInactive"; } else { $stage = 4;}?> ?>">
				<center>
					<i class="bi-4-circle-fill text-2xl"></i><br>
						Mode de payement
				</center>
			</span>

			<span target="_blank" id="stageMark_5" class="text-xs p-1 <?php if ($show_stape['verification_signatures'] != 1) { echo "toolInactive"; } else { $stage = 5;}?> ?>">
				<center>
					<i class="bi-5-circle-fill text-2xl"></i><br>
						Impression, signatures
				</center>
			</span>

			<span target="_blank" id="stageMark_6" class="text-xs p-1 <?php if ($show_stape['depot_list'] != 1) { echo "toolInactive"; } else { $stage = 6;}?> ?>">
				<center>
					<i class="bi-6-circle-fill text-2xl"></i><br>
						Dépôt de la liste
				</center>
			</span>
<?php
		}
	}
}
 ?>

				