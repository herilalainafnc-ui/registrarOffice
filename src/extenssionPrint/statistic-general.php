<?php 
$yearNow = date('Y');

$semestre =$_POST['semestre'];
$yearScoolNow = $_POST['yearStatistic'];

$findSessionOnSS = $dtb->query('SELECT * FROM t_2023_session WHERE session_name ="'.$semestre.'" AND session_year = "'.$yearScoolNow.'"');

$showSessionOnSS = $findSessionOnSS->fetch();
$session_id = $showSessionOnSS['session_id'];

$globalStatTotalQ = $dtb->query('SELECT COUNT(DISTINCT ins.student_id) AS total
 FROM t_2024_inscription_session ins
 INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
 WHERE ins.session_id = "'.$session_id.'"
	 AND ins.etude_mention IN (SELECT filiere_sigle FROM filiere WHERE filiere_sigle != "CPRE" AND filiere_sigle != "EDUC")
	 AND (std.suspended IS NULL OR std.suspended != 1)
	 AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)');
$globalStatTotal = intval(($globalStatTotalQ->fetch())['total'] ?? 0);

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique générale </b><em class="text-xs"> • <b>Session : </b> <?=$semestre." ".$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
				<th style="width: 8%" colspan="2">R.niveau</th>
				<th style="width: 8%" colspan="2">Licence 1</th>
				<th style="width: 8%" colspan="2">Licence 2</th>
				<th style="width: 8%" colspan="2">Licence 3</th>
				<th style="width: 8%" colspan="2">Master 1</th>
				<th style="width: 8%" colspan="2">Master 2</th>
				<th style="width: 4%" colspan="2">Total</th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th style="color: orange;"></th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
			</tr>
			
		</thead>
		<tbody>
			<?php

 $mentio = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE" AND filiere_sigle !="EDUC"');

	$rm_N = 0;
	$rm_A = 0;
	$licence1_N = 0;
	$licence1_A = 0;
	$licence2_N = 0;
	$licence2_A = 0;
	$licence3_N = 0;
	$licence3_A = 0;
	$master1_N = 0;
	$master1_A = 0;
	$master2_N = 0;
	$master2_A = 0;
	$thorizontal_N = 0;
	$thorizontal_A = 0;

	while($mt = $mentio->fetch()){
		$filiere_sigle= $mt['filiere_sigle'];
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 0 AND ins.new_student = 1 THEN ins.student_id END) AS RM_N,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 0 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS RM_A,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 1 AND ins.new_student = 1 THEN ins.student_id END) AS Licence1_N,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 1 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS Licence1_A,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 2 AND ins.new_student = 1 THEN ins.student_id END) AS Licence2_N,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 2 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS Licence2_A,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 3 AND ins.new_student = 1 THEN ins.student_id END) AS Licence3_N,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 3 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS Licence3_A,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 4 AND ins.new_student = 1 THEN ins.student_id END) AS Master1_N,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 4 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS Master1_A,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 5 AND ins.new_student = 1 THEN ins.student_id END) AS Master2_N,
		COUNT(DISTINCT CASE WHEN ins.niveau_std = 5 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS Master2_A,
		COUNT(DISTINCT ins.student_id) AS MentionTotal

 FROM t_2024_inscription_session ins
 INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
 WHERE ins.etude_mention = "'.$filiere_sigle.'" AND ins.session_id = "'.$session_id.'" AND (std.suspended IS NULL OR std.suspended != 1) AND (std.retrait_universite IS NULL OR std.retrait_universite = 0) ORDER BY ins.etude_mention');

           $row = $result->fetch();

            	echo "<tr>";   
	   				echo "<td>" . $mention . "</td>"; 
	                echo "<td>" . $row['RM_N'] . "</td>";
	                echo "<td>" . $row['RM_A'] . "</td>";
	                echo "<td>" . $row['Licence1_N'] . "</td>";
	                echo "<td>" . $row['Licence1_A'] . "</td>";
	                echo "<td>" . $row['Licence2_N'] . "</td>";
	                echo "<td>" . $row['Licence2_A'] . "</td>";
	                echo "<td>" . $row['Licence3_N'] . "</td>";
	                echo "<td>" . $row['Licence3_A'] . "</td>";
	                echo "<td>" . $row['Master1_N'] . "</td>";
	                echo "<td>" . $row['Master1_A'] . "</td>";
	                echo "<td>" . $row['Master2_N'] . "</td>";
	                echo "<td>" . $row['Master2_A'] . "</td>";
	 				echo "<td>".$sommeHoriz_N =  
	                $row['RM_N']
	                + $row['Licence1_N']
	                + $row['Licence2_N']
	                + $row['Licence3_N']
	                + $row['Master1_N']
	                + $row['Master2_N']."</td>";
	                echo "<td>".$sommeHoriz_A =  
	                $row['RM_A']
	                + $row['Licence1_A']
	                + $row['Licence2_A']
	                + $row['Licence3_A']
	                + $row['Master1_A']
	                + $row['Master2_A']."</td>";

    			echo "</tr>";
    		
$rm_N += $row['RM_N'];
$rm_A += $row['RM_A'];
$licence1_N += $row['Licence1_N'];
$licence1_A += $row['Licence1_A'];
$licence2_N += $row['Licence2_N'];
$licence2_A += $row['Licence2_A'];
$licence3_N += $row['Licence3_N'];
$licence3_A += $row['Licence3_A'];
$master1_N += $row['Master1_N'];
$master1_A += $row['Master1_A'];
$master2_N += $row['Master2_N'];
$master2_A += $row['Master2_A'];
$thorizontal_N += intval($sommeHoriz_N);
$thorizontal_A += intval($sommeHoriz_A);
   
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid; color: orange;" >
				<th style="color: orange;">Sous total</th>
				<th style="color: orange;"><?=$rm_N?></th>
				<th style="color: orange;"><?=$rm_A?></th>
				<th style="color: orange;"><?=$licence1_N?></th>
				<th style="color: orange;"><?=$licence1_A?></th>
				<th style="color: orange;"><?=$licence2_N?></th>
				<th style="color: orange;"><?=$licence2_A?></th>
				<th style="color: orange;"><?=$licence3_N?></th>
				<th style="color: orange;"><?=$licence3_A?></th>
				<th style="color: orange;"><?=$master1_N?></th>
				<th style="color: orange;"><?=$master1_A?></th>
				<th style="color: orange;"><?=$master2_N?></th>
				<th style="color: orange;"><?=$master2_A?></th>
				<th style="color: orange;"><?=$thorizontal_N?></th>
				<th style="color: orange;"><?=$thorizontal_A?></th>
				
			</tr>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th colspan="2"><?=$rm_N+$rm_A?></th>
				<th colspan="2"><?=$licence1_N+$licence1_A?></th>
				<th colspan="2"><?=$licence2_N+$licence2_A?></th>
				<th colspan="2"><?=$licence3_N+$licence3_A?></th>
				<th colspan="2"><?=$master1_N+$master1_A?></th>
				<th colspan="2"><?=$master2_N+$master2_A?></th>
				<th colspan="2"><?=$globalStatTotal?></th>
			</tr>
		</thead>
	</table>

</div>