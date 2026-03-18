<style>
.std-toolbar {
	background: rgba(15, 23, 42, 0.6);
	backdrop-filter: blur(8px);
	border-bottom: 1px solid rgba(51, 65, 85, 0.5);
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
	background: rgba(51, 65, 85, 0.5);
}
.std-toolbar-btn:active {
	background: rgba(14, 165, 233, 0.2);
}
.std-toolbar-btn i {
	font-size: 20px;
	opacity: 0.9;
}
.std-toolbar-btn span {
	font-size: 10px;
	font-weight: 500;
	color: #94a3b8;
	text-align: center;
	line-height: 1.2;
}
.std-toolbar-btn:hover span {
	color: #e2e8f0;
}
.std-toolbar-btn.toolInactive {
	opacity: 0.4;
	pointer-events: none;
}
.std-toolbar-divider {
	width: 1px;
	height: 40px;
	background: rgba(51, 65, 85, 0.5);
	margin: 0 4px;
	align-self: center;
}
.std-dropdown-menu {
	background: rgba(15, 23, 42, 0.95);
	backdrop-filter: blur(12px);
	border: 1px solid rgba(51, 65, 85, 0.6);
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
	color: #cbd5e1;
	transition: all 0.15s ease;
}
.std-dropdown-menu li a:hover {
	background: rgba(14, 165, 233, 0.15);
	color: #38bdf8;
}
[data-theme="light"] .std-toolbar {
	background: rgba(255, 255, 255, 0.8);
	border-bottom-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .std-toolbar-btn:hover {
	background: rgba(241, 245, 249, 0.8);
}
[data-theme="light"] .std-toolbar-btn span {
	color: #64748b;
}
[data-theme="light"] .std-toolbar-btn:hover span {
	color: #1e293b;
}
[data-theme="light"] .std-dropdown-menu {
	background: rgba(255, 255, 255, 0.98);
	border-color: rgba(203, 213, 225, 0.8);
}
[data-theme="light"] .std-dropdown-menu li a {
	color: #475569;
}
[data-theme="light"] .std-dropdown-menu li a:hover {
	background: rgba(14, 165, 233, 0.1);
	color: #0284c7;
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



