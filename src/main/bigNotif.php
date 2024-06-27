<!-- FOR MESUPRES -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="bigNotifCours" style="backdrop-filter: blur(3px);">

		<div class="w-[1000px] bg-slate-100 border-2 border-slate-700 mx-auto my-[1%] opacity-100 drop-shadow-2xl">
			<form method="post" action="../app/addCours.php" enctype="multipart/form-data">
			<div class="p-2 text-black">
				<b>Ajouter un cours.</b>
			</div>
			<div class="p-2">
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->

			<b class="toolInactive">Info Générale.</b>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-2/12">
					<label>Sigle</label><br>
				 	<input class="input w-full" type="text" name="sigle">
				</div>
				<div class="w-5/12">
					<label>Titre du cours</label><br>
				 	<input class="input w-full" type="text" name="title">
				</div>
				<div class="w-5/12">
					<label>Titre en anglais</label>
				 	<input class="input w-full" type="text" name="title_english">
				</div>
			</div>
<hr><br>
			<b class="toolInactive">Détail.</b>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-2/12">
					<label>Crédit</label><br>
 					<select class="input w-full" type="number" name="nb_credit">
 						<option>1</option>
 						<option>2</option>
 						<option>3</option>
 						<option>4</option>
 						<option>5</option>
 						<option>6</option>
 						<option>7</option>
 					</select>
				</div>
				<div class="w-2/12">
					<label>Catégorie</label><br>
 					<select class="input w-full" type="number" name="category">
 						<option value="0">Général</option>
 						<option value="1">Majeur</option>
 						<option value="2">Séléctive</option>
 						<option value="3">Additionnel</option>
 					</select>
				</div>
				<div class="w-3/12">
					<label>Département</label><br>
 					<select class="input w-full" type="number" name="dep_desc">
<?php 
$cat = $dtb->query("SELECT * FROM filiere");

while($ct = $cat->fetch()){
?>
 						<option><?=$ct['filiere_sigle']; ?></option>

<?php 
}
?>
 					</select>
				</div>
				<div class="w-5/12">
					<label>Professeur</label><br>
 					<select class="input w-full" type="number" name="teacher_id">
<?php 
$teach = $dtb->query("SELECT * FROM teacher ORDER BY lastName");

while($tch = $teach->fetch()){
?>
 						<option value="<?=$tch['uid'] ?>"><b><?=$tch['lastName']?></b> <?=$tch['name']; ?></option>

<?php 
}
?>
 					</select>
				</div>
			</div>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-2/12">
					<label>Niveau du cours</label><br>
 					<select class="input w-full" type="number" name="yearlevel">
 						<option value="1">Licence 1</option>
 						<option value="2">Licence 2</option>
 						<option value="3">Licence 3</option>
 						<option value="4">Master 1</option>
 						<option value="5">Master 2</option>
 					</select>
				</div>
				<div class="w-2/12">
					<label>Semèstre</label><br>
 					<select class="input w-full" type="number" name="semester">
 						<option>1</option>
 						<option>2</option>
 					</select>
				</div>
				<div class="w-2/12">
					<label>Laboratoire</label>
 					<select class="input w-full" type="number" name="lab">
 						<option></option>
 						<option>Lab 1</option>
 						<option>Lab 2</option>
 						<option>Lab 3</option>
 						<option>Simulation Room</option>
 						<option>Labo recherche</option>
 					</select>
				</div>
				
			</div>
<hr><br>
			<b class="toolInactive">Autre.</b>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-3/12">
					<label>Description</label><br>
 					<textarea class="w-full h-12 input" name="description"></textarea>
				</div>
				<div class="w-3/12">
					<label>Remarque</label><br>
				 	<textarea class="w-full h-12 input" name="remark"></textarea>
				</div>
			</div>

				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifAddCours" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnAddCours" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Enregistrer">
				</center>
			</div>
			</form>
		</div>

	</div>



<script type="text/javascript">
	$(document).ready(function() {
			/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#addCours').click(function(){
			$('#bigNotifCours').css({'display':'block'});
		});
		
		$('#cancelnotifAddCours').click(function(){
			$('#bigNotifCours').css({'display':'none'});
		});
	});
</script>
<style type="text/css">
	.input{
		padding: 0px 2px 0px 2px;
		font-size: 13px;
	}
</style>