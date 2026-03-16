<?php
// Connexion centralisée pour l'API
require_once __DIR__ . '/../data/backdb.php';

// Durée de vie de la session : 10 heures
ini_set('session.gc_maxlifetime', 36000);
ini_set('session.cookie_lifetime', 36000);
session_start();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'get_cours':
        getCours();
        break;
    case 'get_salles':
        getSalles();
        break;
    case 'get_teachers':
        getTeachers();
        break;
    case 'get_parcours':
        getParcours();
        break;
    case 'check_conflicts':
        checkConflicts();
        break;
    case 'add_seance':
        addSeance();
        break;
    case 'delete_seance':
        deleteSeance();
        break;
    case 'get_seance':
        getSeance();
        break;
    case 'move_seance':
        moveSeance();
        break;
    case 'check_move_conflicts':
        checkMoveConflicts();
        break;
    case 'update_seance':
        updateSeance();
        break;
    default:
        echo json_encode(['error' => 'Action non reconnue']);
}

// Récupérer la liste des cours
function getCours() {
    global $dtb;
    $mention = $_GET['mention'] ?? '';
    $niveau = $_GET['niveau'] ?? '';
    $semester = $_GET['semester'] ?? '';
    
    $sql = "SELECT id, Sigle, title, dep_desc, parcours, yearlevel, semester, id_teacher FROM t_2023_cours WHERE remove != 1";
    if($mention) $sql .= " AND dep_desc = " . $dtb->quote($mention);
    if($niveau) $sql .= " AND yearlevel = " . intval($niveau);
    if($semester) $sql .= " AND semester = " . intval($semester);
    $sql .= " ORDER BY Sigle";
    
    $result = $dtb->query($sql);
    echo json_encode($result->fetchAll(PDO::FETCH_ASSOC));
}

// Récupérer la liste des salles
function getSalles() {
    global $dtb;
    $result = $dtb->query("SELECT * FROM t_2024_salles ORDER BY salle_code");
    echo json_encode($result->fetchAll(PDO::FETCH_ASSOC));
}

// Récupérer la liste des enseignants
function getTeachers() {
    global $dtb;
    $result = $dtb->query("SELECT uid, name, lastName FROM teacher ORDER BY name");
    echo json_encode($result->fetchAll(PDO::FETCH_ASSOC));
}

// Récupérer la liste des parcours (avec mapping mention_id vers dep_desc)
function getParcours() {
    global $dtb;
    $mention = $_GET['mention'] ?? '';
    
    // Mapping code mention (dep_desc) vers mention_id dans la table parcours
    // 1=THEO, 2=GEST, 3=INFO, 4=NURS, 5=EDUC, 6=COMM, 7=LANG, 8=DROI
    $mentionMapping = [
        'THEO' => 1,
        'GEST' => 2,
        'INFO' => 3,
        'NURS' => 4,
        'EDUC' => 5,
        'COMM' => 6,
        'LANG' => 7,
        'DROI' => 8
    ];
    
    if($mention && isset($mentionMapping[$mention])) {
        $mentionId = $mentionMapping[$mention];
        $result = $dtb->query("SELECT id, nom FROM parcours WHERE mention_id = $mentionId ORDER BY nom");
    } else {
        // Retourner tous les parcours avec le code mention
        $sql = "SELECT p.id, p.nom, p.mention_id,
                CASE p.mention_id 
                    WHEN 1 THEN 'THEO'
                    WHEN 2 THEN 'GEST'
                    WHEN 3 THEN 'INFO'
                    WHEN 4 THEN 'NURS'
                    WHEN 5 THEN 'EDUC'
                    WHEN 6 THEN 'DROI'
                    WHEN 7 THEN 'COMM'
                    WHEN 8 THEN 'LANG'
                END as mention_code
                FROM parcours p ORDER BY p.mention_id, p.nom";
        $result = $dtb->query($sql);
    }
    echo json_encode($result->fetchAll(PDO::FETCH_ASSOC));
}

