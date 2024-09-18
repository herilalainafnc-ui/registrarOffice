<!-- AJOUT DE COURS -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="bigNotifCours" style="backdrop-filter: blur(3px);">

		<div class="w-[700px] bg-slate-300 border-2 border-slate-700 mx-auto my-[1%] opacity-100 drop-shadow-2xl">
			<form method="post" action="../app/.cours/addCours.php" enctype="multipart/form-data" class="form-no-refrech-cours">
			<div class="p-2 text-black">
				<b>Ajouter un cours.</b>
			</div>
			<div class="p-2">
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->

			<b class="toolInactive">Info Générale.</b>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-2/12">
					<label>Sigle</label><br>
				 	<input class="input w-full requierd-cours-1" type="text" name="sigle" id="sigleInput">
				</div>
				<div class="w-5/12">
					<label>Titre du cours</label><br>
				 	<input class="input w-full requierd-cours-2" type="text" name="title">
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
 						<option>8</option>
 						<option>9</option>
 						<option>10</option>
 						<option>11</option>
 						<option>12</option>
 						<option>13</option>
 						<option>14</option>
 						<option>15</option>
 						<option>16</option>
 						<option>17</option>
 						<option>18</option>
 						<option>19</option>
 						<option>20</option>
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
					<label>Mention</label><br>
 					<select class="input w-full" name="dep_desc" id="mentionSelect">
<?php 
$cat = $dtb->query("SELECT * FROM filiere");

while($ct = $cat->fetch()){
?>
 						<option><?=$ct['filiere_sigle']?></option>

<?php 
}
?>
 					</select>
				</div>
				<div class="w-5/12">
					<label>Parcours</label>
					<div id="parcours">
	 					<select class="input w-full" type="number" name="parcours">
							<option value="all">-</option>
	 					</select>
 					</div>
				</div>
			</div>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-2/12">
					<label>Niveau du cours</label><br>
 					<select class="input w-full" type="number" name="yearlevel">
 						<option value="0">Remise à niveau</option>
 						<option value="1" selected>Licence 1</option>
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
				<div class="w-3/12">
					<label>Laboratoire</label>
 					<select class="input w-full" type="number" name="lab">
 						<option value="0"></option>
 						<option value="1">Lab 1</option>
 						<option value="2">Lab 2</option>
 						<option value="3">Lab 3</option>
 						<option value="4">Simulation Room</option>
 						<option value="5">Labo recherche</option>
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
<hr><br>
			<b class="toolInactive">Autre.</b>

			<div class="w-full gap-2 flex mb-3">
				<div class="w-6/12">
					<label>Description</label><br>
 					<textarea class="w-full h-12 inputDescript" name="description"></textarea>
				</div>
				<div class="w-6/12">
					<label>Remarque</label><br>
				 	<textarea class="w-full h-12 inputDescript" name="remark"></textarea>
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


<!-- AJOUT DE PROF -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="bigNotifProf" style="backdrop-filter: blur(3px);">

		<div class="w-[700px] bg-slate-300 border-2 border-slate-700 mx-auto my-[1%] opacity-100 drop-shadow-2xl">
			<form id="formToAddProf" enctype="multipart/form-data">
			<div class="p-2 text-black flex">
				<div class="w-7/12">
					<b>Ajouter un enseignant.</b>	
				</div>
				<div class="w-5/12 text-right">
					<p><em class="text-red-500" id="alert-prof"></em></p>	
				</div>
			</div>
			<div class="p-2 flex gap-2">
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
<div class="w-7/12">
	<div class="w-full/12 mb-2">
		<label>Nom</label><br>
		<input class="input w-full requierd-prof-1" type="text" name="name">
	</div>
	<div class="w-full/12 mb-2">
		<label>Prénom</label><br>
		<input class="input w-full" type="text" name="lastName">
	</div>
	<div class="w-full flex gap-1 mb-2">
		<div class="w-5/12">
			<label>Date de naissance</label><br>
			<input class="input w-full" type="date" name="birthday">
		</div>
		<div class="w-7/12">
			<label>Lieu de naissance</label><br>
			<input class="input w-full" type="text" name="lieuN">
		</div>	
	</div>

	<div class="w-full/12 mb-2">
		<label>Adresse</label><br>
		<input class="input w-full requierd-prof-2" type="text" name="address">
	</div>

	<div class="w-full flex gap-1 mb-2">
		<div class="w-6/12">
			<label>Genre</label><br>
			<select class="input w-full" type="number" name="sex">
				<option value="1">Masculin</option>
				<option value="0">Féminin</option>
			</select>
		</div>
		<div class="w-6/12">
			<label>Groupe de sang</label><br>
			<input class="input w-full" type="text" name="blood_group">
		</div>	
	</div>

