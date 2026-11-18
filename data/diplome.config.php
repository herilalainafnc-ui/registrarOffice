<?php
/**
 * Configuration du diplome (nouveau modele 2025+).
 *
 * Les valeurs sont stockees dans la table `settings` (type = cle, description = valeur),
 * ce qui permet de changer les signataires, les arretes et le calage d'impression
 * sans toucher au code.
 *
 * Utilisation :
 *   require_once(__DIR__ . '/diplome.config.php');
 *   $cfg = diplomeConfig($dtb);          // tableau cle => valeur (defauts inclus)
 *   diplomeConfigSave($dtb, $data, $uid); // enregistrement
 */

/**
 * Valeurs par defaut (servent aussi de liste blanche des cles autorisees).
 */
function diplomeConfigDefaults() {
	return [
		// Arretes ministeriels cites dans les considerants
		'diplome_arrete_1'          => "N° 12.258 / 2022 - MESupRES du 29/04/2022",
		'diplome_arrete_2'          => "N° 31577 / 2012 de la FOP du 12/12/12",

		// Lieu, date de remise et libelles
		'diplome_lieu'              => "Sambaina",
		'diplome_date_remise'       => "", // ex: "02 août 2026" — vide = date du jour
		'diplome_etudiant_libelle'  => "L'étudiant",

		// Signataire 1 : Recteur
		'diplome_recteur_titre'     => "Le Recteur",
		'diplome_recteur_nom'       => "Dr Daniel Kancel",
		'diplome_recteur_signature' => "recteur.png",

		// Signataire 2 : President du Comite Directeur
		'diplome_president_titre'     => "Le président du Comité Directeur",
		'diplome_president_nom'       => "Dr Solofo Georges Jean Mesmert",
		'diplome_president_signature' => "president.png",

		// Annee a partir de laquelle le nouveau modele s'applique par defaut
		'diplome_new_model_from_year' => "2025",

		// Credits a valider pour que le diplome soit delivrable.
		// Licence : 180 (bulletin academique §383). Master : valeur usuelle LMD,
		// a confirmer aupres du bureau academique.
		'diplome_credits_licence' => "180",
		'diplome_credits_master'  => "120",

		// Calage d'impression sur le papier pre-imprime (en mm, peut etre negatif)
		'diplome_offset_x'          => "0",
		'diplome_offset_y'          => "0",
	];
}

/**
 * Lit la configuration du diplome, en completant par les valeurs par defaut.
 *
 * @param PDO $dtb
 * @return array cle => valeur
 */
function diplomeConfig(PDO $dtb) {
	static $cache = null;
	if ($cache !== null) { return $cache; }

	$cfg = diplomeConfigDefaults();
	try {
		$st = $dtb->prepare("SELECT type, description FROM settings WHERE type LIKE 'diplome\_%'");
		$st->execute();
		foreach ($st->fetchAll() as $row) {
			$k = $row['type'];
			// On ignore les cles inconnues, et les valeurs vides gardent le defaut
			if (array_key_exists($k, $cfg) && $row['description'] !== null && $row['description'] !== '') {
				$cfg[$k] = $row['description'];
			}
		}
	} catch (Exception $e) {
		// En cas de souci de lecture, on reste sur les valeurs par defaut
	}
	$cache = $cfg;
	return $cfg;
}

/**
 * Enregistre la configuration (INSERT ou UPDATE selon existence de la cle).
 * Seules les cles connues sont acceptees.
 *
 * @param PDO   $dtb
 * @param array $data cle => valeur
 * @param mixed $userId
 * @return int nombre de cles enregistrees
 */
function diplomeConfigSave(PDO $dtb, array $data, $userId = null) {
	$allowed = diplomeConfigDefaults();
	$now = date('Y-m-d H:i:s');
	$n = 0;

	$find   = $dtb->prepare('SELECT settings_id FROM settings WHERE type = :type LIMIT 1');
	$update = $dtb->prepare('UPDATE settings SET description = :val, last_change_user_id = :uid, last_change_datetime = :dt WHERE type = :type');
	$insert = $dtb->prepare('INSERT INTO settings(type, description, last_change_user_id, last_change_datetime) VALUES(:type, :val, :uid, :dt)');

	foreach ($data as $k => $v) {
		if (!array_key_exists($k, $allowed)) { continue; }
		$v = trim((string)$v);

		$find->execute([':type' => $k]);
		if ($find->fetch()) {
			$update->execute([':val' => $v, ':uid' => $userId, ':dt' => $now, ':type' => $k]);
		} else {
			$insert->execute([':type' => $k, ':val' => $v, ':uid' => $userId, ':dt' => $now]);
		}
		$n++;
	}
	return $n;
}

