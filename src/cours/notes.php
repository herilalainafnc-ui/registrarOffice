<div class="mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);" id="notesCoursContainer">
<?php 
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
	$cors = $dtb->query('SELECT * FROM t_2023_notes WHERE Sigle ="'.$sigle.'" AND annee_scolaire = "'.$scolaire.'" AND remove = 0 ORDER BY student_id');

	$nbr = 1;
	while ($cours_table = $cors->fetch()) {
		$idcours = $cours_table['id'];
		$noteNbr = $i . $nbr;

		$jer = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE student_id='".$cours_table['student_id']."'");
		$apotr = $jer->fetch();
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
						$inputValue = $cours_table['grade'];
						if ($isValidationCourse) {
							if ($cours_table['grade'] == -2 || $cours_table['grade'] >= 10) {
								$inputValue = 'V';
							} elseif ($cours_table['grade'] > 0 && $cours_table['grade'] < 10) {
								$inputValue = 'E';
							} elseif ((float)$cours_table['grade'] === 0.0) {
								$inputValue = '';
							}
						}
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
						<a class="px-2"><?php
							if ($isValidationCourse) {
								if ($cours_table['grade'] == -2 || $cours_table['grade'] >= 10) echo 'V';
								elseif ($cours_table['grade'] > 0 && $cours_table['grade'] < 10) echo 'E';
								else echo '';
							} else {
								echo $cours_table['grade'];
							}
						?></a>
<?php } ?>
					</td>
					<td class="stp-<?=$nbr.$soustract;?> <?php 
						if ($isValidationCourse) {
							if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) echo "bg-green-500";
							elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) echo "bg-red-500";
						} else {
							if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) echo "bg-green-500";
							elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) echo "bg-red-500";
						}
					?> text-center" title="<?php 
						if ($isValidationCourse) {
							if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) echo "Validé";
							elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) echo "Echec";
						} else {
							if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) echo "Succès";
							elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) echo "Echec";
						}
					?>">
<?php 
						if ($isValidationCourse) {
							if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) echo "V";
							elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) echo "E";
						} else {
							if ($cours_table['grade'] == -2 OR $cours_table['grade'] >= 10) echo "S";
							elseif ($cours_table['grade'] < 10 and $cours_table['grade'] > 0) echo "E";
						}
