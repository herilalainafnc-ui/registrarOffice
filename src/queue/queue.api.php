<?php
/**
 * API de gestion de file d'attente
 * Endpoints AJAX pour l'incrémentation et l'appel groupé
 */
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

// Auto-migration: create global music table if it doesn't exist
try {
    $dtb->query("SELECT id FROM t_queue_global_music LIMIT 0");
} catch(Exception $e) {
    try {
        $dtb->exec("
            CREATE TABLE IF NOT EXISTS t_queue_global_music (
                id INT PRIMARY KEY DEFAULT 1,
                music_playlist TEXT DEFAULT NULL,
                music_current_index INT DEFAULT 0,
                music_youtube_url VARCHAR(255) DEFAULT NULL,
                music_playing TINYINT(1) DEFAULT 0,
                music_volume INT DEFAULT 50,
                video_fullscreen TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $dtb->exec("INSERT IGNORE INTO t_queue_global_music (id) VALUES (1)");
        // Migrate existing playlist from active session if any
        try {
            $migRow = $dtb->query("SELECT music_playlist, music_current_index, music_youtube_url, music_playing, music_volume FROM t_queue_sessions WHERE status = 'active' AND music_playlist IS NOT NULL ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($migRow && !empty($migRow['music_playlist'])) {
                $dtb->prepare("UPDATE t_queue_global_music SET music_playlist = :pl, music_current_index = :idx, music_youtube_url = :url, music_playing = :playing, music_volume = :vol WHERE id = 1")
                    ->execute(['pl' => $migRow['music_playlist'], 'idx' => $migRow['music_current_index'], 'url' => $migRow['music_youtube_url'], 'playing' => $migRow['music_playing'], 'vol' => $migRow['music_volume']]);
            }
        } catch(Exception $e3) {}
    } catch(Exception $e2) {}
}

// Auto-migration: add video_fullscreen column if missing
try {
    $dtb->query("SELECT video_fullscreen FROM t_queue_global_music LIMIT 0");
} catch(Exception $e) {
    try {
        $dtb->exec("ALTER TABLE t_queue_global_music ADD COLUMN video_fullscreen TINYINT(1) DEFAULT 0");
    } catch(Exception $e2) {}
}

// Helper: get global music row
function getGlobalMusic(PDO $dtb): array {
    $row = $dtb->query("SELECT * FROM t_queue_global_music WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $dtb->exec("INSERT IGNORE INTO t_queue_global_music (id) VALUES (1)");
        $row = ['id' => 1, 'music_playlist' => null, 'music_current_index' => 0, 'music_youtube_url' => null, 'music_playing' => 0, 'music_volume' => 50, 'video_fullscreen' => 0];
    }
    return $row;
}

// Fonction utilitaire : vérification de niveau adaptée à l'API JSON
function apiRequireLevel($level) {
    if (!function_exists('hasLevel') || !hasLevel($level)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Niveau insuffisant.']);
        exit;
    }
}

// Fonction utilitaire : vérification CSRF adaptée à l'API JSON
function apiRequireCsrf() {
    if (!function_exists('verify_csrf') || !verify_csrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide. Veuillez rafraîchir la page.']);
        exit;
    }
}

try {
    switch ($action) {

        // =====================================================================
        // GESTION DES SESSIONS
        // =====================================================================
        case 'create_session':
            apiRequireLevel(ROLE_REGISTRAR);
            apiRequireCsrf();

            $name = trim($_POST['session_name'] ?? '');
            $date = $_POST['session_date'] ?? date('Y-m-d');

            if (empty($name)) {
                throw new Exception('Le nom de la session est requis.');
            }

            // Fermer toute session active existante
            $dtb->exec("UPDATE t_queue_sessions SET status = 'closed', closed_at = NOW() WHERE status = 'active'");

            $stmt = $dtb->prepare("INSERT INTO t_queue_sessions (session_name, session_date, created_by) VALUES (:name, :date, :user_id)");
            $stmt->execute([
                'name' => $name,
                'date' => $date,
                'user_id' => $_SESSION['user_id']
            ]);

            echo json_encode(['success' => true, 'session_id' => $dtb->lastInsertId(), 'message' => 'Session créée avec succès']);
            break;

        case 'get_active_session':
            $stmt = $dtb->query("SELECT * FROM t_queue_sessions WHERE status = 'active' ORDER BY id DESC LIMIT 1");
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Aucune session active']);
                break;
            }

            // Stats de la session
            $stats = getQueueSessionStats($dtb, $session['id']);
            $session['stats'] = $stats;

            echo json_encode(['success' => true, 'session' => $session]);
            break;

        case 'close_session':
            apiRequireLevel(ROLE_REGISTRAR);
            apiRequireCsrf();

            $session_id = (int)($_POST['session_id'] ?? 0);
            $stmt = $dtb->prepare("UPDATE t_queue_sessions SET status = 'closed', closed_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $session_id]);

            echo json_encode(['success' => true, 'message' => 'Session fermée']);
            break;

        case 'get_all_sessions':
            apiRequireLevel(ROLE_REGISTRAR);

            $sessions = $dtb->query("
                SELECT s.*, 
                    (SELECT COUNT(*) FROM t_queue_tickets WHERE session_id = s.id) as total_tickets,
                    (SELECT COUNT(*) FROM t_queue_tickets WHERE session_id = s.id AND status = 'waiting') as waiting,
                    (SELECT COUNT(*) FROM t_queue_tickets WHERE session_id = s.id AND status = 'done') as done,
                    (SELECT COUNT(*) FROM t_queue_tickets WHERE session_id = s.id AND status = 'serving') as serving,
                    (SELECT COUNT(*) FROM t_queue_tickets WHERE session_id = s.id AND status = 'called') as called
                FROM t_queue_sessions s 
                ORDER BY s.id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'sessions' => $sessions]);
            break;

        case 'reactivate_session':
            apiRequireLevel(ROLE_REGISTRAR);
            apiRequireCsrf();

            $session_id = (int)($_POST['session_id'] ?? 0);
            if ($session_id <= 0) throw new Exception('Session invalide.');

            // Vérifier que la session existe
            $check = $dtb->prepare("SELECT id, status FROM t_queue_sessions WHERE id = :id");
            $check->execute(['id' => $session_id]);
            $sess = $check->fetch(PDO::FETCH_ASSOC);
            if (!$sess) throw new Exception('Session introuvable.');
            if ($sess['status'] === 'active') throw new Exception('Cette session est déjà active.');

            // Fermer toute session active existante
            $dtb->exec("UPDATE t_queue_sessions SET status = 'closed', closed_at = NOW() WHERE status = 'active'");

            // Réactiver la session choisie
            $dtb->prepare("UPDATE t_queue_sessions SET status = 'active', closed_at = NULL WHERE id = :id")
                ->execute(['id' => $session_id]);

            echo json_encode(['success' => true, 'message' => 'Session réactivée avec succès']);
            break;

        case 'delete_session':
            apiRequireLevel(ROLE_REGISTRAR);
            apiRequireCsrf();

            $session_id = (int)($_POST['session_id'] ?? 0);
            if ($session_id <= 0) throw new Exception('Session invalide.');

            // Vérifier que la session n'est pas active
            $check = $dtb->prepare("SELECT status FROM t_queue_sessions WHERE id = :id");
            $check->execute(['id' => $session_id]);
            $sess = $check->fetch(PDO::FETCH_ASSOC);
            if (!$sess) throw new Exception('Session introuvable.');
            if ($sess['status'] === 'active') throw new Exception('Impossible de supprimer une session active. Fermez-la d\'abord.');

            // Supprimer les tickets et appels associés puis la session
            $dtb->prepare("DELETE FROM t_queue_batch_calls WHERE session_id = :id")->execute(['id' => $session_id]);
            $dtb->prepare("DELETE FROM t_queue_tickets WHERE session_id = :id")->execute(['id' => $session_id]);
            $dtb->prepare("DELETE FROM t_queue_sessions WHERE id = :id")->execute(['id' => $session_id]);

            echo json_encode(['success' => true, 'message' => 'Session supprimée']);
            break;

        // =====================================================================
        // GÉNÉRATION DE TICKETS
        // =====================================================================
        case 'generate_ticket':
            apiRequireLevel(ROLE_REGISTRAR);

            $session_id = (int)($_POST['session_id'] ?? 0);
            $student_name = trim($_POST['student_name'] ?? '');
            $student_matricule = trim($_POST['student_id'] ?? '');
            $mention = trim($_POST['mention'] ?? '');

            if ($session_id <= 0) {
                throw new Exception('Session invalide.');
            }

            // Vérifier que la session est active
            $session = $dtb->prepare("SELECT * FROM t_queue_sessions WHERE id = :id AND status = 'active'");
            $session->execute(['id' => $session_id]);
            if (!$session->fetch()) {
                throw new Exception('La session n\'est pas active.');
            }

            // Incrémenter le compteur et créer le ticket (transaction)
            $dtb->beginTransaction();
            try {
                // Incrémenter le dernier numéro
                $dtb->prepare("UPDATE t_queue_sessions SET last_ticket_number = last_ticket_number + 1 WHERE id = :id")
                     ->execute(['id' => $session_id]);

                // Récupérer le nouveau numéro
                $numStmt = $dtb->prepare("SELECT last_ticket_number FROM t_queue_sessions WHERE id = :id");
                $numStmt->execute(['id' => $session_id]);
                $newNumber = $numStmt->fetchColumn();

                // Créer le ticket
                $stmt = $dtb->prepare("INSERT INTO t_queue_tickets (session_id, ticket_number, student_name, student_id, mention) 
                                       VALUES (:session_id, :number, :name, :student_id, :mention)");
                $stmt->execute([
                    'session_id' => $session_id,
                    'number' => $newNumber,
                    'name' => $student_name ?: null,
                    'student_id' => $student_matricule ?: null,
                    'mention' => $mention ?: null
                ]);

                $dtb->commit();

                echo json_encode([
                    'success' => true,
                    'ticket' => [
                        'id' => $dtb->lastInsertId(),
                        'number' => $newNumber,
                        'formatted' => str_pad($newNumber, 3, '0', STR_PAD_LEFT),
                        'student_name' => $student_name,
                        'mention' => $mention
                    ],
                    'message' => 'Ticket #' . str_pad($newNumber, 3, '0', STR_PAD_LEFT) . ' créé'
                ]);
            } catch (Exception $e) {
                $dtb->rollBack();
                throw $e;
            }
            break;

        // =====================================================================
        // APPEL GROUPÉ (BATCH CALL)
        // =====================================================================
        case 'call_next':
            apiRequireLevel(ROLE_REGISTRAR);

            $session_id = (int)($_POST['session_id'] ?? 0);
            $batch_size = max(1, min(10, (int)($_POST['batch_size'] ?? 1)));

            if ($session_id <= 0) {
                throw new Exception('Session invalide.');
            }

            $dtb->beginTransaction();
            try {
                // Récupérer les N prochains tickets en attente
                $stmt = $dtb->prepare("
                    SELECT id, ticket_number, student_name, mention 
                    FROM t_queue_tickets 
                    WHERE session_id = :session_id AND status = 'waiting' 
                    ORDER BY ticket_number ASC 
                    LIMIT :batch_size
                ");
                $stmt->bindValue(':session_id', $session_id, PDO::PARAM_INT);
                $stmt->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
                $stmt->execute();
                $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($tickets)) {
                    $dtb->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Aucun ticket en attente.']);
                    break;
                }

                // Passer les tickets précédemment "called" en "serving"
                $dtb->prepare("
                    UPDATE t_queue_tickets SET status = 'serving', served_at = NOW() 
                    WHERE session_id = :session_id AND status = 'called'
                ")->execute(['session_id' => $session_id]);

                // Mettre à jour le statut des tickets sélectionnés
                $ticketIds = array_column($tickets, 'id');
                $ticketNumbers = array_column($tickets, 'ticket_number');
                $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

                $updateStmt = $dtb->prepare("
                    UPDATE t_queue_tickets 
                    SET status = 'called', called_at = NOW(), called_by = ? 
                    WHERE id IN ($placeholders)
                ");
                $params = array_merge([$_SESSION['user_id']], $ticketIds);
                $updateStmt->execute($params);

                // Enregistrer le batch call
                $batchStmt = $dtb->prepare("
                    INSERT INTO t_queue_batch_calls (session_id, ticket_numbers, batch_size, called_by, announcement_text) 
                    VALUES (:session_id, :numbers, :size, :user_id, :text)
                ");

                $formattedNumbers = array_map(fn($n) => str_pad($n, 3, '0', STR_PAD_LEFT), $ticketNumbers);
                $announcementText = 'Numéros appelés : ' . implode(', ', $formattedNumbers);

                $batchStmt->execute([
                    'session_id' => $session_id,
                    'numbers' => json_encode($ticketNumbers),
                    'size' => count($tickets),
                    'user_id' => $_SESSION['user_id'],
                    'text' => $announcementText
                ]);

                $dtb->commit();

                echo json_encode([
                    'success' => true,
                    'called_tickets' => array_map(function($t) {
                        $t['formatted'] = str_pad($t['ticket_number'], 3, '0', STR_PAD_LEFT);
                        return $t;
                    }, $tickets),
                    'batch_size' => count($tickets),
                    'announcement' => $announcementText,
                    'message' => count($tickets) . ' numéro(s) appelé(s)'
                ]);
            } catch (Exception $e) {
                $dtb->rollBack();
                throw $e;
            }
            break;

        // =====================================================================
        // APPEL SPÉCIFIQUE (par numéro)
        // =====================================================================
        case 'call_specific':
            apiRequireLevel(ROLE_REGISTRAR);

            $session_id = (int)($_POST['session_id'] ?? 0);
            $ticket_number = (int)($_POST['ticket_number'] ?? 0);

            $stmt = $dtb->prepare("
                UPDATE t_queue_tickets SET status = 'called', called_at = NOW(), called_by = :user_id 
                WHERE session_id = :session_id AND ticket_number = :number AND status IN ('waiting', 'skipped', 'called', 'absent')
            ");
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'session_id' => $session_id,
                'number' => $ticket_number
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Ticket #' . str_pad($ticket_number, 3, '0', STR_PAD_LEFT) . ' appelé']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ticket introuvable ou déjà traité.']);
            }
            break;

        // =====================================================================
        // MARQUER COMME TRAITÉ / PASSÉ
        // =====================================================================
        // =====================================================================
        // RAPPELER UN NUMÉRO (met à jour called_at pour re-déclencher l'affichage)
        // =====================================================================
        case 'recall':
            apiRequireLevel(ROLE_REGISTRAR);

            $session_id = (int)($_POST['session_id'] ?? 0);
            $ticket_number = (int)($_POST['ticket_number'] ?? 0);

            $stmt = $dtb->prepare("
                UPDATE t_queue_tickets SET called_at = NOW(), called_by = :user_id 
                WHERE session_id = :session_id AND ticket_number = :number AND status = 'called'
            ");
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'session_id' => $session_id,
                'number' => $ticket_number
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Ticket #' . str_pad($ticket_number, 3, '0', STR_PAD_LEFT) . ' rappelé']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ticket introuvable ou pas en état appelé.']);
            }
            break;

        case 'mark_done':
            apiRequireLevel(ROLE_REGISTRAR);

            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            $dtb->prepare("UPDATE t_queue_tickets SET status = 'done', served_at = NOW() WHERE id = :id")
                 ->execute(['id' => $ticket_id]);

            echo json_encode(['success' => true, 'message' => 'Ticket traité']);
            break;

        case 'mark_skipped':
            apiRequireLevel(ROLE_REGISTRAR);

            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            $dtb->prepare("UPDATE t_queue_tickets SET status = 'skipped' WHERE id = :id")
                 ->execute(['id' => $ticket_id]);

            echo json_encode(['success' => true, 'message' => 'Ticket passé']);
            break;

        case 'mark_absent':
            apiRequireLevel(ROLE_REGISTRAR);

            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            $dtb->prepare("UPDATE t_queue_tickets SET status = 'absent' WHERE id = :id AND status IN ('called', 'serving')")
                 ->execute(['id' => $ticket_id]);

            echo json_encode(['success' => true, 'message' => 'Ticket marqué absent']);
            break;

        // =====================================================================
        // LISTE DES TICKETS (filtré par statut)
        // =====================================================================
        case 'get_tickets':
            $session_id = (int)($_REQUEST['session_id'] ?? 0);
            $status = $_REQUEST['status'] ?? 'all';
            $limit = min(100, max(1, (int)($_REQUEST['limit'] ?? 100)));

            if ($session_id <= 0) {
                $activeSession = $dtb->query("SELECT id FROM t_queue_sessions WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();
                $session_id = $activeSession ? $activeSession['id'] : 0;
            }

            $sql = "SELECT id, ticket_number, student_name, student_id, mention, status, created_at, called_at 
                    FROM t_queue_tickets WHERE session_id = :session_id";
            $params = ['session_id' => $session_id];

            if ($status !== 'all') {
                $sql .= " AND status = :status";
                $params['status'] = $status;
            }

            $sql .= " ORDER BY ticket_number ASC LIMIT $limit";

            $stmt = $dtb->prepare($sql);
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'tickets' => $tickets]);
            break;

        // =====================================================================
        // ENDPOINT TEMPS RÉEL (polling par l'écran public)
        // =====================================================================
        case 'public_status':
            // Pas besoin d'authentification - endpoint public
            $session = $dtb->query("SELECT * FROM t_queue_sessions WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                echo json_encode(['success' => false, 'active' => false, 'message' => 'Aucune session active']);
                break;
            }

            // Tickets actuellement appelés
            $calledStmt = $dtb->prepare("
                SELECT ticket_number, student_name, mention, called_at 
                FROM t_queue_tickets 
                WHERE session_id = :id AND status = 'called' 
                ORDER BY ticket_number ASC
            ");
            $calledStmt->execute(['id' => $session['id']]);
            $calledTickets = $calledStmt->fetchAll(PDO::FETCH_ASSOC);

            // Dernier batch call
            $lastBatch = $dtb->prepare("
                SELECT * FROM t_queue_batch_calls 
                WHERE session_id = :id 
                ORDER BY called_at DESC LIMIT 1
            ");
            $lastBatch->execute(['id' => $session['id']]);
            $lastBatchData = $lastBatch->fetch(PDO::FETCH_ASSOC);

            // Prochains en attente (pour l'affichage public)
            $waitingStmt = $dtb->prepare("
                SELECT ticket_number, student_name, mention 
                FROM t_queue_tickets 
                WHERE session_id = :id AND status = 'waiting' 
                ORDER BY ticket_number ASC LIMIT 8
            ");
            $waitingStmt->execute(['id' => $session['id']]);
            $waitingTickets = $waitingStmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats
            $stats = getQueueSessionStats($dtb, $session['id']);

            echo json_encode([
                'success' => true,
                'active' => true,
                'session' => [
                    'id' => $session['id'],
                    'name' => $session['session_name'],
                    'date' => $session['session_date']
                ],
                'called_tickets' => array_map(function($t) {
                    $t['formatted'] = str_pad($t['ticket_number'], 3, '0', STR_PAD_LEFT);
                    return $t;
                }, $calledTickets),
                'waiting_tickets' => array_map(function($t) {
                    $t['formatted'] = str_pad($t['ticket_number'], 3, '0', STR_PAD_LEFT);
                    return $t;
                }, $waitingTickets),
                'last_batch' => $lastBatchData,
                'stats' => $stats,
                'music' => (function() use ($dtb) {
                    $gm = getGlobalMusic($dtb);
                    $video_id = '';
                    if (!empty($gm['music_youtube_url']) && preg_match('/v=([a-zA-Z0-9_-]{11})/', $gm['music_youtube_url'], $m)) $video_id = $m[1];
                    $pl = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
                    return [
                        'youtube_url' => $gm['music_youtube_url'] ?? '',
                        'video_id' => $video_id,
                        'playing' => (int)($gm['music_playing'] ?? 0),
                        'volume' => (int)($gm['music_volume'] ?? 50),
                        'playlist' => is_array($pl) ? $pl : [],
                        'current_index' => (int)($gm['music_current_index'] ?? 0)
                    ];
                })(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // =====================================================================
        // MUSIQUE D'AMBIANCE (YouTube) - contrôlée par l'admin
        // =====================================================================
        case 'set_music':
            apiRequireLevel(ROLE_REGISTRAR);

            $youtube_url = trim($_POST['youtube_url'] ?? '');
            $playing = (int)($_POST['playing'] ?? 0);
            $volume = max(0, min(100, (int)($_POST['volume'] ?? 50)));

            // Extraire l'ID YouTube de l'URL
            $video_id = '';
            if (!empty($youtube_url)) {
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $m)) {
                    $video_id = $m[1];
                    $youtube_url = 'https://www.youtube.com/watch?v=' . $video_id;
                } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtube_url)) {
                    $video_id = $youtube_url;
                    $youtube_url = 'https://www.youtube.com/watch?v=' . $video_id;
                }
            }

            $dtb->prepare("UPDATE t_queue_global_music SET music_youtube_url = :url, music_playing = :playing, music_volume = :volume WHERE id = 1")
                ->execute(['url' => $youtube_url ?: null, 'playing' => $playing ? 1 : 0, 'volume' => $volume]);

            echo json_encode(['success' => true, 'message' => 'Musique mise \u00e0 jour', 'video_id' => $video_id]);
            break;

        case 'get_music':
            // Public - pas d'auth requise pour que l'\u00e9cran public puisse lire
            $gm = getGlobalMusic($dtb);

            $video_id = '';
            if (!empty($gm['music_youtube_url'])) {
                if (preg_match('/v=([a-zA-Z0-9_-]{11})/', $gm['music_youtube_url'], $m)) {
                    $video_id = $m[1];
                }
            }

            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (!is_array($playlist)) $playlist = [];

            echo json_encode([
                'success' => true,
                'music' => [
                    'youtube_url' => $gm['music_youtube_url'] ?? '',
                    'video_id' => $video_id,
                    'playing' => (int)($gm['music_playing'] ?? 0),
                    'volume' => (int)($gm['music_volume'] ?? 50),
                    'playlist' => $playlist,
                    'current_index' => (int)($gm['music_current_index'] ?? 0),
                    'video_fullscreen' => (int)($gm['video_fullscreen'] ?? 0)
                ]
            ]);
            break;

        case 'set_video_fullscreen':
            apiRequireLevel(ROLE_REGISTRAR);
            $fullscreen = (int)($_POST['fullscreen'] ?? 0);
            $dtb->prepare("UPDATE t_queue_global_music SET video_fullscreen = :fs WHERE id = 1")
                ->execute(['fs' => $fullscreen ? 1 : 0]);
            echo json_encode(['success' => true, 'video_fullscreen' => $fullscreen ? 1 : 0]);
            break;

        // =====================================================================
        // PLAYLIST - Gestion de la liste de lecture vidéo (GLOBALE)
        // =====================================================================
        case 'add_to_playlist':
            apiRequireLevel(ROLE_REGISTRAR);
            $youtube_url = trim($_POST['youtube_url'] ?? '');
            $title = trim($_POST['title'] ?? '');

            if (empty($youtube_url)) throw new Exception('URL YouTube requise.');

            $video_id = '';
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $m)) {
                $video_id = $m[1];
            } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtube_url)) {
                $video_id = $youtube_url;
            }
            if (empty($video_id)) throw new Exception('URL YouTube invalide.');

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (!is_array($playlist)) $playlist = [];

            // Récupérer le titre YouTube via oEmbed (gratuit, sans clé API)
            if (empty($title)) {
                $oembed_url = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $video_id . '&format=json';
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
                $oembed_json = @file_get_contents($oembed_url, false, $ctx);
                if ($oembed_json) {
                    $oembed_data = json_decode($oembed_json, true);
                    if (!empty($oembed_data['title'])) {
                        $title = $oembed_data['title'];
                    }
                }
            }

            $playlist[] = [
                'video_id' => $video_id,
                'url' => 'https://www.youtube.com/watch?v=' . $video_id,
                'title' => $title ?: 'Vidéo ' . (count($playlist) + 1)
            ];

            $currentIndex = (int)($gm['music_current_index'] ?? 0);
            $syncUrl = $gm['music_youtube_url'] ?? '';
            if (count($playlist) === 1) {
                $currentIndex = 0;
                $syncUrl = $playlist[0]['url'];
            }

            $dtb->prepare("UPDATE t_queue_global_music SET music_playlist = :playlist, music_current_index = :idx, music_youtube_url = :url WHERE id = 1")
                ->execute(['playlist' => json_encode($playlist), 'idx' => $currentIndex, 'url' => $syncUrl]);

            echo json_encode(['success' => true, 'playlist' => $playlist, 'current_index' => $currentIndex, 'video_id' => $video_id, 'message' => 'Vidéo ajoutée']);
            break;

        case 'remove_from_playlist':
            apiRequireLevel(ROLE_REGISTRAR);
            $index = (int)($_POST['index'] ?? -1);

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            $currentIndex = (int)($gm['music_current_index'] ?? 0);

            if ($index < 0 || $index >= count($playlist)) throw new Exception('Index invalide.');

            array_splice($playlist, $index, 1);

            $syncUrl = '';
            if (count($playlist) === 0) {
                $currentIndex = 0;
            } else {
                if ($currentIndex >= count($playlist)) $currentIndex = count($playlist) - 1;
                $syncUrl = $playlist[$currentIndex]['url'] ?? '';
            }

            $dtb->prepare("UPDATE t_queue_global_music SET music_playlist = :playlist, music_current_index = :idx, music_youtube_url = :url WHERE id = 1")
                ->execute(['playlist' => json_encode($playlist), 'idx' => $currentIndex, 'url' => $syncUrl ?: null]);

            echo json_encode(['success' => true, 'playlist' => $playlist, 'current_index' => $currentIndex, 'message' => 'Vidéo retirée']);
            break;

        case 'playlist_next':
            apiRequireLevel(ROLE_REGISTRAR);

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (empty($playlist)) throw new Exception('Playlist vide.');

            $currentIndex = ((int)($gm['music_current_index'] ?? 0) + 1) % count($playlist);
            $syncUrl = $playlist[$currentIndex]['url'] ?? '';

            $dtb->prepare("UPDATE t_queue_global_music SET music_current_index = :idx, music_youtube_url = :url, music_playing = 1 WHERE id = 1")
                ->execute(['idx' => $currentIndex, 'url' => $syncUrl]);

            echo json_encode(['success' => true, 'current_index' => $currentIndex, 'video_id' => $playlist[$currentIndex]['video_id'] ?? '']);
            break;

        case 'playlist_prev':
            apiRequireLevel(ROLE_REGISTRAR);

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (empty($playlist)) throw new Exception('Playlist vide.');

            $currentIndex = ((int)($gm['music_current_index'] ?? 0) - 1 + count($playlist)) % count($playlist);
            $syncUrl = $playlist[$currentIndex]['url'] ?? '';

            $dtb->prepare("UPDATE t_queue_global_music SET music_current_index = :idx, music_youtube_url = :url, music_playing = 1 WHERE id = 1")
                ->execute(['idx' => $currentIndex, 'url' => $syncUrl]);

            echo json_encode(['success' => true, 'current_index' => $currentIndex, 'video_id' => $playlist[$currentIndex]['video_id'] ?? '']);
            break;

        case 'set_playlist_index':
            apiRequireLevel(ROLE_REGISTRAR);
            $index = (int)($_POST['index'] ?? 0);

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if ($index < 0 || $index >= count($playlist)) throw new Exception('Index invalide.');

            $syncUrl = $playlist[$index]['url'] ?? '';

            $dtb->prepare("UPDATE t_queue_global_music SET music_current_index = :idx, music_youtube_url = :url, music_playing = 1 WHERE id = 1")
                ->execute(['idx' => $index, 'url' => $syncUrl]);

            echo json_encode(['success' => true, 'current_index' => $index, 'video_id' => $playlist[$index]['video_id'] ?? '']);
            break;

        case 'reorder_playlist':
            apiRequireLevel(ROLE_REGISTRAR);
            $from = (int)($_POST['from_index'] ?? -1);
            $to = (int)($_POST['to_index'] ?? -1);

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (!is_array($playlist)) $playlist = [];
            $currentIndex = (int)($gm['music_current_index'] ?? 0);

            if ($from < 0 || $from >= count($playlist) || $to < 0 || $to >= count($playlist)) throw new Exception('Index invalide.');

            // Move item
            $item = array_splice($playlist, $from, 1)[0];
            array_splice($playlist, $to, 0, [$item]);

            // Track the currently playing item
            if ($currentIndex === $from) {
                $currentIndex = $to;
            } elseif ($from < $currentIndex && $to >= $currentIndex) {
                $currentIndex--;
            } elseif ($from > $currentIndex && $to <= $currentIndex) {
                $currentIndex++;
            }

            $syncUrl = !empty($playlist[$currentIndex]) ? ($playlist[$currentIndex]['url'] ?? '') : '';

            $dtb->prepare("UPDATE t_queue_global_music SET music_playlist = :playlist, music_current_index = :idx, music_youtube_url = :url WHERE id = 1")
                ->execute(['playlist' => json_encode($playlist), 'idx' => $currentIndex, 'url' => $syncUrl]);

            echo json_encode(['success' => true, 'playlist' => $playlist, 'current_index' => $currentIndex, 'message' => 'Playlist réorganisée']);
            break;

        case 'refresh_playlist_titles':
            apiRequireLevel(ROLE_REGISTRAR);

            $gm = getGlobalMusic($dtb);
            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (!is_array($playlist) || empty($playlist)) throw new Exception('Playlist vide.');

            $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $updated = 0;
            foreach ($playlist as &$item) {
                if (!empty($item['video_id'])) {
                    $oembed_url = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $item['video_id'] . '&format=json';
                    $oembed_json = @file_get_contents($oembed_url, false, $ctx);
                    if ($oembed_json) {
                        $oembed_data = json_decode($oembed_json, true);
                        if (!empty($oembed_data['title'])) {
                            $item['title'] = $oembed_data['title'];
                            $updated++;
                        }
                    }
                }
            }
            unset($item);

            $dtb->prepare("UPDATE t_queue_global_music SET music_playlist = :playlist WHERE id = 1")
                ->execute(['playlist' => json_encode($playlist)]);

            echo json_encode(['success' => true, 'playlist' => $playlist, 'message' => $updated . ' titre(s) mis à jour']);
            break;

        case 'clear_playlist':
            apiRequireLevel(ROLE_REGISTRAR);

            $dtb->prepare("UPDATE t_queue_global_music SET music_playlist = NULL, music_current_index = 0, music_youtube_url = NULL, music_playing = 0 WHERE id = 1")
                ->execute();

            echo json_encode(['success' => true, 'message' => 'Playlist vidée']);
            break;

        case 'playlist_auto_next':
            // Public endpoint - auto-advance when video ends on display screen
            $gm = getGlobalMusic($dtb);

            $playlist = !empty($gm['music_playlist']) ? json_decode($gm['music_playlist'], true) : [];
            if (!is_array($playlist)) $playlist = [];

            if (count($playlist) <= 1) {
                if (count($playlist) === 1) {
                    echo json_encode(['success' => true, 'action' => 'loop', 'video_id' => $playlist[0]['video_id'] ?? '']);
                } else {
                    echo json_encode(['success' => true, 'action' => 'stop']);
                }
                break;
            }

            $newIndex = ((int)($gm['music_current_index'] ?? 0) + 1) % count($playlist);
            $newUrl = $playlist[$newIndex]['url'] ?? '';

            $dtb->prepare("UPDATE t_queue_global_music SET music_current_index = :idx, music_youtube_url = :url WHERE id = 1")
                ->execute(['idx' => $newIndex, 'url' => $newUrl]);

            echo json_encode(['success' => true, 'action' => 'next', 'current_index' => $newIndex, 'video_id' => $playlist[$newIndex]['video_id'] ?? '']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue: ' . $action]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// =========================================================================
// FONCTIONS UTILITAIRES
// =========================================================================
function getQueueSessionStats(PDO $dtb, int $session_id): array {
    $stats = $dtb->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status = 'called' THEN 1 ELSE 0 END) as called,
            SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) as serving,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
        FROM t_queue_tickets WHERE session_id = :id
    ");
    $stats->execute(['id' => $session_id]);
    return $stats->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'waiting' => 0, 'called' => 0,
        'serving' => 0, 'done' => 0, 'skipped' => 0, 'absent' => 0
    ];
}
