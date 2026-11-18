<?php
/* Tests de data/studio.visuels.php (Studio UAZ, etape 5) : brouillons, historique, validation, journal.
   Sur la base locale (migration data/migration_studio_visuels.sql) ; les visuels de test sont effaces a la fin.
   php tests/test_studio_visuels.php */

require __DIR__ . '/../data/backdb.php';
require __DIR__ . '/../data/studio.visuels.php';

$echecs = 0;
$total = 0;
function verifier(string $nom, $obtenu, $attendu): void
{
    global $echecs, $total;
    $total++;
    if ($obtenu !== $attendu) {
        $echecs++;
        echo "ÉCHEC  $nom\n        obtenu  : " . var_export($obtenu, true) . "\n        attendu : " . var_export($attendu, true) . "\n";
    }
}
function lance(callable $f): string
{
    try { $f(); return ''; } catch (StudioErreur $e) { return $e->getMessage(); }
}

$media = (int) $dtb->query('SELECT id FROM compt_utilisateur WHERE level = 5 AND etat = 1 ORDER BY id LIMIT 1')->fetchColumn();
$autre = (int) $dtb->query('SELECT id FROM compt_utilisateur WHERE level = 5 AND etat = 1 AND id != ' . $media . ' ORDER BY id LIMIT 1')->fetchColumn();
$super = (int) $dtb->query('SELECT id FROM compt_utilisateur WHERE level = 1 AND etat = 1 ORDER BY id LIMIT 1')->fetchColumn();
if (!$media || !$super) { exit("Il faut un compte Media et un superadmin en base locale.\n"); }
$autre = $autre ?: $media;

$ids = [];
$nettoyer = function () use ($dtb, &$ids) {
    foreach ($ids as $id) {
        $dtb->prepare('DELETE FROM t_studio_version WHERE visuel_id = ?')->execute([$id]);
        $dtb->prepare('DELETE FROM t_studio_journal WHERE visuel_id = ?')->execute([$id]);
        $dtb->prepare('DELETE FROM t_studio_visuel WHERE id = ?')->execute([$id]);
        foreach (glob(studioImagesDossier($id) . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir(studioImagesDossier($id));
    }
    $dtb->prepare("DELETE FROM t_studio_role WHERE cree_par = -1")->execute();
};
register_shutdown_function($nettoyer);

/* --- roles */
$rSuper = studioRoles($dtb, $super, 1);
verifier('superadmin : valide et administre', [$rSuper['validateur'], $rSuper['admin']], [true, true]);
$rMedia = studioRoles($dtb, $media, 5);
verifier('media : éditeur seulement', [$rMedia['editeur'], $rMedia['validateur'], $rMedia['admin']], [true, false, false]);
$dtb->prepare("INSERT INTO t_studio_role (user_id, role, cree_par, cree_le) VALUES (?, 'validateur', -1, NOW())")->execute([$autre]);
verifier('un validateur désigné valide', studioRoles($dtb, $autre, 5)['validateur'], true);
$dtb->prepare('DELETE FROM t_studio_role WHERE cree_par = -1')->execute();
verifier('presets à validation obligatoire', [studioValidationObligatoire('com'), studioValidationObligatoire('rec'), studioValidationObligatoire('evt')], [true, true, false]);

/* --- un brouillon incomplet s'enregistre */
$com = studioPreset('com');
$jeu = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/com-test.json'), true)['donnees'];
$v = studioVisuelEnregistrer($dtb, null, $com, '4:5', ['titre' => 'Report de la journée', 'service' => 'Service de test'], [], null, 'auto', $media);
$ids[] = $id = (int) $v['id'];
verifier('brouillon créé', [$v['statut'], $v['auteur_id'], $v['titre']], ['brouillon', $media, 'Report de la journée']);
verifier('champs inconnus ignorés', array_key_exists('piege', $v['donnees']), false);
$v2 = studioVisuelEnregistrer($dtb, $v, $com, '4:5', ['titre' => 'Report de la journée', 'service' => 'Service de test', 'piege' => '<script>'], [], null, 'auto', $media);
verifier('champ hors preset ignoré', array_key_exists('piege', $v2['donnees']), false);
verifier('enregistrements rapprochés = une version', count(studioHistorique($dtb, $id)), 1);
verifier('refus : donnees incompletes', lance(fn () => studioSoumettre($dtb, studioVisuelLire($dtb, $id), $com)) !== '', true);
$v2 = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, $id), $com, '4:5', $jeu, [], null, 'manuel', $media);
verifier('version manuelle = une de plus', count(studioHistorique($dtb, $id)), 2);

