<?php
/**
 * DEBUG TEMPORAIRE - Diagnostic connexion étudiant
 * À SUPPRIMER après résolution du problème
 */

require('../data/backdb.php');
require('../data/middleware.php');
initMiddleware($dtb);

header('Content-Type: text/html; charset=utf-8');

if (!isLoggedIn()) {
    die('Non connecté. <a href="./index">Se connecter</a>');
}

$user = Middleware::getCurrentUser();

echo '<h2>🔍 Diagnostic Espace Étudiant</h2>';
echo '<hr>';

// 1. Infos du compte utilisateur
echo '<h3>1. Compte utilisateur (compt_utilisateur)</h3>';
echo '<table border="1" cellpadding="5" style="border-collapse:collapse;">';
$fields = ['id', 'nom', 'prenom', 'pseudo', 'level', 'privilege', 'user_type', 'student_id', 'teacher_uid', 'etat'];
foreach ($fields as $f) {
    $val = $user[$f] ?? '<span style="color:red;">COLONNE ABSENTE</span>';
    echo "<tr><td><b>$f</b></td><td>$val</td></tr>";
}
echo '</table>';

// 2. Vérification des colonnes
echo '<h3>2. Colonnes de la table compt_utilisateur</h3>';
try {
    $cols = $dtb->query("SHOW COLUMNS FROM compt_utilisateur")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');
    $needed = ['user_type', 'student_id', 'teacher_uid'];
    foreach ($needed as $c) {
        $exists = in_array($c, $colNames);
        echo ($exists ? '✅' : '❌') . " Colonne <b>$c</b> " . ($exists ? 'existe' : '<b>MANQUANTE</b>') . '<br>';
    }
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage();
}

// 3. Détection du rôle
echo '<h3>3. Détection du rôle</h3>';
echo 'isStudent(): ' . (isStudent() ? '✅ OUI' : '❌ NON') . '<br>';
echo 'isTeacher(): ' . (isTeacher() ? '✅ OUI' : '❌ NON') . '<br>';
echo 'isAdmin(): ' . (isAdmin() ? '✅ OUI' : '❌ NON') . '<br>';
echo 'isRegistrar(): ' . (isRegistrar() ? '✅ OUI' : '❌ NON') . '<br>';

// 4. Recherche du student_id
echo '<h3>4. Recherche de student_id</h3>';
$studentId = getStudentId();
echo 'getStudentId(): <b>' . ($studentId ?: '<span style="color:red;">NULL</span>') . '</b><br>';

// 4b. Fallback direct
echo '<h3>4b. Recherche manuelle dans tbl_2024_etudiant</h3>';
$nom = $user['nom'] ?? '';
$prenom = $user['prenom'] ?? '';
$pseudo = $user['pseudo'] ?? '';

