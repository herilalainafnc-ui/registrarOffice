<div class="mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);" id="notesCoursContainer">
<?php 
require_once __DIR__ . '/../../data/notes.statut.php';
$ctxUser = function_exists('currentUser') ? currentUser() : null;
$sessionUserLevel = (int)($ctxUser['level'] ?? ($_SESSION['user_level'] ?? ($_SESSION['level'] ?? 99)));
$normalizedPrivilege = strtolower(trim((string)($ctxUser['privilege'] ?? ($privilege ?? ''))));
$canRemoveStudentFromCourse = false;
if (function_exists('isRegistrar') && function_exists('isChefMention')) {
	$canRemoveStudentFromCourse = isRegistrar() || isChefMention();
} else {
	$canRemoveStudentFromCourse = (
		$normalizedPrivilege == "registrar"
		|| $normalizedPrivilege == "administrator"
		|| $normalizedPrivilege == "superadmin"
		|| $normalizedPrivilege == "chef_mention"
		|| $normalizedPrivilege == "chef mention"
		|| strpos($normalizedPrivilege, 'chef') !== false
		|| in_array($sessionUserLevel, [1, 2, 3, 6], true)
		|| $sessionUserLevel === 6
		|| (isset($rg_user['level']) && (int)$rg_user['level'] === 6)
	);
}