/* --- soumission : le contenu doit etre complet */
verifier('complet : soumis', lance(fn () => studioSoumettre($dtb, studioVisuelLire($dtb, $id), $com)), '');
$v4 = studioVisuelLire($dtb, $id);
verifier('statut a_valider', $v4['statut'], 'a_valider');
verifier('export refusé tant qu\'il attend', studioExportRefuse($v4, $com), 'Ce visuel attend encore sa validation.');
verifier('export refusé sans visuel enregistré', studioExportRefuse(null, $com) !== null, true);
verifier('preset facultatif : export libre', studioExportRefuse(null, studioPreset('evt')), null);

/* --- decision */
verifier('l\'auteur ne se valide pas', lance(fn () => studioDecider($dtb, $v4, true, null, $media, $rMedia)), 'Seul un validateur peut décider.');
$rAuteurValidateur = ['editeur' => true, 'validateur' => true, 'admin' => false];
verifier('un validateur ne valide pas son propre visuel', str_contains(lance(fn () => studioDecider($dtb, $v4, true, null, $media, $rAuteurValidateur)), 'soi-même'), true);
verifier('refus sans motif', str_contains(lance(fn () => studioDecider($dtb, $v4, false, '  ', $super, $rSuper)), 'pourquoi'), true);
verifier('refus motivé', lance(fn () => studioDecider($dtb, $v4, false, 'Date à confirmer', $super, $rSuper)), '');
$v5 = studioVisuelLire($dtb, $id);
verifier('statut refusé + motif', [$v5['statut'], $v5['motif_refus']], ['refuse', 'Date à confirmer']);
verifier('export refusé après refus', str_contains((string) studioExportRefuse($v5, $com), 'refusé'), true);
verifier('resoumission après refus', lance(fn () => studioSoumettre($dtb, $v5, $com)), '');
verifier('validation', lance(fn () => studioDecider($dtb, studioVisuelLire($dtb, $id), true, null, $super, $rSuper)), '');
$v6 = studioVisuelLire($dtb, $id);
verifier('statut validé, validateur noté', [$v6['statut'], (bool) $v6['valide_nom']], ['valide', true]);
verifier('export permis une fois validé', studioExportRefuse($v6, $com), null);

/* --- toucher au contenu remet en brouillon */
$jeu2 = $jeu;
$jeu2['titre'] = 'Report de la journée du campus';
$v7 = studioVisuelEnregistrer($dtb, $v6, $com, '4:5', $jeu2, [], null, 'auto', $media);
verifier('contenu modifié : retour en brouillon', [$v7['statut'], $v7['retourBrouillon'], $v7['valide_nom']], ['brouillon', true, null]);
verifier('export refusé après modification', studioExportRefuse($v7, $com) !== null, true);
/* la legende et le format ne sont pas du contenu */
$v8 = studioVisuelEnregistrer($dtb, $v7, $com, '4:5', $jeu2, [], null, 'auto', $media);
lance(fn () => studioSoumettre($dtb, studioVisuelLire($dtb, $id), $com));
studioDecider($dtb, studioVisuelLire($dtb, $id), true, null, $super, $rSuper);
$v9 = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, $id), $com, 'A4', $jeu2, [], 'Une légende', 'auto', $media);
verifier('format et légende : la validation tient', [$v9['statut'], $v9['legende'], $v9['format']], ['valide', 'Une légende', 'A4']);

/* --- historique et restauration */
$h = studioHistorique($dtb, $id);
verifier('historique du plus récent au plus ancien', $h[0]['numero'] > $h[count($h) - 1]['numero'], true);
$r = studioRestaurer($dtb, studioVisuelLire($dtb, $id), $h[count($h) - 1]['numero'], $media);
verifier('restauration : l\'ancien titre revient', $r['donnees']['titre'], 'Report de la journée');
verifier('restauration : une version de plus', count(studioHistorique($dtb, $id)) > count($h), true);
verifier('version inconnue', lance(fn () => studioRestaurer($dtb, studioVisuelLire($dtb, $id), 999, $media)), 'Cette version n\'existe plus.');

