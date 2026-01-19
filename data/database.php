<?php
/**
 * =============================================================================
 * CLASSE DB - Requêtes Sécurisées
 * =============================================================================
 * 
 * Cette classe fournit des méthodes sécurisées pour les requêtes SQL
 * en utilisant des requêtes préparées pour éviter les injections SQL.
 * 
 * @author Infinit Registrar
 * @version 1.0.0
 */

class DB {
    
    private static $pdo = null;
    
    /**
     * Initialise la connexion PDO
     */
    public static function init($pdo) {
        self::$pdo = $pdo;
    }
    
    /**
     * Récupère la connexion PDO
     */
    public static function getPdo() {
        return self::$pdo;
    }
    
    /**
     * ==========================================================================
     * MÉTHODES DE REQUÊTE SÉCURISÉES
     * ==========================================================================
     */
    
    /**
     * Exécute une requête SELECT et retourne tous les résultats
     * 
     * @param string $sql La requête SQL avec des placeholders :nom
     * @param array $params Les paramètres à lier
     * @return array Les résultats
     * 
     * Exemple:
     * $students = DB::select("SELECT * FROM tbl_2024_etudiant WHERE student_id = :id", ['id' => $student_id]);
     */
    public static function select($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Exécute une requête SELECT et retourne le premier résultat
     * 
     * @param string $sql La requête SQL avec des placeholders :nom
     * @param array $params Les paramètres à lier
     * @return array|false Le premier résultat ou false
     * 
     * Exemple:
     * $student = DB::selectOne("SELECT * FROM tbl_2024_etudiant WHERE student_id = :id", ['id' => $student_id]);
     */
    public static function selectOne($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Exécute une requête INSERT
     * 
     * @param string $table Nom de la table
     * @param array $data Données à insérer ['colonne' => 'valeur']
     * @return string|false L'ID inséré ou false
     * 
     * Exemple:
     * $id = DB::insert('tbl_2024_etudiant', ['nom' => 'Dupont', 'prenom' => 'Jean']);
     */
    public static function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($data);
        
        return self::$pdo->lastInsertId();
    }
    
    /**
     * Exécute une requête UPDATE
     * 
     * @param string $table Nom de la table
     * @param array $data Données à mettre à jour
     * @param string $where Condition WHERE avec placeholders
     * @param array $whereParams Paramètres pour la condition WHERE
     * @return int Nombre de lignes affectées
     * 
     * Exemple:
     * DB::update('tbl_2024_etudiant', ['nom' => 'Martin'], 'student_id = :id', ['id' => 123]);
     */
    public static function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setString = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));
        
        return $stmt->rowCount();
    }
    
    /**
     * Exécute une requête DELETE
     * 
     * @param string $table Nom de la table
     * @param string $where Condition WHERE avec placeholders
     * @param array $params Paramètres pour la condition
     * @return int Nombre de lignes supprimées
     * 
     * Exemple:
     * DB::delete('tbl_2024_etudiant', 'student_id = :id', ['id' => 123]);
     */
    public static function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Exécute une requête personnalisée (INSERT, UPDATE, DELETE)
     * 
     * @param string $sql La requête SQL
     * @param array $params Les paramètres
     * @return int Nombre de lignes affectées
     */
    public static function execute($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * ==========================================================================
     * MÉTHODES UTILITAIRES COURANTES
     * ==========================================================================
     */
    
    /**
     * Recherche un enregistrement par ID
     */
    public static function find($table, $id, $idColumn = 'id') {
        return self::selectOne(
            "SELECT * FROM {$table} WHERE {$idColumn} = :id LIMIT 1",
            ['id' => $id]
        );
    }
    
    /**
     * Recherche un étudiant par student_id
     */
    public static function findStudent($studentId) {
        return self::selectOne(
            "SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id",
            ['student_id' => $studentId]
        );
    }
    
    /**
     * Recherche un utilisateur par ID
     */
    public static function findUser($userId) {
        return self::selectOne(
            "SELECT * FROM compt_utilisateur WHERE id = :id AND etat = 1",
            ['id' => $userId]
        );
    }
    
    /**
     * Recherche une session
     */
    public static function findSession($sessionId) {
        return self::selectOne(
            "SELECT * FROM t_2023_session WHERE session_id = :session_id",
            ['session_id' => $sessionId]
        );
    }
    
    /**
     * Recherche une filière par sigle ou description
     */
    public static function findFiliere($value, $byDescription = false) {
        $column = $byDescription ? 'filiere_description' : 'filiere_sigle';
        return self::selectOne(
            "SELECT * FROM filiere WHERE {$column} = :value",
            ['value' => $value]
        );
    }
    
    /**
     * Recherche un parcours
     */
    public static function findParcours($value, $byDepartement = false) {
        $column = $byDepartement ? 'departement' : 'description';
        if ($byDepartement) {
            return self::select(
                "SELECT * FROM filiere_parcours WHERE departement = :value ORDER BY description",
                ['value' => $value]
            );
        }
        return self::selectOne(
            "SELECT * FROM filiere_parcours WHERE description = :value",
            ['value' => $value]
        );
    }
    
    /**
     * Recherche les notes d'un étudiant
     */
    public static function findNotes($studentId, $options = []) {
        $sql = "SELECT * FROM t_2023_notes WHERE student_id = :student_id AND ajout = 1";
        $params = ['student_id' => $studentId];
        
        if (isset($options['session_id'])) {
            $sql .= " AND session_id = :session_id";
            $params['session_id'] = $options['session_id'];
        }
        
        if (isset($options['sigle'])) {
            $sql .= " AND Sigle = :sigle";
            $params['sigle'] = $options['sigle'];
        }
        
        $sql .= " ORDER BY title_cours";
        
        return self::select($sql, $params);
    }
    
    /**
     * Compte les résultats
     */
    public static function count($table, $where = '1=1', $params = []) {
        $result = self::selectOne(
            "SELECT COUNT(*) as count FROM {$table} WHERE {$where}",
            $params
        );
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Vérifie si un enregistrement existe
     */
    public static function exists($table, $where, $params = []) {
        return self::count($table, $where, $params) > 0;
    }
    
    /**
     * ==========================================================================
     * RECHERCHE AVEC LIKE (sécurisée)
     * ==========================================================================
     */
    
    /**
     * Recherche avec LIKE sécurisé
     * 
     * @param string $table Table à rechercher
     * @param array $columns Colonnes où chercher
     * @param string $search Terme de recherche
     * @param string $extraWhere Conditions supplémentaires
     * @param array $extraParams Paramètres supplémentaires
     * @return array Résultats
     * 
     * Exemple:
     * $results = DB::searchLike('t_2023_cours', ['Sigle', 'title'], $input, 'remove != 1', []);
     */
    public static function searchLike($table, $columns, $search, $extraWhere = '', $extraParams = [], $limit = 200) {
        $conditions = [];
        $params = [];
        
        // Échapper les caractères spéciaux LIKE
        $searchEscaped = '%' . addcslashes($search, '%_') . '%';
        
        foreach ($columns as $i => $column) {
            $paramName = "search_{$i}";
            $conditions[] = "{$column} LIKE :{$paramName}";
            $params[$paramName] = $searchEscaped;
        }
        
        $sql = "SELECT * FROM {$table} WHERE (" . implode(' OR ', $conditions) . ")";
        
        if (!empty($extraWhere)) {
            $sql .= " AND ({$extraWhere})";
            $params = array_merge($params, $extraParams);
        }
        
        $sql .= " LIMIT {$limit}";
        
        return self::select($sql, $params);
    }
    
    /**
     * Recherche d'étudiants avec plusieurs critères
     */
    public static function searchStudents($search, $filters = [], $limit = 200) {
        $columns = ['student_id', 'student_nom', 'student_prenom'];
        $extraWhere = '';
        $extraParams = [];
        
        if (isset($filters['annee_scolaire'])) {
            $extraWhere .= (empty($extraWhere) ? '' : ' AND ') . 'annee_scolaire = :annee_scolaire';
            $extraParams['annee_scolaire'] = $filters['annee_scolaire'];
        }
        
        if (isset($filters['etude_envisage'])) {
            $extraWhere .= (empty($extraWhere) ? '' : ' AND ') . 'etude_envisage = :etude_envisage';
            $extraParams['etude_envisage'] = $filters['etude_envisage'];
        }
        
        if (isset($filters['annee_etude'])) {
            $extraWhere .= (empty($extraWhere) ? '' : ' AND ') . 'annee_etude = :annee_etude';
            $extraParams['annee_etude'] = $filters['annee_etude'];
        }
        
        return self::searchLike('tbl_2024_etudiant', $columns, $search, $extraWhere, $extraParams, $limit);
    }
    
    /**
     * Recherche de cours
     */
    public static function searchCours($search, $limit = 200) {
        return self::searchLike(
            't_2023_cours',
            ['Sigle', 'title', 'dep_desc', 'title_english'],
            $search,
            'remove != 1',
            [],
            $limit
        );
    }
}

/**
 * =============================================================================
 * FONCTIONS HELPER GLOBALES
 * =============================================================================
 */

/**
 * Initialise la classe DB avec la connexion PDO
 */
function initDB($pdo) {
    DB::init($pdo);
}

/**
 * Raccourci pour DB::selectOne
 */
function db_find($table, $id, $idColumn = 'id') {
    return DB::find($table, $id, $idColumn);
}

/**
 * Raccourci pour DB::selectOne avec requête personnalisée
 */
function db_one($sql, $params = []) {
    return DB::selectOne($sql, $params);
}

/**
 * Raccourci pour DB::select
 */
function db_all($sql, $params = []) {
    return DB::select($sql, $params);
}
