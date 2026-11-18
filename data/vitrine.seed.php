<?php
/**
 * Installation et mise a jour des reglages de la vitrine.
 *
 * A relancer apres chaque ajout de champ dans la liste ci-dessous : les
 * libelles, groupes et types sont reecrits, les VALEURS deja saisies par
 * l'administration sont preservees (ON DUPLICATE KEY ne touche pas `valeur`).
 *
 *   php data/vitrine.seed.php
 */

chdir(__DIR__ . '/..');
require __DIR__ . '/config.php';

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// --- Table -----------------------------------------------------------------
$sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/migration_vitrine_config.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) {
    try { $pdo->exec($q); }
    catch (Throwable $e) {
        if (stripos($e->getMessage(), 'exists') === false) {
            echo "  ECHEC : " . $e->getMessage() . "\n";
        }
    }
}

/* --- Champs ----------------------------------------------------------------
   [groupe, cle, libelle, type, aide, valeur par defaut]
   La valeur par defaut reprend ce que la vitrine affiche aujourd'hui : on part
   de l'existant pour que l'interface n'apparaisse jamais vide. */
$champs = [
    ['identite', 'univ.nom',      'Nom de l’université', 'texte', '', 'Université Adventiste Zurcher'],
    ['identite', 'univ.sigle',    'Sigle',               'texte', '', 'UAZ'],
    ['identite', 'univ.baseline', 'Phrase d’accroche',   'long',
        'Affichée sous le nom, sur la page d’accueil',
        'Sept mentions, un campus sur les hauts plateaux, et une communauté réunie autour de valeurs partagées.'],

    ['dates', 'date.rentree',         'Date de rentrée',           'date', '', '2026-10-13'],
    ['dates', 'date.test',            'Date du test d’admission',  'date', '', '2026-10-13'],
    ['dates', 'annee.academique',     'Année académique',          'texte', 'Ex : 2026-2027', '2026-2027'],
    ['dates', 'inscriptions.libelle', 'Bandeau d’ouverture',       'texte',
        'Affiché en haut de la page Admissions', 'Inscriptions ouvertes 2026-2027'],

    ['contact', 'contact.bp',       'Boîte postale',           'texte', '', 'BP 325'],
    ['contact', 'contact.ville',    'Ville et code postal',    'texte', '', 'Antsirabe 110'],
    ['contact', 'contact.pays',     'Pays',                    'texte', '', 'MADAGASCAR'],
    ['contact', 'contact.tel1',     'Téléphone principal',     'texte',
        'Format affiché ; le lien d’appel en est déduit automatiquement', '+261 34 38 180 81'],
    ['contact', 'contact.tel2',     'Téléphone secondaire',    'texte', '', '+261 34 76 076 98'],
    ['contact', 'contact.email',    'Adresse email',           'texte', '', 'registrar@zurcher.edu.mg'],
    ['contact', 'contact.distance', 'Distance d’Antananarivo', 'texte', '', '138 km'],

    ['reseaux', 'reseau.facebook',  'Facebook',  'url', '', 'https://www.facebook.com/uazsambaina'],
    ['reseaux', 'reseau.instagram', 'Instagram', 'url', '', 'https://www.instagram.com/adventist.university.zurcher/'],
    ['reseaux', 'reseau.linkedin',  'LinkedIn',  'url', '', 'https://mg.linkedin.com/school/zurcher/'],
    ['reseaux', 'reseau.youtube',   'YouTube',   'url', '', 'https://www.youtube.com/@uaz-universiteadventistezu6053'],

    ['accueil', 'hero.formules', 'Formules du titre animé', 'liste',
        'Une par ligne. Encadrez d’astérisques le mot à mettre en avant : Votre *futur* commence ici',
        "Université Adventiste Zurcher\nVotre *futur* commence ici\nYour *future* starts here\n"
        . "Manomboka eto ny *hoavinao*\nPréparer aujourd’hui les *leaders* de demain"],
    ['accueil', 'hero.bouton1', 'Libellé du bouton principal',  'texte', '', 'Se pré-inscrire'],
    ['accueil', 'hero.bouton2', 'Libellé du bouton secondaire', 'texte', '', 'Voir les formations'],
    ['accueil', 'hero.poster',  'Image de repli de la vidéo',   'image',
        'Affichée avant le chargement de la vidéo, et si la lecture est impossible', ''],

    ['formations', 'formations.titre',     'Titre de la section', 'texte', '', 'Nos Formations'],
    ['formations', 'formations.soustitre', 'Sous-titre',          'long',  '',
        'Sept mentions et quatorze parcours de Licence, habilités par le MESupRES.'],
    ['formations', 'img.theologie',            'Image — Théologie',            'image', '', ''],
    ['formations', 'img.gestion',              'Image — Gestion',              'image', '', ''],
    ['formations', 'img.informatique',         'Image — Informatique',         'image', '', ''],
    ['formations', 'img.sciences-infirmieres', 'Image — Sciences Infirmières', 'image', '', ''],
    ['formations', 'img.communication',        'Image — Communication',        'image', '', ''],
    ['formations', 'img.etudes-anglophones',   'Image — Études Anglophones',   'image', '', ''],
    ['formations', 'img.droit',                'Image — Droit',                'image', '', ''],

    ['campus', 'campus.titre',     'Titre de la section', 'texte', '', 'Notre Campus'],
    ['campus', 'campus.soustitre', 'Sous-titre',          'long',  '',
        'Un cadre propice à l’étude, sur les hauts plateaux malgaches.'],
    ['campus', 'campus.ouvrages',  'Nombre d’ouvrages',   'texte',
        'Affiché sur la carte Bibliothèque. Chiffre non vérifié à ce jour.', 'plus de 20 000'],

    ['admissions', 'admissions.titre',     'Titre de la section', 'texte', '', 'Rejoignez l’UAZ'],
    ['admissions', 'admissions.soustitre', 'Sous-titre',          'long',  '',
        'Commencez votre parcours académique dans une université d’excellence.'],
    ['admissions', 'admissions.credits',   'Crédits pour la Licence', 'texte',
        'Affiché sur la feuille de route du cursus', '180'],
];

$ins = $pdo->prepare(
    'INSERT INTO vitrine_config (cle, valeur, groupe, libelle, aide, type, ordre)
     VALUES (:c, :v, :g, :l, :a, :t, :o)
     ON DUPLICATE KEY UPDATE
         libelle = VALUES(libelle), aide = VALUES(aide),
         groupe  = VALUES(groupe),  type = VALUES(type), ordre = VALUES(ordre)'
);

$ordre = [];
foreach ($champs as $c) {
    list($groupe, $cle, $libelle, $type, $aide, $defaut) = $c;
    $ordre[$groupe] = ($ordre[$groupe] ?? 0) + 10;
    $ins->execute([
        ':c' => $cle, ':v' => $defaut, ':g' => $groupe, ':l' => $libelle,
        ':a' => ($aide !== '' ? $aide : null), ':t' => $type, ':o' => $ordre[$groupe],
    ]);
}

printf("  %d reglages installes ou mis a jour\n", count($champs));
foreach ($pdo->query('SELECT groupe, COUNT(*) n FROM vitrine_config GROUP BY groupe ORDER BY groupe') as $r) {
    printf("    %-12s %d\n", $r['groupe'], $r['n']);
}