/* --- images : écrites sous un nom tiré du contenu, reprises par la duplication, nettoyées */
$evt = studioPreset('evt');
$emp = $evt['images'][0]['id'];
$png = tempnam(sys_get_temp_dir(), 'stv') . '.png';
$im = imagecreatetruecolor(40, 30);
imagepng($im, $png);
$prep = studioPreparerImage($png);
@unlink($png);
$donneesEvt = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/evt-trs.json'), true)['donnees'];
$e1 = studioVisuelEnregistrer($dtb, null, $evt, '4:5', $donneesEvt, [$emp => ['nouveau' => $prep, 'alt' => 'Une photo de test', 'ajustement' => 'remplir', 'cadrage' => ['x' => 2, 'y' => 0.25, 'zoom' => 9]]], null, 'auto', $media);
$ids[] = $e1['id'];
$m = $e1['images'][$emp] ?? null;
verifier('image enregistrée', [studioNomImageValide($m['fichier'] ?? null), $m['alt'] ?? null], [true, 'Une photo de test']);
verifier('options bornées', [$m['cadrage']['x'], $m['cadrage']['y'], $m['cadrage']['zoom']], [1.0, 0.25, 4.0]);
verifier('image relue', studioImageLire((int) $e1['id'], $m['fichier'])['type'], 'image/jpeg');
verifier('nom d\'image piégé refusé', studioImageLire((int) $e1['id'], '../../config.php'), null);
verifier('nom d\'image piégé non valide', studioNomImageValide('../a-123456789abc.jpg'), false);
$e2 = studioVisuelEnregistrer($dtb, $e1, $evt, '4:5', $donneesEvt, [$emp => ['fichier' => $m['fichier'], 'alt' => 'Une photo de test']], null, 'auto', $media);
verifier('image gardée sans renvoi', $e2['images'][$emp]['fichier'], $m['fichier']);
$e2 = studioVisuelEnregistrer($dtb, $e2, $evt, '4:5', $donneesEvt, [$emp => ['fichier' => $m['fichier'], 'alt' => 'Une photo de test']], null, 'manuel', $media);
$copie = studioVisuelDupliquer($dtb, studioVisuelLire($dtb, (int) $e1['id']), $autre);
$ids[] = $copie['id'];
verifier('copie : brouillon au nom du compte, image copiée', [$copie['statut'], $copie['auteur_id'], is_file(studioImagesDossier((int) $copie['id']) . '/' . $m['fichier'])], ['brouillon', $autre, true]);
verifier('copie : l\'original ne bouge pas', studioVisuelLire($dtb, (int) $e1['id'])['statut'], 'brouillon');
verifier('fichier hors du visuel refusé', isset(studioVisuelEnregistrer($dtb, $copie, $evt, '4:5', $donneesEvt, [$emp => ['fichier' => '../x-123456789abc.jpg', 'alt' => 'a']], null, 'auto', $autre)['images'][$emp]), false);
$sans = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, (int) $e1['id']), $evt, '4:5', $donneesEvt, [], null, 'manuel', $media);
verifier('image retirée', $sans['images'], []);
verifier('l\'ancienne image reste pour l\'historique', is_file(studioImagesDossier((int) $e1['id']) . '/' . $m['fichier']), true);

/* --- droits */
verifier('l\'auteur modifie', studioPeutModifier($copie, $autre, studioRoles($dtb, $autre, 5)), true);
verifier('un autre éditeur ne modifie pas', $media === $autre ? true : studioPeutModifier($copie, $media, $rMedia), $media === $autre ? true : false);
verifier('le superadmin modifie', studioPeutModifier($copie, $super, $rSuper), true);

/* --- liste, suppression, journal */
verifier('mes visuels', in_array($id, array_column(studioVisuelsListe($dtb, 'mes', $media), 'id')), true);
verifier('« à valider » ne montre pas les brouillons', in_array($id, array_column(studioVisuelsListe($dtb, 'a_valider', $media), 'id')), false);
studioJournalAjouter($dtb, studioVisuelLire($dtb, $id), $com, 'Report', '4:5', 'pdf', $media);
$j = studioJournalLire($dtb, 5)[0];
verifier('journal : qui, quoi, format', [$j['visuel_id'] == $id, $j['sortie'], $j['format'], $j['statut_visuel']], [true, 'pdf', '4:5', 'brouillon']);
verifier('export noté sur le visuel', studioVisuelLire($dtb, $id)['exporte_le'] !== null, true);
studioVisuelSupprimer($dtb, $id);
verifier('supprimé : introuvable', studioVisuelLire($dtb, $id), null);
verifier('supprimé : plus dans la liste', in_array($id, array_column(studioVisuelsListe($dtb, 'tous', $media), 'id')), false);

