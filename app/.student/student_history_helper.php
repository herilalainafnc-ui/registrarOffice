<?php
/**
 * Helper pour enregistrer l'historique des modifications d'informations étudiantes
 * À inclure dans les fichiers qui modifient les données étudiantes
 */

/**
 * Mapping des noms de champs vers leurs libellés en français
 */
function getFieldLabels() {
    return [
        // Informations personnelles
        'student_nom' => 'Nom',
        'student_prenom' => 'Prénom',
        'sex' => 'Sexe',
        'dateNaissance' => 'Date de naissance',
        'lieuNaissance' => 'Lieu de naissance',
        'nationalite' => 'Nationalité',
        'religion' => 'Religion',
        'student_email' => 'Email',
        'student_tel' => 'Téléphone',
        'student_adresse' => 'Adresse',
        'student_region' => 'Région',
        
        // Documents d'identité
        'num_cin' => 'Numéro CIN',
        'cin_date_delivre' => 'Date délivrance CIN',
        'cin_region' => 'Région CIN',
        'num_visa' => 'Numéro Visa',
        
        // Études
        'etude_envisage' => 'Mention',
        'etude_option' => 'Parcours',
        'annee_etude' => 'Niveau d\'études',
        'annee_scolaire' => 'Année scolaire',
        'new_student' => 'Type étudiant',
        'status' => 'Statut paiement',
        'graduated' => 'Diplômé',
        'abonment' => 'Abonnement',
        
        // Situation familiale
        'situationf' => 'Situation familiale',
        'nb_enfant' => 'Nombre d\'enfants',
        'nom_conjoint' => 'Nom conjoint',
        
        // Parents
        'father_name' => 'Nom du père',
        'father_prof' => 'Profession du père',
        'mother_name' => 'Nom de la mère',
        'mother_prof' => 'Profession de la mère',
        'parent_tel' => 'Téléphone parents',
        'parent_adresse' => 'Adresse parents',
        
        // Responsable financier
        'sponsor_nom' => 'Nom responsable financier',
        'sponsor_prenom' => 'Prénom responsable financier',
        'sponsor_tel' => 'Téléphone responsable',
        'sponsor_adresse' => 'Adresse responsable',
        
        // Image
        'image_student' => 'Photo',
        
        // Statuts spéciaux
        'suspended' => 'Suspension',
        'motif_suspension' => 'Motif suspension',
        'date_debut_suspension' => 'Début suspension',
        'date_fin_suspension' => 'Fin suspension',
        'retrait_universite' => 'Retrait université',
        'type_retrait' => 'Type de retrait',
        'cause_depart' => 'Cause du départ',
        'date_depart_universite' => 'Date de départ',
        'date_retour_probable' => 'Date retour probable',
        
        // Baccalauréat
        'serie_bacc' => 'Série baccalauréat',
        'obtention_bacc' => 'Année obtention bac',
        
        // Diplôme précédent
        'diplome_preced' => 'Diplôme précédent',
        'date_obtent_diplome_preced' => 'Date obtention diplôme'
    ];
}

/**
 * Enregistre une modification dans l'historique
 * 
 * @param PDO $dtb Connexion à la base de données
 * @param string $student_id Matricule étudiant
 * @param int $student_db_id ID dans tbl_2024_etudiant
 * @param string $field_name Nom du champ
 * @param mixed $old_value Ancienne valeur
 * @param mixed $new_value Nouvelle valeur
 * @param int $user_id ID de l'utilisateur
 * @param string $action_type Type d'action
 * @param string $commentaire Commentaire optionnel
 */
