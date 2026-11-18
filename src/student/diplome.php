<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion(); refuserEtudiant(); // notes : réservées au personnel ?>
<div class=" mt-2 p-2 pb-0 overflow-auto" style="max-height: calc(100vh - 246px);">
<?php 
$date = date('Y-m-d');
$langue = $_GET['langue'];

require("./student/level.php");

/**
 * Bareme officiel des mentions (reglement IV.22.2 — "Diplome avec mention"),
 * applique a la MOYENNE GENERALE CUMULATIVE :
 *   17/20 et plus    -> Tres Bien
 *   15,5 - 16,99/20  -> Bien
 *   13   - 15,49/20  -> Assez Bien
 *   10   - 12,99/20  -> Passable
 * Les seuils sont en cascade (>=) pour qu'aucune valeur intermediaire
 * (ex. 16,995) ne passe entre deux tranches.
 */
if ($totalGenCumul >= 17) {
	$mentionPromANG = "With High Distinction";
	$mentionPromFR  = "Mention : Très Bien";
}elseif($totalGenCumul >= 15.5) {
	$mentionPromANG = "With Distinction";
	$mentionPromFR  = "Mention : Bien";
}elseif($totalGenCumul >= 13) {
	$mentionPromANG = "With Distinction";
	$mentionPromFR  = "Mention : Assez Bien";
}elseif($totalGenCumul >= 10) {
	// "Passable" n'a pas d'equivalent en distinction americaine
	$mentionPromANG = "";
	$mentionPromFR  = "Mention : Passable";
}else{
	$mentionPromANG = "";
	$mentionPromFR  = "";
}

/**
 * Condition de délivrance : l'étudiant doit avoir validé le nombre de crédits
 * requis — 180 pour la Licence (bulletin académique §383), seuil paramétrable
 * dans Paramètres > Diplôme.
 * Un crédit est acquis si le cours est réussi (>= 10) ou validé sans note (-2).
 */
require_once(__DIR__ . '/../../data/diplome.config.php');
$creditsValides = diplomeCreditsValides($dtb, $student_id);
$creditsRequis  = diplomeCreditsRequis($dtb, $level > 3);
$creditsManquants = $creditsRequis - $creditsValides;
?>

