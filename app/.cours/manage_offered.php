<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
require_once(__DIR__ . '/../../data/cours.sigle.php');
exigerConnexion(); refuserEnseignant(); // l enseignant ne modifie aucune fiche
require('../../data/backdb.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger middleware et vérifier l'authentification AVANT la sortie HTML
require('../../data/middleware.php');
initMiddleware($dtb);

// Vérifier les permissions AVANT d'inclure head.php (qui produit du HTML)
if (!isset($rg_user) || !in_array((int)($rg_user['level'] ?? 99), [1, 3], true)) {
    // Calculer app_base pour la redirection
    $_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    $_app_base = substr($_app_root, strlen($_doc_root));
    if ($_app_base === false || $_app_base === '/' || $_app_base === '.') $_app_base = '';
    
    header('Location: ' . $_app_base . '/settings');
    exit;
}

// Charger le layout global et les variables du projet (après contrôle d'accès)
require('../../init/head.php');

$sessions = ['Premier semestre', 'Semestre d\'été', 'Deuxième semestre', 'Semestre d\'hiver'];

$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : null;
if (!$academic_year) {
    $mois = date('m');
    $z = intval($mois) < 9 ? date('Y') : date('Y') + 1;
    $academic_year = ($z - 1) . ' - ' . $z;
}

coursSigleAliasInstaller($dtb);
$courses = $dtb->query('SELECT id, Sigle, sigle_affiche, title, dep_desc, yearlevel, semester FROM t_2023_cours ORDER BY dep_desc, yearlevel, title')->fetchAll();

$stmt = $dtb->prepare('SELECT course_id, session_label, offered FROM t_2023_course_sessions WHERE academic_year = :ay');
$stmt->execute(['ay' => $academic_year]);
$existing = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing[$r['course_id']][$r['session_label']] = $r['offered'];
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gestion des cours par session</title>
    <style>
        html, body {
            overflow: hidden;
            height: 100vh;
            max-height: 100vh;
        }

        @media (max-width: 1023px) {
            html, body {
                overflow-x: hidden;
                overflow-y: auto;
                height: auto;
                max-height: none;
            }
        }

        .manage-offered-shell {
            height: calc(100vh - 56px);
        }

        .manage-offered-scroll {
            overflow: auto;
            max-height: calc(100vh - 178px);
        }

        .manage-offered-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
        }
    </style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
    <div class="h-screen w-full <?=$bg_three_color?>">
        <?php require('../../init/topbar.php'); ?>

        <div class="w-full flex flex-col lg:flex-row">
            <?php require('../../init/menubar.php'); ?>

            <div class="w-full lg:w-10/12 flex flex-col manage-offered-shell">
                <div class="back flex-1 overflow-y-auto">
                    <div class="p-4 text-white">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between mb-4">
                            <div>
                                <h2 class="text-xl lg:text-2xl font-semibold">Gestion des cours offerts</h2>
                                <p class="text-slate-300 text-sm">Cochez uniquement les cours réellement offerts pour la session et l'année choisies.</p>
                            </div>

                            <form method="get" class="flex items-center gap-2">
                                <label for="academic_year" class="whitespace-nowrap">Année scolaire</label>
                                <select id="academic_year" name="academic_year" onchange="this.form.submit()" class="input text-black min-w-[180px]">
                                    <?php
                                    $curYear = (int)date('Y');
                                    for ($i = 0; $i < 6; $i++) {
                                        $Y = $curYear - $i;
                                        $opt = ($Y - 1) . ' - ' . $Y;
                                        echo '<option value="' . htmlspecialchars($opt) . '"' . ($opt === $academic_year ? ' selected' : '') . '>' . htmlspecialchars($opt) . '</option>';
                                    }
                                    ?>
                                </select>
                            </form>
                        </div>

                        <form method="post" action="save_offered.php">
                            <input type="hidden" name="academic_year" value="<?=$academic_year?>">

                            <div class="manage-offered-scroll rounded-md border border-slate-700 <?=$bg_two_color?>">
                                <table class="simpleTbl manage-offered-table w-full text-white">
                                    <thead class="<?=$bg_one_color?>">
                                        <tr>
                                            <th class="w-28">Sigle</th>
                                            <th>Titre</th>
                                            <th class="w-20">Dep</th>
                                            <th class="w-16">Niv.</th>
                                            <?php foreach ($sessions as $s) : ?>
                                                <th class="text-center min-w-[120px]"><?=htmlspecialchars($s)?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="<?=$bg_four_color?>">
                                        <?php foreach ($courses as $c): ?>
                                            <tr class="hover:<?=$bg_five_color?> hover:text-black transition-all">
                                                <td class="font-semibold"><?=htmlspecialchars(coursSigleAffiche($c))?></td>
                                                <td><?=htmlspecialchars($c['title'])?></td>
                                                <td><?=htmlspecialchars($c['dep_desc'])?></td>
                                                <td><?=htmlspecialchars($c['yearlevel'])?></td>
                                                <?php foreach ($sessions as $s):
                                                    $checked = (!empty($existing[$c['id']][$s]) && (int)$existing[$c['id']][$s] === 1) ? 'checked' : '';
                                                ?>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="offered[<?=$c['id']?>][<?=htmlspecialchars($s)?>]" value="1" <?=$checked?>>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex items-center justify-between gap-3 mt-4">
                                <p class="text-slate-300 text-sm">Les cours non cochés ne s'afficheront pas dans l'ajout de cours étudiant.</p>
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-md transition-all">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php require('../../init/footer.php'); ?>
            </div>
        </div>
    </div>
</body>
</html>
