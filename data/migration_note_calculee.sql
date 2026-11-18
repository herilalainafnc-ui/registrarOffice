-- La note sur 20 d'une remise est toujours calculee (26/09/2026)
--
-- Jusqu'ici, l'enseignant pouvait saisir la note finale a la main dans la
-- colonne « /20 » de sa feuille : elle prenait alors le pas sur les
-- composantes (note_forcee = 1), et une feuille pouvait porter 18 pour des
-- composantes dont la moyenne ponderee faisait 7,30. La note est desormais
-- toujours la moyenne des composantes selon la ponderation du cours
-- (data/remise.notes.php, remiseCalculerNote), et la colonne ne se saisit
-- plus.
--
-- Cette migration retire les notes imposees restantes, dans les seules
-- feuilles encore en brouillon ou renvoyees : une feuille soumise ou validee
-- n'est pas touchee. Chaque retrait est consigne dans le journal de la
-- feuille, comme une saisie, pour que l'enseignant et le registrariat voient
-- ce qui a change.
--
-- Au 26/09/2026 : 1 note imposee en local, aucune en production (aucune
-- remise). Rejouable : sans note imposee, les deux instructions ne font rien.

INSERT INTO t_remise_notes_journal
    (remise_id, ligne_id, student_id, action, champ, ancienne_valeur, nouvelle_valeur,
     auteur_id, auteur_nom, auteur_role, date_action)
SELECT l.remise_id, l.id, l.student_id, 'saisie', 'note_finale',
       CAST(l.note_finale AS CHAR), NULL,
       NULL, 'Note calculee (regle du 26/09/2026)', 'systeme', NOW()
  FROM t_remise_notes_ligne l
  JOIN t_remise_notes r ON r.id = l.remise_id
 WHERE l.note_forcee = 1 AND r.statut IN ('brouillon', 'rejetee');

UPDATE t_remise_notes_ligne l
  JOIN t_remise_notes r ON r.id = l.remise_id
   SET l.note_forcee = 0, l.note_finale = NULL
 WHERE l.note_forcee = 1 AND r.statut IN ('brouillon', 'rejetee');