// Récupérer une séance
function getSeance() {
    global $dtb;
    $id = intval($_GET['id'] ?? 0);
    $result = $dtb->query("SELECT * FROM t_2024_emploi_du_temps WHERE id = $id");
    echo json_encode($result->fetch(PDO::FETCH_ASSOC));
}

// Vérifier les conflits
function checkConflicts() {
    global $dtb;
    
    $jour = $_POST['jour_semaine'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $salle_id = intval($_POST['salle_id'] ?? 0);
    $id_teacher = intval($_POST['id_teacher'] ?? 0);
    $mention = $_POST['mention'] ?? '';
    $niveau = intval($_POST['niveau'] ?? 0);
    $parcours = $_POST['parcours'] ?? '';
    $exclude_id = intval($_POST['exclude_id'] ?? 0); // Pour édition
    $type_seance = $_POST['type_seance'] ?? 'cours'; // Type de séance
    
    $conflicts = [];
    
    // 1. Conflit de salle (sauf pour les examens - plusieurs examens peuvent avoir lieu dans la même salle)
    if($type_seance !== 'examen') {
        $sql = "SELECT e.*, s.salle_code 
                FROM t_2024_emploi_du_temps e 
                LEFT JOIN t_2024_salles s ON e.salle_id = s.id
                WHERE e.jour_semaine = :jour 
                AND e.salle_id = :salle_id
                AND e.statut = 'confirme'
                AND e.type_seance != 'examen'
                AND (
                    (e.heure_debut < :heure_fin AND e.heure_fin > :heure_debut)
                )";
        if($exclude_id > 0) $sql .= " AND e.id != $exclude_id";
        
        $stmt = $dtb->prepare($sql);
        $stmt->execute([
            ':jour' => $jour,
            ':salle_id' => $salle_id,
            ':heure_debut' => $heure_debut,
            ':heure_fin' => $heure_fin
        ]);
        $salleConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(count($salleConflicts) > 0) {
            foreach($salleConflicts as $c) {
                $conflicts[] = [
                    'type' => 'salle',
                    'severity' => 'error',
                    'message' => "Conflit de salle: {$c['salle_code']} déjà occupée par {$c['cours_sigle']} ({$c['heure_debut']} - {$c['heure_fin']})"
                ];
            }
        }
    }
    
    // 2. Conflit enseignant
    if($id_teacher > 0) {
        $sql = "SELECT e.*, t.name, t.lastName 
                FROM t_2024_emploi_du_temps e 
                LEFT JOIN teacher t ON e.id_teacher = t.uid
                WHERE e.jour_semaine = :jour 
                AND e.id_teacher = :id_teacher
                AND e.statut = 'confirme'
                AND (
                    (e.heure_debut < :heure_fin AND e.heure_fin > :heure_debut)
                )";
        if($exclude_id > 0) $sql .= " AND e.id != $exclude_id";
        
        $stmt = $dtb->prepare($sql);
        $stmt->execute([
            ':jour' => $jour,
            ':id_teacher' => $id_teacher,
            ':heure_debut' => $heure_debut,
            ':heure_fin' => $heure_fin
        ]);
        $teacherConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(count($teacherConflicts) > 0) {
            foreach($teacherConflicts as $c) {
                $conflicts[] = [
                    'type' => 'enseignant',
                    'severity' => 'error',
                    'message' => "Conflit enseignant: {$c['name']} {$c['lastName']} enseigne déjà {$c['cours_sigle']} ({$c['heure_debut']} - {$c['heure_fin']})"
                ];
            }
        }
    }
    
    // 3. Conflit étudiants (même mention/niveau/parcours au même moment)
    // Logique des parcours:
    // - Si le nouveau cours est pour TOUS (vide/null/all) → conflit avec TOUS les cours de la mention/niveau
    // - Si le nouveau cours est pour un parcours SPECIFIQUE → conflit seulement avec:
    //   * les cours pour le MÊME parcours
    //   * les cours pour TOUS les parcours (vide/null/all)
    // - Deux cours de parcours DIFFERENTS et SPECIFIQUES → PAS de conflit
    
    $sql = "SELECT e.* 
            FROM t_2024_emploi_du_temps e 
            WHERE e.jour_semaine = :jour 
            AND e.mention = :mention
            AND e.niveau = :niveau
            AND e.statut = 'confirme'
            AND (e.heure_debut < :heure_fin AND e.heure_fin > :heure_debut)";
    if($exclude_id > 0) $sql .= " AND e.id != $exclude_id";
    
    // Gestion des parcours
    $parcoursVide = empty($parcours) || $parcours == 'all' || $parcours == '';
    
    if($parcoursVide) {
        // Le nouveau cours est pour TOUS les parcours → conflit avec tout
        // (pas de filtre supplémentaire)
    } else {
        // Le nouveau cours est pour un parcours SPECIFIQUE
        // → conflit seulement avec le même parcours OU les cours pour tous
        $sql .= " AND (e.parcours = :parcours OR e.parcours = '' OR e.parcours = 'all' OR e.parcours IS NULL)";
    }
    
    $params = [
        ':jour' => $jour,
        ':mention' => $mention,
        ':niveau' => $niveau,
        ':heure_debut' => $heure_debut,
        ':heure_fin' => $heure_fin
    ];
    if(!$parcoursVide) {
        $params[':parcours'] = $parcours;
    }
    
    $stmt = $dtb->prepare($sql);
    $stmt->execute($params);
    $studentConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($studentConflicts) > 0) {
        foreach($studentConflicts as $c) {
            $parcoursInfo = '';
            if(!empty($c['parcours']) && $c['parcours'] != 'all') {
                $parcoursInfo = " ({$c['parcours']})";
            }
            $conflicts[] = [
                'type' => 'etudiants',
                'severity' => 'warning',
                'message' => "Conflit étudiants: Les L{$c['niveau']} {$c['mention']}{$parcoursInfo} ont déjà {$c['cours_sigle']} ({$c['heure_debut']} - {$c['heure_fin']})"
            ];
        }
    }
    
    echo json_encode([
        'hasConflicts' => count($conflicts) > 0,
        'hasErrors' => count(array_filter($conflicts, fn($c) => $c['severity'] === 'error')) > 0,
        'conflicts' => $conflicts
    ]);
}