/**
 * Total des credits VALIDES par un etudiant.
 * Un credit est acquis si le cours est reussi (note >= 10) ou valide sans note
 * numerique (-2). Les tentatives retirees du cursus (ajout = 0) ne comptent pas.
 *
 * @return int
 */
function diplomeCreditsValides(PDO $dtb, $student_id) {
	$st = $dtb->prepare(
		"SELECT COALESCE(SUM(credit), 0) AS total
		   FROM t_2023_notes
		  WHERE student_id = :sid AND ajout = 1
		    AND (grade >= 10 OR grade = -2)"
	);
	$st->execute([':sid' => $student_id]);
	return (int)$st->fetchColumn();
}

/**
 * Credits requis pour delivrer le diplome, selon le niveau.
 *
 * @param bool $estMaster
 * @return int
 */
function diplomeCreditsRequis(PDO $dtb, $estMaster) {
	$cfg = diplomeConfig($dtb);
	$cle = $estMaster ? 'diplome_credits_master' : 'diplome_credits_licence';
	return (int)$cfg[$cle];
}

/**
 * Normalise un libelle de programme pour la comparaison :
 * minuscules, sans accents, sans le mot "en", espaces reduits.
 * Permet de faire correspondre "Licence Théologie" et "Licence en Droit".
 */
function diplomeNormalise($s) {
	$s = mb_strtolower(trim((string)$s), 'UTF-8');
	$from = ['à','á','â','ä','ã','å','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','ö','õ','ù','ú','û','ü','ý','ÿ'];
	$to   = ['a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','y'];
	$s = str_replace($from, $to, $s);
	$s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
	$s = preg_replace('/\ben\b/', ' ', $s);          // "licence en droit" -> "licence droit"
	$s = trim(preg_replace('/\s+/', ' ', $s));
	return $s;
}

/**
 * Retourne les arretes ministeriels applicables a un programme.
 * Les arretes different selon la filiere et le niveau (table diplome_arretes).
 *
 * @param PDO    $dtb
 * @param bool   $estMaster
 * @param string $filiere ex: "Théologie"
 * @return array ['mesupres' => ..., 'cneat' => ..., 'accronyme' => ..., 'trouve' => bool]
 */
function diplomeArretes(PDO $dtb, $estMaster, $filiere) {
	static $rows = null;
	if ($rows === null) {
		$rows = [];
		try {
			foreach ($dtb->query('SELECT programme, accronyme, arrete_mesupres, arrete_cneat FROM diplome_arretes') as $r) {
				$rows[diplomeNormalise($r['programme'])] = $r;
			}
		} catch (Exception $e) { /* table absente : on retombe sur les valeurs globales */ }
	}

	$cle = diplomeNormalise(($estMaster ? 'Master ' : 'Licence ') . $filiere);
	if (isset($rows[$cle])) {
		return [
			'mesupres'  => $rows[$cle]['arrete_mesupres'],
			'cneat'     => $rows[$cle]['arrete_cneat'],
			'accronyme' => $rows[$cle]['accronyme'],
			'trouve'    => true,
		];
	}
	return ['mesupres' => null, 'cneat' => null, 'accronyme' => null, 'trouve' => false];
}

/**
 * Determine le modele de diplome par defaut pour une annee scolaire donnee.
 * Les etudiants gradues avant 2025 gardent l'ancien modele.
 *
 * @param string $anneeScolaire ex: "2025 - 2026"
 * @param int    $fromYear      annee de bascule (defaut 2025)
 * @return string 'ancien' | 'nouveau'
 */
function diplomeModeleParDefaut($anneeScolaire, $fromYear = 2025) {
	// On retient la derniere annee mentionnee ("2025 - 2026" -> 2026, "2024-2025" -> 2025)
	if (preg_match_all('/(\d{4})/', (string)$anneeScolaire, $m) && !empty($m[1])) {
		$annee = (int)end($m[1]);
		return ($annee >= (int)$fromYear) ? 'nouveau' : 'ancien';
	}
	// Sans information fiable, on reste sur l'ancien modele (choix explicite possible)
	return 'ancien';
}
