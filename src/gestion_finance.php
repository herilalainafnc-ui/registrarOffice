<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Finance</title>
</head>

<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<style>
	/* ========== SHADCN UI STYLE - FINANCE ========== */

	:root {
		--background: 222.2 84% 4.9%;
		--foreground: 210 40% 98%;
		--card: 222.2 84% 4.9%;
		--card-foreground: 210 40% 98%;
		--primary: 199 89% 48%;
		--primary-foreground: 222.2 47.4% 11.2%;
		--secondary: 217.2 32.6% 17.5%;
		--muted: 217.2 32.6% 17.5%;
		--muted-foreground: 215 20.2% 65.1%;
		--border: 217.2 32.6% 17.5%;
		--ring: 199 89% 48%;
		--radius: 0.5rem;
	}

	/* Remove number input spinners */
	input[type=number]::-webkit-inner-spin-button,
	input[type=number]::-webkit-outer-spin-button {
		-webkit-appearance: none;
		margin: 0;
	}
	input[type=number] {
		-moz-appearance: textfield;
	}

	/* Finance Card */
	.finance-card {
		background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
		border: 1px solid rgba(51, 65, 85, 0.5);
		border-radius: 0.75rem;
		backdrop-filter: blur(10px);
		box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
		padding: 1rem;
		margin-bottom: 1rem;
		transition: all 0.2s ease;
	}

	.finance-card:hover {
		border-color: rgba(71, 85, 105, 0.7);
		box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.2);
	}

	.finance-card-title {
		font-size: 1rem;
		font-weight: 600;
		color: #f1f5f9;
		letter-spacing: -0.025em;
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.finance-card-title i {
		color: #0ea5e9;
	}

	/* Semester Badge */
	.semester-badge {
		display: inline-flex;
		align-items: center;
		gap: 0.375rem;
		padding: 0.25rem 0.75rem;
		font-size: 0.7rem;
		font-weight: 600;
		letter-spacing: 0.05em;
		text-transform: uppercase;
		border-radius: 9999px;
		background: rgba(14, 165, 233, 0.15);
		color: #38bdf8;
		border: 1px solid rgba(14, 165, 233, 0.3);
	}

	/* Finance Table */
	.tableFnc {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		border-radius: 0.5rem;
		overflow: hidden;
		border: 1px solid rgba(51, 65, 85, 0.4);
	}

	.tableFnc thead {
		background: rgba(30, 41, 59, 0.9);
	}

	.tableFnc thead tr:first-child th {
		background: rgba(14, 165, 233, 0.1);
		border-bottom: 1px solid rgba(14, 165, 233, 0.2);
		color: #38bdf8;
		font-weight: 600;
		font-size: 0.8rem;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		padding: 0.6rem 0.5rem;
	}

	.tableFnc thead tr:not(:first-child) th {
		padding: 0.4rem 0.35rem;
		background: rgba(30, 41, 59, 0.8);
		color: #94a3b8;
		font-weight: 500;
		font-size: 0.7rem;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		border-bottom: 1px solid rgba(51, 65, 85, 0.5);
	}

	.tableFnc tbody td {
		border-bottom: 1px solid rgba(51, 65, 85, 0.25);
		border-right: 1px solid rgba(51, 65, 85, 0.15);
		text-align: left;
		background: transparent;
		padding: 0;
		transition: background 0.15s ease;
	}

	.tableFnc tbody tr {
		transition: background 0.15s ease;
	}

	.tableFnc tbody tr:hover {
		background: rgba(51, 65, 85, 0.2);
	}

	.tableFnc tbody tr:hover td {
		background: transparent;
	}

	.tableFnc tbody th {
		padding: 0.5rem 0.6rem;
		color: #cbd5e1;
		font-size: 0.75rem;
		font-weight: 500;
		background: rgba(30, 41, 59, 0.4);
		border-bottom: 1px solid rgba(51, 65, 85, 0.25);
		border-right: 1px solid rgba(51, 65, 85, 0.2);
		white-space: nowrap;
	}

	.tableFnc tfoot th {
		padding: 0.6rem;
		background: rgba(30, 41, 59, 0.4);
		border-top: 1px solid rgba(51, 65, 85, 0.4);
	}

	/* Input Fields */
	.prntInput {
		width: 100px;
		padding: 0;
		font-weight: 600;
	}

	.inputFnc {
		background: rgba(15, 23, 42, 0.4);
		padding: 0.4rem 0.5rem;
		width: 100%;
		font-size: 0.7rem;
		text-align: right;
		border: 1px solid transparent;
		border-radius: 0.25rem;
		color: #e2e8f0;
		transition: all 0.2s ease;
		font-weight: 500;
		font-variant-numeric: tabular-nums;
	}

	.inputFnc:hover {
		border-color: rgba(14, 165, 233, 0.4);
		background: rgba(30, 41, 59, 0.6);
	}

	.inputFnc:focus {
		outline: none;
		border-color: #0ea5e9;
		background: rgba(30, 41, 59, 0.8);
		box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.15);
		color: #ffffff;
	}

	/* Save Button */
	.btn-save {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 0.5rem;
		padding: 0.45rem 1.25rem;
		font-size: 0.8rem;
		font-weight: 500;
		border-radius: 0.375rem;
		cursor: pointer;
		border: none;
		outline: none;
		transition: all 0.2s ease;
	}

	.btn-save-inactive {
		background: rgba(51, 65, 85, 0.5);
		color: #64748b;
		cursor: not-allowed;
		border: 1px solid rgba(51, 65, 85, 0.4);
	}

	.btn-save-active {
		background: linear-gradient(135deg, #0ea5e9, #0284c7);
		color: white;
		box-shadow: 0 1px 3px rgba(14, 165, 233, 0.3);
		border: 1px solid transparent;
	}

	.btn-save-active:hover {
		background: linear-gradient(135deg, #0284c7, #0369a1);
		transform: translateY(-1px);
		box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
	}

	/* Page Header */
	.page-header {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		margin-bottom: 1.25rem;
		padding-bottom: 0.75rem;
		border-bottom: 1px solid rgba(51, 65, 85, 0.4);
	}

	.page-header-icon {
		width: 2.5rem;
		height: 2.5rem;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 0.5rem;
		background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(14, 165, 233, 0.1));
		color: #0ea5e9;
		font-size: 1.1rem;
	}

	.page-header-title {
		font-size: 1.35rem;
		font-weight: 700;
		color: #f1f5f9;
		letter-spacing: -0.025em;
	}

	.page-header-desc {
		font-size: 0.8rem;
		color: #64748b;
	}

	/* Scrollbar */
	.finance-scroll::-webkit-scrollbar {
		width: 6px;
		height: 6px;
	}

	.finance-scroll::-webkit-scrollbar-track {
		background: rgba(15, 23, 42, 0.5);
		border-radius: 3px;
	}

	.finance-scroll::-webkit-scrollbar-thumb {
		background: rgba(51, 65, 85, 0.8);
		border-radius: 3px;
	}

	.finance-scroll::-webkit-scrollbar-thumb:hover {
		background: rgba(71, 85, 105, 0.8);
	}

	/* Level header coloring */
	.th-licence { color: #a78bfa; }
	.th-master { color: #f59e0b; }
</style>
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			
				<div class="back flex-1 overflow-hidden">
					
					<div class="p-4 overflow-auto finance-scroll" style="height: calc(100vh - 75px);">

						<!-- Page Header -->
						<div class="page-header">
							<div class="page-header-icon">
								<i class="bi bi-wallet2"></i>
							</div>
							<div>
								<div class="page-header-title">Gestion Finance</div>
								<div class="page-header-desc">Configuration des frais par filière, niveau et semestre</div>
							</div>
						</div>

<?php 
$filiere = $dtb->query("SELECT * FROM filiere WHERE filiere_sigle <> 'CPRE' ORDER BY filiere_sigle");

while($f_list = $filiere->fetch()) {
	$filiere_sigle = $f_list['filiere_sigle'];
 ?>						
						<div class="finance-card">
							<div class="finance-card-title mb-3">
								<i class="bi bi-mortarboard"></i>
								<?=$f_list['filiere_description']?>
							</div>
		<?php 
		for ($i=1; $i < 3; $i++) {

		?>
<form method="post" action="finance/save_finance?filiere=<?=$filiere_sigle?>&semester=<?=$i?>" id="formFor<?=$filiere_sigle?>_semester<?=$i?>">
<div id="pach_<?=$filiere_sigle?>_<?=$i?>" class="mt-3">
	<div class="flex items-center gap-2 mb-2">
		<span class="semester-badge">
			<i class="bi bi-calendar3"></i> Semestre <?=$i?>
		</span>
	</div>
	<div class="overflow-x-auto finance-scroll" style="border-radius: 0.5rem;">
	<table class="tableFnc" id="semestre<?=$i?>_<?=$f_list['filiere_sigle']?>">
		<thead>
			<tr>
				<th style="visibility: hidden;"></th>
				<th colspan="10" class="text-center">SEMESTRE <?=$i?></th>
			</tr>
			<tr class="text-center">
				<th style="visibility: hidden;"></th>
				<th colspan="2" class="th-licence">Licence 1</th>
				<th colspan="2" class="th-licence">Licence 2</th>
				<th colspan="2" class="th-licence">Licence 3</th>
				<th colspan="2" class="th-master">Master 1</th>
				<th colspan="2" class="th-master">Master 2</th>
			</tr>
			<tr class="text-center">
				<th>Liste des frais</th>
				<th>Int.</th>
				<th>Ext.</th>
				<th>Int.</th>
				<th>Ext.</th>
				<th>Int.</th>
				<th>Ext.</th>
				<th>Int.</th>
				<th>Ext.</th>
				<th>Int.</th>
				<th>Ext.</th>
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
		    <tr>
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
			<tr>
				<th class="text-center" colspan="11">
					<button id="submit_<?=$filiere_sigle?>_<?=$i?>" type="submit" class="btn-save btn-save-inactive">
						<i class="bi bi-check2-circle"></i> Enregistrer
					</button>
				</th>
			</tr>
		</tfoot>
		
	</table>
	</div>
<script>
	$(document).ready(function() {
		$('.inputFnc_<?=$filiere_sigle?>_<?=$i?>').on("change", function() {
			$('#submit_<?=$filiere_sigle?>_<?=$i?>').attr('class','btn-save btn-save-active');
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