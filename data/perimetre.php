<?php
/* =============================================================================
   PERIMETRE D'UN COMPTE : le cycle qu'il voit
   -----------------------------------------------------------------------------
   Un compte peut etre limite a un cycle (compt_utilisateur.perimetre_cycle,
   data/migration_perimetre_cycle.sql) : les secretaires academiques de
   Licence et de Master ne voient que le leur, l'autre leur est invisible —
   absent des listes et des recherches, « introuvable » par son adresse.
   Sans perimetre (NULL, tous les autres comptes), rien ne change.

   La regle n'est ecrite qu'ici :
     Licence : annee d'etude ou niveau 0 a 3 (la remise a niveau en fait partie)
     Master  : 4 et 5
   Un etudiant qui passe en Master recoit un nouveau matricule : chaque fiche
   appartient donc a un seul cycle, sans continuite a gerer.
   Ce qui n'a pas de niveau est invisible pour un compte limite.

   Les enseignants et les chefs de mention restent communs : ils ne sont pas
   filtres ; leurs cours, eux, suivent leur niveau. Seule exception, plus bas :
   la LISTE des etudiants d'un chef de mention ne montre que sa mention.
   ========================================================================== */

const PERIMETRE_CYCLES = [
	'L' => ['libelle' => 'Licence', 'niveaux' => [0, 1, 2, 3]],
	'M' => ['libelle' => 'Master',  'niveaux' => [4, 5]],
];

/**
 * Cycle du compte connecte : 'L', 'M', ou null (tout voir).
 *
 * Lu sur le compte a chaque requete (currentUser() relit la ligne) : un
 * changement de perimetre vaut des la page suivante, sans reconnexion.
 */
function perimetreCycle(): ?string
{
	static $cycle = false;
	if ($cycle !== false) { return $cycle; }

	$valeur = null;
	$compte = function_exists('currentUser') ? currentUser() : null;
	if (is_array($compte)) {
		$valeur = $compte['perimetre_cycle'] ?? null;
	} elseif (!empty($_SESSION['user_id']) && isset($GLOBALS['dtb']) && $GLOBALS['dtb'] instanceof PDO) {
		/* Sans le middleware : on relit le compte de la session. */
		try {
			$st = $GLOBALS['dtb']->prepare('SELECT perimetre_cycle FROM compt_utilisateur WHERE id = ? LIMIT 1');
			$st->execute([(int) $_SESSION['user_id']]);
			$valeur = $st->fetchColumn() ?: null;
		} catch (Throwable $e) {
			$valeur = null; // colonne absente : migration pas encore jouee
		}
	}
	$cycle = isset(PERIMETRE_CYCLES[$valeur]) ? $valeur : null;
	return $cycle;
}

/** Libelle du cycle du compte (« Licence », « Master »), vide sans perimetre. */
function perimetreLibelle(): string
{
	$c = perimetreCycle();
	return $c === null ? '' : PERIMETRE_CYCLES[$c]['libelle'];
}

/**
 * Condition a ajouter a une requete sur les etudiants : « AND ... », ou ''
 * sans perimetre. annee_etude est un texte ('0' a '5') : liste de chaines.
 *
 * @param string $alias Alias de tbl_2024_etudiant (« e »), vide sans alias.
 */
function perimetreSqlEtudiant(string $alias = 'e'): string
{
	$c = perimetreCycle();
	if ($c === null) { return ''; }
	$valeurs = implode(', ', array_map(static fn (int $n) => "'$n'", PERIMETRE_CYCLES[$c]['niveaux']));
	return ' AND ' . ($alias !== '' ? $alias . '.' : '') . "annee_etude IN ($valeurs)";
}

/**
 * Condition sur une colonne de niveau entiere (t_2023_cours.yearlevel,
 * t_2023_notes.yearlevel) : « AND ... », ou '' sans perimetre.
 *
 * @param string $colonne Colonne complete, alias compris (« c.yearlevel »).
 */
function perimetreSqlNiveau(string $colonne): string
{
	$c = perimetreCycle();
	if ($c === null) { return ''; }
	return " AND $colonne IN (" . implode(', ', PERIMETRE_CYCLES[$c]['niveaux']) . ')';
}

/**
 * Le niveau donne est-il dans le perimetre ? Vrai sans perimetre ; faux pour
 * un niveau absent ou non numerique quand le compte est limite.
 */
function perimetreNiveauVisible($niveau): bool
{
	$c = perimetreCycle();
	if ($c === null) { return true; }
	$n = is_string($niveau) ? trim($niveau) : $niveau;
	if ($n === null || $n === '' || !is_numeric($n)) { return false; }
	return in_array((int) $n, PERIMETRE_CYCLES[$c]['niveaux'], true);
}

