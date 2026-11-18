<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion();
/* Barre d'outils de la fiche : superadmin, administrateur et registrariat (niveau 3
   et moins), comme init/toolbar.php. Ses impressions sont refusées au-delà
   (ExportController), ses pages « Ajout cours » et « Cours retiré » aussi
   (src/student.php) et les actions correspondantes (app/.student). */
if ((int) ($rg_user['level'] ?? 99) > 3) { return; }
?>
<style>
.std-toolbar {
	background: rgb(var(--c-canvas) / 0.6);
	backdrop-filter: blur(8px);
	border-bottom: 1px solid rgb(var(--c-line) / 0.5);
}
.std-toolbar::-webkit-scrollbar {
	display: none;
}
.std-toolbar-btn {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 8px 12px;
	border-radius: 8px;
	transition: all 0.15s ease;
	min-width: 70px;
	gap: 4px;
}
.std-toolbar-btn:hover {
	background: rgb(var(--c-raised) / 0.5);
}
.std-toolbar-btn:active {
	background: rgb(var(--c-accent) / 0.2);
}
.std-toolbar-btn i {
	font-size: 20px;
	opacity: 0.9;
}
.std-toolbar-btn span {
	font-size: 10px;
	font-weight: 500;
	color: rgb(var(--c-text-3));
	text-align: center;
	line-height: 1.2;
}
.std-toolbar-btn:hover span {
	color: rgb(var(--c-text));
}
.std-toolbar-btn.toolInactive {
	opacity: 0.4;
	pointer-events: none;
}
.std-toolbar-divider {
	width: 1px;
	height: 40px;
	background: rgb(var(--c-raised) / 0.5);
	margin: 0 4px;
	align-self: center;
}
.std-dropdown-menu {
	background: rgb(var(--c-canvas) / 0.95);
	backdrop-filter: blur(12px);
	border: 1px solid rgb(var(--c-line) / 0.6);
	border-radius: 10px;
	padding: 6px;
	min-width: 160px;
	box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}