// Ajouter une séance
function addSeance() {
    global $dtb;
    
    $cours_id = intval($_POST['cours_id'] ?? 0);
    $type_seance = $_POST['type_seance'] ?? 'cours';
    $jour_semaine = $_POST['jour_semaine'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $salle_id = intval($_POST['salle_id'] ?? 0);
    $id_teacher = intval($_POST['id_teacher'] ?? 0);
    $remarque = $_POST['remarque'] ?? '';
    $parcours_input = $_POST['parcours'] ?? ''; // Parcours sélectionné par l'utilisateur
    $force = $_POST['force'] ?? '0'; // Forcer malgré les conflits warning
    
    // Récupérer les infos du cours
    $coursInfo = $dtb->query("SELECT * FROM t_2023_cours WHERE id = $cours_id")->fetch();
    if(!$coursInfo) {
        echo json_encode(['success' => false, 'error' => 'Cours non trouvé']);
        return;
    }
    
    // Récupérer la salle
    $salleInfo = $dtb->query("SELECT * FROM t_2024_salles WHERE id = $salle_id")->fetch();
    $salle_code = $salleInfo ? $salleInfo['salle_code'] : '';
    
    // Utiliser le parcours sélectionné, sinon celui du cours
    $parcours = !empty($parcours_input) ? $parcours_input : ($coursInfo['parcours'] ?? '');
    
    // Vérifier les conflits si on ne force pas
    if($force != '1') {
        $_POST['mention'] = $coursInfo['dep_desc'];
        $_POST['niveau'] = $coursInfo['yearlevel'];
        $_POST['parcours'] = $parcours;
        
        ob_start();
        checkConflicts();
        $conflictResult = json_decode(ob_get_clean(), true);
        
        if($conflictResult['hasErrors']) {
            echo json_encode([
                'success' => false, 
                'error' => 'Conflits détectés',
                'conflicts' => $conflictResult['conflicts']
            ]);
            return;
        }
    }
    
    // Insérer la séance
    $sql = "INSERT INTO t_2024_emploi_du_temps 
            (cours_id, cours_sigle, cours_title, type_seance, niveau, mention, parcours, semester, 
             annee_scolaire, jour_semaine, heure_debut, heure_fin, salle_id, salle_code, 
             id_teacher, remarque, statut, created_by, created_at, updated_at)
            VALUES 
            (:cours_id, :cours_sigle, :cours_title, :type_seance, :niveau, :mention, :parcours, :semester,
             :annee_scolaire, :jour_semaine, :heure_debut, :heure_fin, :salle_id, :salle_code,
             :id_teacher, :remarque, 'confirme', :created_by, NOW(), NOW())";
    
    $stmt = $dtb->prepare($sql);
    $result = $stmt->execute([
        ':cours_id' => $cours_id,
        ':cours_sigle' => $coursInfo['Sigle'],
        ':cours_title' => $coursInfo['title'],
        ':type_seance' => $type_seance,
        ':niveau' => $coursInfo['yearlevel'],
        ':mention' => $coursInfo['dep_desc'],
        ':parcours' => $parcours,
        ':semester' => $coursInfo['semester'],
        ':annee_scolaire' => '2025-2026',
        ':jour_semaine' => $jour_semaine,
        ':heure_debut' => $heure_debut,
        ':heure_fin' => $heure_fin,
        ':salle_id' => $salle_id,
        ':salle_code' => $salle_code,
        ':id_teacher' => $id_teacher ?: $coursInfo['id_teacher'],
        ':remarque' => $remarque,
        ':created_by' => $_SESSION['user_id'] ?? 1
    ]);
    
    if($result) {
        echo json_encode(['success' => true, 'id' => $dtb->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'insertion']);
    }
}

// Supprimer une séance
function deleteSeance() {
    global $dtb;
    $id = intval($_POST['id'] ?? 0);
    
    $result = $dtb->exec("DELETE FROM t_2024_emploi_du_temps WHERE id = $id");
    echo json_encode(['success' => $result !== false]);
}

// Mettre à jour une séance
function updateSeance() {
    global $dtb;
    
    $id = intval($_POST['id'] ?? 0);
    $type_seance = $_POST['type_seance'] ?? 'cours';
    $jour_semaine = $_POST['jour_semaine'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $salle_id = intval($_POST['salle_id'] ?? 0);
    $id_teacher = intval($_POST['id_teacher'] ?? 0);
    $remarque = $_POST['remarque'] ?? '';
    $parcours_input = $_POST['parcours'] ?? '';
    $force = $_POST['force'] ?? '0';
    
    // Récupérer la séance actuelle
    $seance = $dtb->query("SELECT * FROM t_2024_emploi_du_temps WHERE id = $id")->fetch();
    if(!$seance) {
        echo json_encode(['success' => false, 'error' => 'Séance non trouvée']);
        return;
    }
    
    // Récupérer la salle
    $salleInfo = $dtb->query("SELECT * FROM t_2024_salles WHERE id = $salle_id")->fetch();
    $salle_code = $salleInfo ? $salleInfo['salle_code'] : '';
    
    // Vérifier les conflits si on ne force pas
    if($force != '1') {
        $conflicts = checkMoveConflictsInternal($id, $jour_semaine, $heure_debut, $heure_fin, $salle_id, $id_teacher, $seance['mention'], $seance['niveau']);
        
        if(count($conflicts) > 0) {
            echo json_encode([
                'success' => false, 
                'error' => 'Conflits détectés',
                'conflicts' => $conflicts
            ]);
            return;
        }
    }
    
    // Mettre à jour la séance
    $sql = "UPDATE t_2024_emploi_du_temps SET 
            type_seance = :type_seance,
            jour_semaine = :jour_semaine,
            heure_debut = :heure_debut,
            heure_fin = :heure_fin,
            salle_id = :salle_id,
            salle_code = :salle_code,
            id_teacher = :id_teacher,
            parcours = :parcours,
            remarque = :remarque,
            updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $dtb->prepare($sql);
    $result = $stmt->execute([
        ':type_seance' => $type_seance,
        ':jour_semaine' => $jour_semaine,
        ':heure_debut' => $heure_debut,
        ':heure_fin' => $heure_fin,
        ':salle_id' => $salle_id,
        ':salle_code' => $salle_code,
        ':id_teacher' => $id_teacher,
        ':parcours' => $parcours_input,
        ':remarque' => $remarque,
        ':id' => $id
    ]);
    
    if($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
    }
}

// Déplacer une séance (drag & drop)
function moveSeance() {
    global $dtb;
    
    $id = intval($_POST['id'] ?? 0);
    $jour_semaine = $_POST['jour_semaine'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $duree = intval($_POST['duree'] ?? 2);
    
    // Calculer heure de fin
    $heureDebutInt = intval(substr($heure_debut, 0, 2));
    $heureFinInt = $heureDebutInt + $duree;
    $heure_fin = sprintf('%02d:00', $heureFinInt);
    
    // Récupérer la séance actuelle
    $seance = $dtb->query("SELECT * FROM t_2024_emploi_du_temps WHERE id = $id")->fetch();
    if(!$seance) {
        echo json_encode(['success' => false, 'error' => 'Séance non trouvée']);
        return;
    }
    
    // Vérifier les conflits avant de déplacer
    $conflicts = checkMoveConflictsInternal($id, $jour_semaine, $heure_debut, $heure_fin, $seance['salle_id'], $seance['id_teacher'], $seance['mention'], $seance['niveau']);
    
    if(count($conflicts) > 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'Conflits détectés',
            'conflicts' => $conflicts
        ]);
        return;
    }
    
    // Mettre à jour
    $sql = "UPDATE t_2024_emploi_du_temps 
            SET jour_semaine = :jour, heure_debut = :heure_debut, heure_fin = :heure_fin, updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $dtb->prepare($sql);
    $result = $stmt->execute([
        ':jour' => $jour_semaine,
        ':heure_debut' => $heure_debut,
        ':heure_fin' => $heure_fin,
        ':id' => $id
    ]);
    
    echo json_encode(['success' => $result]);
}

// Vérifier les conflits pour un déplacement (interne)
function checkMoveConflictsInternal($exclude_id, $jour, $heure_debut, $heure_fin, $salle_id, $id_teacher, $mention, $niveau) {
    global $dtb;
    $conflicts = [];
    
    // Récupérer le type de séance de la séance qu'on déplace
    $seanceType = $dtb->query("SELECT type_seance FROM t_2024_emploi_du_temps WHERE id = $exclude_id")->fetchColumn();
    
    // 1. Conflit de salle (sauf pour les examens - plusieurs examens peuvent avoir lieu dans la même salle)
    if($seanceType !== 'examen') {
        $sql = "SELECT e.*, s.salle_code 
                FROM t_2024_emploi_du_temps e 
                LEFT JOIN t_2024_salles s ON e.salle_id = s.id
                WHERE e.jour_semaine = :jour 
                AND e.salle_id = :salle_id
                AND e.statut = 'confirme'
                AND e.type_seance != 'examen'
                AND e.id != :exclude_id
                AND (e.heure_debut < :heure_fin AND e.heure_fin > :heure_debut)";
        
        $stmt = $dtb->prepare($sql);
        $stmt->execute([
            ':jour' => $jour,
            ':salle_id' => $salle_id,
            ':exclude_id' => $exclude_id,
            ':heure_debut' => $heure_debut,
            ':heure_fin' => $heure_fin
        ]);
        
        foreach($stmt->fetchAll() as $c) {
            $conflicts[] = [
                'type' => 'salle',
                'message' => "Salle {$c['salle_code']} occupée par {$c['cours_sigle']}"
            ];
        }
    }
    
    // 2. Conflit enseignant
    if($id_teacher > 0) {
        $sql = "SELECT e.*, t.name, t.lastName 
                FROM t_2024_emploi_du_temps e 
                LEFT JOIN teacher t ON e.id_teacher = t.uid
                WHERE e.jour_semaine = :jour 
                AND e.id_teacher = :id_teacher
                AND e.statut = 'confirme'
                AND e.id != :exclude_id
                AND (e.heure_debut < :heure_fin AND e.heure_fin > :heure_debut)";
        
        $stmt = $dtb->prepare($sql);
        $stmt->execute([
            ':jour' => $jour,
            ':id_teacher' => $id_teacher,
            ':exclude_id' => $exclude_id,
            ':heure_debut' => $heure_debut,
            ':heure_fin' => $heure_fin
        ]);
        
        foreach($stmt->fetchAll() as $c) {
            $conflicts[] = [
                'type' => 'enseignant',
                'message' => "Prof {$c['name']} {$c['lastName']} occuppé par {$c['cours_sigle']}"
            ];
        }
    }
    
    // 3. Conflit étudiants - prendre en compte les parcours
    // Récupérer le parcours de la séance qu'on déplace
    $seanceActuelle = $dtb->query("SELECT parcours FROM t_2024_emploi_du_temps WHERE id = $exclude_id")->fetch();
    $parcours = $seanceActuelle ? $seanceActuelle['parcours'] : '';
    $parcoursVide = empty($parcours) || $parcours == 'all' || $parcours == '';
    
    $sql = "SELECT e.* 
            FROM t_2024_emploi_du_temps e 
            WHERE e.jour_semaine = :jour 
            AND e.mention = :mention
            AND e.niveau = :niveau
            AND e.statut = 'confirme'
            AND e.id != :exclude_id
            AND (e.heure_debut < :heure_fin AND e.heure_fin > :heure_debut)";
    
    if(!$parcoursVide) {
        // Cours pour un parcours spécifique → conflit avec même parcours ou tous
        $sql .= " AND (e.parcours = :parcours OR e.parcours = '' OR e.parcours = 'all' OR e.parcours IS NULL)";
    }
    
    $params = [
        ':jour' => $jour,
        ':mention' => $mention,
        ':niveau' => $niveau,
        ':exclude_id' => $exclude_id,
        ':heure_debut' => $heure_debut,
        ':heure_fin' => $heure_fin
    ];
    if(!$parcoursVide) {
        $params[':parcours'] = $parcours;
    }
    
    $stmt = $dtb->prepare($sql);
    $stmt->execute($params);
    
    foreach($stmt->fetchAll() as $c) {
        $parcoursInfo = '';
        if(!empty($c['parcours']) && $c['parcours'] != 'all') {
            $parcoursInfo = " ({$c['parcours']})";
        }
        $conflicts[] = [
            'type' => 'etudiants',
            'message' => "L{$c['niveau']} {$c['mention']}{$parcoursInfo} ont déjà {$c['cours_sigle']}"
        ];
    }
    
    return $conflicts;
}

// Vérifier les conflits pour un déplacement (API)
function checkMoveConflicts() {
    global $dtb;
    
    $id = intval($_POST['id'] ?? 0);
    $jour = $_POST['jour_semaine'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $duree = intval($_POST['duree'] ?? 2);
    
    $heureDebutInt = intval(substr($heure_debut, 0, 2));
    $heureFinInt = $heureDebutInt + $duree;
    $heure_fin = sprintf('%02d:00', $heureFinInt);
    
    // Récupérer la séance
    $seance = $dtb->query("SELECT * FROM t_2024_emploi_du_temps WHERE id = $id")->fetch();
    if(!$seance) {
        echo json_encode(['error' => 'Séance non trouvée']);
        return;
    }
    
    $conflicts = checkMoveConflictsInternal($id, $jour, $heure_debut, $heure_fin, $seance['salle_id'], $seance['id_teacher'], $seance['mention'], $seance['niveau']);
    
    echo json_encode([
        'hasConflicts' => count($conflicts) > 0,
        'conflicts' => $conflicts
    ]);
}
