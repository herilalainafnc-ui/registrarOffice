<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Finance</title>
</head>

<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<style>

	input[type=number]::-webkit-inner-spin-button,
	input[type=number]::-webkit-outer-spin-button {
	  -webkit-appearance: none;
	  margin: 0;
	}

	.inputFnc{
		background: none;
		padding: 0px 3px 0px 3px;
		width: 100%;
		font-size: 11px;
		text-align: right;
		border: none;
		border: 2px solid white;
		
	}
	.inputFnc:hover{
		border: 2px solid #2198c3;
		border-radius: 4px;
		
	}
	.prntInput{
		width: 110px;
		padding: 0px;
		font-weight: bold;
	}
	.tableFnc{
		width: 100%;
	}
	.tableFnc th{
		padding: 4px;
		background-color: #D9EAFF;
	}
	.tableFnc tbody td{
		border: 1px solid grey;
		text-align: left;
		background-color: white;
	}
</style>
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="sm:w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			
				<div class="back flex-1 overflow-hidden">
					
					<div class="p-2 overflow-auto" style="height: calc(100vh - 75px);">
<?php 
$filiere = $dtb->query("SELECT * FROM filiere WHERE filiere_sigle <> 'CPRE' ORDER BY filiere_sigle");

while($f_list = $filiere->fetch()) {
	$filiere_sigle = $f_list['filiere_sigle'];
 ?>						
						<div class="w-full p-1 mb-2">
							<p class="text-white text-[17px]"><b><?=$f_list['filiere_description']?></b></p>
		<?php 
		for ($i=1; $i < 3; $i++) {

		?>
<form method="post" action="finance/save_finance.php?filiere=<?=$filiere_sigle?>&semester=<?=$i?>" id="formFor<?=$filiere_sigle?>_semester<?=$i?>">
<div id="pach_<?=$filiere_sigle?>_<?=$i?>" class="mt-2">
	<table class="tableFnc mb-2" id="semestre<?=$i?>_<?=$f_list['filiere_sigle']?>">
		<thead class="bg-slate-200">
			<tr>
				<th style="visibility: hidden;"></th>
				<th colspan="10" class="text-center">SEMESTRE <?=$i?></th>
			</tr>
			<tr class="text-center text-xs">
				<th style="visibility: hidden;"></th>
				<th colspan="2">License 1</th>
				<th colspan="2">License 2</th>
				<th colspan="2">License 3</th>
				<th colspan="2">Master 1</th>
				<th colspan="2">Master 2</th>
			</tr>
			<tr class="text-center text-xs">
				<th>LISTE DES FRAIS</th>
				<th>INTERNE</th>
				<th>EXTERNE</th>
				<th>INTERNE</th>
				<th>EXTERNE</th>
				<th>INTERNE</th>
				<th>EXTERNE</th>
				<th>INTERNE</th>
				<th>EXTERNE</th>
				<th>INTERNE</th>
				<th>EXTERNE</th>
			</tr>
		</thead>
<?php
// Tableau des frais : [nom colonne SQL => libellé affiché]
$fraisList = [
    "frais_generaux"   => "Frais généraux/semestre",
    "laboratory_info"  => "Labo INFO/semestre",
    "laboratory_lang"  => "Labo LANGUE/semestre",
    "dortoir"          => "Dortoir/jour",
    "cafeteria"        => "Cantine/jour",
    "fond_depot"       => "Fond de dépôt",
    "nb_jours_semestre"=> "Nombre du jour - interne",
    "ecolage"          => "Ecolage <em>(Multiplier par nombre de crédit)</em>",
];

// Si semestre = 2, on ajoute la graduation
if ($i == 2) {
    $fraisList["frais_graduation"] = "Frais de graduation";
}

if ($f_list['filiere_sigle'] == 'THEO' AND $i == 1) {
    $fraisList["frais_costume"] = "Frais de Costume";
}

// Colloque ou Voyage d'étude selon la filière
$fraisList["frais_voyage"] = ($f_list['filiere_sigle'] == 'THEO') ? "Colloque" : "Voyage d'étude";



// Niveaux Licence et Master
$categories = [
    "L" => range(1, 3),  // Licence niveaux 1 à 3
    "M" => range(4, 5),  // Master niveaux 4 et 5
];

// Statuts possibles
$statuses = ["Interne", "Externe"];
?>

		<tbody>
		<?php foreach ($fraisList as $col => $label): ?>
		    <tr class="text-xs">
		        <th><?= $label ?></th>
		        <?php foreach ($categories as $cat => $levels): ?>
		            <?php foreach ($levels as $level): ?>
		                <?php foreach ($statuses as $status): ?>
		                    <?php
		                        $query = $dtb->query("
		                            SELECT * FROM t_2024_finance_detail_licence
		                            WHERE std_mention = '".$filiere_sigle."'
		                              AND semester = '".$i."'
		                              AND categorie = '".$cat."'
		                              AND std_status = '".$status."'
		                              AND level = '".$level."'
		                        ");
		                        $row = $query->fetch();
		                    ?>
		                    <td class="prntInput">

		                        <input type="number"
		                               name="<?=$col?>_<?=$filiere_sigle?>_<?=$level?>_<?=$i?>_<?=$status?>"
		                               class="inputFnc inputFnc_<?=$filiere_sigle?>_<?=$i?> transition duration-300 ease-in-out"
		                               value="<?=$row[$col]?>">
		                    </td>
		                <?php endforeach; ?>
		            <?php endforeach; ?>
		        <?php endforeach; ?>
		    </tr>
		<?php endforeach; ?>
		</tbody>

		<tfoot>
			<tr class="text-xs">
				<th class="text-center" colspan="11">
					
					<button id="submit_<?=$filiere_sigle?>_<?=$i?>" type="submit" class="px-5 py-1 bg-slate-400 rounded-md text-white toolInactive">Enregistrer</button>
				</th>
			</tr>
		</tfoot>
		
	</table>
<script>
	$(document).ready(function() {
		$('.inputFnc_<?=$filiere_sigle?>_<?=$i?>').on("change", function() {
			$('#submit_<?=$filiere_sigle?>_<?=$i?>').attr('class','px-5 py-1 bg-cyan-800 rounded-md text-white');
		});
	});
</script>
</div>
</form>
		<?php
		}
		 ?>
						</div>

<?php } ?>
					</div>
					
					<?php require('../init/footer.php'); ?>
				</div>


			</div>

		</div>

	</div>	
</body>
</html>

<script>
	$ (document).ready(function(){
		
		$('.inputFnc').click(function(){
			this.select();
		});

	});
</script>