</div>
<div class="w-5/12">
	<div class="w-full mb-2">
		<label class="text-sm" for="teacher_image">Photos</label><br>

		<label for="teacher_image">
			<div class="bg-slate-600 rounded-md h-20 w-20 text-center py-3">
				<i class="bi-image text-4xl text-white"></i>
			</div>
		</label>
		<input type="file" name="teacher_image" id="teacher_image" class="hidden">
		
	</div>

	<div class="w-full/12 mb-2">
		<label>Téléphone</label><br>
		<input class="input w-full requierd-prof-3" type="text" name="phone">
	</div>

	<div class="w-full/12 mb-2">
		<label>Email</label><br>
		<input class="input w-full requierd-prof-4" type="text" name="email">
	</div>


	<div class="w-full/12 mb-2">
		<label>Dilplôme</label><br>
		<input class="input w-full requierd-prof-5" type="text" name="diplome">
	</div>

	<div class="w-full/12 mb-2">
		<label>Réligion</label><br>
		<select class="input w-full" type="number" name="religion">
			<option>Adventiste</option>
			<option>Non Adventiste</option>
		</select>
	</div>

	<div class="w-full/12 mb-2">
		<label>Position</label><br>
		<input class="input w-full requierd-prof-6" type="text" name="position">
	</div>
</div>	
				<!-- :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::: -->
			</div>
			<div class="p-3">
				<center>
					<a href="#" id="cancelnotifAddProf" class="bg-slate-400 p-2 rounded-md">Annuler</a>
					<input id="btnAddProf" type="button" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Enregistrer">
				</center>
			</div>
			</form>
		</div>

	</div>

<script type="text/javascript">
	$(document).ready(function() {
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
			$('#mentionSelect').on('change',function(){
				var mentionSelect = $(this).val();
					
					$.ajax({
					url:"./services/parcours.live.addCours.php",
					method:"POST",
					data:{mentionSelect:mentionSelect},

					success:function(data){
						$("#parcours").html(data);
					}
				});
			});
		
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#addCours').click(function(){
			$('#bigNotifCours').css({'display':'block'});
			$('#sigleInput').focus();
		});
		
		$('#cancelnotifAddCours').click(function(){
			$('#bigNotifCours').css({'display':'none'});
		});
		$('.requierd-cours-1').keyup(function() {
			var r_2 = $('.requierd-cours-2').val();

			if ($(this).val() == "" || r_2 == "") {

				$('#btnAddCours').attr('class','bg-slate-400 p-2 rounded-md mx-1 toolInactive');
			
			}else{
			
				$('#btnAddCours').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');

			}

		});
		$('.requierd-cours-2').keyup(function() {
			var r_1 = $('.requierd-cours-1').val();

			if ($(this).val() == "" || r_1 == "") {

				$('#btnAddCours').attr('class','bg-slate-400 p-2 rounded-md mx-1 toolInactive');
				
			}else{

				$('#btnAddCours').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			}

		});

		$('.form-no-refrech-cours').on('submit',function (e) {
			e.preventDefault();

			var url = '../app/.cours/addCours.php';
			var data = $(this).serialize();
			
			$.post(url,data,function(response){
				alert('Opération bien effectué.');
				$('#bigNotifCours').css({'display':'none'});
			});
		});
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#addProf').click(function(){
			$('#bigNotifProf').css({'display':'block'});
		});
		
		$('#cancelnotifAddProf').click(function(){
			$('#bigNotifProf').css({'display':'none'});
		});
		
		$('#btnAddProf').click(function() {
			var rec_1 = $('.requierd-prof-1').val();
			var rec_2 = $('.requierd-prof-2').val();
			var rec_3 = $('.requierd-prof-3').val();
			var rec_4 = $('.requierd-prof-4').val();
			var rec_5 = $('.requierd-prof-5').val();
			

			if (rec_1 !="" && rec_2 !="" && rec_3 !="" && rec_4 !="" && rec_5 !="") {
				$('#formToAddProf').attr('method','post');
				$('#formToAddProf').attr('action','../app/.prof/addProf.php');
				$('#btnAddProf').attr('type','submit');

			}else{
				$('.requierd-prof-1').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-2').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-3').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-4').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-5').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				
				$('#alert-prof').text('Ces zones sont obligatoires !');
			}
		});
		
	});
</script>
<style type="text/css">
	.input{
		padding: 0px 2px 0px 2px;
		font-size: 13px;
	}

	.inputDescript{
		padding: 2px 2px 2px 2px;
		font-size: 13px;
		border-radius: 5px;
		background: #f2f2f2;
	}
</style>