/* --- 30 versions au plus */
$p = studioVisuelEnregistrer($dtb, null, $evt, '4:5', $donneesEvt, [], null, 'manuel', $media);
$ids[] = $p['id'];
for ($i = 0; $i < 34; $i++) { $d = $donneesEvt; $d['titre'] = 'Titre ' . $i; $p = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, (int) $p['id']), $evt, '4:5', $d, [], null, 'manuel', $media); }
verifier('l\'historique garde les ' . STUDIO_VERSIONS_MAX . ' dernières versions', count(studioHistorique($dtb, (int) $p['id'])), STUDIO_VERSIONS_MAX);

/* --- mise en page libre : bornée, enregistrée, versionnée, et partie du contenu valide */
$mep = studioMiseEnPage($evt, ['4:5' => ['titre' => ['dx' => 1e9, 'dy' => '12.34', 'k' => 99, 'l' => 5, 'zz' => 1], 'inconnu' => ['dx' => 1], 'lieu' => ['dx' => 'abc']], '9:99' => ['titre' => ['dx' => 1]], 'A4' => 'x']);
verifier('mise en page bornée, arrondie, inconnus ignorés', $mep, ['4:5' => ['titre' => ['dx' => 3000.0, 'dy' => 12.3, 'l' => 20.0, 'k' => 4.0]]]);
verifier('mise en page absurde : vide', studioMiseEnPage($evt, 'x'), []);
$m1 = studioVisuelEnregistrer($dtb, null, $evt, '4:5', $donneesEvt, [], null, 'manuel', $media, ['4:5' => ['titre' => ['dx' => 40, 'dy' => 20]]]);
$ids[] = $m1['id'];
verifier('mise en page enregistrée', $m1['mise_en_page'], ['4:5' => ['titre' => ['dx' => 40.0, 'dy' => 20.0]]]);
verifier('sans mise en page, la signature d\'avant est la même', studioSignature('evt', $donneesEvt, []), studioSignature('evt', $donneesEvt, [], []));
verifier('avec, la signature change', studioSignature('evt', $donneesEvt, [], $m1['mise_en_page']) !== studioSignature('evt', $donneesEvt, []), true);
$m2 = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, (int) $m1['id']), $evt, '4:5', $donneesEvt, [], null, 'manuel', $media, ['4:5' => ['titre' => ['dx' => 80, 'dy' => 20]]]);
verifier('déplacer un élément = une version de plus', count(studioHistorique($dtb, (int) $m1['id'])), 2);
$back = studioRestaurer($dtb, studioVisuelLire($dtb, (int) $m1['id']), 1, $media);
verifier('restaurer rend la position d\'avant', $back['mise_en_page']['4:5']['titre']['dx'], 40.0);
$cp = studioVisuelDupliquer($dtb, studioVisuelLire($dtb, (int) $m1['id']), $autre);
$ids[] = $cp['id'];
verifier('la copie garde la mise en page', $cp['mise_en_page'], $back['mise_en_page']);
$sans = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, (int) $m1['id']), $evt, '4:5', $donneesEvt, [], null, 'auto', $media);
verifier('enregistrer sans la passer l\'efface (le client la renvoie toujours)', $sans['mise_en_page'], []);

