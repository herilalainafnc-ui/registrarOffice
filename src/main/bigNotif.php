<!-- AJOUT DE COURS -->
	<div class="absolute w-full h-screen top-0 left-0 z-40 hidden" id="bigNotifCours" style="backdrop-filter: blur(3px);">

		<div class="w-[700px] bg-slate-300 border-2 border-slate-700 mx-auto my-[1%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/app/.cours/addCours" enctype="multipart/form-data" class="form-no-refrech-cours">
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
 						<option value="2">Sélectionné</option>
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
					<label>Semestre</label><br>
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
	<div class="fixed inset-0 z-50 hidden" id="bigNotifProf">
		<!-- Overlay -->
		<div class="fixed inset-0 bg-black/60 backdrop-blur-sm" id="overlayAddProf"></div>
		
		<!-- Modal Container -->
		<div class="fixed inset-0 overflow-y-auto">
			<div class="flex min-h-full items-center justify-center p-4">
				<div class="relative w-full max-w-2xl bg-[#0f172a] border border-slate-700/50 rounded-xl shadow-2xl shadow-black/20">
					
					<!-- Header -->
					<div class="flex items-center justify-between p-5 border-b border-slate-700/50">
						<div class="flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center">
								<i class="bi-person-plus-fill text-cyan-400"></i>
							</div>
							<div>
								<h2 class="text-lg font-semibold text-slate-100">Ajouter un enseignant</h2>
								<p class="text-xs text-slate-500">Remplissez les informations du professeur</p>
							</div>
						</div>
						<button type="button" id="closeAddProf" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-200 transition-colors">
							<i class="bi-x-lg text-sm"></i>
						</button>
					</div>
					
					<!-- Body -->
					<form id="formToAddProf" enctype="multipart/form-data">
						<div class="p-5">
							<p class="text-red-400 text-sm mb-4 hidden" id="alert-prof"></p>
							
							<div class="flex gap-5">
								<!-- Left Column -->
								<div class="flex-1 space-y-4">
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Nom <span class="text-red-400">*</span></label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all requierd-prof-1" type="text" name="name" placeholder="Nom de famille">
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Prénom</label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all" type="text" name="lastName" placeholder="Prénom">
									</div>
									
									<div class="grid grid-cols-2 gap-3">
										<div>
											<label class="block text-sm font-medium text-slate-300 mb-1.5">Date de naissance</label>
											<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all" type="date" name="birthday">
										</div>
										<div>
											<label class="block text-sm font-medium text-slate-300 mb-1.5">Lieu de naissance</label>
											<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all" type="text" name="lieuN" placeholder="Ville">
										</div>
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Adresse <span class="text-red-400">*</span></label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all requierd-prof-2" type="text" name="address" placeholder="Adresse complète">
									</div>
									
									<div class="grid grid-cols-2 gap-3">
										<div>
											<label class="block text-sm font-medium text-slate-300 mb-1.5">Genre</label>
											<select class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all" name="sex">
												<option value="1">Masculin</option>
												<option value="0">Féminin</option>
											</select>
										</div>
										<div>
											<label class="block text-sm font-medium text-slate-300 mb-1.5">Groupe sanguin</label>
											<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all" type="text" name="blood_group" placeholder="Ex: A+">
										</div>
									</div>
								</div>
								
								<!-- Right Column -->
								<div class="w-[220px] space-y-4">
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Photo</label>
										<label for="teacher_image" class="cursor-pointer group">
											<div class="w-24 h-24 bg-slate-800 border-2 border-dashed border-slate-600 rounded-xl flex flex-col items-center justify-center gap-1 group-hover:border-cyan-500/50 group-hover:bg-slate-800/80 transition-all">
												<i class="bi-camera text-2xl text-slate-500 group-hover:text-cyan-400 transition-colors"></i>
												<span class="text-xs text-slate-500 group-hover:text-slate-400">Ajouter</span>
											</div>
										</label>
										<input type="file" accept=".jpg, .png" name="teacher_image" id="teacher_image" class="hidden">
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Téléphone <span class="text-red-400">*</span></label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all requierd-prof-3" type="text" name="phone" placeholder="034 00 000 00">
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Email <span class="text-red-400">*</span></label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all requierd-prof-4" type="email" name="email" placeholder="email@exemple.com">
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Diplôme <span class="text-red-400">*</span></label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all requierd-prof-5" type="text" name="diplome" placeholder="Master, Doctorat...">
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Religion</label>
										<select class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all" name="religion">
											<option>Adventiste</option>
											<option>Non Adventiste</option>
										</select>
									</div>
									
									<div>
										<label class="block text-sm font-medium text-slate-300 mb-1.5">Position <span class="text-red-400">*</span></label>
										<input class="w-full px-3 py-2 bg-slate-800/50 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 focus:border-cyan-500/40 transition-all requierd-prof-6" type="text" name="position" placeholder="Professeur, Assistant...">
									</div>
								</div>
							</div>
						</div>
						
						<!-- Footer -->
						<div class="flex items-center justify-end gap-3 p-5 border-t border-slate-700/50 bg-slate-900/50 rounded-b-xl">
							<button type="button" id="cancelnotifAddProf" class="px-4 py-2 text-sm font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg transition-colors">
								Annuler
							</button>
							<button type="button" id="btnAddProf" class="px-4 py-2 text-sm font-medium text-white bg-cyan-600 hover:bg-cyan-500 rounded-lg transition-colors flex items-center gap-2">
								<i class="bi-check-lg"></i>
								Enregistrer
							</button>
						</div>
					</form>
					
				</div>
			</div>
		</div>
	</div>


<script type="text/javascript">
	$(document).ready(function() {
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
			$('#mentionSelect').on('change',function(){
				var mentionSelect = $(this).val();
					
					$.ajax({
					url:"<?=$app_base?>/src/services/parcours.live.addCours",
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

			var url = '<?=$app_base?>/app/.cours/addCours';
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
		
		$('#cancelnotifAddProf, #closeAddProf, #overlayAddProf').click(function(){
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
				$('#formToAddProf').attr('action','<?=$app_base?>/app/.prof/addProf.php');
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

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
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