<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

if (Middleware::getCurrentUser()['level'] > 3) {
    die("Accès refusé.");
}

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($formId > 0) {
    // Delete the form (dependencies will be deleted if foreign keys have ON DELETE CASCADE)
    // If not using cascading deletes, you should delete answers, responses, options, and questions manually here
    try {
        $dtb->beginTransaction();
        
        // Clean answers
        $dtb->query("DELETE FROM rg_eval_answers WHERE response_id IN (SELECT id FROM rg_eval_responses WHERE form_id = $formId)");
        // Clean responses
        $dtb->query("DELETE FROM rg_eval_responses WHERE form_id = $formId");
        
        // Clean options
        $dtb->query("DELETE FROM rg_eval_options WHERE question_id IN (SELECT id FROM rg_eval_questions WHERE form_id = $formId)");
        // Clean questions
        $dtb->query("DELETE FROM rg_eval_questions WHERE form_id = $formId");
        
        // Clean form
        $dtb->query("DELETE FROM rg_eval_forms WHERE id = $formId");
        
        $dtb->commit();
    } catch (Exception $e) {
        $dtb->rollBack();
        die("Erreur de suppression: " . $e->getMessage());
    }
}

// Rediriger vers l'admin
header("Location: " . $app_base . "/evaluation/admin");
exit;
