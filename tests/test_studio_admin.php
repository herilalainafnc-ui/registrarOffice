<?php
/* Tests de data/studio.admin.php (Studio UAZ, étape 6) : réglages, listes, validateurs, médiathèque.
   Sur la base locale (migration data/migration_studio_admin.sql) ; ce que le test crée est effacé à la fin.
   php tests/test_studio_admin.php */

require __DIR__ . '/../data/backdb.php';
require __DIR__ . '/../data/studio.admin.php';

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
$super = (int) $dtb->query('SELECT id FROM compt_utilisateur WHERE level = 1 AND etat = 1 ORDER BY id LIMIT 1')->fetchColumn();
$etudiant = (int) $dtb->query('SELECT id FROM compt_utilisateur WHERE level = 8 AND etat = 1 ORDER BY id LIMIT 1')->fetchColumn();
$avant = [];
foreach ($dtb->query('SELECT cle, valeur FROM t_studio_reglage')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) { $avant[$k] = $v; }
$mediaIds = [];
$nettoyer = function () use ($dtb, $avant, &$mediaIds) {
    $dtb->exec('DELETE FROM t_studio_reglage');
    foreach ($avant as $k => $v) { $dtb->prepare('INSERT INTO t_studio_reglage (cle, valeur, modifie_par, modifie_le) VALUES (?, ?, 0, NOW())')->execute([$k, $v]); }
    foreach ($mediaIds as $id) { studioMediaSupprimer($dtb, $id); $dtb->prepare('DELETE FROM t_studio_media WHERE id = ?')->execute([$id]); }
    $dtb->exec("DELETE FROM t_studio_role WHERE cree_par = -1");
};
register_shutdown_function($nettoyer);

/* --- réglages : nettoyés */
$presets = array_keys(studioPresets());
$reg = studioReglagesNettoyer([
    'inactifs' => ['ann', 'inconnu', 'ann', 7],
    'defauts' => ['com' => ['service' => '  Service  de   test ', 'inconnu' => 'x', 'titre' => str_repeat('a', 500)], 'inconnu' => ['x' => 'y'], 'ins' => ['filieres' => ['a', 'b'], 'anneeUniversitaire' => '2026-2027']],
    'listes' => ['services' => ['Registrariat', ' registrariat ', '', 'Media', str_repeat('z', 200)], 'annees' => ['2026 - 2027'], 'filieres' => 'x', 'autre' => ['y']],
]);
verifier('types inactifs : connus, sans doublon', $reg['inactifs'], ['ann']);
verifier('valeurs par défaut : espaces réduits, champs inconnus ignorés, jamais une liste', [$reg['defauts']['com']['service'], isset($reg['defauts']['com']['inconnu']), isset($reg['defauts']['inconnu']), isset($reg['defauts']['ins']['filieres']), $reg['defauts']['ins']['anneeUniversitaire']], ['Service de test', false, false, false, '2026-2027']);
verifier('valeur par défaut coupée à la longueur du champ', mb_strlen($reg['defauts']['com']['titre']), 80);
verifier('listes : doublons (casse) et lignes vides ôtés, 80 caractères', [$reg['listes']['services'][0], $reg['listes']['services'][1], count($reg['listes']['services']), mb_strlen($reg['listes']['services'][2])], ['Registrariat', 'Media', 3, 80]);
verifier('liste invalide : vide', $reg['listes']['filieres'], []);
$tous = studioReglagesNettoyer(['inactifs' => $presets]);
verifier('au moins un type reste actif', count($tous['inactifs']), count($presets) - 1);
verifier('réglages absurdes : valeurs d\'origine', studioReglagesNettoyer('x'), ['inactifs' => [], 'defauts' => [], 'listes' => ['services' => [], 'annees' => [], 'filieres' => []]]);

/* --- enregistrés, relus, types actifs */
studioReglagesSauver($dtb, $reg, $super);
$lu = studioReglages($dtb);
verifier('réglages relus', [$lu['inactifs'], $lu['listes']['services']], [['ann'], ['Registrariat', 'Media', str_repeat('z', 80)]]);
verifier('type désactivé : plus créable', [studioPresetActif($lu, 'ann'), studioPresetActif($lu, 'evt')], [false, true]);
studioReglagesSauver($dtb, studioReglagesNettoyer(['listes' => ['services' => ['Registrariat']]]), $super);
verifier('réécriture : rien d\'ancien ne reste', studioReglages($dtb)['inactifs'], []);

/* --- service émetteur : dans la liste, quand elle existe */
$com = studioPreset('com');
verifier('service hors liste : refusé', studioServicesErreurs($com, ['service' => 'Cuisine'], ['Registrariat']), ['« Service émetteur » : choisissez un service de la liste.']);
verifier('service dans la liste : accepté', studioServicesErreurs($com, ['service' => 'Registrariat'], ['Registrariat']), []);
verifier('pas de liste : saisie libre', studioServicesErreurs($com, ['service' => 'Cuisine'], []), []);
verifier('service vide : pas de reproche (c\'est « à remplir »)', studioServicesErreurs($com, ['service' => ''], ['Registrariat']), []);
$evt = studioPreset('evt');
verifier('EVT : l\'organisateur est un service émetteur aussi', count(studioServicesErreurs($evt, ['organisateur' => 'X'], ['Registrariat'])), 1);
$jeu = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/com-test.json'), true)['donnees'];
verifier('contenu complet : un service hors liste le refuse', count(array_filter(studioContenuErreurs($com, $jeu, [], [], ['Registrariat']), static fn ($e) => str_contains($e, 'service de la liste'))), 1);

