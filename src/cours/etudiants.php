<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">
<?php 
$year = date('Y')+1;
for ($i=0; $i < 4 ; $i++) { 

$soustract = $year - $i;
$preced = $soustract - 1;	
?>

<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all overflow-auto'>
<div class="text-center bg-gradient-to-r from-cyan-500">
	<b>Etudiants en année <?php echo $scolaire = $preced." - ".$soustract; ?>.</b>
</div>

	<div>

		<div>


			<table class="simpleTbl mb-1">
				<thead class="<?=$bg_one_color?> text-white">
					<tr>
						<td class="w-20">ID</td>
						<td>Nom et Prénoms</td>
						<td class="w-20">Niveau</td>
						<td class="w-20">Semestre</td>
						<td class="w-4"><span class="bi-trash3-fill"></span></td>
					</tr>
				</thead>
				<tbody class="<?=$bg_four_color?>">
<?php
$sigle = $profil['Sigle'];
$title = $profil['title'];
$cors = $dtb->query('SELECT * FROM t_2023_notes WHERE Sigle ="'.$sigle.'" AND annee_scolaire = "'.$scolaire.'" AND remove = 0 ORDER BY student_id');

$nbr = 1;
while ($cours_table = $cors->fetch()) {
$idcours = $cours_table['id'];
 ?>

<form method="post" action="<?=$app_base?>/app/completnotes?id=<?=$id?>&idcours=<?=$idcours?>" class="form-no-refrech">
					<tr>
						<td class="bg-gradient-to-r from-orange-800 to-orange-400"><?=$cours_table['student_id']?></td>
						<td class="c<?=$nbr.$i;?>"><?php

$jer = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE student_id='".$cours_table['student_id']."'");
$apotr = $jer->fetch();
if($apotr){
	if(is_null($apotr['student_nom']) AND is_null($apotr['student_prenom'])){
		echo "<em style='color:red'>Non défini</em>";
	}else{
		echo $apotr['student_nom']." ".$apotr['student_prenom'];
	}
}else{
	echo "<em style='color:red'>Etudiant non inscrit dans la base!!</em>";
}
					?></td>
						<td class="c<?=$nbr.$i;?>">
<?php 
if($apotr){
	echo "L".$apotr['annee_etude'];
}else{
	echo "-";
}
 ?>			
						</td>
					<td class="c<?=$nbr.$i;?>"><?=$cours_table['semester']?></td>
						<td>
							<div class="nav-item dropstart" style="list-style: none">
								<a href="#" class="btn nav-link" type="button" role="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="bi-three-dots-vertical"></span></a>
								<ul class="dropdown-menu">
							        <li>
<a class="dropdown-item" href="actions/dell-listcours.incours?idSupprCours=<?=$idcours?>&id=<?=$id?>"><span class="bi-trash3-fill" style="color: red;"></span> Supprimer cet étudiant</a></li>
					          		</ul>
							</div>
						</td>
					</tr>
		<button type="submit" style="display: none;"></button>
		<script type="text/javascript">
			$(document).ready(function(){
				$('.form-no-refrech').on('submit',function(e){
					e.preventDefault();
					var url = 'actions/completnotes';
					var data = $(this).serialize();

					$.post(url,data,function(response){
							
							$('.g<?=$nbr.$i?>').css({
								'border':'none',
								'border-radius':'0px',
								'background':'none'
							})
							$('.g<?=$nbr.$i?>').blur();
							
							
							if($('.g<?=$nbr.$i;?>').val() == 0){
								$('.stp<?=$nbr.$i;?>').css({
									'background' : 'none',
								})
								$('.stp<?=$nbr.$i;?>').innerHTML('');

							}else if($('.g<?=$nbr.$i;?>').val() < 10){
								$('.stp<?=$nbr.$i;?>').css({
									'background' : '#ff0000',
									'color' : 'white',
								})
								$('.stp<?=$nbr.$i;?>').innerHTML('E');

							}else if($('.g<?=$nbr.$i;?>').val()>=10){
								$('.stp<?=$nbr.$i;?>').css({
									'background' : '#15dd2a',
									'color' : 'white',
								})
								$('.stp<?=$nbr.$i;?>').innerHTML('S');

							}
							
					})

				})
			})

		</script>
</form>
					
<?php 

$nbr++;
	}
 ?>
				</tbody>
			</table>

<div class="text-center text-white">
	<b><p style="margin: 0px;">Nombre : <?php 
if (($nbr-1)<=1) {
	echo ($nbr-1)." étudiant";
}else{
	echo ($nbr-1)." étudiants";
}
	?></p></b>

</div>
	</div>

				</div>
</div>
<?php 
	}
 ?>
</div>