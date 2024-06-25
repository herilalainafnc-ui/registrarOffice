<div class="w-full grid gap-2 grid-cols-2">
<?php 
	$yearTicket = $_POST['yearTicket'];
	$printName = "Ticket-email";
	$student = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire = "'.$yearTicket.'" AND annee_etude = 1 ORDER BY student_id');

	while ($afficher = $student->fetch()){
?>
	
	<div class="w-full border-1 border-black">
		
		<div class="px-2 h-[80px]">
			<b> <?=$afficher['student_id']?></b><br>
			<b class="text-sm"> <?=strtoupper($afficher['student_nom'])." ".$afficher['student_prenom']?></b><br>
			<p class="text-xs"><?=$afficher['etude_envisage']." - ".$afficher['etude_option']?></p>
		</div>

		<hr class="m-0">
		
		<div class="text-center text-sm">
			Votre accès à l'adresse Email Zurcher			
		</div>

		<hr class="m-0">
		
		<div class="px-2 bg-slate-100 text-center text-sm h-[42px]">	
			<label></label><b> <?=$afficher['student_email']?></b><br>
			<label>Password : </label><b> <?=$afficher['password']?></b>
		</div>

		<hr class="m-0">

		<div class="px-2 text-sm text-bold">
			<em>Veuillez garder ce ticket car le contenu est sensible!</em>
		</div>

	</div>

<?php 
	}
?>	
</div>