/* --- validateurs */
verifier('étudiant : ne peut pas valider', lance(fn () => studioValidateurAjouter($dtb, $etudiant, -1)), 'Un compte étudiant ne peut pas valider.');
verifier('compte inconnu', lance(fn () => studioValidateurAjouter($dtb, 99999999, -1)), 'Ce compte n\'existe pas ou est désactivé.');
studioValidateurAjouter($dtb, $media, -1);
studioValidateurAjouter($dtb, $media, -1);
verifier('un validateur désigné (une seule ligne)', count(array_filter(studioValidateurs($dtb), static fn ($v) => (int) $v['user_id'] === $media)), 1);
verifier('il valide (rôle)', studioRoles($dtb, $media, 5)['validateur'], true);
$prenom = (string) $dtb->query('SELECT nom FROM compt_utilisateur WHERE id = ' . $media)->fetchColumn();
verifier('la recherche ne repropose pas un validateur', in_array($media, array_column(studioComptesRecherche($dtb, mb_substr($prenom, 0, 3)), 'id')), false);
studioValidateurRetirer($dtb, $media);
verifier('retiré : il ne valide plus', studioRoles($dtb, $media, 5)['validateur'], false);
verifier('recherche : retrouvé, jamais un étudiant ni le superadmin', [in_array($media, array_column(studioComptesRecherche($dtb, mb_substr($prenom, 0, 3)), 'id')), in_array($etudiant, array_column(studioComptesRecherche($dtb, mb_substr((string) $dtb->query('SELECT nom FROM compt_utilisateur WHERE id = ' . $etudiant)->fetchColumn(), 0, 3)), 'id')), in_array($super, array_column(studioComptesRecherche($dtb, mb_substr((string) $dtb->query('SELECT nom FROM compt_utilisateur WHERE id = ' . $super)->fetchColumn(), 0, 3)), 'id'))], [true, false, false]);
verifier('recherche trop courte : rien', studioComptesRecherche($dtb, 'a'), []);

/* --- médiathèque */
$png = tempnam(sys_get_temp_dir(), 'stm') . '.png';
$im = imagecreatetruecolor(40, 30);
imagepng($im, $png);
$prep = studioPreparerImage($png);
@unlink($png);
verifier('titre obligatoire', lance(fn () => studioMediaAjouter($dtb, $prep, ' ', 'alt', $super)), 'Donnez un titre à l\'image.');
verifier('description obligatoire', str_contains(lance(fn () => studioMediaAjouter($dtb, $prep, 'Titre', '', $super)), 'Décrivez'), true);
$m = studioMediaAjouter($dtb, $prep, 'Le campus', 'Vue du campus au lever du soleil', $super);
$mediaIds[] = $m['id'];
$lue = studioMediaLire($dtb, $m['id']);
verifier('image rangée, relue, avec son texte alternatif', [is_file($lue['chemin']), $lue['alt'], $lue['type']], [true, 'Vue du campus au lever du soleil', 'image/jpeg']);
verifier('dans la liste', in_array($m['id'], array_column(studioMediaListe($dtb), 'id')), true);
verifier('nom de fichier piégé : introuvable', studioMediaLire($dtb, 987654), null);
$m2 = studioMediaAjouter($dtb, $prep, 'Le même fichier', 'La même image', $super);
$mediaIds[] = $m2['id'];
studioMediaSupprimer($dtb, $m['id']);
verifier('supprimée : plus lisible', studioMediaLire($dtb, $m['id']), null);
verifier('le fichier reste tant qu\'une autre ligne le cite', is_file($lue['chemin']), true);
studioMediaSupprimer($dtb, $m2['id']);
verifier('plus citée : le fichier part', is_file($lue['chemin']), false);

/* --- images déjà utilisées */
$evtP = studioPreset('evt');
$jeuEvt = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/evt-trs.json'), true)['donnees'];
$png2 = tempnam(sys_get_temp_dir(), 'stu') . '.png';
imagepng(imagecreatetruecolor(20, 20), $png2);
$v = studioVisuelEnregistrer($dtb, null, $evtP, '4:5', $jeuEvt, ['image' => ['nouveau' => studioPreparerImage($png2), 'alt' => 'Déjà utilisée']], null, 'manuel', $media);
@unlink($png2);
$u = studioImagesUtilisees($dtb, $media);
verifier('image déjà utilisée : retrouvée avec son texte', [count(array_filter($u, static fn ($x) => $x['visuel'] === (int) $v['id'] && $x['alt'] === 'Déjà utilisée'))], [1]);
verifier('… jamais celles d\'un autre compte', count(array_filter(studioImagesUtilisees($dtb, $super), static fn ($x) => $x['visuel'] === (int) $v['id'])), 0);
$dtb->prepare('DELETE FROM t_studio_version WHERE visuel_id = ?')->execute([$v['id']]);
$dtb->prepare('DELETE FROM t_studio_visuel WHERE id = ?')->execute([$v['id']]);
foreach (glob(studioImagesDossier((int) $v['id']) . '/*') ?: [] as $f) { @unlink($f); }
@rmdir(studioImagesDossier((int) $v['id']));

echo $echecs === 0 ? "OK : $total vérifications.\n" : "$echecs échec(s) sur $total.\n";
exit($echecs === 0 ? 0 : 1);
