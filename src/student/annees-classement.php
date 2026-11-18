<?php
/*
 * Les années universitaires des classements (Dean's List, Top Students).
 *
 * De la plus récente à la plus ancienne, jusqu'à l'année en cours (elle
 * commence en septembre) : même calcul que dans src/student/deans-list.annees.php
 * et src/student/meilleurs-etudiants.annees.php, où la page N montre la
 * N-ième année. Une page hors bornes (ancien lien, saisie) donne la plus proche.
 */
if (!function_exists('anneesClassement')) {
	/**
	 * @return array{annees: string[], page: int}
	 */
	function anneesClassement(PDO $dtb) {
		$mois = (int) date('n');
		$an = (int) date('Y');
		$enCours = $mois >= 9 ? $an . '-' . ($an + 1) : ($an - 1) . '-' . $an;

		$requete = $dtb->prepare('SELECT DISTINCT session_year FROM t_2023_session WHERE session_year IS NOT NULL AND session_year <= :currentYear ORDER BY session_year DESC');
		$requete->execute([':currentYear' => $enCours]);
		$annees = $requete->fetchAll(PDO::FETCH_COLUMN);

		$page = isset($_GET['page_annee']) ? (int) $_GET['page_annee'] : 1;
		$page = max(1, min($page, max(1, count($annees))));

		return ['annees' => $annees, 'page' => $page];
	}
}