echo "Recherche par nom='$nom', prenom='$prenom'...<br>";
try {
    $stmt = $dtb->prepare("SELECT id, student_id, student_nom, student_prenom, image_student, annee_scolaire 
                           FROM tbl_2024_etudiant 
                           WHERE student_nom = :nom AND student_prenom = :prenom 
                           ORDER BY annee_scolaire DESC LIMIT 3");
    $stmt->execute(['nom' => $nom, 'prenom' => $prenom]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($results) {
        echo '<table border="1" cellpadding="5" style="border-collapse:collapse;">';
        echo '<tr><th>id</th><th>student_id</th><th>student_nom</th><th>student_prenom</th><th>image_student</th><th>annee_scolaire</th></tr>';
        foreach ($results as $r) {
            echo '<tr>';
            foreach ($r as $v) echo "<td>" . htmlspecialchars($v ?? '') . "</td>";
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<span style="color:orange;">Aucun résultat par nom/prenom exact.</span><br>';
        
        // Essayer LIKE
        echo "Recherche LIKE nom='%$nom%', prenom='%$prenom%'...<br>";
        $stmt = $dtb->prepare("SELECT id, student_id, student_nom, student_prenom, image_student 
                               FROM tbl_2024_etudiant 
                               WHERE student_nom LIKE :nom OR student_prenom LIKE :prenom 
                               ORDER BY annee_scolaire DESC LIMIT 5");
        $stmt->execute(['nom' => "%$nom%", 'prenom' => "%$prenom%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            echo '<table border="1" cellpadding="5" style="border-collapse:collapse;">';
            echo '<tr><th>id</th><th>student_id</th><th>student_nom</th><th>student_prenom</th><th>image_student</th></tr>';
            foreach ($results as $r) {
                echo '<tr>';
                foreach ($r as $v) echo "<td>" . htmlspecialchars($v ?? '') . "</td>";
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<span style="color:red;">Aucun résultat LIKE non plus.</span><br>';
        }
    }
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage();
}

echo "Recherche par pseudo='$pseudo' (= student_id?)...<br>";
try {
    $stmt = $dtb->prepare("SELECT id, student_id, student_nom, student_prenom, image_student 
                           FROM tbl_2024_etudiant WHERE student_id = :pseudo LIMIT 1");
    $stmt->execute(['pseudo' => $pseudo]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        echo '✅ Trouvé: student_id=' . $r['student_id'] . ', nom=' . $r['student_nom'] . '<br>';
    } else {
        echo '<span style="color:orange;">Non trouvé par pseudo.</span><br>';
    }
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage();
}

// 5. getStudentInfo
echo '<h3>5. getStudentInfo()</h3>';
$info = getStudentInfo();
if ($info) {
    echo '✅ Info trouvée:<br>';
    echo 'student_id: ' . ($info['student_id'] ?? 'N/A') . '<br>';
    echo 'student_nom: ' . ($info['student_nom'] ?? 'N/A') . '<br>';
    echo 'student_prenom: ' . ($info['student_prenom'] ?? 'N/A') . '<br>';
    echo 'image_student: <b>' . ($info['image_student'] ?? '<span style="color:red;">VIDE</span>') . '</b><br>';
    if (!empty($info['image_student'])) {
        $photoUrl = '/app/photosetudiants/' . $info['image_student'];
        echo 'Photo URL: <a href="' . $photoUrl . '" target="_blank">' . $photoUrl . '</a><br>';
        echo '<img src="' . $photoUrl . '" width="100" onerror="this.alt=\'❌ Image introuvable à cette URL\'"><br>';
        
        // Tester aussi avec app_base
        $_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        $_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
        $app_base = substr($_app_root, strlen($_doc_root));
        if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
        
        $photoUrl2 = $app_base . '/app/photosetudiants/' . $info['image_student'];
        echo 'Photo URL (avec app_base="' . $app_base . '"): <a href="' . $photoUrl2 . '" target="_blank">' . $photoUrl2 . '</a><br>';
        echo '<img src="' . $photoUrl2 . '" width="100" onerror="this.alt=\'❌ Image introuvable à cette URL\'"><br>';
    }
} else {
    echo '<span style="color:red; font-size:18px;">❌ getStudentInfo() retourne NULL — C\'est LE problème</span><br>';
    echo 'Causes possibles:<br>';
    echo '- isStudent() retourne false → le rôle n\'est pas détecté<br>';
    echo '- getStudentId() retourne null → pas de liaison entre le compte et l\'étudiant<br>';
    echo '- La colonne student_id n\'existe pas dans compt_utilisateur ET le nom/prenom ne matchent pas<br>';
}

// 6. Session
echo '<h3>6. Données en session</h3>';
echo '<pre>';
$safe = $_SESSION;
unset($safe['infinit_password']); // Ne pas afficher le mot de passe
print_r($safe);
echo '</pre>';

echo '<hr><p style="color:gray;">🗑️ Supprimer ce fichier après diagnostic: src/debug.student.php</p>';
?>