?>
					</td>
					<td>
						<div class="nav-item dropstart" style="list-style: none">
							<a href="#" class="btn nav-link" type="button" role="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="bi-three-dots-vertical"></span></a>
							<ul class="dropdown-menu">
								<li><a class="dropdown-item" href="app/.cours/dell-listcours.incours?idSupprCours=<?=$idcours?>&id=<?=$id?>"><span class="bi-trash3-fill" style="color: red;"></span> Supprimer cet étudiant</a></li>
							</ul>
						</div>
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

	// ===== VALIDATION =====
	function validateNote(value) {
		if (isValidationCourse) {
			var v = (value || '').trim().toUpperCase();
			if (v === 'V' || v === 'E') return { valid: true };
			return { valid: false, message: 'Pour ce cours, utilisez uniquement V (validé) ou E (échec).' };
		}

		if (value === 'ok' || value === 'Ok' || value === 'OK') return { valid: true };
		var n = parseFloat(value);
		if (isNaN(n)) return { valid: false, message: 'La note doit être un nombre valide.' };
		if (n > 20) return { valid: false, message: 'La note ne peut pas dépasser 20.' };
		if (n < 0) return { valid: false, message: 'La note ne peut pas être négative.' };
		return { valid: true };
	}

	// ===== VALIDATION VISUELLE EN TEMPS REEL =====
	$(document).on('input', '.note-input', function() {
		var el = $(this);

		if (isValidationCourse) {
			var raw = el.val() || '';
			var upper = raw.toUpperCase();
			if (upper !== raw) el.val(upper);

			el.next('.note-warning').remove();
			if (upper !== '' && upper !== 'V' && upper !== 'E') {
				el.css({'border':'2px solid #ef4444','border-radius':'4px','background':'rgba(239,68,68,0.15)'});
				el.after('<span class="note-warning text-[10px] text-red-400 block">Utilisez V ou E</span>');
			} else {
				el.css({'border':'','border-radius':'','background':''});
			}
			return;
		}

		var val = el.val().replace(',', '.');
		var n = parseFloat(val);

		el.next('.note-warning').remove();

		if (val !== '' && !isNaN(n)) {
			if (n > 20) {
				el.css({'border':'2px solid #ef4444','border-radius':'4px','background':'rgba(239,68,68,0.15)'});
				el.after('<span class="note-warning text-[10px] text-red-400 block">Max 20</span>');
			} else if (n < 0 && n != -2) {
				el.css({'border':'2px solid #f97316','border-radius':'4px','background':'rgba(249,115,22,0.15)'});
				el.after('<span class="note-warning text-[10px] text-orange-400 block">Négatif</span>');
			} else {
				el.css({'border':'','border-radius':'','background':''});
			}
		} else {
			el.css({'border':'','border-radius':'','background':''});
		}
	});

	// ===== Arrondir à 2 décimales au blur =====
	$(document).on('blur', '.note-input', function() {
		var el = $(this);

		if (isValidationCourse) {
			var vv = (el.val() || '').trim().toUpperCase();
			el.val(vv);
			return;
		}

		var val = el.val().replace(',', '.');
		var n = parseFloat(val);
		if (val !== '' && !isNaN(n) && n != -2) {
			var rounded = Math.round(n * 100) / 100;
			if (rounded !== n) {
				el.val(rounded);
			}
		}
	});

	// ===== SAUVEGARDE INDIVIDUELLE AU CHANGE (même pattern que transcriptSS.php) =====
	var noteTimeout;
	$(document).on('change', '.note-input', function() {
		var input = $(this);
		var url = input.attr('data-action');
		var nbr = input.attr('data-nbr');
		var noteValue = input.val().trim();
		if (!isValidationCourse) {
			noteValue = noteValue.replace(',', '.');
		} else {
			noteValue = noteValue.toUpperCase();
			input.val(noteValue);
		}

		if (noteValue === '') return;

		// Arrondir à 2 décimales pour les cours numériques
		if (!isValidationCourse) {
			var nVal = parseFloat(noteValue);
			if (!isNaN(nVal) && nVal != -2) {
				noteValue = '' + (Math.round(nVal * 100) / 100);
				input.val(noteValue);
			}
		}

		var v = validateNote(noteValue);
		if (!v.valid) {
			if (typeof Toast !== 'undefined') Toast.error(v.message);
			input.focus();
			return;
		}

		// Même format que transcriptSS: nb_crd + nbr = valeur
		var data = 'nb_crd' + nbr + '=' + encodeURIComponent(noteValue);

		clearTimeout(noteTimeout);
		noteTimeout = setTimeout(function() {
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

						var n = parseFloat(noteValue.replace(',', '.'));
						var row = input.closest('tr');
						var st = row.find('td[class*="stp-"]');

						input.css({'border':'','border-radius':'','background':''});
						input.blur();

						if (isValidationCourse) {
							if (noteValue === 'V') {
								st.removeClass('bg-red-500').css({'background':'#15dd2a','color':'white'}).text('V');
							} else if (noteValue === 'E') {
								st.removeClass('bg-green-500').css({'background':'#ff0000','color':'white'}).text('E');
							} else {
								st.removeClass('bg-green-500 bg-red-500').css({'background':'','color':''}).text('');
							}
						} else {
							if (n == 0 || noteValue === '') {
								st.removeClass('bg-green-500 bg-red-500').css({'background':'','color':''}).text('');
							} else if (n == -2 || n >= 10) {
								st.removeClass('bg-red-500').css({'background':'#15dd2a','color':'white'}).text('S');
							} else if (n < 10 && n > 0) {
								st.removeClass('bg-green-500').css({'background':'#ff0000','color':'white'}).text('E');
							}
						}
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
		}, 300);
	});

	// ===== BOUTON ENREGISTRER TOUTES LES NOTES PAR ANNEE =====
	$(document).on('click', '.bulk-save-btn', function() {
		var btn = $(this);
		var year = btn.attr('data-year');
		var inputs = $('.note-input-' + year);
		var notes = [];

		inputs.each(function() {
			var val = $(this).val().trim();
			if (!isValidationCourse) {
				val = val.replace(',', '.');
			}

			if (isValidationCourse) {
				val = val.toUpperCase();
				if (val !== 'V' && val !== 'E') {
					return;
				}
			}

			var n = parseFloat(val);
			if (val !== '' && (isValidationCourse || !isNaN(n))) {
				notes.push({
					idcours: $(this).attr('data-idcours'),
					student_id: $(this).attr('data-student-id'),
					grade: val
				});
			}
		});

		if (notes.length === 0) {
			if (typeof Toast !== 'undefined') Toast.warning('Aucune note à enregistrer');
			return;
		}

		if (!confirm('Enregistrer ' + notes.length + ' note(s) pour l\'année ' + (year-1) + ' - ' + year + ' ?')) return;

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
						var rawVal = ($(this).val() || '').trim();
						var val = isValidationCourse ? rawVal.toUpperCase() : rawVal.replace(',', '.');
						var n = parseFloat(val);
						var st = $(this).closest('tr').find('td[class*="stp-"]');
						$(this).css({'border':'','background':''});
						if (isValidationCourse) {
							if (val === 'V') st.css({'background':'#15dd2a','color':'white'}).text('V');
							else if (val === 'E') st.css({'background':'#ff0000','color':'white'}).text('E');
							else st.css({'background':'','color':''}).text('');
						} else if (!isNaN(n) && n <= 20) {
							if (n == 0) st.css({'background':'','color':''}).text('');
							else if (n == -2 || n >= 10) st.css({'background':'#15dd2a','color':'white'}).text('S');
							else if (n < 10 && n > 0) st.css({'background':'#ff0000','color':'white'}).text('E');
						}
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
