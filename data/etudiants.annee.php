<?php
/**
 * Etudiants d'une annee scolaire, pour les exports par annee : liste FOP,
 * MESUPRES, liste Work Education, tickets et modele d'e-mails des nouveaux.
 *
 * Ce sont les etudiants inscrits a une session de cette annee (l'annee d'une
 * inscription est celle de sa session), une fiche active par matricule. Ces
 * exports lisaient le champ « Annee » de la fiche, qui retarde ou avance, et
 * gardaient les fiches supprimees : en 2025-2026, 22 fiches de l'annee sans
 * inscription et 22 inscrits portant une autre annee (releve du 07/10/2026).
 */

if (!function_exists('etudiantsDeLAnnee')) {
/**
 * Condition SQL sur la table tbl_2024_etudiant (nommee sans alias).
 *
 * $admisSansInscription : pour les documents des nouveaux (tickets, e-mails),
 * ajoute les fiches de l'annee qui n'ont encore aucune inscription, c'est-a-dire
 * les admis prepares avant leur passage au guichet.
 */
function etudiantsDeLAnnee(PDO $dtb, string $annee, bool $admisSansInscription = false): string
{
    $a = $dtb->quote($annee);
    $condition = "tbl_2024_etudiant.id IN (
        SELECT (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id
                 ORDER BY e2.`remove` ASC, e2.id ASC LIMIT 1)
          FROM t_2024_inscription_session ins
          JOIN t_2023_session s ON s.session_id = ins.session_id
         WHERE s.session_year = $a AND COALESCE(ins.`remove`, 0) != 1)";

    if ($admisSansInscription) {
        $condition = "($condition OR (tbl_2024_etudiant.annee_scolaire = $a AND NOT EXISTS (
            SELECT 1 FROM t_2024_inscription_session i3
             WHERE i3.student_id = tbl_2024_etudiant.student_id AND COALESCE(i3.`remove`, 0) != 1)))";
    }

    return "tbl_2024_etudiant.`remove` = 0 AND $condition";
}
}
