<?php 
$date = date('Y-m-d');
$langue = $_GET['langue'];

require("./student/level.php");

if ($totalGenCumul >= 17) {
	$mentionPromANG = "With High Distinction";
	$mentionPromFR = "Mention : Très Bien";
}elseif($totalGenCumul >= 15.5 AND $totalGenCumul <= 16.99) {
	$mentionPromANG = "With Distinction";
	$mentionPromFR = "Mention : Bien";
}elseif($totalGenCumul >= 14 AND $totalGenCumul <= 15.49) {
	$mentionPromANG = "With Distinction";
	$mentionPromFR = "Mention : Assez Bien";
}elseif($totalGenCumul < 14){
	$mentionPromANG = "";
	$mentionPromFR = "";
}

if($level <3 ){
?>
<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>	
	<h1 class="text-red-500 text-center"><em><i class="bi-exclamation-triangle-fill"></i> Le diplôme de <b><?=$student_prenom?></b> n'est pas disponible, car son niveau n'est pas encore Licence 3.</em></h1>
</div>
<?php
}elseif($level >= 3 AND $totalGenCumul < 0 ) {
 ?>
<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>	
	<h1 class="text-red-500 text-center"><em><i class="bi-exclamation-triangle-fill"></i> Le diplôme de <b><?=$student_prenom?></b> n'est pas encore disponible, car toutes les notes ne sont pas encore remise.</em></h1>
</div>
<?php
}else{
 ?>

<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-2 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
	<div class="relative" id="diplomeConcept">
		<?php 
		if (isset($_GET['langue']) AND $langue == 'ANG') {
			echo '<img src="../file/Diplôme UAZ-ang.jpg">';
		}else{
			echo '<img src="../file/Diplôme UAZ-fr.jpg">';
		}
		?>
		
		
		<div class="absolute w-full text-black text-center top-0">
			<div class="h-[220px]"></div>
			<input type="text" value="<?=ucwords(strtolower($student_nom))." ".ucwords(strtolower($student_prenom))?>" class="font-tangerine text-[70px] bg-transparent p-0 border-0 w-full h-[90px] text-center">
			<div class="h-[60px]"></div>
		<?php 
		if (isset($_GET['langue']) AND $langue == 'ANG') {
			if ($level>3) {
				echo '<input type="text" value="Master of '.$etude_envisage_ang.'" class="font-bahnschrift text-[42px] bg-transparent p-0 border-0 w-full text-center">';	
			}else{
				echo '<input type="text" value="Bachelor of '.$etude_envisage_ang.'" class="font-bahnschrift text-[42px] bg-transparent p-0 border-0 w-full text-center">';	
			}
		}else{
			if ($level>3) {
				echo '<input type="text" value="Master en '.$etude_envisage.'" class="font-bahnschrift text-[42px] bg-transparent p-0 border-0 w-full text-center">';
				if($etude_envisage == "Gestion") {
					echo '<input type="text" value="(Master of Business Administration)" class="font-bahnschrift text-[42px] bg-transparent p-0 border-0 w-full text-center">';
				}	
			}else{
				echo '<input type="text" value="Licence en '.$etude_envisage.'" class="font-bahnschrift text-[42px] bg-transparent p-0 border-0 w-full text-center">';
			}
		}
		?>
			
			
			
			
		<?php 
		if (isset($_GET['langue']) AND $langue == 'ANG') {
			echo '<input type="text" value="'.$etude_option_ang.' course" class="font-bahnschrift text-[16px] bg-transparent p-0 border-0 w-full text-center">';
			echo '<input type="text" value="'.$mentionPromANG.'" class="font-candara text-italic text-[19px] bg-transparent p-0 border-0 w-full text-center">';
		}else{
			echo '<input type="text" value="Parcours: '.$etude_option.'" class="font-bahnschrift text-[16px] bg-transparent p-0 border-0 w-full text-center">';
			echo '<input type="text" value="'.$mentionPromFR.'" class="font-candara text-italic text-[19px] bg-transparent p-0 border-0 w-full text-center">';
		}
		?>	
			
			

			<div class="h-[18px]"></div>
		<?php 
		if (isset($_GET['langue']) AND $langue == 'ANG') {
			?>
				<p class="font-times text-[17px]">
				 <br> 
		
			<input type="text" value="With all the rights, privileges and honors pertaining to it." class="bg-transparent p-0 border-0 w-full text-center">
			<input type="text" value="Granted in Sambaina, Antsirabe, Madagascar, on this Sunday <?php echo '04 Aogust '.date('Y');?>" class="bg-transparent p-0 border-0 w-full text-center mt-[20px]">
			</p>

			<?php
		}else{
			?>
				<p class="font-times text-[17px] mt-[15px]">	
			<input type="text" value="pour en jouir avec les droits et prérogatives qui y sont attachés." class="bg-transparent p-0 border-0 w-full text-center">
			<input type="text" value="Remis à Sambaina Antsirabe, Madagascar, en ce Dimanche <?php echo '04 août '.date('Y');?>" class="bg-transparent p-0 border-0 w-full text-center mt-[20px]">
			</p>

			<?php
		}
		?>	
			

		</div>
	</div>

</div>

<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>	
	<button type="button" onclick="printThisDiplome()" class="bg-green-600 w-full p-2 rounded-md text-white"><span class="bi-download"></span> Exporter</button>
</div>

<?php 
 }
 ?>