$year = date('Y')+1;
$isValidationCourse = (
	(int)($profil['category'] ?? 0) === 5
	|| stripos((string)($profil['Sigle'] ?? ''), 'RELP 291') !== false
	|| stripos((string)($profil['title'] ?? ''), 'formation spirituelle') !== false
);
for ($i=0; $i < 4 ; $i++) { 
	$soustract = $year - $i;
	$preced = $soustract - 1;
	$scolaire = $preced." - ".$soustract;
?>
<div class="p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
	<div class="text-center bg-gradient-to-r from-cyan-500">
		<b>Etudiants en année <?=$scolaire?>.</b>
	</div>
	<div>
		<table class="simpleTbl mb-1">
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<td class="w-20">ID</td>
					<td>Nom et Prénoms</td>
					<td class="w-20">Niveau</td>
					<td class="w-20">Semestre</td>
					<td class="w-20">Notes/20</td>
					<td class="w-4">Etat</td>
					<td class="w-4"><span class="bi-trash3-fill"></span></td>
				</tr>
			</thead>
			<tbody class="<?=$bg_four_color?>">
<?php
	$sigle = $profil['Sigle'];
	$title = $profil['title'];
	/* Recherche par sigle et non par id_cours : 921 notes pointent vers une fiche
	   de cours réutilisée pour un autre cours (ex. « Anatomie I » sur la fiche
	   « Anatomie II »). Requête préparée : un sigle contenant un guillemet
	   cassait la page. */
	require_once(__DIR__ . '/../../data/perimetre.php');
	$cors = $dtb->prepare('SELECT n.*, s.session_name, s.session_semester, s.session_year FROM t_2023_notes n LEFT JOIN t_2023_session s ON s.session_id = n.session_id WHERE n.Sigle = ? AND n.annee_scolaire = ? AND n.remove = 0 ORDER BY n.session_id ASC, n.student_id ASC');
	$cors->execute([$sigle, $scolaire]);

	$nbr = 1;
	$currentSessionId = null;
	while ($cours_table = $cors->fetch()) {
		$idcours = $cours_table['id'];
		$noteNbr = $i . $nbr;
		$sessionId = (int)($cours_table['session_id'] ?? 0);

		$jer = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :sid ORDER BY remove ASC, id ASC LIMIT 1');
		$jer->execute(['sid' => $cours_table['student_id']]);
		$apotr = $jer->fetch();

		// Ne pas afficher les etudiants suspendus ou retires de l'universite.
		if (
			$apotr
			&& (
				(isset($apotr['suspended']) && (int)$apotr['suspended'] === 1)
				|| (isset($apotr['retrait_universite']) && (int)$apotr['retrait_universite'] !== 0)
			)
		) {
			continue;
		}

		// Hors du perimetre du compte (data/perimetre.php) : invisible.
		if (!perimetreEtudiantVisible($apotr)) {
			continue;
		}

		if ($currentSessionId !== $sessionId) {
			$currentSessionId = $sessionId;
			$sessionName = trim((string)($cours_table['session_name'] ?? ''));
			$sessionSemester = trim((string)($cours_table['session_semester'] ?? ''));
			$sessionYear = trim((string)($cours_table['session_year'] ?? ''));
			$sessionLabel = 'Session #'.$currentSessionId;
			if ($sessionName !== '' || $sessionSemester !== '' || $sessionYear !== '') {
				$sessionLabel = trim($sessionName.' - S'.$sessionSemester.' / '.$sessionYear, ' -/S');
				if ($sessionLabel === '') {
					$sessionLabel = 'Session #'.$currentSessionId;
				}
			}
?>
				<tr class="bg-cyan-900 text-white">
					<td colspan="7"><b><?=htmlspecialchars($sessionLabel)?></b></td>
				</tr>
<?php
		}

		/* -4 (pas encore noté) s'affiche NN, -2 V, -3 E : data/notes.statut.php. */
		$noteStatut = noteStatut($cours_table['grade'], $isValidationCourse);
		$isStatusOnlyGrade = !$noteStatut['chiffree'];
		$statusSymbol = $noteStatut['code'];
		$statusTitle = $noteStatut['titre'];
		$statusClass = noteClasseEtat($noteStatut['code']);
?>
				<tr id="row<?=$noteNbr?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black">
					<td class="bg-gradient-to-r from-orange-800 to-orange-400"><?=$cours_table['student_id']?></td>
					<td><?php
						if($apotr){
							if(is_null($apotr['student_nom']) AND is_null($apotr['student_prenom'])){
								echo "<em style='color:red'>Non défini</em>";
							}else{
								echo $apotr['student_nom']." ".$apotr['student_prenom'];
							}
						}else{
							echo "<em style='color:red'>Etudiant supprimé de la base.</em>";
						}
					?></td>
					<td><?php echo $apotr ? "L".$apotr['annee_etude'] : "-"; ?></td>
					<td><?=$cours_table['semester']?></td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0">
<?php if ($privilege == "registrar" OR $privilege == "administrator" OR $privilege == "superadmin") { ?>
						<?php
						$inputValue = $isStatusOnlyGrade ? $noteStatut['libelle'] : $cours_table['grade'];
						?>
						<input class="insimple text-sm bg-transparent px-2 note-input note-input-<?=$soustract?>"
							type="text"
							name="nb_crd<?=$noteNbr?>"
							value="<?=$inputValue;?>"
							data-action="<?=$app_base?>/app/.student/updatenote?id=<?=$id?>&nbr=<?=$noteNbr?>&note_id=<?=$idcours?>&as=<?=$i?>&user_id=<?=$rg_id?>"
							data-nbr="<?=$noteNbr?>"
							data-idcours="<?=$idcours?>"
							data-student-id="<?=$cours_table['student_id']?>"
							data-validation-course="<?=$isValidationCourse ? 1 : 0?>"
							data-year="<?=$soustract?>">
<?php } else { ?>
						<a class="px-2"><?php if ($isStatusOnlyGrade) { echo $statusSymbol; } else { echo $cours_table['grade']; } ?></a>
<?php } ?>
					</td>
					<td class="stp-<?=$nbr.$soustract;?> <?=$statusClass?> text-center" title="<?=$statusTitle?>">
<?=$statusSymbol?>
					</td>
					<td>
						<?php if ($canRemoveStudentFromCourse): ?>
						<div class="nav-item dropstart" style="list-style: none">
							<a href="#" class="btn nav-link" type="button" role="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="bi-three-dots-vertical"></span></a>
							<ul class="dropdown-menu">
								<li><a class="dropdown-item" href="app/.cours/dell-listcours.incours?idSupprCours=<?=$idcours?>&id=<?=$id?>&rg_id=<?=$rg_id?>"><span class="bi-trash3-fill" style="color: red;"></span> Supprimer cet étudiant</a></li>
							</ul>
						</div>
						<?php endif; ?>
					</td>
				</tr>
<?php
		$nbr++;
	}
	$count = $nbr - 1;
?>
			</tbody>
		</table>

		<div class="text-center text-white">
			<b><p style="margin: 0px;">Nombre : <?=$count <= 1 ? $count." étudiant" : $count." étudiants"?></p></b>
		</div>

<?php if (($privilege == "registrar" OR $privilege == "administrator" OR $privilege == "superadmin") && $count > 0) { ?>
		<div class="flex items-center justify-between mt-2 px-2 py-2 rounded-md bg-slate-700">
			<div id="bulk-alert-<?=$soustract?>" class="text-xs"></div>
			<button type="button" class="bulk-save-btn px-4 py-2 bg-cyan-700 hover:bg-cyan-600 text-white rounded-md text-sm transition-all flex items-center gap-2" data-year="<?=$soustract?>">
				<i class="bi-check2-all"></i> Enregistrer toutes les notes (<?=$preced?> - <?=$soustract?>)
			</button>
		</div>
<?php } ?>
	</div>
</div>
<?php } ?>
</div>