.std-dropdown-menu li a {
	display: block;
	padding: 8px 12px;
	border-radius: 6px;
	font-size: 12px;
	color: rgb(var(--c-text-2));
	transition: all 0.15s ease;
}
.std-dropdown-menu li a:hover {
	background: rgb(var(--c-accent) / 0.15);
	color: rgb(var(--c-accent-text));
}
[data-theme="light"] .std-toolbar {
	background: rgb(var(--c-surface) / 0.8);
	border-bottom-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .std-toolbar-btn:hover {
	background: rgb(var(--c-surface-2) / 0.8);
}
[data-theme="light"] .std-toolbar-btn span {
	color: rgb(var(--c-text-3));
}
[data-theme="light"] .std-toolbar-btn:hover span {
	color: rgb(var(--c-text));
}
[data-theme="light"] .std-dropdown-menu {
	background: rgb(var(--c-surface) / 0.98);
	border-color: rgb(var(--c-line-strong) / 0.8);
}
[data-theme="light"] .std-dropdown-menu li a {
	color: rgb(var(--c-text-2));
}
[data-theme="light"] .std-dropdown-menu li a:hover {
	background: rgb(var(--c-accent) / 0.1);
	color: rgb(var(--c-accent-text));
}

/* Mobile : la barre reste HORIZONTALE et défilable (et non empilée en colonne,
   ce que provoquait par erreur la règle .flex.w-full du responsive.css) */
@media (max-width: 1023px) {
	.std-toolbar {
		flex-direction: row !important;
		flex-wrap: nowrap !important;
		overflow-x: auto !important;
		-webkit-overflow-scrolling: touch;
	}
	.std-toolbar > a.std-toolbar-btn,
	.std-toolbar > .std-toolbar-divider {
		flex: 0 0 auto !important;
	}
	.std-toolbar-divider {
		height: 32px !important;
		align-self: center !important;
	}
}
</style>

<div class="std-toolbar w-full flex items-center gap-1 px-2 py-1.5 <?=$txt_one_color?> overflow-x-auto overflow-y-hidden" style="scrollbar-width: none; -ms-overflow-style: none;">
	

	<!-- Check list -->
	<a target="_blank" href="<?=$app_base?>/src/data.topdf?ptype=Checklist&id=<?=$id?>&student_id=<?=$student_id?>&student_nom=<?=$student_nom?>&student_prenom=<?=$student_prenom?>&etude_envisage=<?=$etude_envisage?>&etude_option=<?=$etude_option?>&student_tel=<?=$student_tel?>&image_student=<?=$image_student?>&lookup_code=<?=$lookup_code?>&status=<?=$status?>&date_entry=<?=$date_entry?>" class="std-toolbar-btn">
		<i class="bi-check-square-fill text-amber-400"></i>
		<span>Check list</span>
	</a>

	<!-- Badge -->
	<a target="_blank" href="<?=$app_base?>/src/data.topdf?ptype=Badge&id=<?=$id?>&student_id=<?=$student_id?>&student_nom=<?=$student_nom?>&student_prenom=<?=$student_prenom?>&etude_envisage=<?=$etude_envisage?>&etude_option=<?=$etude_option?>&student_tel=<?=$student_tel?>&image_student=<?=$image_student?>&lookup_code=<?=$lookup_code?>&status=<?=$status?>&abonment=<?=$abonment?>&date_entry=<?=$date_entry?>" class="std-toolbar-btn">
		<i class="bi-person-badge-fill text-slate-400"></i>
		<span>Badge</span>
	</a>

	<!-- Carte d'abonnement -->
	<a target="_blank" href="<?=$app_base?>/src/data.topdf?ptype=Abonnement Caf&id=<?=$id?>&student_id=<?=$student_id?>&student_nom=<?=$student_nom?>&student_prenom=<?=$student_prenom?>&etude_envisage=<?=$etude_envisage?>&etude_option=<?=$etude_option?>&student_tel=<?=$student_tel?>&image_student=<?=$image_student?>&lookup_code=<?=$lookup_code?>&status=<?=$status?>&date_entry=<?=$date_entry?>" class="std-toolbar-btn <?php if ($status == "Externe" OR $status == "externe" OR $status == "" OR $abonment == "0"){ echo "toolInactive";} ?>">
		<i class="bi-credit-card-fill text-purple-400"></i>
		<span>Carte CAF</span>
	</a>

	<!-- Certificat scolarité -->
	<a target="_blank" href="<?=$app_base?>/src/data.topdf?ptype=Certificat de scolarité&id=<?=$id?>&student_id=<?=$student_id?>&student_nom=<?=$student_nom?>&student_prenom=<?=$student_prenom?>&etude_envisage=<?=$etude_envisage?>&etude_option=<?=$etude_option?>&student_tel=<?=$student_tel?>&image_student=<?=$image_student?>&lookup_code=<?=$lookup_code?>&status=<?=$status?>&date_entry=<?=$date_entry?>" class="std-toolbar-btn">
		<i class="bi-file-earmark-text-fill text-slate-400"></i>
		<span>Certificat</span>
	</a>

	<!-- Worked Points -->
	<a target="_blank" href="<?=$app_base?>/src/data.topdf?ptype=Worked_point&id=<?=$id?>&student_id=<?=$student_id?>&student_nom=<?=$student_nom?>&student_prenom=<?=$student_prenom?>&etude_envisage=<?=$etude_envisage?>&level=<?=$level?>&student_tel=<?=$student_tel?>&image_student=<?=$image_student?>" class="std-toolbar-btn">
		<i class="bi-person-lines-fill text-slate-400"></i>
		<span>Worked</span>
	</a>

	<div class="std-toolbar-divider"></div>

	<!-- Ajout cours -->
	<a href="?id=<?=$id?>&page=newCours" class="std-toolbar-btn">
		<i class="bi-folder-plus text-cyan-400"></i>
		<span>Ajout cours</span>
	</a>

	<!-- Cours retiré -->
	<a href="?id=<?=$id;?>&page=courssupprim" class="std-toolbar-btn">
		<i class="bi-trash2 text-red-400"></i>
		<span>Cours retiré</span>
	</a>

	<div class="std-toolbar-divider"></div>

	<!-- Fiche d'inscription -->
	<a id="exportFichInsc" href="#" class="std-toolbar-btn <?php if (isset($_GET['page']) and ($_GET['page'] == 'diplome') or empty($_GET['page'])) { echo "toolInactive"; }?>">
		<i class="bi-file-text-fill text-emerald-400"></i>
		<span>Fiche inscr.</span>
	</a>

	<!-- Exporter PDF -->
	<a href="#" id="<?php if (!empty($_GET['page']) AND $_GET['page'] == 'bulletin'){echo 'exportBulletin';} elseif(!empty($_GET['page']) AND $_GET['page'] == 'transcript'){echo 'exportTranscript';} elseif(!empty($_GET['page']) AND $_GET['page'] == 'transcriptSS'){echo 'exportTranscriptSS';} ?>" class="std-toolbar-btn <?php if (isset($_GET['page']) and ($_GET['page'] == 'information' or $_GET['page'] == 'newCours' or $_GET['page'] == 'diplome') or empty($_GET['page'])) { echo "toolInactive"; }?>">
		<i class="bi-filetype-pdf text-blue-400"></i>
		<span>Exporter</span>
	</a>
</div>