<style type="text/css">
	
	@font-face{font-family: 'trajanPro'; src: url('css/fonts/TrajanPro-Bold.otf');}
	@font-face{font-family: 'Sangharia'; src: url('css/fonts/Regina_Script_PERSONAL_USE_ONLY.ttf');}
	@font-face{font-family: 'ananda'; src: url('css/fonts/Ananda Personal Use.ttf');}
	@font-face{font-family: 'caviar'; src: url('css/fonts/CaviarDreams_Bold.ttf');}
	@font-face{font-family: 'bahnschrift'; src: url('css/fonts/BAHNSCHRIFT.TTF');}
	@font-face{font-family: 'Tangerine'; src: url('css/fonts/Tangerine_Bold.ttf');}
	@font-face{font-family: 'TimesNewRomas'; src: url('css/fonts/Times New Normal Regular.ttf');}
	@font-face{font-family: 'Behind'; src: url('css/fonts/Behind Script.otf');}
	@font-face{font-family: 'candara'; src: url('css/fonts/Candara_Bold.ttf');}
	
	.font-trajanPro{
		font-family: 'trajanPro';
	}
	.font-candara{
		font-family: 'candara';
		font-style: italic;
		font-weight: bold;
	}


	.font-sangharia{
		font-family: 'Sangharia';
	}
	.font-ananda{
		font-family: 'ananda';
	}
	.font-tangerine{
		font-family: 'Tangerine';
	}
	.font-behind{
		font-family: 'Behind';
	}
	.font-bahnschrift{
		font-family: 'bahnschrift';
		font-weight: bolder;
		
	}

	.font-caviar{
		font-family: 'caviar';
	}

	.font-arial{
		font-family: 'Arial';
		font-weight: bold;
	}

	.font-times{
		font-family: 'TimesNewRomas';

	}
</style>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

			<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script type="text/javascript">

function printThisDiplome(){


			alert('Download process !');
			
			var element = document.getElementById('diplomeConcept');
			
			var opt = {
			  margin:       0,
			  filename:     '<?=$student_id?>-DIPLOME_<?=$date?>.pdf',
			  image:        { type: 'jpeg', quality: 2 },
			  html2canvas:  { scale: 10 },
			  jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
			};

			// New Promise-based usage:
			html2pdf().set(opt).from(element).save();

			// Old monolithic-style usage:
			html2pdf(element, opt);
}
</script>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->