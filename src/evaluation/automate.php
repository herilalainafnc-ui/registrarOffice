<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
require_once('../data/cours.sigle.php');
coursSigleAliasInstaller($dtb);
initMiddleware($dtb);

// Configuration et Helpers
$currentUser = Middleware::getCurrentUser();
if ($currentUser['level'] > 3 && $currentUser['level'] != 5) {
    die("Accès non autorisé.");
}

$action = $_POST['action'] ?? null;
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$message = '';
$generated_links = [];
$preview_students = null;

// ======================================================
// 18 QUESTIONS INSTITUTIONNELLES - Formulaire UAZ
// ======================================================
$institutional_questions = [
    // Q1 : échelle Médiocre → Excellent
    ['text' => "APPRECIATION GLOBALE DU COURS",
     'type' => 'rating',
     'options' => ["1- Médiocre", "2- Passable", "3- Bon", "4- Excellent"]],
    // Q2-Q16 : échelle pas du tout → entièrement d'accord
    ['text' => "1- Un descriptif du cours m'a été donné au début du cours", 'type' => 'rating'],
    ['text' => "2- LE COURS: Les objectifs du cours ont été atteints", 'type' => 'rating'],
    ['text' => "3- LE COURS: Le contenu du cours a été bien couvert durant la période allouée", 'type' => 'rating'],
    ['text' => "4- LE COURS: Les documents et les supports de cours donnent assez d'explication pour comprendre le cours", 'type' => 'rating'],
    ['text' => "5- LE PROFESSEUR: L'enseignant maîtrise la discipline enseignée", 'type' => 'rating'],
    ['text' => "6- LE PROFESSEUR: L'enseignant est bien organisé et a une bonne méthode d'enseignement", 'type' => 'rating'],
    ['text' => "7- LE PROFESSEUR: Les activités d'apprentissage étaient cohérentes avec les compétences à développer, elles étaient logiques et dans un langage facile à comprendre", 'type' => 'rating'],
    ['text' => "8- LE PROFESSEUR: Le planning et le rythme du cours étaient satisfaisants", 'type' => 'rating'],
    ['text' => "09- LE PROFESSEUR: Le cours était vivant, interactif", 'type' => 'rating'],
    ['text' => "10- LE PROFESSEUR: Est-ce que vous êtes satisfait de votre participation dans le cours ?", 'type' => 'rating'],
    ['text' => "11- LE PROFESSEUR: L'enseignant nous encourage à participer et à poser des questions", 'type' => 'rating'],
    ['text' => "12- LE PROFESSEUR: L'enseignant nous transmet des valeurs morales et spirituelles", 'type' => 'rating'],
    ['text' => "13- LE PROFESSEUR: Les modes d'évaluation des connaissances me paraissaient appropriées (test, devoirs, QCM, examen, projet, présentation ...)", 'type' => 'rating'],
    ['text' => "14- LE PROFESSEUR: Les travaux à faire contiennent suffisamment d'instructions et de détails pour m'aider à comprendre la tâche à effectuer", 'type' => 'rating'],
    ['text' => "15- LE PROFESSEUR: Avez-vous le sentiment d'être évalué correctement et équitablement ?", 'type' => 'rating'],
    // Q17-Q18 : champs texte
    ['text' => "16- Points forts", 'type' => 'paragraph'],
    ['text' => "17- Points à améliorer et suggestions selon les étudiants", 'type' => 'paragraph'],
];
$default_rating_options = [
    "(1)= pas du tout",
    "(2)= plutôt pas d'accord",
    "(3)= plutôt d'accord",
    "(4)= entièrement d'accord"
];
function selectEvaluationStudents(array $students): array {
    $total = count($students);
    if ($total <= 5) {
        return [
            'students' => $students,
            'total' => $total,
            'selected' => $total,
            'sample_size' => $total,
            'mode' => 'all',
        ];
    }

    $sampleSize = (int)ceil($total * 0.35);
    $sampleSize = max(5, $sampleSize);
    $sampleSize = min($total, $sampleSize);

    $shuffledStudents = $students;
    shuffle($shuffledStudents);
    $selectedStudents = array_slice($shuffledStudents, 0, $sampleSize);

    usort($selectedStudents, function ($left, $right) {
        $leftName = trim(($left['student_nom'] ?? '') . ' ' . ($left['student_prenom'] ?? ''));
        $rightName = trim(($right['student_nom'] ?? '') . ' ' . ($right['student_prenom'] ?? ''));
        return strcasecmp($leftName, $rightName);
    });

    return [
        'students' => $selectedStudents,
        'total' => $total,
        'selected' => count($selectedStudents),
        'sample_size' => $sampleSize,
        'mode' => 'sample',
    ];
}

