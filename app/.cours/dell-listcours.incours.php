<?php
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
// Autorises: superadmin, admin, registraire, chef de mention
if (!in_array($currentUserLevel, [1, 2, 3, 6], true)) {
    http_response_code(403);
    if (!empty($_GET['id'])) {
        header('location:' . $app_base . '/course?id=' . (int)$_GET['id'] . '&page=notes&error=access_denied');
    } else {
        header('location:' . $app_base . '/courses?error=access_denied');
    }
    exit;
}

require '../../data/backdb.php';

$idSupprCours = isset($_GET['idSupprCours']) ? (int)$_GET['idSupprCours'] : 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$last_change_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_GET['rg_id']) ? (int)$_GET['rg_id'] : 0);
$last_change_datetime = date('Y-m-d H:i:s');
$retrait_date = date('Y-m-d H:i:s');
$ajout = 0;
$remove = 1;

if ($idSupprCours > 0) {
    $sql = "UPDATE t_2023_notes
            SET ajout = :ajout,
                remove = :remove,
                retrait_date = :retrait_date,
                last_change_user_id = :last_change_user_id,
                last_change_datetime = :last_change_datetime
            WHERE id = :id";

    $stmt = $dtb->prepare($sql);
    $stmt->bindValue(':ajout', $ajout, PDO::PARAM_INT);
    $stmt->bindValue(':remove', $remove, PDO::PARAM_INT);
    $stmt->bindValue(':retrait_date', $retrait_date, PDO::PARAM_STR);
    $stmt->bindValue(':last_change_user_id', $last_change_user_id, PDO::PARAM_INT);
    $stmt->bindValue(':last_change_datetime', $last_change_datetime, PDO::PARAM_STR);
    $stmt->bindValue(':id', $idSupprCours, PDO::PARAM_INT);
    $stmt->execute();
}

if ($id > 0) {
    header('location:' . $app_base . '/course?id=' . $id . '&page=notes');
    exit;
}

header('location:' . $app_base . '/courses');
exit;
