<?php
/* =============================================================================
   Echeances de paiement — la date limite de chaque tranche, session par session.

   Un type de paiement (t_2024_etudiant_finace.mode_payement) partage le reste
   a payer apres les frais generaux en tranches : A en une fois, B en deux, E
   en quatre. C et D ne sont plus proposes au guichet mais restent sur
   d'anciens dossiers, dont la fiche peut etre reimprimee.

   Les dates etaient ecrites en dur dans la fiche d'inscription, les memes pour
   toutes les sessions : une fiche de 2026 - 2027 imprimait celles de
   2025 - 2026. Elles sont dans t_paiement_echeance
   (data/migration_paiement_echeances.sql) et se reglent sur /finance/echeances.

   Lues par src/extenssionPrint/fiche_inscription.php (la fiche imprimee) et
   inscription/stages/mode.payement.php (l'etape « Mode de paiement »).
   ========================================================================== */

/**
 * Les types de paiement : libelle, parts (en % du reste a payer, dans l'ordre
 * des tranches) et s'ils sont encore proposes au guichet.
 *
 * @return array<string, array{libelle: string, parts: int[], propose: bool}>
 */
function echeancesTypes(): array
{
    return [
        'A' => ['libelle' => 'En une fois', 'parts' => [100], 'propose' => true],
        'B' => ['libelle' => 'Deux tranches de 50 %', 'parts' => [50, 50], 'propose' => true],
        'C' => ['libelle' => 'Deux tranches, 75 % puis 25 %', 'parts' => [75, 25], 'propose' => false],
        'D' => ['libelle' => 'Trois tranches : 40 %, 30 % et 30 %', 'parts' => [40, 30, 30], 'propose' => false],
        'E' => ['libelle' => 'Quatre tranches de 25 %', 'parts' => [25, 25, 25, 25], 'propose' => true],
    ];
}

/**
 * Les dates d'une session : ['B' => [1 => '2026-04-30', 2 => '2026-07-10'], ...].
 *
 * Sans la table (migration pas encore jouee), aucune date : la fiche imprime
 * des cases vides, comme pour une session pas encore reglee.
 *
 * @return array<string, array<int, string>>
 */
function echeancesSession(PDO $dtb, $sessionId): array
{
    $sessionId = (int) $sessionId;
    if ($sessionId <= 0) {
        return [];
    }
    try {
        $req = $dtb->prepare(
            'SELECT type_paiement, tranche, date_limite FROM t_paiement_echeance
              WHERE session_id = :session_id ORDER BY type_paiement, tranche'
        );
        $req->execute(['session_id' => $sessionId]);
    } catch (PDOException $e) {
        return [];
    }

    $dates = [];
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $dates[$l['type_paiement']][(int) $l['tranche']] = $l['date_limite'];
    }
    return $dates;
}

/**
 * Remplace les dates d'une session.
 *
 * $dates : ['A' => [1 => 'AAAA-MM-JJ'], 'B' => [1 => '...', 2 => ''], ...] ;
 * une date vide retire la tranche, un type absent n'est pas touche. Chaque
 * tranche doit venir apres la precedente du meme type.
 *
 * @return int lignes ajoutees, changees ou retirees
 * @throws InvalidArgumentException message lisible, pour l'utilisateur
 */
function echeancesEnregistrer(PDO $dtb, int $sessionId, array $dates, ?int $auteur): int
{
    $types = echeancesTypes();
    $propres = [];
    foreach ($dates as $type => $tranches) {
        if (!isset($types[$type]) || !is_array($tranches)) {
            throw new InvalidArgumentException('Type de paiement inconnu : ' . $type . '.');
        }
        $precedente = null;
        foreach ($types[$type]['parts'] as $i => $part) {
            $rang = $i + 1;
            $date = trim((string) ($tranches[$rang] ?? ''));
            if ($date === '') {
                $propres[$type][$rang] = null;
                continue;
            }
            $lue = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$lue || $lue->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Type ' . $type . ', tranche ' . $rang . ' : date illisible.');
            }
            if ($precedente !== null && $date <= $precedente) {
                throw new InvalidArgumentException('Type ' . $type . ' : chaque tranche doit venir après la précédente.');
            }
            $precedente = $date;
            $propres[$type][$rang] = $date;
        }
    }

    $existe = $dtb->prepare('SELECT COUNT(*) FROM t_2023_session WHERE session_id = :session_id');
    $existe->execute(['session_id' => $sessionId]);
    if ((int) $existe->fetchColumn() === 0) {
        throw new InvalidArgumentException('Session introuvable : rechargez la page.');
    }

    /* L'auteur et l'heure ne changent que si la date change (les affectations
       se lisent de gauche a droite : date_limite est encore l'ancienne). */
    $ecrire = $dtb->prepare(
        'INSERT INTO t_paiement_echeance (session_id, type_paiement, tranche, date_limite, modifie_par, modifie_le)
         VALUES (:session_id, :type, :tranche, :date_limite, :auteur, NOW()) AS n
         ON DUPLICATE KEY UPDATE
            modifie_par = IF(t_paiement_echeance.date_limite <> n.date_limite, n.modifie_par, t_paiement_echeance.modifie_par),
            modifie_le = IF(t_paiement_echeance.date_limite <> n.date_limite, n.modifie_le, t_paiement_echeance.modifie_le),
            date_limite = n.date_limite'
    );
    $retirer = $dtb->prepare('DELETE FROM t_paiement_echeance WHERE session_id = :session_id AND type_paiement = :type AND tranche = :tranche');

    $changees = 0;
    $dtb->beginTransaction();
    try {
        foreach ($propres as $type => $tranches) {
            foreach ($tranches as $rang => $date) {
                $cle = ['session_id' => $sessionId, 'type' => $type, 'tranche' => $rang];
                if ($date === null) {
                    $retirer->execute($cle);
                    $changees += $retirer->rowCount();
                } else {
                    $ecrire->execute($cle + ['date_limite' => $date, 'auteur' => $auteur]);
                    $changees += $ecrire->rowCount() > 0 ? 1 : 0;
                }
            }
        }
        $dtb->commit();
    } catch (Throwable $e) {
        if ($dtb->inTransaction()) {
            $dtb->rollBack();
        }
        throw $e;
    }
    return $changees;
}

/**
 * Une date d'echeance en francais : « 31 mars 2026 » (la fiche), ou
 * « 31 mars » quand l'annee est $sansAnnee.
 */
function echeanceDate(string $ymd, ?int $sansAnnee = null): string
{
    static $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($ymd . ' 12:00:00');
    if ($t === false) {
        return '';
    }
    $annee = (int) date('Y', $t);
    $texte = (int) date('j', $t) . ($t && (int) date('j', $t) === 1 ? 'er' : '') . ' ' . $mois[(int) date('n', $t) - 1];
    return $annee === $sansAnnee ? $texte : $texte . ' ' . $annee;
}