function logStudentModification($dtb, $student_id, $student_db_id, $field_name, $old_value, $new_value, $user_id, $action_type = 'modification', $commentaire = null) {
    
    // Ne pas enregistrer si les valeurs sont identiques
    if ($old_value === $new_value || (empty($old_value) && empty($new_value))) {
        return false;
    }
    
    $labels = getFieldLabels();
    $field_label = isset($labels[$field_name]) ? $labels[$field_name] : $field_name;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    
    $insert = $dtb->prepare("INSERT INTO t_student_modification_history 
        (student_id, student_db_id, field_name, field_label, old_value, new_value, action_type, action_by, ip_address, user_agent, commentaire)
        VALUES 
        (:student_id, :student_db_id, :field_name, :field_label, :old_value, :new_value, :action_type, :action_by, :ip_address, :user_agent, :commentaire)");
    
    return $insert->execute([
        'student_id' => $student_id,
        'student_db_id' => $student_db_id,
        'field_name' => $field_name,
        'field_label' => $field_label,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'action_type' => $action_type,
        'action_by' => $user_id,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'commentaire' => $commentaire
    ]);
}

/**
 * Compare deux tableaux et enregistre toutes les différences
 * 
 * @param PDO $dtb Connexion à la base de données
 * @param array $oldData Anciennes données
 * @param array $newData Nouvelles données
 * @param string $student_id Matricule étudiant
 * @param int $student_db_id ID dans tbl_2024_etudiant
 * @param int $user_id ID de l'utilisateur
 * @param string $action_type Type d'action
 * @param array $fieldsToTrack Liste des champs à suivre (optionnel, tous si vide)
 */
function logStudentModifications($dtb, $oldData, $newData, $student_id, $student_db_id, $user_id, $action_type = 'modification', $fieldsToTrack = []) {
    
    $labels = getFieldLabels();
    $fieldsToCheck = empty($fieldsToTrack) ? array_keys($labels) : $fieldsToTrack;
    
    $changesCount = 0;
    
    foreach ($fieldsToCheck as $field) {
        $oldValue = isset($oldData[$field]) ? $oldData[$field] : null;
        $newValue = isset($newData[$field]) ? $newData[$field] : null;
        
        // Normaliser les valeurs pour la comparaison
        $oldValue = is_null($oldValue) ? '' : trim($oldValue);
        $newValue = is_null($newValue) ? '' : trim($newValue);
        
        if ($oldValue !== $newValue) {
            logStudentModification($dtb, $student_id, $student_db_id, $field, $oldValue, $newValue, $user_id, $action_type);
            $changesCount++;
        }
    }
    
    return $changesCount;
}

/**
 * Formate une valeur pour l'affichage
 */
function formatValueForDisplay($field_name, $value) {
    if (empty($value) || $value === '0000-00-00') {
        return '<em class="text-slate-500">Non renseigné</em>';
    }
    
    switch ($field_name) {
        case 'sex':
            return $value == 'M' ? 'Masculin' : 'Féminin';
        
        case 'new_student':
            if ($value == 1) return 'Nouveau';
            if ($value == 10) return 'Spécial';
            return 'Ancien';
        
        case 'status':
            if ($value == 'PAYÉ') return '<span class="text-green-500">PAYÉ</span>';
            if ($value == 'NON PAYÉ') return '<span class="text-red-500">NON PAYÉ</span>';
            return $value;
        
        case 'suspended':
            return $value == 1 ? '<span class="text-orange-500">Suspendu</span>' : 'Non suspendu';
        
        case 'retrait_universite':
            if ($value == 2) return '<span class="text-blue-500">Retrait momentané</span>';
            if ($value == 3) return '<span class="text-blue-700">Retrait définitif</span>';
            return 'Non';
        
        case 'graduated':
            return $value == 1 ? '<span class="text-green-500">Oui</span>' : 'Non';
        
        case 'abonment':
            return $value == 1 ? 'Oui' : 'Non';
        
        case 'annee_etude':
            if ($value == 0) return 'Remise à niveau';
            if ($value <= 3) return 'Licence ' . $value;
            return 'Master ' . ($value - 3);
        
        case 'dateNaissance':
        case 'cin_date_delivre':
        case 'date_debut_suspension':
        case 'date_fin_suspension':
        case 'date_depart_universite':
        case 'date_retour_probable':
            return date('d/m/Y', strtotime($value));
        
        default:
            return htmlspecialchars($value);
    }
}