<?php if ($privilege == "registrar" OR $privilege == "administrator" OR $privilege == "superadmin") { ?>
<script>
$(function(){
	const isValidationCourse = <?= $isValidationCourse ? 'true' : 'false' ?>;

	/* Codes acceptés en plus des notes chiffrées : V (validé), E (échec) et
	   NN (pas encore noté, enregistré -4). Même règle que data/notes.statut.php. */
	function normaliserCode(value) {
		var v = (value || '').trim().toUpperCase();
		if (v === 'VALIDE' || v === 'OK') return 'V';
		if (v === 'ECHEC' || v === 'ÉCHEC') return 'E';
		return v;
	}
	function estCode(v) {
		return v === 'V' || v === 'E' || v === 'NN';
	}

	// ===== VALIDATION =====
	function validateNote(value) {
		var v = normaliserCode(value);
		if (estCode(v)) return { valid: true };
		if (isValidationCourse) {
			return { valid: false, message: 'Pour ce cours, utilisez uniquement V (validé), E (échec) ou NN (pas encore noté).' };
		}
		var n = parseFloat(String(value).replace(',', '.'));
		if (isNaN(n)) return { valid: false, message: 'La note doit être un nombre, V, E ou NN.' };
		if (n > 20) return { valid: false, message: 'La note ne peut pas dépasser 20.' };
		if (n < 0) return { valid: false, message: 'La note ne peut pas être négative. Saisissez NN pour « pas encore noté ».' };
		return { valid: true };
	}

	// ===== PASTILLE D'ETAT : S, E, V ou NN =====
	function majEtat(st, valeur) {
		var v = normaliserCode(valeur);
		var n = parseFloat(v.replace(',', '.'));
		var etat, fond;
		if (v === 'V' || n === -2) { etat = 'V'; fond = '#15dd2a'; }
		else if (v === 'E' || n === -3) { etat = 'E'; fond = '#ff0000'; }
		else if (v === 'NN' || v === '' || isNaN(n) || n < 0) { etat = 'NN'; fond = '#64748b'; }
		else if (isValidationCourse) { etat = n >= 10 ? 'V' : 'E'; fond = n >= 10 ? '#15dd2a' : '#ff0000'; }
		else { etat = n >= 10 ? 'S' : 'E'; fond = n >= 10 ? '#15dd2a' : '#ff0000'; }
		st.removeClass('bg-green-500 bg-red-500 bg-slate-500').css({'background': fond, 'color': 'white'}).text(etat);
	}

	/* Le script est rejoue a chaque retour sur cette page sans rechargement
	   (src/js/zone-ajax.js) : ses delegations portent un espace de noms, et
	   les precedentes sont retirees, pour qu'une note ne parte qu'une fois. */
	$(document).off('.ficheCoursNotes');

	// ===== VALIDATION VISUELLE EN TEMPS REEL =====
	$(document).on('input.ficheCoursNotes', '.note-input', function() {
		var el = $(this);
		var brut = el.val() || '';
		el.next('.note-warning').remove();
		el.css({'border':'','border-radius':'','background':''});

		if (isValidationCourse) {
			var upper = brut.toUpperCase();
			if (upper !== brut) el.val(upper);
			// « N » : saisie de NN en cours
			if (upper !== '' && upper !== 'N' && !estCode(upper)) {
				el.css({'border':'2px solid #ef4444','border-radius':'4px','background':'rgba(239,68,68,0.15)'});
				el.after('<span class="note-warning text-[10px] text-red-400 block">Utilisez V, E ou NN</span>');
			}
			return;
		}

		var code = normaliserCode(brut);
		if (estCode(code) || code === 'N') return;
		var n = parseFloat(brut.replace(',', '.'));
		if (brut !== '' && !isNaN(n)) {
			if (n > 20) {
				el.css({'border':'2px solid #ef4444','border-radius':'4px','background':'rgba(239,68,68,0.15)'});
				el.after('<span class="note-warning text-[10px] text-red-400 block">Max 20</span>');
			} else if (n < 0) {
				el.css({'border':'2px solid #f97316','border-radius':'4px','background':'rgba(249,115,22,0.15)'});
				el.after('<span class="note-warning text-[10px] text-orange-400 block">Négatif : saisir NN</span>');
			}
		}
	});

	// ===== NORMALISER ET ARRONDIR AU BLUR =====
	$(document).on('blur.ficheCoursNotes', '.note-input', function() {
		var el = $(this);
		var code = normaliserCode(el.val());
		if (isValidationCourse || estCode(code)) {
			el.val(code);
			return;
		}
		var n = parseFloat((el.val() || '').replace(',', '.'));
		if (!isNaN(n)) {
			var rounded = Math.round(n * 100) / 100;
			if (rounded !== n) el.val(rounded);
		}
	});

	// ===== SAUVEGARDE INDIVIDUELLE AU CHANGE (même pattern que transcriptSS.php) =====
	$(document).on('change.ficheCoursNotes', '.note-input', function() {
		var input = $(this);
		var url = input.attr('data-action');
		var nbr = input.attr('data-nbr');
		var noteValue = (input.val() || '').trim();
		if (noteValue === '') return;

		var code = normaliserCode(noteValue);
		if (isValidationCourse || estCode(code)) {
			noteValue = code;
		} else {
			noteValue = noteValue.replace(',', '.');
			var nVal = parseFloat(noteValue);
			if (!isNaN(nVal)) noteValue = '' + (Math.round(nVal * 100) / 100);
		}
		input.val(noteValue);

		var v = validateNote(noteValue);
		if (!v.valid) {
			if (typeof Toast !== 'undefined') Toast.error(v.message);
			input.focus();
			return;
		}

		// Même format que transcriptSS: nb_crd + nbr = valeur
		var data = 'nb_crd' + nbr + '=' + encodeURIComponent(noteValue);

		/* Un délai par champ : avec un délai commun, passer vite d'une note à
		   l'autre annulait l'enregistrement de la précédente. */
		clearTimeout(input.data('noteTimeout'));
		input.data('noteTimeout', setTimeout(function() {
			$.ajax({
				url: url,
				method: 'POST',
				data: data,
				dataType: 'json',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
					input.css('opacity', '0.5');
				},
				success: function(resp) {
					input.css('opacity', '1');
					if (resp && resp.success) {
						if (typeof Toast !== 'undefined') Toast.success(resp.message || 'Note enregistrée!');
						input.css({'border':'','border-radius':'','background':''});
						input.blur();
						majEtat(input.closest('tr').find('td[class*="stp-"]'), noteValue);
					} else {
						if (typeof Toast !== 'undefined') Toast.error(resp.message || 'Erreur');
					}
				},
				error: function(xhr) {
					input.css('opacity', '1');
					var msg = 'Erreur serveur';
					try { var r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch(e) {}
					if (typeof Toast !== 'undefined') Toast.error(msg);
				}
			});
		}, 300));
	});

	// ===== BOUTON ENREGISTRER TOUTES LES NOTES PAR ANNEE =====
	$(document).on('click.ficheCoursNotes', '.bulk-save-btn', function() {
		var btn = $(this);
		var year = btn.attr('data-year');
		var inputs = $('.note-input-' + year);
		var notes = [];

		inputs.each(function() {
			var brut = ($(this).val() || '').trim();
			if (brut === '') return;
			var code = normaliserCode(brut);
			var val;
			if (estCode(code)) {
				val = code;
			} else if (isValidationCourse) {
				return;
			} else {
				val = brut.replace(',', '.');
				if (isNaN(parseFloat(val))) return;
			}
			notes.push({
				idcours: $(this).attr('data-idcours'),
				student_id: $(this).attr('data-student-id'),
				grade: val
			});
		});

		if (notes.length === 0) {
			if (typeof Toast !== 'undefined') Toast.warning('Aucune note à enregistrer');
			return;
		}

		// Confirmation en modale : au premier clic on demande, puis le clic est rejoué une fois confirmé.
		if (btn.data('confirme') !== 1) {
			var texteNotes = 'Enregistrer ' + notes.length + ' note(s) pour l\'année ' + (year-1) + ' - ' + year + ' ?';
			(typeof Toast !== 'undefined' && typeof Toast.confirm === 'function'
				? Toast.confirm(texteNotes, { title: 'Enregistrer les notes', confirmText: 'Enregistrer' })
				: Promise.resolve(false)   // sans modale, on n'enregistre pas sans accord
			).then(function(ok) {
				if (ok) { btn.data('confirme', 1); btn.trigger('click'); }
			});
			return;
		}
		btn.removeData('confirme');

		var originalHtml = btn.html();
		btn.prop('disabled', true).html('<i class="bi-hourglass-split"></i> Enregistrement...');

		$.ajax({
			url: APP_BASE + '/app/.cours/bulksavenotes',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ notes: notes }),
			success: function(resp) {
				if (typeof resp === 'string') { try { resp = JSON.parse(resp); } catch(e) { resp = {success:false}; } }

				if (resp.saved > 0) {
					if (typeof Toast !== 'undefined') Toast.success(resp.saved + ' note(s) enregistrée(s)');
					inputs.each(function() {
						var brut = ($(this).val() || '').trim();
						if (brut === '') return;
						$(this).css({'border':'','background':''});
						majEtat($(this).closest('tr').find('td[class*="stp-"]'), brut);
					});
				}
				if (resp.errors && resp.errors.length > 0) {
					var errMsg = resp.errors.map(function(e) { return e.message; }).join('\n');
					if (typeof Toast !== 'undefined') Toast.error(errMsg);
				}
				btn.prop('disabled', false).html(originalHtml);
			},
			error: function() {
				if (typeof Toast !== 'undefined') Toast.error('Erreur réseau');
				btn.prop('disabled', false).html(originalHtml);
			}
		});
	});

});
</script>
<?php } ?>
