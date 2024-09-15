<?php 
	require('../../data/backdb.php');
	$student_id = $_POST['student_id'];

 ?>
<b>Session</b>

<?php
	
	$y = date('Y');

	$findStudent_Session = $dtb->query('SELECT * FROM t_2024_inscription_session WHERE student_id="'.$student_id.'" ORDER BY id DESC');

	$showStudent_Session = $findStudent_Session->fetch();

	if (!empty($showStudent_Session)) {
	
		$aSem = $y." - ".($y+1);
		$aSem_ = ($y-1)." - ".$y;

		if (date('m') >= 7) {
			if ($showStudent_Session['annee_scolaire'] != $aSem) {

				echo '<br><a class="text-sm text-red-400">Non attribuée</a>';

			}else{

				$select_session = $dtb->query('SELECT * FROM t_2023_session WHERE session_id = "'.$showStudent_Session['session_id'].'"');
		
				$show_session = $select_session->fetch();

				echo '<p class="text-sm text-slate-400">'.$show_session['session_name'].'<br>'.$show_session['session_year'].'</p>';
				echo '<p id="session_id" style="display:none">'.$show_session['session_id'].'</p>';

			}
		}elseif (date('m') < 7) {
			if ($showStudent_Session['annee_scolaire'] != $aSem_) {

				echo '<br><a class="text-sm text-red-400">Non attribuée</a>';

			}else{

				$select_session = $dtb->query('SELECT * FROM t_2023_session WHERE session_id = "'.$showStudent_Session['session_id'].'"');
		
				$show_session = $select_session->fetch();

				echo '<p class="text-sm text-slate-400">'.$show_session['session_name'].'<br>'.$show_session['session_year'].'</p>';
				echo '<p id="session_id">'.$show_session['session_id'].'</p>';

			}
		}
	}else{
		echo '<br><a class="text-sm text-red-400">Non attribuée</a>';
	}

 ?>