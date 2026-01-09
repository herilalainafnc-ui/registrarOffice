<?php
/**
 * Classe de gestion de l'authenticité des documents
 * Système anti-contrefaçon avec QR Code
 */

class DocumentVerification {
    private $dtb;
    private $timezone;
    
    public function __construct($dtb) {
        $this->dtb = $dtb;
        // Fuseau horaire Madagascar (UTC+3)
        $this->timezone = new DateTimeZone('Indian/Antananarivo');
    }
    
    /**
     * Obtient la date/heure actuelle en UTC+3
     */
    private function getCurrentDateTime() {
        $dt = new DateTime('now', $this->timezone);
        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Formate une date en UTC+3
     */
    private function formatDate($dateString, $format = 'd/m/Y à H:i') {
        $dt = new DateTime($dateString, $this->timezone);
        return $dt->format($format);
    }
    
    /**
     * Génère un code unique pour le document
     * Format: UAZ-ANNÉE-XXXXXX (6 caractères alphanumériques)
     */
    public function generateDocCode() {
        $dt = new DateTime('now', $this->timezone);
        $year = $dt->format('Y');
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Sans I, O, 0, 1 pour éviter confusion
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return "UAZ-{$year}-{$code}";
    }
    
    /**
     * Génère le hash SHA-256 pour validation
     */
    public function generateHash($docCode, $studentId, $docType, $dateEmission) {
        $secret = 'UAZ_SECRET_KEY_2024_REGISTRAIRE';
        $data = $docCode . $studentId . $docType . $dateEmission . $secret;
        return hash('sha256', $data);
    }
    
    /**
     * Crée un nouveau document vérifiable
     */
    public function createVerifiableDocument($studentId, $studentName, $docType, $extraData = []) {
        // Générer un code unique
        do {
            $docCode = $this->generateDocCode();
            $check = $this->dtb->prepare("SELECT COUNT(*) FROM t_document_verification WHERE doc_code = ?");
            $check->execute([$docCode]);
        } while ($check->fetchColumn() > 0);
        
        $dateEmission = $this->getCurrentDateTime();
        $docHash = $this->generateHash($docCode, $studentId, $docType, $dateEmission);
        
        $sessionId = $extraData['session_id'] ?? null;
        $level = $extraData['level'] ?? null;
        $semester = $extraData['semester'] ?? null;
        $docDataJson = !empty($extraData['doc_data']) ? json_encode($extraData['doc_data']) : null;
        $emisPar = $extraData['emis_par'] ?? ($_SESSION['user_name'] ?? 'Système');
        
        $sql = "INSERT INTO t_document_verification 
                (doc_code, doc_hash, doc_type, student_id, student_name, session_id, level, semester, doc_data, date_emission, emis_par) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->dtb->prepare($sql);
        $stmt->execute([
            $docCode, $docHash, $docType, $studentId, $studentName,
            $sessionId, $level, $semester, $docDataJson, $dateEmission, $emisPar
        ]);
        
        return [
            'doc_code' => $docCode,
            'doc_hash' => $docHash,
            'date_emission' => $dateEmission,
            'student_name' => $studentName,
            'student_id' => $studentId,
            'doc_type' => $docType
        ];
    }
    
    /**
     * Récupère ou crée un document vérifiable pour un bulletin
     */
    public function getOrCreateBulletinVerification($studentId, $studentName, $level = null, $semester = null) {
        $sql = "SELECT * FROM t_document_verification 
                WHERE student_id = ? AND doc_type = 'bulletin' AND statut = 'valide'";
        $params = [$studentId];
        
        if ($level && $level != 'all') {
            $sql .= " AND level = ?";
            $params[] = $level;
        }
        if ($semester && $semester != 'all') {
            $sql .= " AND semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY date_emission DESC LIMIT 1";
        
        $stmt = $this->dtb->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return [
                'doc_code' => $existing['doc_code'],
                'doc_hash' => $existing['doc_hash'],
                'date_emission' => $existing['date_emission'],
                'student_name' => $existing['student_name'],
                'student_id' => $existing['student_id'],
                'doc_type' => $existing['doc_type'],
                'is_new' => false
            ];
        }
        
        $result = $this->createVerifiableDocument($studentId, $studentName, 'bulletin', [
            'level' => $level,
            'semester' => $semester,
            'doc_data' => ['type' => 'Relevé de notes', 'niveau' => $level, 'semestre' => $semester]
        ]);
        $result['is_new'] = true;
        
        return $result;
    }
    
    /**
     * Vérifie un document par son code
     */
    public function verifyDocument($docCode) {
        $sql = "SELECT * FROM t_document_verification WHERE doc_code = ?";
        $stmt = $this->dtb->prepare($sql);
        $stmt->execute([$docCode]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc) {
            $this->logVerification($docCode, 'non_trouve');
            return ['valid' => false, 'status' => 'non_trouve', 'message' => 'Document non trouvé', 'icon' => '❌'];
        }
        
        // Mettre à jour les stats
        $updateSql = "UPDATE t_document_verification 
                      SET nb_verifications = nb_verifications + 1, 
                          derniere_verification = NOW(), ip_derniere_verif = ?
                      WHERE doc_code = ?";
        $this->dtb->prepare($updateSql)->execute([$_SERVER['REMOTE_ADDR'] ?? null, $docCode]);
        
        switch ($doc['statut']) {
            case 'valide':
                $this->logVerification($docCode, 'valide');
                return ['valid' => true, 'status' => 'valide', 'message' => 'Document authentique et valide', 'icon' => '✅', 'data' => $doc];
            case 'annule':
                $this->logVerification($docCode, 'annule');
                return ['valid' => false, 'status' => 'annule', 'message' => 'Ce document a été annulé', 'motif' => $doc['motif_annulation'], 'icon' => '❌', 'data' => $doc];
            case 'expire':
                $this->logVerification($docCode, 'expire');
                return ['valid' => false, 'status' => 'expire', 'message' => 'Ce document a expiré', 'icon' => '⚠️', 'data' => $doc];
            case 'suspendu':
                $this->logVerification($docCode, 'invalide');
                return ['valid' => false, 'status' => 'suspendu', 'message' => 'Document temporairement suspendu', 'icon' => '⚠️', 'data' => $doc];
            default:
                return ['valid' => false, 'status' => 'inconnu', 'message' => 'Statut inconnu', 'icon' => '❓'];
        }
    }
    
    private function logVerification($docCode, $resultat) {
        $sql = "INSERT INTO t_verification_log (doc_code, ip_address, user_agent, resultat) VALUES (?, ?, ?, ?)";
        $stmt = $this->dtb->prepare($sql);
        $stmt->execute([$docCode, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $resultat]);
    }
    
    public function annulerDocument($docCode, $motif) {
        $sql = "UPDATE t_document_verification SET statut = 'annule', motif_annulation = ? WHERE doc_code = ?";
        return $this->dtb->prepare($sql)->execute([$motif, $docCode]);
    }
    
    /**
     * Génère le contenu texte pour le QR Code (infos directes, pas d'URL)
     */
    public function generateQRContent($docCode, $studentName, $studentId, $docType, $dateEmission, $docHash, $recap = []) {
        // Informations essentielles encodées dans le QR (format compact)
        $content = "UAZ|";
        $content .= "{$docCode}|";
        $content .= "{$studentId}|";
        $content .= "{$studentName}|";
        $content .= ucfirst($docType) . "|";
        $content .= $this->formatDate($dateEmission, 'd/m/Y H:i') . "|";
        
        // Récapitulatif
        if (!empty($recap)) {
            $content .= "Sess:" . ($recap['sessions'] ?? 0) . "|";
            $content .= "Cours:" . ($recap['cours'] ?? 0) . "|";
            $content .= "Cr:" . ($recap['credits'] ?? 0) . "|";
            $content .= "Moy:" . ($recap['moyenne'] ?? 0);
        }
        
        return $content;
    }
    
    /**
     * Génère le HTML du bloc QR Code pour le bulletin
     * QR contient les infos directement (pas d'URL de redirection)
     */
    public function getQRCodeHTML($docCode, $dateEmission, $studentName = '', $studentId = '', $docType = 'bulletin', $docHash = '', $recap = []) {
        // Contenu du QR code avec les infos essentielles (format compact pour meilleure lisibilité)
        $qrContent = $this->generateQRContent($docCode, $studentName, $studentId, $docType, $dateEmission, $docHash, $recap);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&ecc=M&data=' . urlencode($qrContent);
        
        $dateFormatted = $this->formatDate($dateEmission);
        
        return '
        <div style="border: 1px solid #334155; padding: 6px; margin-top: 8px; background: #f8fafc; border-radius: 4px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 95px; vertical-align: top;">
                        <img src="' . $qrUrl . '" style="width: 90px; height: 90px;" alt="QR Code">
                    </td>
                    <td style="vertical-align: top; padding-left: 8px; font-size: 9px;">
                        <b style="font-size: 10px; color: #0f766e;">🔐 DOCUMENT AUTHENTIFIÉ</b><br>
                        <span style="color: #475569;">Émis le : ' . $dateFormatted . ' (UTC+3)</span><br>
                        <span style="font-size: 8px; color: #64748b;">Scannez le QR pour voir les informations</span>
                    </td>
                </tr>
            </table>
        </div>';
    }
}