<?php if($level <3 ){ ?>
<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
	<h1 class="text-red-500 text-center"><em><i class="bi-exclamation-triangle-fill"></i> Le diplôme de <b><?=$student_prenom?></b> n'est pas disponible, car son niveau n'est pas encore Licence 3.</em></h1>
</div>
<?php
}elseif($creditsValides < $creditsRequis){
?>
<div class='p-3 <?=$bg_two_color?> mb-4 rounded-md border-2 border-slate-700'>
	<h1 class="text-red-500 text-center mb-3">
		<em><i class="bi-exclamation-triangle-fill"></i>
		Le diplôme de <b><?=$student_prenom?></b> n'est pas délivrable : les crédits requis ne sont pas atteints.</em>
	</h1>
	<table class="simpleTbl w-full text-xs text-white">
		<tbody class="<?=$bg_one_color?>">
			<tr>
				<td class="p-2 w-8/12 text-right">Crédits validés</td>
				<td class="p-2 w-4/12"><b><?=$creditsValides?></b></td>
			</tr>
			<tr>
				<td class="p-2 text-right">Crédits requis (<?= $level > 3 ? 'Master' : 'Licence' ?>)</td>
				<td class="p-2"><b><?=$creditsRequis?></b></td>
			</tr>
			<tr class="bg-red-900">
				<td class="p-2 text-right">Il manque</td>
				<td class="p-2"><b><?=$creditsManquants?> crédit<?= $creditsManquants > 1 ? 's' : '' ?></b></td>
			</tr>
		</tbody>
	</table>
	<p class="text-center text-slate-400 text-xs mt-2">
		<a href="<?=$app_base?>/reglement#p383" class="underline hover:text-cyan-400">Règlement §383</a>
		— un crédit est acquis si le cours est réussi (≥ 10/20) ou validé sans note.
	</p>
</div>
<?php
}elseif($level >= 3 AND $totalGenCumul < 0 ) {
 ?>
<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>	
	<h1 class="text-red-500 text-center"><em><i class="bi-exclamation-triangle-fill"></i> Le diplôme de <b><?=$student_prenom?></b> n'est pas encore disponible, car toutes les notes ne sont pas encore remise.</em></h1>
</div>
<?php
}else{

	// ---- Choix du modele de diplome ---------------------------------------
	// Les etudiants gradues avant 2025 gardent l'ancien modele ; a partir de
	// 2025 le nouveau modele s'applique. Le defaut est calcule sur l'annee
	// scolaire mais reste modifiable a la main.
	require_once(__DIR__ . '/../../data/diplome.config.php');
	$cfgDip = diplomeConfig($dtb);
	$modeleDefaut = diplomeModeleParDefaut($annee_scolaire, $cfgDip['diplome_new_model_from_year']);
	$modele = isset($_GET['modele']) ? strtolower(trim((string)$_GET['modele'])) : $modeleDefaut;
	if (!in_array($modele, ['ancien', 'nouveau'], true)) { $modele = $modeleDefaut; }

	$urlBase = $app_base.'/student?id='.$id.'&page=diplome&langue='.urlencode($langue);
 ?>

<!-- SELECTEUR DE MODELE -->
<div class='p-2 <?=$bg_two_color?> mb-2 rounded-md border-2 border-slate-700 flex flex-wrap items-center gap-2'>
	<span class="text-[11px] uppercase tracking-wide text-slate-400">Modèle</span>
	<a href="<?=$urlBase?>&modele=ancien" class="px-3 py-1.5 rounded-md text-sm transition-all <?= $modele === 'ancien' ? 'bg-cyan-700 text-white' : $bg_four_color.' text-slate-300 hover:bg-slate-600' ?>">
		Ancien (avant 2025)
	</a>
	<a href="<?=$urlBase?>&modele=nouveau" class="px-3 py-1.5 rounded-md text-sm transition-all <?= $modele === 'nouveau' ? 'bg-cyan-700 text-white' : $bg_four_color.' text-slate-300 hover:bg-slate-600' ?>">
		Nouveau (2025 et après)
	</a>
	<span class="text-[11px] text-slate-400 ml-auto">
		Année scolaire : <b><?=htmlspecialchars((string)$annee_scolaire, ENT_QUOTES, 'UTF-8')?></b>
		— proposé : <b><?=$modeleDefaut === 'nouveau' ? 'nouveau' : 'ancien'?></b>
	</span>
</div>

<?php if ($modele === 'nouveau'): ?>

	<div class='p-1 <?=$bg_two_color?> mb-2 rounded-md border-2 border-slate-700'>
		<?php require(__DIR__ . '/diplome.new.php'); ?>
	</div>
	<p class="text-[11px] text-slate-400 mb-2">
		<i class="bi-info-circle"></i> Ce modèle s'imprime sur le papier pré-imprimé : l'export ne contient que le texte et les signatures.
		Signatures, arrêtés et calage se règlent dans <a class="text-cyan-400 underline" href="<?=$app_base?>/settings/diplome">Paramètres &gt; Diplôme</a>.
	</p>

<?php else: ?>

<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-2 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
	<div class="relative" id="diplomeConcept">
		<?php 
		if (isset($_GET['langue']) AND $langue == 'ANG') {
			echo '<img src="'.$app_base.'/file/Diplôme UAZ-ang.jpg">';
		}else{
			echo '<img src="'.$app_base.'/file/Diplôme UAZ-fr.jpg">';
		}
		?>
		
		
		<div class="absolute w-full text-black text-center top-0">
			<div class="h-[114px]"></div>
			<input type="text" name="arretee_firstLine" value="Arrêté ministrériel d’habilitation N° 12.258 / 2022 - MESupRES du 29/04/2022" class="p-0 w-full text-center bg-transparent border-0" style="font-family: 'Times New Roman', serif; font-size: 17px;"><br>
			<input type="text" name="arretee_secondLine" value="Arrêté ministériel d’équivalence administrative N° 31577 / 2012 de la FOP du 12/12/12" class="p-0 w-full text-center bg-transparent border-0" style="font-family: 'Times New Roman', serif; font-size: 17px;">
			<div class="h-[23px]"></div>

<?php 
		if (isset($_GET['langue']) AND $langue == 'ANG') {
			echo '<input type="text" name="arretee_secondLine" value="The Executive Committee, upon the recommendation of the Academic
" class="p-0 w-full text-center bg-transparent border-0" style="font-family: \'Times New Roman\', serif; font-size: 19px;"><br>';
			
			echo '<input type="text" name="arretee_secondLine" value="Council of the University is granting to" class="p-0 w-full text-center bg-transparent border-0" style="font-family: \'Times New Roman\', serif; font-size: 19px;">';
		}else{
			echo '<input type="text" name="arretee_secondLine" value="Le comité directeur, sur la recommandation du conseil académique de l’université, a accordé à" class="p-0 w-full text-center bg-transparent border-0" style="font-family: \'Times New Roman\', serif; font-size: 19px;">';
		}
?>

			

			<div class="h-[5px]"></div>

			<input type="text" value="<?=ucwords(strtolower($student_nom))." ".ucwords(strtolower($student_prenom))?>" class="font-tangerine 
<?php 
	$carName = intval(strlen($student_nom));
	$carLastName = intval(strlen($student_prenom));
	$strlen = $carName + $carLastName;

	if($strlen > 70) {
		echo 'text-[45px]';	
	}elseif($strlen > 60) {
		echo 'text-[50px]';	
	}elseif($strlen > 50) {
		echo 'text-[55px]';	
	}elseif($strlen > 40) {
		echo 'text-[65px]';	
	}elseif($strlen > 30) {
		echo 'text-[70px]';	
	}elseif($strlen <= 30){
		echo 'text-[75px]';	
	}
	
 ?>

			 bg-transparent p-0 border-0 w-full h-[90px] text-center">
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
			<input type="text" value="Granted in Sambaina, Antsirabe, Madagascar, on this Sunday <?php echo $strlen.' 04 Aogust '.date('Y');?>" class="bg-transparent p-0 border-0 w-full text-center mt-[20px]">
			</p>

			<?php
		}else{
			?>
				<p class="font-times text-[17px] mt-[15px]">	
			<input type="text" value="pour en jouir avec les droits et prérogatives qui y sont attachés." class="bg-transparent p-0 border-0 w-full text-center">
			<input type="text" value="Remis à Sambaina Antsirabe, Madagascar, en ce Dimanche <?php echo ' 04 août '.date('Y');?>" class="bg-transparent p-0 border-0 w-full text-center mt-[20px]">
			</p>

			<?php
		}
		?>	
			

		</div>
	</div>

