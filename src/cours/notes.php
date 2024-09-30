<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">
<?php 
$year = date('Y')+1;
for ($i=0; $i < 4 ; $i++) { 

$soustract = $year - $i;
$preced = $soustract - 1;	
?>

<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
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
						<td class="w-20">Notes/20</td>
						<td class="w-4">État</td>
						<td class="w-4"><span class="bi-trash3-fill"></span></td>
					</tr>
				</thead>
				<tbody class="<?=$bg_four_color?>">
<?php
$sigle = $profil['Sigle'];
$title = $profil['title'];
$cors = $dtb->query('SELECT * FROM t_2023_notes WHERE Sigle ="'.$sigle.'" AND title_cours = "'.$title.'" AND annee_scolaire = "'.$scolaire.'" AND ajout = 1 ORDER BY student_id');

$nbr = 1;
while ($cours_table = $cors->fetch()) {
$idcours = $cours_table['id'];
 ?>

<form method="post" action="../app/.cours/completnotes.php?id=<?=$id?>&idcours=<?=$idcours?>&year=<?=$preced.$soustract;?>&sigle=<?=$cours_table['Sigle']?>" class="form-no-refrech<?=$nbr.$soustract?>">
					<tr id="<?=$cours_table['Sigle'].$preced.$soustract?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black">
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
					<td class="c<?=$nbr.$soustract;?>">L<?=$apotr['annee_etude']?></td>
					<td class="c<?=$nbr.$soustract;?>"><?=$cours_table['semester']?></td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0">
<?php 
if ($privilege == "registrar" OR $privilege == "administrator") {
 ?>
<input class="insimple text-sm bg-transparent px-2 g<?=$nbr.$soustract;?>" type="text" name="note" value="<?=$cours_table['grade'];?>" min="0" max="20">
<?php 
}else{
	echo "<a class='px-2'>".$cours_table['grade']."</a>";
}
 ?>
					</td>
					<td class='stp<?=$nbr.$soustract;?> <?php 
if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) {
	echo "bg-green-500";

}elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) {
	echo "bg-red-500";
}elseif ($cours_table['grade'] == 0){
	echo "bg-none";
} ?> text-center' title="<?php 
if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) {
	echo "Succès";
}elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0){
	echo "Echec";
}elseif ($cours_table['grade'] == 0){
	echo "";
}

							 ?>">
						<?php 
	if ($cours_table['grade'] == 0 OR $cours_table['grade'] == "") {
		echo "";
	}elseif ($cours_table['grade'] < 10) {
		echo "E";
	}elseif ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10){
		echo "S";
	}
							 ?>
					</td>
					<td>
							<div class="nav-item dropstart" style="list-style: none">
								<a href="#" class="btn nav-link" type="button" role="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="bi-three-dots-vertical"></span></a>
								<ul class="dropdown-menu">
							        <li>
<a class="dropdown-item" href="app/.cours/dell-listcours.incours.php?idSupprCours=<?=$idcours?>&id=<?=$id?>"><span class="bi-trash3-fill" style="color: red;"></span> Supprimer cet étudiant</a></li>
					          		</ul>
							</div>
						</td>
					</tr>
		<button type="submit" style="display: none;"></button>
		<script type="text/javascript">
			$(document).ready(function(){
				$('.form-no-refrech<?=$nbr.$soustract?>').on('submit',function(e){
					e.preventDefault();
					
					var url = '../app/.cours/completnotes.php?id=<?=$id?>&idcours=<?=$idcours?>&year=<?=$preced.$soustract;?>&sigle=<?=$cours_table['Sigle']?>';
					var data = $(this).serialize();

					$.post(url,data,function(response){
							
							$('.g<?=$nbr.$soustract?>').css({
								'border':'none',
								'border-radius':'0px',
								'background':'none'
							})
							$('.g<?=$nbr.$soustract?>').blur();
							
							
							if($('.g<?=$nbr.$soustract;?>').val() == 0){
								$('.stp<?=$nbr.$soustract;?>').css({
									'background' : 'none',
								})
								$('.stp<?=$nbr.$soustract;?>').text('');

							}else if($('.g<?=$nbr.$soustract;?>').val() < 10){
								$('.stp<?=$nbr.$soustract;?>').css({
									'background' : '#ff0000',
									'color' : 'white',
								})
								$('.stp<?=$nbr.$soustract;?>').text('E');

							}else if($('.g<?=$nbr.$soustract;?>').val()>=10){
								$('.stp<?=$nbr.$soustract;?>').css({
									'background' : '#15dd2a',
									'color' : 'white',
								})
								$('.stp<?=$nbr.$soustract;?>').text('S');

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