<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php');?>
    <title>Espace Enseignant</title>
    <style>
        /* Animation d'entrée slide-in */
        body {
            animation: slideInFromRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
// Vérifier que l'utilisateur est connecté et est un professeur
require('../data/middleware.php');
initMiddleware($dtb);

// Autoriser l'accès aux admins, registrars ET professeurs
if (!isTeacher() && !isAdmin() && !isRegistrar()) {
    header('Location: ./index.php');
    exit;
}

// Récupérer les informations du professeur
$teacherUid = getTeacherUid();
$teacherInfo = null;

if ($teacherUid) {
    $stmt = $dtb->prepare("SELECT * FROM teacher WHERE uid = :uid");
    $stmt->execute(['uid' => $teacherUid]);
    $teacherInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer l'année scolaire courante
$currentYear = date('m') >= 7 ? date('Y').' - '.(date('Y')+1) : (date('Y')-1).' - '.date('Y');

// Récupérer les cours du professeur
$courses = getTeacherCourses();

// Récupérer les étudiants du professeur
$students = getTeacherStudents($currentYear);
?>

<div class="h-screen w-full <?=$bg_three_color?>">
    
    <!-- TOP BAR --><?php require('../init/topbar.php');?>

    <div class="w-full flex flex-col lg:flex-row">
        
        <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            
            <div class="w-full px-4 py-4 flex-1 overflow-auto">
                
                <!-- En-tête -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-white mb-2">
                        <i class="bi bi-person-workspace mr-2"></i>
                        Espace Enseignant
                    </h1>
                    <?php if ($teacherInfo): ?>
                    <p class="text-slate-400">
                        Bienvenue, <?= htmlspecialchars($teacherInfo['name'].' '.$teacherInfo['lastName']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    
                    <!-- Mes Cours -->
                    <div class="<?=$bg_one_color?> rounded-lg p-4">
                        <h2 class="text-lg font-bold text-white mb-4">
                            <i class="bi bi-book mr-2"></i>
                            Mes Cours (<?= count($courses) ?>)
                        </h2>
                        
                        <div class="max-h-80 overflow-auto">
                            <?php if (empty($courses)): ?>
                                <p class="text-slate-400 text-center py-4">Aucun cours assigné</p>
                            <?php else: ?>
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-600 text-white">
                                        <tr>
                                            <th class="p-2 text-left">Sigle</th>
                                            <th class="p-2 text-left">Cours</th>
                                            <th class="p-2 text-center">Crédit</th>
                                            <th class="p-2 text-center">Sem.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                        <tr class="hover:bg-slate-600 border-b border-slate-600">
                                            <td class="p-2 text-white"><?= htmlspecialchars($course['Sigle']) ?></td>
                                            <td class="p-2 text-white">
                                                <a href="./cours.php?id=<?= $course['id'] ?>" class="hover:text-cyan-400">
                                                    <?= htmlspecialchars($course['title']) ?>
                                                </a>
                                            </td>
                                            <td class="p-2 text-center text-white"><?= $course['nb_crd'] ?></td>
                                            <td class="p-2 text-center text-white"><?= $course['semester'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Mes Étudiants -->
                    <div class="<?=$bg_one_color?> rounded-lg p-4">
                        <h2 class="text-lg font-bold text-white mb-4">
                            <i class="bi bi-people mr-2"></i>
                            Mes Étudiants (<?= count($students) ?>)
                        </h2>
                        
                        <div class="max-h-80 overflow-auto">
                            <?php if (empty($students)): ?>
                                <p class="text-slate-400 text-center py-4">Aucun étudiant inscrit à vos cours</p>
                            <?php else: ?>
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-600 text-white">
                                        <tr>
                                            <th class="p-2 text-left">Matricule</th>
                                            <th class="p-2 text-left">Nom</th>
                                            <th class="p-2 text-left">Mention</th>
                                            <th class="p-2 text-center">Niveau</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $displayedStudents = array_slice($students, 0, 50); // Limiter à 50
                                        foreach ($displayedStudents as $student): 
                                        ?>
                                        <tr class="hover:bg-slate-600 border-b border-slate-600">
                                            <td class="p-2 text-white"><?= htmlspecialchars($student['student_id']) ?></td>
                                            <td class="p-2 text-white">
                                                <a href="./student.php?id=<?= urlencode($student['student_id']) ?>" class="hover:text-cyan-400">
                                                    <?= htmlspecialchars($student['student_nom'].' '.$student['student_prenom']) ?>
                                                </a>
                                            </td>
                                            <td class="p-2 text-white text-xs"><?= htmlspecialchars($student['etude_envisage']) ?></td>
                                            <td class="p-2 text-center text-white">L<?= $student['annee_etude'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($students) > 50): ?>
                                        <tr>
                                            <td colspan="4" class="p-2 text-center text-slate-400">
                                                ... et <?= count($students) - 50 ?> autres étudiants
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Actions rapides -->
                <div class="mt-6 <?=$bg_one_color?> rounded-lg p-4">
                    <h2 class="text-lg font-bold text-white mb-4">
                        <i class="bi bi-lightning mr-2"></i>
                        Actions Rapides
                    </h2>
                    <div class="flex flex-wrap gap-3">
                        <a href="./accueil.cours.php" class="bg-cyan-700 hover:bg-cyan-600 text-white px-4 py-2 rounded-lg">
                            <i class="bi bi-list-ul mr-2"></i>Voir tous mes cours
                        </a>
                        <a href="./accueil" class="bg-slate-600 hover:bg-slate-500 text-white px-4 py-2 rounded-lg">
                            <i class="bi bi-people mr-2"></i>Voir mes étudiants
                        </a>
                    </div>
                </div>

            </div>
            
        </div>
    </div>
</div>

</body>
</html>