</div>

<?php endif; // fin ancien / nouveau modele ?>

<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
	<button type="button" onclick="printThisDiplome()" class="bg-green-600 w-full p-2 rounded-md text-white"><span class="bi-download"></span> Exporter</button>
</div>

<?php
 }
 ?>
</div>
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

			// Nouveau modele (texte seul sur papier pre-imprime) ou ancien modele (fond image)
			var nouveau = document.getElementById('diplomeNouveau');
			var element = nouveau || document.getElementById('diplomeConcept');
			if (!element) { return; }

			// Le repere de zone imprimable ne doit jamais apparaitre dans le PDF
			var mentionVide = null;
			if (nouveau) {
				element.classList.add('dip-exporting');
				// Mention laissee vide : ne pas imprimer ", mention" tout seul
				var wrap = element.querySelector('.dip-mention-wrap');
				var men  = element.querySelector('.dip-mention-wrap .dip-edit');
				if (wrap && men && men.textContent.trim() === '') {
					wrap.classList.add('dip-hidden');
					mentionVide = wrap;
				}
			}

			var opt = {
			  margin:       0,
			  filename:     '<?=$student_id?>-DIPLOME' + (nouveau ? '-2025' : '') + '_<?=$date?>.pdf',
			  image:        { type: 'jpeg', quality: 0.98 },
			  html2canvas:  { scale: nouveau ? 4 : 10, backgroundColor: '#ffffff', useCORS: true },
			  jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
			};

			html2pdf().set(opt).from(element).save().then(function(){
				if (nouveau) { element.classList.remove('dip-exporting'); }
				if (mentionVide) { mentionVide.classList.remove('dip-hidden'); }
			});
}
</script>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->