/** L'etudiant (ligne de tbl_2024_etudiant) est-il visible ? Faux s'il n'existe pas et que le compte est limite. */
function perimetreEtudiantVisible($etudiant): bool
{
	if (perimetreCycle() === null) { return true; }
	return is_array($etudiant) && perimetreNiveauVisible($etudiant['annee_etude'] ?? null);
}

/** Le cours (ligne de t_2023_cours) est-il visible ? */
function perimetreCoursVisible($cours): bool
{
	if (perimetreCycle() === null) { return true; }
	return is_array($cours) && perimetreNiveauVisible($cours['yearlevel'] ?? null);
}

/* -----------------------------------------------------------------------------
   MENTION D'UN CHEF DE MENTION
   Sa liste des etudiants (/etudiants, data/listes.php) ne montre que sa
   mention, posee par le registrariat dans compt_utilisateur.perimetre_mention
   (data/migration_perimetre_mention.sql) — pas lue dans le « Poste », que
   chacun modifie lui-meme. C'est tout : fiches, classements, tableau de bord
   et recherche generale restent communs (choix de l'utilisateur, 07/10/2026).
   -------------------------------------------------------------------------- */

/** Le compte connecte est-il un chef de mention (niveau 6, hors niveaux 1 a 3) ? */
function perimetreChefMention(): bool
{
	$compte = function_exists('currentUser') ? currentUser() : null;
	$niveau = (int) (is_array($compte) ? ($compte['level'] ?? 0) : ($_SESSION['user_level'] ?? 0));
	if ($niveau <= 3) { return false; }
	if ($niveau === 6) { return true; }
	return is_array($compte)
		&& (($compte['privilege'] ?? '') === 'chef_mention' || ($compte['user_type'] ?? '') === 'chef_mention');
}

/**
 * Mention du chef de mention connecte, telle qu'ecrite sur les fiches
 * (etude_envisage). false : pas de restriction (pas un chef, ou colonne pas
 * encore creee) ; null : chef sans mention attribuee.
 *
 * @return string|null|false
 */
function perimetreMention()
{
	static $mention = 0;
	if ($mention !== 0) { return $mention; }
	if (!perimetreChefMention()) { return $mention = false; }

	$compte = function_exists('currentUser') ? currentUser() : null;
	if (!is_array($compte) && !empty($_SESSION['user_id']) && isset($GLOBALS['dtb']) && $GLOBALS['dtb'] instanceof PDO) {
		/* Sans le middleware : on relit le compte de la session. */
		$st = $GLOBALS['dtb']->prepare('SELECT * FROM compt_utilisateur WHERE id = ? LIMIT 1');
		$st->execute([(int) $_SESSION['user_id']]);
		$compte = $st->fetch(PDO::FETCH_ASSOC) ?: null;
	}
	if (!is_array($compte) || !array_key_exists('perimetre_mention', $compte)) {
		return $mention = false; // migration pas encore jouee : rien ne change
	}
	$valeur = trim((string) ($compte['perimetre_mention'] ?? ''));
	return $mention = ($valeur === '' ? null : $valeur);
}

/**
 * Condition a ajouter a la liste des etudiants : « AND ... », ou '' sans
 * restriction. Un chef sans mention ne voit personne (la liste le dit).
 *
 * @param string $alias Alias de tbl_2024_etudiant (« e »), vide sans alias.
 */
function perimetreSqlListeMention(?PDO $dtb, string $alias = 'e'): string
{
	$mention = perimetreMention();
	if ($mention === false) { return ''; }
	if ($mention === null || !$dtb) { return ' AND 1 = 0'; }
	return ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'etude_envisage = ' . $dtb->quote($mention);
}

/**
 * Mentions proposees au registrariat pour un chef de mention : celles des
 * fiches, ecrites comme elles (« Etudes anglophones », « Education »). Les
 * libelles de la table filiere different parfois (« Éducation ») et ne
 * retrouveraient pas les etudiants.
 *
 * @return string[]
 */
function perimetreMentionsPossibles(PDO $dtb): array
{
	return $dtb->query("SELECT DISTINCT etude_envisage FROM tbl_2024_etudiant
		WHERE `remove` = 0 AND TRIM(COALESCE(etude_envisage, '')) <> '' ORDER BY etude_envisage")->fetchAll(PDO::FETCH_COLUMN);
}

/** Mention envoyee par le formulaire des comptes, si elle existe ; null sinon. */
function perimetreMentionValide(PDO $dtb, $valeur): ?string
{
	$valeur = trim((string) $valeur);
	return $valeur !== '' && in_array($valeur, perimetreMentionsPossibles($dtb), true) ? $valeur : null;
}
