-- Cycle d'un parcours : licence, master, ou les deux (23/09/2026)
--
-- Les parcours de master ne sont pas ceux de la licence, mais la table ne le
-- disait pas : les 21 parcours etaient proposes aux deux cycles. On retrouve
-- ainsi « Systemes d'information et genie logiciel » chez 194 licencies et 26
-- masters, et « Management des affaires » chez 97 et 26.
--
-- Valeur par defaut « L,M » : tous les parcours existants restent proposes
-- partout, l'etat precedent est donc reproduit a l'identique. Le reglage se
-- fait ensuite parcours par parcours dans /settings/mentions.
--
-- data/mentions.php pose cette colonne automatiquement a la premiere lecture
-- (mentionParcoursInstaller) : ce fichier sert a l'appliquer a la main.

ALTER TABLE filiere_parcours
  ADD COLUMN cycles VARCHAR(10) NOT NULL DEFAULT 'L,M' AFTER departement;