// ---- Action : aperçu des étudiants ciblés (sans générer) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'preview_students') {
    $cours_id_prev = intval($_POST['cours_id'] ?? 0);
    $session_val_prev = intval($_POST['session'] ?? 0);
    if ($cours_id_prev && $session_val_prev) {
        $stmt_prev = $dtb->prepare("
            SELECT n.student_id, e.student_nom, e.student_prenom, e.student_email
            FROM t_2023_notes n
            JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
            WHERE n.id_cours = ? AND n.session_id = ? AND n.ajout = 1 AND n.remove = 0
            ORDER BY e.student_nom, e.student_prenom
        ");
        $stmt_prev->execute([$cours_id_prev, $session_val_prev]);
        $preview_students = selectEvaluationStudents($stmt_prev->fetchAll());
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'students' => $preview_students['students'] ?? [], 'total' => $preview_students['total'] ?? 0, 'selected' => $preview_students['selected'] ?? 0, 'sample_size' => $preview_students['sample_size'] ?? 0, 'mode' => $preview_students['mode'] ?? 'all'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ---- Action : générer le formulaire + liens ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'automate_form') {
    $teacher_id = $_POST['teacher_id'] ?? 0;
    $cours_id = intval($_POST['cours_id'] ?? 0);
    $session_val = intval($_POST['session'] ?? 0);
    
    // 1. Récupération des infos du cours
    $stmt = $dtb->prepare("SELECT title FROM t_2023_cours WHERE id = ?");
    $stmt->execute([$cours_id]);
    $course_title = $stmt->fetchColumn() ?: "Cours #$cours_id";
    
    // 2. Création du formulaire (ou réutilisation si déjà existant pour ce cours+session)
    $form_title = "Évaluation : " . $course_title;
    $form_desc = "Formulaire d'évaluation institutionnel pour le cours : " . $course_title;

    $stmtCheck = $dtb->prepare("SELECT id FROM rg_eval_forms WHERE cours_id = ? AND session_id = ? LIMIT 1");
    $stmtCheck->execute([$cours_id, $session_val]);
    $existingFormId = $stmtCheck->fetchColumn();

    if ($existingFormId) {
        $form_id = (int)$existingFormId;
        $message .= "Un formulaire existe déjà pour ce cours/session (ID: <strong>$form_id</strong>). Les liens sont régénérés.<br>";
        $skipQuestions = true;
    } else {
        $stmt = $dtb->prepare("INSERT INTO rg_eval_forms (title, description, cours_id, session_id, is_published, created_by) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$form_title, $form_desc, $cours_id, $session_val, $currentUser['id']]);
        $form_id = (int)$dtb->lastInsertId();
        $skipQuestions = false;
    }
    
    // 3. Questions institutionnelles standard (18 questions)
    $questions_to_add = $institutional_questions;
    
    // 4. Insertion des questions dans la BDD (uniquement si nouveau formulaire)
    if (!$skipQuestions) {
        $position = 1;
        foreach ($questions_to_add as $q) {
            $stmt_q = $dtb->prepare("INSERT INTO rg_eval_questions (form_id, question_text, question_type, is_required, display_order) VALUES (?, ?, ?, 1, ?)");
            $stmt_q->execute([$form_id, $q['text'], $q['type'], $position]);
            $question_id = $dtb->lastInsertId();
            if ($q['type'] === 'rating') {
                $opts = $q['options'] ?? $default_rating_options;
                $opt_pos = 1;
                foreach ($opts as $opt) {
                    $stmt_opt = $dtb->prepare("INSERT INTO rg_eval_options (question_id, option_text, display_order) VALUES (?, ?, ?)");
                    $stmt_opt->execute([$question_id, $opt, $opt_pos]);
                    $opt_pos++;
                }
            }
            $position++;
        }
    }
    
    // 5. Récupération des étudiants inscrits 
    $stmt_std = $dtb->prepare("
        SELECT n.student_id, e.student_nom, e.student_prenom, e.student_email 
        FROM t_2023_notes n
        JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        WHERE n.id_cours = ? AND n.session_id = ? AND n.ajout = 1 AND n.remove = 0
    ");
    $stmt_std->execute([$cours_id, $session_val]);
    $selection = selectEvaluationStudents($stmt_std->fetchAll());
    $inscribed = $selection['students'];
    
    $message .= "Formulaire <strong>$form_title</strong> généré avec succès ! (" . count($questions_to_add) . " questions ajoutées).<br>";
    $message .= "<strong>" . $selection['total'] . "</strong> étudiants inscrits trouvés dans ce cours.<br>";
    if ($selection['mode'] === 'all') {
        $message .= "Tous les étudiants participent à l'évaluation car l'effectif est inférieur ou égal à 5.<br>";
    } else {
        $message .= "<strong>" . $selection['selected'] . "</strong> étudiant(s) sélectionné(s) aléatoirement pour l'évaluation (35% minimum 5).<br>";
    }
    
    // 6. Génération des liens (Simulation d'automatisation d'envoi)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
    $evaluation_link = $base_url . "/evaluation/form/" . $form_id;

    foreach ($inscribed as $std) {
        if (!empty($std['student_email'])) {
            $generated_links[] = [
                'name' => $std['student_nom'] . ' ' . $std['student_prenom'],
                'email' => $std['student_email'],
                'url' => $evaluation_link . "?email=" . urlencode($std['student_email'])
            ];
        }
    }
    
    if (empty($inscribed)) {
        $message .= "<span class='text-red-500'>Attention : Aucun étudiant trouvé pour ce cours et cette session.</span>";
    }

    // 7. Envoi des emails personnalisés à chaque étudiant
    if (!empty($generated_links)) {
        @set_time_limit(0);

        $emailSent   = 0;
        $emailFailed = 0;
        $emailNoAddr = 0;

        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            // Email désactivé (ex : développement local)
            $message .= "<span class='text-yellow-400'>Envoi email désactivé (MAIL_ENABLED=false). Liens générés uniquement.</span><br>";
        } else {
            // Charger PHPMailer (vendor local ou production)
            $phpmailerSrc = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
            $phpmailerSrc .= '/vendor/phpmailer/phpmailer/src';
            $usePhpMailer = is_file($phpmailerSrc . '/PHPMailer.php');
            if ($usePhpMailer) {
                require_once $phpmailerSrc . '/Exception.php';
                require_once $phpmailerSrc . '/PHPMailer.php';
                require_once $phpmailerSrc . '/SMTP.php';
            }

            $logoPath     = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2)) . '/file/UAZLogo.png';
            $logoBinary   = @file_get_contents($logoPath);
            $logoCid      = 'logo_eval_uaz';
            $logoFileName = 'UAZLogo.png';
            $logoInlineTag = ($logoBinary !== false)
                ? '<img src="cid:' . $logoCid . '" alt="UAZ" style="max-height:56px;max-width:200px;">'
                : '<span style="color:#e8f1f8;font-size:16px;font-weight:700;">UAZ</span>';

            foreach ($generated_links as $link) {
                $recipient = strtolower(trim($link['email']));
                if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $emailNoAddr++;
                    continue;
                }

                $studentName = htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8');
                $evalUrl     = $link['url'];
                $courseTitle = htmlspecialchars($course_title, ENT_QUOTES, 'UTF-8');
                $subject     = "Évaluation du cours : " . $course_title;

                $bodyHtml = '
<p style="margin:0 0 14px;">Bonjour <strong>' . $studentName . '</strong>,</p>
<p style="margin:0 0 14px;">L\'Université Adventiste Zurcher vous invite à compléter le formulaire d\'évaluation pour le cours :</p>
<p style="margin:0 0 18px;font-size:16px;font-weight:700;color:#1e3a6e;">' . $courseTitle . '</p>
<p style="margin:0 0 18px;">Votre avis est important pour améliorer la qualité de l\'enseignement. L\'évaluation est <strong>anonyme</strong> et ne prend que quelques minutes.</p>
<div style="text-align:center;margin:24px 0;">
  <a href="' . $evalUrl . '" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;">
    Remplir l\'évaluation
  </a>
</div>
<p style="margin:0;font-size:12px;color:#64748b;">Ou copiez ce lien dans votre navigateur :<br><a href="' . $evalUrl . '" style="color:#1d4ed8;word-break:break-all;">' . $evalUrl . '</a></p>';

                $plainText = "Bonjour {$link['name']},\n\nL'Université Adventiste Zurcher vous invite à compléter le formulaire d'évaluation pour le cours :\n{$course_title}\n\nLien : {$evalUrl}\n\nMessage automatique, merci de ne pas répondre à cet email.";

                $htmlFull = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;">'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:20px 0;background:#f8fafc;"><tr><td align="center">'
                    . '<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">'
                    . '<tr><td align="center" style="background:#0a1628;padding:18px 24px;">'
                    . '<div>' . $logoInlineTag . '</div>'
                    . '<div style="color:#e8f1f8;font-size:18px;font-weight:700;margin-top:10px;">Université Adventiste Zurcher</div>'
                    . '</td></tr>'
                    . '<tr><td style="padding:24px;color:#0f172a;font-size:15px;line-height:1.6;">' . $bodyHtml . '</td></tr>'
                    . '</table>'
                    . '<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="margin-top:10px;"><tr>'
                    . '<td align="center" style="font-size:12px;color:#64748b;line-height:1.7;padding:0 10px;">'
                    . 'Message automatique, merci de ne pas répondre à cet email.<br>'
                    . 'Contact: <a href="mailto:registrar@zurcher.edu.mg" style="color:#0b63ce;">registrar@zurcher.edu.mg</a> | Tel: 034 38 180 81<br>'
                    . '&copy; ' . date('Y') . ' Université Adventiste Zurcher'
                    . '</td></tr></table>'
                    . '</td></tr></table></body></html>';

                $ok = false;
                if ($usePhpMailer) {
                    try {
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = defined('SMTP_HOST')   ? SMTP_HOST   : 'smtp.gmail.com';
                        $mail->SMTPAuth   = defined('SMTP_AUTH')   ? SMTP_AUTH   : true;
                        $mail->Username   = defined('SMTP_USER')   ? SMTP_USER   : 'no-reply@zurcher.edu.mg';
                        $mail->Password   = defined('SMTP_PASS')   ? SMTP_PASS   : '';
                        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = defined('SMTP_PORT')   ? SMTP_PORT   : 587;
                        $mail->CharSet    = 'UTF-8';
                        $mail->setFrom(
                            defined('SMTP_FROM')      ? SMTP_FROM      : 'no-reply@zurcher.edu.mg',
                            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Université Adventiste Zurcher'
                        );
                        $mail->addAddress($recipient, $link['name']);
                        $mail->Subject = $subject;
                        $mail->isHTML(true);
                        $mail->Body    = $htmlFull;
                        $mail->AltBody = $plainText;
                        if ($logoBinary !== false) {
                            $mail->addStringEmbeddedImage($logoBinary, $logoCid, $logoFileName, 'base64', 'image/png');
                        }
                        $mail->send();
                        $ok = true;
                    } catch (\PHPMailer\PHPMailer\Exception $e) {
                        $ok = false;
                    }
                } else {
                    // Fallback : mail() natif (fonctionne si le serveur a un MTA configuré)
                    $safeSubject = mb_encode_mimeheader($subject, 'UTF-8', 'Q');
                    $boundary    = 'mixed_' . md5(microtime(true) . $recipient);
                    $boundaryAlt = 'alt_'   . md5(microtime(true) . 'alt' . $recipient);
                    $headers     = implode("\r\n", [
                        'MIME-Version: 1.0',
                        'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"',
                        'From: ' . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'UAZ') . ' <' . (defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@zurcher.edu.mg') . '>',
                        'Reply-To: ' . (defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@zurcher.edu.mg'),
                        'X-Mailer: PHP/' . phpversion(),
                    ]);
                    $body  = '--' . $boundaryAlt . "\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $plainText . "\r\n";
                    $body .= '--' . $boundaryAlt . "\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"  . $htmlFull . "\r\n";
                    $body .= '--' . $boundaryAlt . "--\r\n";
                    $ok = @mail($recipient, $safeSubject, $body, $headers);
                    if (!$ok) { usleep(50000); $ok = @mail($recipient, $safeSubject, $body, $headers); }
                }
                $ok ? $emailSent++ : $emailFailed++;
            }

            if ($emailSent > 0) {
                $message .= "<span class='text-green-400'><strong>$emailSent</strong> email(s) envoyé(s) avec succès.</span><br>";
            }
            if ($emailFailed > 0) {
                $message .= "<span class='text-red-400'><strong>$emailFailed</strong> email(s) en échec (vérifiez la config SMTP).</span><br>";
            }
            if ($emailNoAddr > 0) {
                $message .= "<span class='text-yellow-400'><strong>$emailNoAddr</strong> étudiant(s) sans adresse email valide.</span><br>";
            }
        } // end MAIL_ENABLED
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok'          => !empty($generated_links),
            'links'       => $generated_links,
            'total'       => $selection['total'] ?? count($inscribed),
            'selected'    => $selection['selected'] ?? count($inscribed),
            'sample_size' => $selection['sample_size'] ?? count($inscribed),
            'mode'        => $selection['mode'] ?? 'all',
            'emailSent'   => $emailSent ?? 0,
            'emailFailed' => $emailFailed ?? 0,
            'emailNoAddr' => $emailNoAddr ?? 0,
            'message'     => $message,
            'form_id'     => $form_id ?? 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Récupérer la liste des profs pour le formulaire
// id_teacher dans cours référence teacher.uid (pas teacher_id)
$teachers = $dtb->query("SELECT uid as id, CONCAT(name, ' ', lastName) as nom_complet FROM teacher ORDER BY name")->fetchAll();
$courses = $dtb->query("SELECT id as id_cours, title, Sigle, sigle_affiche, semester, dep_desc, id_teacher FROM t_2023_cours WHERE title IS NOT NULL AND remove != 1 ORDER BY title, semester")->fetchAll();
$sessions = $dtb->query("SELECT session_id, session_name, session_year FROM t_2023_session ORDER BY session_id DESC")->fetchAll();

// Map course_id → sessions that have actual enrollments (with student count)
$course_sessions_raw = $dtb->query("
    SELECT n.id_cours, n.session_id, COUNT(DISTINCT n.student_id) as cnt
    FROM t_2023_notes n
    WHERE n.ajout = 1 AND n.remove = 0
    GROUP BY n.id_cours, n.session_id
    ORDER BY n.session_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$course_sessions_map = [];
foreach ($course_sessions_raw as $row) {
    $course_sessions_map[(string)$row['id_cours']][] = [
        'session_id' => (int)$row['session_id'],
        'cnt'        => (int)$row['cnt'],
    ];
}

// Build session lookup: session_id → {name, year}
$sessions_lookup = [];
foreach ($sessions as $s) {
    $sessions_lookup[$s['session_id']] = $s['session_name'] . ' ' . $s['session_year'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php');
    // $br_one_color n'est pas défini dans head.php (seulement br_two_color et br_three_color)
    $br_one_color = $br_two_color;
    ?>
    <title>Automatisation - Formulaires</title>
</head>
<body class="<?= $bg_three_color ?> sm:text-xs lg:text-sm">
    <div class="h-screen w-full <?= $bg_three_color ?>">
        
        <!-- TOP BAR --><?php require('../init/topbar.php');?>

        <div class="w-full flex flex-col lg:flex-row">
            
            <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

            <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            
                <div class="w-full px-0.5 flex flex-1 overflow-hidden">
                    <div class="<?= $bg_one_color ?> my-1 mx-0.5 w-full p-4 <?= $txt_one_color ?> overflow-auto">
                        
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h1 class="text-3xl font-bold">Automatisation d'Envoi</h1>
                                <p class="mt-2 text-sm <?= $txt_two_color ?>">Générer le formulaire institutionnel (18 questions) pour un cours et préparer les liens personnalisés pour chaque étudiant inscrit.</p>
                            </div>
                            <!-- a href="/evaluation/admin" class="flex items-center px-4 py-2 text-sm <?= $txt_one_color ?> <?= $bg_two_color ?> border <?= $br_one_color ?> rounded hover:bg-slate-500 transition-colors shadow-sm">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Retour
                            </a -->
                        </div>
            
                        <?php if ($message): ?>
                        <div class="p-4 mb-6 text-blue-100 bg-blue-900 rounded-lg border border-blue-500">
                            <?= $message ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                            <!-- FORMULAIRE DE GENERATION -->
                            <div class="p-6 <?= $bg_two_color ?> rounded-lg shadow-sm border <?= $br_one_color ?>">
                                <h2 class="mb-4 text-xl font-semibold border-b <?= $br_one_color ?> pb-2">1. Paramètres du Cours</h2>
                                <form method="POST" action="">
                                    
                                    <!-- RECHERCHE PROFESSEUR -->
                                    <div class="mb-4">
                                        <label class="block mb-2 text-sm font-medium">Professeur (Filtre optionnel)</label>
                                        <input type="hidden" id="teacher_id" name="teacher_id" value="">
                                        <div class="relative" id="teacher-wrapper">
                                            <input type="text" id="teacher_search" autocomplete="off"
                                                placeholder="Rechercher un professeur..."
                                                class="block w-full border <?= $br_one_color ?> rounded-md <?= $bg_one_color ?> <?= $txt_one_color ?> p-2 pr-8"
                                                oninput="filterTeachers()" onfocus="openDropdown('teacher-list')" />
                                            <button type="button" onclick="clearTeacher()" class="absolute right-2 top-2 text-gray-400 hover:text-red-400 text-xs">✕</button>
                                            <ul id="teacher-list" class="absolute z-20 w-full max-h-48 overflow-y-auto border <?= $br_one_color ?> <?= $bg_one_color ?> rounded-md shadow-lg mt-1" style="display:none;">
                                                <li class="px-3 py-1.5 cursor-pointer hover:bg-blue-700 text-sm" data-id="" onclick="selectTeacher('', 'Tous les professeurs')">— Tous les professeurs —</li>
                                                <?php foreach($teachers as $t): ?>
                                                <li class="px-3 py-1.5 cursor-pointer hover:bg-blue-700 text-sm teacher-option" data-id="<?= $t['id'] ?>" onclick="selectTeacher('<?= $t['id'] ?>', <?= htmlspecialchars(json_encode($t['nom_complet']), ENT_QUOTES) ?>)">
                                                    <?= htmlspecialchars($t['nom_complet']) ?>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- RECHERCHE COURS -->
                                    <div class="mb-4">
                                        <label class="block mb-2 text-sm font-medium">Cours <span class="text-red-400">*</span></label>
                                        <input type="hidden" id="cours_id" name="cours_id" value="" required>
                                        <div class="relative" id="cours-wrapper">
                                            <input type="text" id="cours_search" autocomplete="off"
                                                placeholder="Rechercher un cours..."
                                                class="block w-full border <?= $br_one_color ?> rounded-md <?= $bg_one_color ?> <?= $txt_one_color ?> p-2 pr-8"
                                                oninput="filterCours()" onfocus="openDropdown('cours-list')" />
                                            <button type="button" onclick="clearCours()" class="absolute right-2 top-2 text-gray-400 hover:text-red-400 text-xs">✕</button>
                                            <ul id="cours-list" class="absolute z-20 w-full max-h-48 overflow-y-auto border <?= $br_one_color ?> <?= $bg_one_color ?> rounded-md shadow-lg mt-1" style="display:none;">
                                                <?php foreach($courses as $c):
                                                    $sem_badge = $c['semester'] == 1 ? 'S1' : ($c['semester'] == 2 ? 'S2' : 'S' . $c['semester']);
                                                    $label_full = htmlspecialchars($c['title'] . ' [' . coursSigleAffiche($c) . ' ' . $sem_badge . ']');
                                                    $select_label = $c['title'] . ' [' . coursSigleAffiche($c) . ' ' . $sem_badge . ']';
                                                ?>
                                                <li class="px-3 py-1.5 cursor-pointer hover:bg-blue-700 text-sm cours-option"
                                                    data-id="<?= $c['id_cours'] ?>"
                                                    data-teacher="<?= $c['id_teacher'] ?>"
                                                    data-label="<?= $label_full ?>"
                                                    onclick="selectCours('<?= $c['id_cours'] ?>', <?= htmlspecialchars(json_encode($select_label), ENT_QUOTES) ?>)">
                                                    <span class="font-medium"><?= htmlspecialchars($c['title']) ?></span>
                                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-blue-900 text-blue-200"><?= htmlspecialchars(coursSigleAffiche($c)) ?></span>
                                                    <span class="ml-1 text-xs px-1 py-0.5 rounded bg-slate-700 text-slate-300"><?= $sem_badge ?></span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <p id="no-courses-msg" class="mt-1 text-xs text-yellow-400" style="display:none;">Aucun cours pour ce professeur — tous affichés.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="block mb-2 text-sm font-medium">Session académique</label>
                                        <select name="session" id="session_select" class="block w-full border <?= $br_one_color ?> rounded-md shadow-sm <?= $bg_one_color ?> <?= $txt_one_color ?> p-2" required>
                                            <option value="">-- Choisir un cours d'abord --</option>
                                        </select>
                                        <p id="session-hint" class="mt-1 text-xs <?= $txt_two_color ?>">Sélectionnez un cours pour voir ses sessions disponibles.</p>
                                    </div>
                                    
                                    <div class="flex gap-2 mt-2">
                                        <button type="button" id="btn-preview" onclick="ajaxAction('preview_students')"
                                            class="flex-1 px-4 py-2 text-white bg-slate-600 rounded-md hover:bg-slate-500 flex items-center justify-center gap-2 text-sm">
                                            <i class="bi bi-eye"></i> Vérifier les étudiants
                                        </button>
                                        <button type="button" id="btn-generate" onclick="ajaxAction('automate_form')"
                                            class="flex-1 px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 flex items-center justify-center gap-2 text-sm">
                                            <i class="bi bi-send"></i> Générer &amp; Envoyer
                                        </button>
                                    </div>
                                </form>
                            </div>
                
                            <!-- PANNEAU DROIT : Aperçu formulaire + Étudiants -->
                            <div class="flex flex-col gap-4">

                                <!-- APERÇU DU FORMULAIRE (toujours visible) -->
                                <div class="p-5 <?= $bg_two_color ?> rounded-lg shadow-sm border <?= $br_one_color ?>">
                                    <h2 class="mb-3 text-base font-semibold flex items-center gap-2">
                                        <i class="bi bi-list-check text-blue-400"></i>
                                        Formulaire institutionnel <span class="text-xs font-normal <?= $txt_two_color ?> ml-1">(18 questions)</span>
                                    </h2>
                                    <div class="space-y-1 overflow-y-auto max-h-64 pr-1" id="questions-preview">
                                        <?php foreach ($institutional_questions as $i => $q): ?>
                                        <div class="flex items-start gap-2 py-1 border-b <?= $br_one_color ?> last:border-0">
                                            <span class="text-xs font-bold text-blue-400 w-6 shrink-0 pt-0.5"><?= $i + 1 ?></span>
                                            <span class="text-xs <?= $txt_one_color ?> leading-snug"><?= htmlspecialchars($q['text']) ?></span>
                                            <span class="ml-auto shrink-0 text-xs px-1.5 py-0.5 rounded <?= $q['type'] === 'rating' ? 'bg-blue-900 text-blue-200' : 'bg-purple-900 text-purple-200' ?>">
                                                <?= $q['type'] === 'rating' ? '1→4' : 'texte' ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- ÉTUDIANTS CIBLÉS -->
                                <div class="p-5 <?= $bg_two_color ?> rounded-lg shadow-sm border <?= $br_one_color ?> flex-1">
                                    <h2 class="mb-3 text-base font-semibold flex items-center gap-2">
                                        <i class="bi bi-people text-green-400"></i>
                                        Étudiants ciblés
                                    </h2>
                                    <div id="students-panel">
                                        <div class="flex flex-col items-center justify-center h-24 text-sm <?= $txt_two_color ?> border-2 border-dashed <?= $br_one_color ?> rounded gap-2">
                                            <i class="bi bi-arrow-left text-lg"></i>
                                            Sélectionnez un cours et une session, puis cliquez <em>Vérifier</em>.
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
                
                <?php require('../init/footer.php'); ?>
            </div>
        </div>
    </div>
    
    <script>
    // ---- Utilitaires dropdown ----
    function openDropdown(id) {
        document.getElementById(id).style.display = 'block';
    }
    document.addEventListener('click', function(e) {
        ['teacher-list','cours-list'].forEach(function(id) {
            var el = document.getElementById(id);
            var wrapper = el ? el.closest('.relative') : null;
            if (el && wrapper && !wrapper.contains(e.target)) el.style.display = 'none';
        });
    });

    // ---- Professeur ----
    function filterTeachers() {
        var q = document.getElementById('teacher_search').value.toLowerCase();
        document.querySelectorAll('.teacher-option').forEach(function(li) {
            li.style.display = li.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
        openDropdown('teacher-list');
    }
    function selectTeacher(id, label) {
        document.getElementById('teacher_id').value = id;
        document.getElementById('teacher_search').value = label;
        document.getElementById('teacher-list').style.display = 'none';
        applyTeacherFilterToCours(id);
    }
    function clearTeacher() {
        selectTeacher('', '');
        document.getElementById('teacher_search').value = '';
    }

    // ---- Data from PHP ----
    var courseSessionsMap = <?= json_encode($course_sessions_map,  JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
    var sessionsLookup   = <?= json_encode($sessions_lookup,       JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
    var allSessions      = <?= json_encode(array_values($sessions),JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '[]' ?>;

    // ---- Session dropdown ----
    function updateSessionDropdown(courseId) {
        var sel = document.getElementById('session_select');
        var hint = document.getElementById('session-hint');
        sel.innerHTML = '';

        var courseSessions = courseId ? (courseSessionsMap[String(courseId)] || []) : null;

        if (!courseId) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- Choisir un cours d\'abord --';
            sel.appendChild(opt);
            hint.textContent = 'Sélectionnez un cours pour voir ses sessions disponibles.';
            return;
        }

        if (!courseSessions || courseSessions.length === 0) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- Aucune inscription trouvée pour ce cours --';
            sel.appendChild(opt);
            hint.textContent = 'Aucun étudiant inscrit n\'a été trouvé pour ce cours dans aucune session.';
            hint.className = 'mt-1 text-xs text-red-400';
            return;
        }

        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = '-- Choisir une session --';
        sel.appendChild(opt0);

        courseSessions.forEach(function(cs) {
            var label = sessionsLookup[cs.session_id];
            if (!label) label = 'Session #' + cs.session_id;
            var opt = document.createElement('option');
            opt.value = cs.session_id;
            opt.textContent = label + ' — ' + cs.cnt + ' étudiant(s)';
            sel.appendChild(opt);
        });

        hint.textContent = courseSessions.length + ' session(s) avec des inscrits.';
        hint.className = 'mt-1 text-xs text-green-400';
    }

    // ---- Cours ----
    function applyTeacherFilterToCours(teacherId) {
        var items = document.querySelectorAll('.cours-option');
        var visible = 0;
        items.forEach(function(li) {
            var match = !teacherId || String(li.dataset.teacher) === String(teacherId);
            li.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        document.getElementById('no-courses-msg').style.display = (visible === 0 && teacherId) ? 'block' : 'none';
        if (visible === 0 && teacherId) {
            items.forEach(function(li) { li.style.display = ''; });
        }
        clearCours();
    }
    function filterCours() {
        var q = document.getElementById('cours_search').value.toLowerCase();
        var teacherId = document.getElementById('teacher_id').value;
        document.querySelectorAll('.cours-option').forEach(function(li) {
            var textMatch = li.dataset.label.toLowerCase().includes(q);
            var teacherMatch = !teacherId || String(li.dataset.teacher) === String(teacherId);
            li.style.display = (textMatch && teacherMatch) ? '' : 'none';
        });
        openDropdown('cours-list');
    }
    function selectCours(id, label) {
        document.getElementById('cours_id').value = id;
        document.getElementById('cours_search').value = label;
        document.getElementById('cours-list').style.display = 'none';
        updateSessionDropdown(id);
    }
    function clearCours() {
        document.getElementById('cours_id').value = '';
        document.getElementById('cours_search').value = '';
        updateSessionDropdown('');
    }

    // ---- Copier lien ----
    function copyToClipboard(elementId) {
        var copyText = document.getElementById(elementId);
        if (!copyText) return;
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value)
            .then(function() { showToast('Lien copié dans le presse-papiers !', 'success'); })
            .catch(function() { showToast('Erreur lors de la copie.', 'error'); });
    }

    // ---- Thème PHP → JS ----
    var themeClasses = {
        bgOne:  '<?= $bg_one_color ?>',
        bgTwo:  '<?= $bg_two_color ?>',
        txtOne: '<?= $txt_one_color ?>',
        brOne:  '<?= $br_one_color ?>',
        txtTwo: '<?= $txt_two_color ?>',
    };

    // ---- Toast notifications ----
    function showToast(msg, type) {
        var existing = document.getElementById('automate-toast');
        if (existing) existing.remove();
        var colors = type === 'error'   ? 'bg-red-900 border-red-500 text-red-100'
                   : type === 'success' ? 'bg-green-900 border-green-500 text-green-100'
                   :                      'bg-blue-900 border-blue-500 text-blue-100';
        var d = document.createElement('div');
        d.id = 'automate-toast';
        d.className = 'fixed top-5 right-5 z-[999] px-5 py-3 rounded-lg border shadow-2xl text-sm max-w-sm leading-relaxed ' + colors;
        d.innerHTML = msg;
        document.body.appendChild(d);
        setTimeout(function() { if (d.parentNode) d.remove(); }, 5000);
    }

    // ---- Spinner sur bouton ----
    function setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            btn._origHtml = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin h-4 w-4 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10" stroke-dasharray="30 70"/></svg> En cours...';
            btn.classList.add('opacity-70');
        } else {
            btn.innerHTML = btn._origHtml || btn.innerHTML;
            btn.classList.remove('opacity-70');
        }
    }

    // ---- Échapper HTML ----
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---- Action AJAX principale ----
    function ajaxAction(action) {
        var coursId   = document.getElementById('cours_id').value;
        var sessionId = document.getElementById('session_select').value;
        if (!coursId)   { showToast('Veuillez sélectionner un cours.', 'error'); return; }
        if (!sessionId) { showToast('Veuillez sélectionner une session académique.', 'error'); return; }

        var btn = document.getElementById(action === 'preview_students' ? 'btn-preview' : 'btn-generate');
        setLoading(btn, true);

        var fd = new FormData();
        fd.append('action', action);
        fd.append('cours_id', coursId);
        fd.append('session', sessionId);
        fd.append('teacher_id', document.getElementById('teacher_id').value || '');

        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(json) {
            setLoading(btn, false);
            if (action === 'preview_students') {
                renderStudentsPreview(json.students || []);
            } else {
                renderGenerateResult(json);
            }
        })
        .catch(function(err) {
            setLoading(btn, false);
            showToast('Erreur réseau : ' + err.message, 'error');
        });
    }

    // ---- Rendu : aperçu étudiants ----
    function renderStudentsPreview(students) {
        var panel = document.getElementById('students-panel');
        if (!panel) return;
        if (students.length === 0) {
            panel.innerHTML = '<div class="flex items-center justify-center h-24 text-sm text-red-400 border-2 border-dashed border-red-900 rounded gap-2"><i class="bi bi-exclamation-triangle"></i>&nbsp;Aucun étudiant inscrit pour ce cours / cette session.</div>';
            return;
        }
        var html = '<p class="text-sm text-yellow-400 mb-3 flex items-center gap-1"><i class="bi bi-eye"></i>&nbsp;Aperçu — <strong>' + students.length + '</strong> étudiant(s) seront ciblés. Au-delà de 5 inscrits, la sélection est aléatoire et limitée à 35% minimum 5.</p>';
        html += '<div class="space-y-1 overflow-y-auto max-h-64 pr-1">';
        students.forEach(function(std, i) {
            var nom   = escHtml((std.student_nom || '') + ' ' + (std.student_prenom || ''));
            var email = escHtml(std.student_email || '—');
            html += '<div class="flex items-center gap-3 px-3 py-2 ' + themeClasses.bgOne + ' border ' + themeClasses.brOne + ' rounded">'
                  + '<span class="text-xs font-bold text-slate-400 w-5 text-right">' + (i + 1) + '</span>'
                  + '<div class="flex-1 min-w-0">'
                  +   '<div class="text-sm font-medium truncate">' + nom + '</div>'
                  +   '<div class="text-xs text-blue-300 truncate">' + email + '</div>'
                  + '</div></div>';
        });
        html += '</div>';
        panel.innerHTML = html;
        showToast('<strong>' + students.length + '</strong> étudiant(s) trouvé(s) pour ce cours.', 'success');
    }

    // ---- Rendu : résultat génération + envoi ----
    function renderGenerateResult(json) {
        var panel = document.getElementById('students-panel');
        if (!panel) return;

        if (!json.ok || !json.links || json.links.length === 0) {
            panel.innerHTML = '<div class="flex items-center justify-center h-24 text-sm text-red-400 border-2 border-dashed border-red-900 rounded gap-2"><i class="bi bi-exclamation-triangle"></i>&nbsp;Aucun lien généré. Vérifiez le cours et la session.</div>';
            showToast('Aucun étudiant trouvé pour ce cours / cette session.', 'error');
            return;
        }

        var toastMsg = '✅ Formulaire généré !<br><strong>' + (json.selected || json.links.length) + '</strong> étudiant(s) ciblé(s) sur <strong>' + (json.total || (json.links.length)) + '</strong> inscrit(s).';
        if ((json.mode || '') === 'sample') {
            toastMsg += '<br>La sélection a été tirée aléatoirement selon la règle 35% minimum 5.';
        }
        if ((json.emailSent   || 0) > 0) toastMsg += '<br>📧 ' + json.emailSent + ' email(s) envoyé(s)';
        if ((json.emailFailed || 0) > 0) toastMsg += '<br>❌ ' + json.emailFailed + ' échec(s)';
        if ((json.emailNoAddr || 0) > 0) toastMsg += '<br>⚠️ ' + json.emailNoAddr + ' sans adresse';
        showToast(toastMsg, 'success');

        var html = '<p class="text-sm text-green-400 mb-2 flex items-center gap-1"><i class="bi bi-check-circle-fill"></i>&nbsp;Formulaire généré — <strong>' + json.links.length + '</strong> lien(s) créé(s).</p>';

        // Badges résumé email
        html += '<div class="flex gap-2 flex-wrap mb-3">';
        if ((json.emailSent   || 0) > 0) html += '<span class="text-xs px-2 py-0.5 bg-green-900 text-green-300 rounded-full"><i class="bi bi-envelope-check"></i> ' + json.emailSent + ' envoyé(s)</span>';
        if ((json.emailFailed || 0) > 0) html += '<span class="text-xs px-2 py-0.5 bg-red-900 text-red-300 rounded-full"><i class="bi bi-envelope-x"></i> ' + json.emailFailed + ' échec(s)</span>';
        if ((json.emailNoAddr || 0) > 0) html += '<span class="text-xs px-2 py-0.5 bg-yellow-900 text-yellow-300 rounded-full"><i class="bi bi-person-x"></i> ' + json.emailNoAddr + ' sans email</span>';
        html += '</div>';

        html += '<div class="space-y-2 overflow-y-auto max-h-64 pr-1">';
        json.links.forEach(function(link, i) {
            var inputId = 'ajax-link-' + i;
            var url = escHtml(link.url || '');
            html += '<div class="p-2.5 ' + themeClasses.bgOne + ' border ' + themeClasses.brOne + ' rounded">'
                  + '<div class="font-medium text-sm">' + escHtml(link.name || '') + '</div>'
                  + '<div class="text-xs mb-1.5 text-blue-300">' + escHtml(link.email || '') + '</div>'
                  + '<div class="flex items-center gap-2">'
                  +   '<input type="text" readonly value="' + url + '" id="' + inputId + '" class="w-full text-xs px-2 py-1 ' + themeClasses.bgTwo + ' ' + themeClasses.txtOne + ' border ' + themeClasses.brOne + ' rounded cursor-text">'
                  +   '<button type="button" onclick="copyToClipboard(\'' + inputId + '\')" class="px-2 py-1 text-xs bg-slate-600 text-white rounded hover:bg-slate-500 whitespace-nowrap">Copier</button>'
                  + '</div></div>';
        });
        html += '</div>';
        panel.innerHTML = html;
    }
    </script>
</body>
</html>