/* --- blocs ajoutés : contrôlés, enregistrés avec leur image, versionnés, copiés, partie du contenu valide */
$brut = [
	['id' => 'b1', 'type' => 'texte', 'texte' => "Bonjour <b>x</b>\n\n\nsuite", 'taille' => 99999, 'graisse' => 123, 'couleur' => 'rouge;background:red', 'align' => 'justify', 'maj' => 1, 'geo' => ['4:5' => ['x' => 1e9, 'y' => 'abc', 'l' => -3, 'h' => 5], '9:99' => ['x' => 1]]],
	['id' => 'b2', 'type' => 'forme', 'forme' => 'cercle', 'couleur' => 'goldLight', 'opacite' => 7, 'rayon' => -4, 'geo' => ['4:5' => ['x' => 10, 'y' => 20, 'l' => 100, 'h' => 100]]],
	['id' => 'b3', 'type' => 'image', 'geo' => ['4:5' => ['x' => 0, 'y' => 0, 'l' => 120, 'h' => 80]]],
	['id' => 'b1', 'type' => 'texte', 'texte' => 'doublon'], ['id' => '../x', 'type' => 'texte'], ['id' => 'b9', 'type' => 'script'], 'x',
];
$bl = studioBlocs($evt, $brut);
verifier('blocs : doublon, identifiant piégé, type inconnu ignorés', array_column($bl, 'id'), ['b1', 'b2', 'b3']);
verifier('texte : bornes, graisse, couleur de la charte, alignement, retours multiples réduits', [$bl[0]['taille'], $bl[0]['graisse'], $bl[0]['couleur'], $bl[0]['align'], $bl[0]['maj'], $bl[0]['texte']], [600, 600, 'navy', 'left', true, "Bonjour <b>x</b>\nsuite"]);
verifier('position bornée, format inconnu ignoré', $bl[0]['geo'], ['4:5' => ['x' => 6000.0, 'y' => 0.0, 'l' => 4.0, 'h' => 5.0]]);
verifier('forme : opacité et rayon bornés', [$bl[1]['forme'], $bl[1]['opacite'], $bl[1]['rayon']], ['cercle', 1.0, 0]);
verifier('blocs absurdes : vide', studioBlocs($evt, 'x'), []);
verifier('30 blocs au plus', count(studioBlocs($evt, array_map(static fn ($i) => ['id' => 'b' . $i, 'type' => 'forme'], range(1, 45)))), STUDIO_BLOCS_MAX);
verifier('un bloc image = un emplacement « libre<id> »', array_column(studioEmplacements($evt, $bl), 'id'), ['image', 'libreb3']);

$png2 = tempnam(sys_get_temp_dir(), 'stb') . '.png';
$im2 = imagecreatetruecolor(30, 20);
imagepng($im2, $png2);
$prep2 = studioPreparerImage($png2);
@unlink($png2);
$e1b = studioVisuelEnregistrer($dtb, null, $evt, '4:5', $donneesEvt, ['libreb3' => ['nouveau' => $prep2, 'alt' => 'Un bloc image', 'ajustement' => 'remplir']], null, 'manuel', $media, [], $bl);
$ids[] = $e1b['id'];
verifier('blocs enregistrés (3), image du bloc rangée avec son texte alternatif', [count($e1b['blocs']), studioNomImageValide($e1b['images']['libreb3']['fichier'] ?? null), $e1b['images']['libreb3']['alt'] ?? null], [3, true, 'Un bloc image']);
verifier('blocs : signature avec / sans', studioSignature('evt', $donneesEvt, [], [], $e1b['blocs']) !== studioSignature('evt', $donneesEvt, []), true);
verifier('sans blocs ni mise en page, la signature d\'avant est la même', studioSignature('evt', $donneesEvt, [], [], []), studioSignature('evt', $donneesEvt, []));
$cb = studioVisuelDupliquer($dtb, studioVisuelLire($dtb, (int) $e1b['id']), $autre);
$ids[] = $cb['id'];
verifier('la copie garde les blocs et l\'image du bloc', [count($cb['blocs']), is_file(studioImagesDossier((int) $cb['id']) . '/' . $e1b['images']['libreb3']['fichier'])], [3, true]);
$e2b = studioVisuelEnregistrer($dtb, studioVisuelLire($dtb, (int) $e1b['id']), $evt, '4:5', $donneesEvt, [], null, 'manuel', $media, [], array_slice($bl, 0, 2));
verifier('un bloc image retiré : son image est nettoyée du visuel', [count($e2b['blocs']), isset($e2b['images']['libreb3'])], [2, false]);
$h2 = studioHistorique($dtb, (int) $e1b['id']);
verifier('l\'historique garde l\'état avec le bloc image', count($h2) >= 2, true);
$r2 = studioRestaurer($dtb, studioVisuelLire($dtb, (int) $e1b['id']), $h2[count($h2) - 1]['numero'], $media);
verifier('restaurer rend les 3 blocs et leur image', [count($r2['blocs']), isset($r2['images']['libreb3']), is_file(studioImagesDossier((int) $e1b['id']) . '/' . ($r2['images']['libreb3']['fichier'] ?? 'x'))], [3, true, true]);

echo $echecs === 0 ? "OK : $total vérifications.\n" : "$echecs échec(s) sur $total.\n";
exit($echecs === 0 ? 0 : 1);
