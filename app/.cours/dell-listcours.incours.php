<?php
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require '../../data/backdb.php';

$idSupprCours = isset($_GET['idSupprCours']) ? (int)$_GET['idSupprCours'] : 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$last_change_user_id = isset($_GET['rg_id']) ? (int)$_GET['rg_id'] : 0;
$last_change_datetime = date('Y-m-d');

if ($idSupprCours > 0) {
    $sql = "UPDATE t_2023_notes
            SET remove = 1,
                last_change_user_id = :last_change_user_id,
                last_change_datetime = :last_change_datetime
            WHERE id = :id";

    $stmt = $dtb->prepare($sql);
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
