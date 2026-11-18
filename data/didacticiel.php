<?php
/* =============================================================================
   Didacticiel : les visites guidees de l'application.

   Une visite se deroule SUR la vraie page : chaque etape met un element en
   evidence (selecteur CSS « cible ») et dit a quoi il sert. Le moteur est
   src/js/didacticiel.js ; la page qui les recense, src/didacticiel.php
   (/didacticiel).

   Une visite :
     id, titre, resume, groupe, icone
     pour     qui la voit : 'personnel', 'registrariat', 'inscription', 'enseignant'
     pages    les adresses ou elle se deroule
     si       facultatif : un element qui doit exister (vue de detail d'une page)
     accueil  proposee a la premiere arrivee sur la page
     hub      recensee sur /didacticiel (sinon : seulement depuis sa page)
     depart   l'adresse ou la lancer depuis /didacticiel (defaut : pages[0])
     suite    la visite qui prend le relais apres la derniere etape
     etapes   [ cible, titre, texte, mobile (cible de repli sur petit ecran),
                texteMobile, chemins (etape propre a certaines adresses),
                action (bouton qui ouvre un lien de la page et enchaine une visite) ]

   Une etape dont la cible n'existe pas est sautee : les visites suivent ainsi
   les droits du compte (un menu sans « Candidatures » n'en parle pas). Une
   cible presente mais cachee (menu replie sur telephone) se replie sur
   « mobile », sinon l'etape s'affiche au centre de l'ecran.
   ========================================================================== */

if (!function_exists('didacticielProfil')) {

/** Ce que le compte connecte peut ouvrir, au sens des visites. */
function didacticielProfil(): array
{
	$niveau = (int) ($_SESSION['user_level'] ?? 99);
	$niveauEnseignant = defined('ROLE_TEACHER') ? (int) ROLE_TEACHER : 7;
	$niveauEtudiant = defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8;
	/* Meme regle que le menu et DashboardController::tableauDeBord : un
	   registraire (niveau 1 a 3) par ailleurs enseignant reste du personnel. */
	$enseignant = $niveau > 3 && ($niveau === $niveauEnseignant || (function_exists('isTeacher') && isTeacher()));
	$etudiant = $niveau === $niveauEtudiant || (function_exists('isStudent') && isStudent());
	/* Le responsable du Work Education n'ouvre que sa page, son compte, la
	   messagerie et le bulletin : pas les visites du personnel. */
	$workEducation = function_exists('estWorkEducation') && estWorkEducation();
	$personnel = !$enseignant && !$etudiant && !$workEducation;
	$registrariat = $personnel && function_exists('hasAnyPrivilege') && hasAnyPrivilege(['superadmin', 'registrar']);
	return [
		'niveau'       => $niveau,
		'enseignant'   => $enseignant,
		'etudiant'     => $etudiant,
		'personnel'    => $personnel,
		'registrariat' => $registrariat,
		/* L'entree « Inscription » du menu : personnel jusqu'au niveau 6 */
		'inscription'  => $personnel && $niveau <= 6,
		/* Les publics des visites de chaque page (data/didacticiel.pages.php),
		   memes regles que leurs controleurs et que le menu. */
		'tous'          => !$etudiant,
		'niveau3'       => $personnel && $niveau <= 3,
		'parametres'    => $personnel && $niveau <= 4,
		'superadmin'    => $personnel && $niveau === 1,
		/* /retouche-photo : le Media et le superadmin, comme DashboardController::retouchePhoto */
		'media'         => $personnel && in_array($niveau, [1, 5], true),
		'reinscription' => $personnel && in_array($niveau, [1, 3, 4], true),
		/* /inscriptions/tardives : niveaux 1 a 4, comme InscriptionController::tardives */
		'tardives'      => $personnel && $niveau <= 4,
		/* /work-education : son responsable, et les niveaux 1 a 3 */
		'workEducation' => $workEducation || ($personnel && $niveau <= 3),
		'coursSession'  => $registrariat || ($personnel && $niveau === 1),
		/* Le menu « Mon enseignement » : enseignants et chefs de mention qui enseignent */
		'enseignement'  => function_exists('aEspaceEnseignant') ? (bool) aEspaceEnseignant() : $enseignant,
	];
}

/** Toutes les visites, textes compris, pour ce profil. */
function didacticielVisites(array $p): array
{
	$recherche = $p['registrariat']
		? "Un étudiant (nom ou matricule), un cours (sigle ou titre), un enseignant ou une page du menu."
		: ($p['personnel'] ? "Un étudiant (nom ou matricule) ou une page du menu." : "Une page du menu.");

	$aide = [
		'cible' => '[data-tuto="didacticiel"]', 'mobile' => '#topbarMenuToggle',
		'titre' => 'Retrouver cette aide',
		'texte' => "D'autres visites vous attendent ici, en bas du menu, avec les raccourcis clavier (touche ?). Chaque page qui en a une la propose.",
		'texteMobile' => "Dans le menu, tout en bas : les autres visites et les raccourcis clavier.",
	];

	$visites = [];

	/* --- Personnel ------------------------------------------------------------ */
	$visites[] = [
		'id' => 'premiers-pas', 'pour' => 'personnel', 'groupe' => 'Prise en main', 'icone' => 'bi-compass',
		'titre' => 'Premiers pas',
		'resume' => 'Le menu, la recherche rapide, votre compte et le tableau de bord.',
		'pages' => ['/tableau-de-bord'], 'accueil' => true, 'hub' => true,
		'etapes' => [
			['titre' => 'Bienvenue dans Infinit Registrar',
			 'texte' => "Une minute pour trouver vos repères. Avancez avec les boutons ou les flèches du clavier ; Échap arrête la visite à tout moment."],
			['cible' => '#sidebarMenu nav', 'mobile' => '#topbarMenuToggle', 'titre' => 'Le menu',
			 'texte' => "Les pages que votre compte peut ouvrir, rangées par sections. Un clic sur le titre d'une section la replie ou la déplie.",
			 'texteMobile' => "Ce bouton ouvre le menu : les pages que votre compte peut ouvrir, rangées par sections."],
			['cible' => '#sidebarMenu .side-compte', 'mobile' => '[data-tuto="compte-mobile"]', 'titre' => 'Votre compte',
			 'texte' => "Un clic sur votre nom : Mon compte (photo, mot de passe), le Bulletin académique, ce didacticiel et la déconnexion."],
			['cible' => '.rc-declencheur', 'titre' => 'La recherche rapide',
			 'texte' => $recherche . " Depuis n'importe quelle page : Ctrl K, ou la touche /."],
			['cible' => '#themeToggle', 'titre' => 'Clair ou sombre',
			 'texte' => "Le thème qui convient à la lumière de votre bureau. Il reste choisi sur cet ordinateur."],
			['cible' => '.tb-annee', 'titre' => "L'année affichée",
			 'texte' => "Le tableau de bord porte sur l'année choisie ici. Ses chiffres se comparent à l'année précédente, à la même date."],
			['cible' => '.tb-stats', 'titre' => 'Les chiffres clés',
			 'texte' => "Inscrits, nouveaux étudiants, Licence et Master, candidatures. La ligne grise donne le point de comparaison."],
			['cible' => '.tb-rentree', 'titre' => 'La rentrée en direct',
			 'texte' => "Survolez la courbe, ou parcourez-la avec les flèches : chaque jour se compare à l'an dernier à la même date. « Jour par jour » montre le rythme des six dernières semaines ; la légende masque une année."],
			['cible' => '.tb-guichet', 'titre' => 'Dossiers au guichet',
			 'texte' => "Chaque dossier de l'année, à sa première étape à faire. Ouvrez une étape pour voir les dossiers qui l'attendent, les plus anciens en premier."],
			['cible' => '.tb-traiter', 'titre' => 'À traiter',
			 'texte' => "Ce qui attend une action : notes non saisies, candidatures, réinscriptions… Chaque ligne mène à la page où la régler."],
			['cible' => '[data-tuto="acces-rapides"]', 'titre' => 'Accès rapides',
			 'texte' => "Les pages les plus utiles pour votre rôle, à un clic."],
			['cible' => '.tb-activite', 'titre' => 'Activité',
			 'texte' => "Qui utilise l'application, jour après jour, d'après le journal : personnel, enseignants, étudiants. Les échecs de connexion et les erreurs serveur de la semaine y sont signalés."],
			$aide,
		],
	];

	$visites[] = [
		'id' => 'listes', 'pour' => 'personnel', 'groupe' => 'Étudiants', 'icone' => 'bi-people',
		'titre' => 'Trouver un étudiant',
		'resume' => 'Rechercher, filtrer et trier une liste, puis ouvrir une fiche.',
		'pages' => ['/etudiants', '/courses', '/professors'], 'depart' => '/etudiants', 'hub' => true,
		'etapes' => [
			['titre' => 'Les listes',
			 'texte' => "Étudiants, cours, enseignants : les trois listes se manient de la même façon."],
			['cible' => '.rc-declencheur', 'titre' => 'Chercher',
			 'texte' => "Tapez un nom ou un matricule, puis Entrée : le tableau ne garde que les lignes qui correspondent. Les flèches mènent aussi directement à une fiche."],
			['cible' => '#listeFiltresBouton', 'titre' => 'Filtres',
			 'texte' => "Mention, parcours, niveau, année… Chaque choix indique combien de lignes il donnerait. Les filtres s'ajoutent à la recherche."],
			['cible' => '#listeTriBouton', 'titre' => 'Trier',
			 'texte' => "Par colonne, croissant ou décroissant. Un clic sur l'en-tête d'une colonne fait la même chose."],
			['cible' => '#liste-barre', 'titre' => 'Ce qui est affiché',
			 'texte' => "Le nombre de lignes et ce qui les filtre ; chaque pastille se retire d'un clic sur ×. L'adresse de la page garde le tout : mettez-la en favori ou envoyez-la à un collègue."],
			['cible' => '.toolbar-container', 'titre' => 'Les actions',
			 'texte' => "Exports, communication, Work Education, badges : les documents et les envois tirés de la liste, selon vos droits."],
			['cible' => '#liste-suite:not([hidden])', 'titre' => 'La suite de la liste',
			 'texte' => "La liste se charge par lots pour rester rapide : ce bouton affiche les lignes suivantes."],
			['cible' => '#liste-corps tr:first-child', 'chemins' => ['/etudiants'], 'titre' => 'Ouvrir une fiche',
			 'texte' => "Un clic sur une ligne ouvre la fiche de l'étudiant. La visite peut continuer sur sa fiche.",
			 'action' => ['libelle' => 'Ouvrir cette fiche', 'lien' => '#liste-corps tr:first-child a[href]', 'suite' => 'fiche-etudiant']],
			['cible' => '#liste-corps tr:first-child', 'chemins' => ['/courses', '/professors'], 'titre' => 'Ouvrir une fiche',
			 'texte' => "Un clic sur une ligne ouvre sa fiche."],
		],
	];

	$visites[] = [
		'id' => 'fiche-etudiant', 'pour' => 'personnel', 'groupe' => 'Étudiants', 'icone' => 'bi-person-vcard',
		'titre' => "La fiche d'un étudiant",
		'resume' => "L'identité, les volets de la fiche et le passage d'un étudiant à l'autre.",
		'pages' => ['/student'], 'si' => '#zoneFiche',
		'etapes' => [
			['cible' => '.student-profile-sidebar', 'titre' => "L'étudiant",
			 'texte' => "Photo, matricule, mention, niveau et contacts. Un clic sur la photo : l'agrandir ou modifier le dossier."],
			['cible' => '.student-menu-items', 'titre' => 'Les volets de la fiche',
			 'texte' => "Informations, relevé, nouveaux cours, bulletin, historique des notes, diplôme… En bas, les décisions : suspension, retrait, suppression. Un volet grisé n'est pas ouvert à votre compte."],
			['cible' => '#zoneFiche', 'titre' => 'Le volet ouvert',
			 'texte' => "Passer d'un volet à l'autre ne recharge pas la page, et l'adresse garde le volet : le bouton Retour du navigateur y ramène."],
			['cible' => '.rc-declencheur', 'titre' => 'Un autre étudiant',
			 'texte' => "Ctrl K, son nom ou son matricule, Entrée : sa fiche s'ouvre, sans repasser par la liste."],
		],
	];

	$visites[] = [
		'id' => 'inscription', 'pour' => 'inscription', 'groupe' => 'Admissions', 'icone' => 'bi-person-plus',
		'titre' => 'Inscrire un étudiant',
		'resume' => "Ouvrir un dossier et suivre les étapes du guichet, de la session à la signature.",
		'pages' => ['/inscription'], 'depart' => '/inscription/inscription', 'si' => '.insc-parcours', 'hub' => true, 'suite' => 'inscription-dossier',
		'etapes' => [
			['cible' => '#form-recherche', 'titre' => 'Ouvrir un dossier',
			 'texte' => "Le matricule, le nom ou le prénom de l'étudiant, puis Ouvrir. L'adresse du dossier peut être rechargée ou envoyée à un collègue."],
			['cible' => '.insc-parcours', 'titre' => 'Les étapes du guichet',
			 'texte' => "Chaque dossier passe par ces étapes, dans cet ordre : session, informations, cours, paiement, fiche d'inscription, signatures."],
			['titre' => 'À vous',
			 'texte' => "Ouvrez un dossier : la visite continuera sur le guichet."],
		],
	];

	$visites[] = [
		'id' => 'inscription-dossier', 'pour' => 'inscription', 'groupe' => 'Admissions', 'icone' => 'bi-person-plus',
		'titre' => "Le guichet d'inscription",
		'resume' => "Le dossier ouvert : où l'on en est, et comment avancer.",
		'pages' => ['/inscription'], 'si' => '#inscGuichet',
		'etapes' => [
			['cible' => '.insc-dossier-tete', 'titre' => 'Le dossier',
			 'texte' => "L'étudiant : photo, type d'inscription, matricule et niveau. Vérifiez que c'est le bon avant d'aller plus loin."],
			['cible' => '#inscGuichet', 'titre' => 'Le guichet',
			 'texte' => "L'étape en cours est mise en avant. Pour un étudiant suspendu, cours, paiement et fiche sont bloqués."],
			['cible' => '.insc-actions', 'titre' => 'Avancer',
			 'texte' => "Suivant passe à l'étape d'après une fois celle-ci remplie ; Retour revient en arrière."],
			['cible' => '.insc-bande', 'titre' => 'État du dossier',
			 'texte' => "La session et l'avancement du dossier, tels qu'ils sont enregistrés."],
			['cible' => '#contentMain', 'titre' => "L'étape en cours",
			 'texte' => "Ce qu'il faut remplir maintenant. Tout commence par la session : le semestre et l'année de l'inscription."],
		],
	];

	$visites[] = [
		'id' => 'remises', 'pour' => 'registrariat', 'groupe' => 'Notes', 'icone' => 'bi-clipboard2-check',
		'titre' => 'Contrôler les remises de notes',
		'resume' => "Les feuilles transmises par les enseignants : contrôler, valider ou renvoyer.",
		'pages' => ['/remise-notes'], 'si' => '[data-tuto="remises-onglets"]', 'hub' => true,
		'etapes' => [
			['cible' => '[data-tuto="remises-entete"]', 'titre' => 'Les remises de notes',
			 'texte' => "Les enseignants saisissent leurs notes dans une feuille, puis la transmettent. Vous la contrôlez ici : validée, elle reporte les notes dans l'historique et le bulletin des étudiants."],
			['cible' => '[data-tuto="remises-onglets"]', 'titre' => 'Où en sont les feuilles',
			 'texte' => "En attente : transmises, à contrôler. Renvoyées : retournées à l'enseignant avec un motif. Puis les validées, les brouillons, ou toutes."],
			['cible' => '[data-tuto="remises-liste"] tbody tr:first-child', 'titre' => 'Ouvrir une feuille',
			 'texte' => "Ouvrez une feuille pour la contrôler. La visite continue sur la feuille.",
			 'action' => ['libelle' => 'Ouvrir cette feuille', 'lien' => '[data-tuto="remises-liste"] tbody tr:first-child a[href]', 'suite' => 'remise-controle']],
		],
	];

	$visites[] = [
		'id' => 'remise-controle', 'pour' => 'registrariat', 'groupe' => 'Notes', 'icone' => 'bi-clipboard2-check',
		'titre' => 'Contrôler une feuille',
		'resume' => 'La feuille transmise, le contrôle de cohérence, la décision.',
		'pages' => ['/remise-notes'], 'si' => '[data-tuto="remise-feuille"]',
		'etapes' => [
			['cible' => '[data-tuto="remise-feuille"]', 'titre' => 'La feuille transmise',
			 'texte' => "Le cours, l'enseignant, et la pondération qu'il a appliquée."],
			['cible' => '[data-tuto="remise-coherence"]', 'titre' => 'Le contrôle de cohérence',
			 'texte' => "Ce que l'application a vérifié : notes manquantes, hors barème, pondération incomplète… Une incohérence bloquante empêche la validation."],
			['cible' => '[data-tuto="remise-grille"]', 'titre' => 'Les notes',
			 'texte' => "Les notes de chaque étudiant, telles que l'enseignant les a saisies."],
			['cible' => '[data-tuto="remise-decision"]', 'titre' => 'La décision',
			 'texte' => "Valider reporte les notes dans l'historique et le bulletin. Renvoyer rend la feuille à l'enseignant, avec un motif qu'il lira en l'ouvrant."],
		],
	];

	/* --- Enseignants --------------------------------------------------------------- */
	$visites[] = [
		'id' => 'enseignant', 'pour' => 'enseignant', 'groupe' => 'Mon enseignement', 'icone' => 'bi-compass',
		'titre' => 'Premiers pas',
		'resume' => 'Votre espace : vos cours, vos étudiants, le menu et la recherche.',
		'pages' => ['/teacher/dashboard'], 'accueil' => true, 'hub' => true,
		'etapes' => [
			['titre' => 'Bienvenue dans votre espace enseignant',
			 'texte' => "Vos cours, vos étudiants, la remise des notes et les présences. Avancez avec les boutons ou les flèches du clavier ; Échap arrête la visite."],
			['cible' => '#sidebarMenu nav', 'mobile' => '#topbarMenuToggle', 'titre' => 'Votre menu',
			 'texte' => "Mes cours (cette page), Remise des notes et Présences.",
			 'texteMobile' => "Ce bouton ouvre le menu : Mes cours, Remise des notes et Présences."],
			['cible' => '[data-tuto="mes-cours"]', 'titre' => 'Mes cours',
			 'texte' => "Les cours qui vous sont attribués cette année. Le titre d'un cours ouvre sa feuille de notes."],
			['cible' => '[data-tuto="mes-etudiants"]', 'titre' => 'Mes étudiants',
			 'texte' => "Les étudiants inscrits à vos cours."],
			['cible' => '.rc-declencheur', 'titre' => 'La recherche rapide',
			 'texte' => $recherche . " Depuis n'importe quelle page : Ctrl K, ou la touche /."],
			$aide,
		],
	];

	$visites[] = [
		'id' => 'enseignant-remise', 'pour' => 'enseignant', 'groupe' => 'Mon enseignement', 'icone' => 'bi-journal-check',
		'titre' => 'Saisir et transmettre ses notes',
		'resume' => "Vos feuilles de notes, la saisie, puis la transmission au registrariat.",
		'pages' => ['/teacher/remise-notes'], 'si' => '[data-tuto="remise-principe"]', 'hub' => true,
		'etapes' => [
			['cible' => '[data-tuto="remise-principe"]', 'titre' => 'Comment ça marche',
			 'texte' => "Une feuille par cours et par semestre. Vous la remplissez, puis vous la transmettez ; le registrariat la contrôle, et sa validation reporte les notes dans le bulletin."],
			['cible' => '#remiseAnnee', 'titre' => "L'année",
			 'texte' => "Les feuilles de l'année choisie ici."],
			['cible' => '[data-tuto="feuilles"]', 'titre' => 'Vos feuilles',
			 'texte' => "Une ligne par cours et par semestre, avec son état : brouillon, en attente de validation, renvoyée pour correction, validée."],
			['cible' => '[data-tuto="feuilles"] tbody tr:first-child', 'titre' => 'Ouvrir une feuille',
			 'texte' => "Ouvrez une feuille pour saisir les notes. La visite continue sur la feuille.",
			 'action' => ['libelle' => 'Ouvrir cette feuille', 'lien' => '[data-tuto="feuilles"] tbody tr:first-child a.bouton[href]', 'suite' => 'enseignant-feuille']],
		],
	];

	$visites[] = [
		'id' => 'enseignant-feuille', 'pour' => 'enseignant', 'groupe' => 'Mon enseignement', 'icone' => 'bi-journal-check',
		'titre' => 'La feuille de notes',
		'resume' => 'Colonnes, pondération, saisie, contrôle et transmission.',
		'pages' => ['/teacher/remise-notes'], 'si' => '[data-tuto="feuille-grille"]',
		'etapes' => [
			['cible' => '[data-tuto="feuille-cartouche"]', 'titre' => 'La feuille',
			 'texte' => "Les mêmes informations que la feuille imprimée : cours, semestre, nombre d'étudiants, notes prêtes."],
			['cible' => '[data-tuto="feuille-poids"]', 'titre' => 'Les colonnes et la pondération',
				 'texte' => "Ajoutez autant de colonnes que vous voulez (Quiz, TP, exposé...) avec « Colonne », donnez-leur un nom, puis indiquez la part de chacune dans la ligne « Pondération » : le total doit faire 100 %. « Répartir à parts égales » fait le partage pour vous."],
			['cible' => '[data-tuto="feuille-grille"]', 'titre' => 'Les notes',
			 'texte' => "Une ligne par étudiant, comme dans un tableur : flèches, Ctrl+C / Ctrl+V (même depuis Excel), Ctrl+Z, glisser pour choisir plusieurs cases, bordures à tirer pour agrandir une colonne ou une ligne. La note sur 20 se calcule seule (survolez-la pour voir le calcul) ; chaque note est enregistrée dès que vous quittez la case."],
			['cible' => '[data-tuto="feuille-transmettre"]', 'titre' => 'Contrôle et transmission',
			 'texte' => "Ce qui manque ou ne va pas s'affiche ici. Quand tout est prêt, Transmettre envoie la feuille au registrariat ; elle reste en lecture seule jusqu'à sa décision."],
			['cible' => '#basculeJournal', 'titre' => 'Le journal',
			 'texte' => "L'historique de la feuille : enregistrements, transmissions, renvois."],
		],
	];

	/* Une visite pour chaque page du menu */
	require_once __DIR__ . '/didacticiel.pages.php';
	$visites = array_merge($visites, didacticielVisitesPages());

	$visites = array_values(array_filter($visites, fn ($v) => !empty($p[$v['pour']])));
	foreach ($visites as &$v) {
		$v['titre'] = didacticielTypo($v['titre']);
		$v['resume'] = didacticielTypo($v['resume']);
		foreach ($v['etapes'] as &$e) {
			foreach (['titre', 'texte', 'texteMobile'] as $k) {
				if (isset($e[$k])) { $e[$k] = didacticielTypo($e[$k]); }
			}
		}
		unset($e);
	}
	unset($v);
	return $visites;
}

/** Espaces insecables de la typographie francaise : « : » ou « ? » ne commencent jamais une ligne. */
function didacticielTypo(string $t): string
{
	return strtr($t, [' :' => "\u{00A0}:", ' ;' => "\u{00A0};", ' ?' => "\u{00A0}?", ' !' => "\u{00A0}!", '« ' => "«\u{00A0}", ' »' => "\u{00A0}»"]);
}

/**
 * Ce que le moteur recoit sur chaque page : la liste des visites du compte,
 * avec les etapes de celles qui se deroulent ici seulement.
 */
function didacticielConfig(string $chemin, string $appBase): array
{
	$p = didacticielProfil();
	$visites = [];
	foreach (didacticielVisites($p) as $v) {
		$ici = in_array($chemin, $v['pages'], true);
		$visites[] = array_filter([
			'id'      => $v['id'],
			'titre'   => $v['titre'],
			'pages'   => $v['pages'],
			'si'      => $v['si'] ?? null,
			'accueil' => !empty($v['accueil']),
			/* Le texte de l'invitation « Premiere visite ? », si la visite a le sien */
			'invitation' => $ici && !empty($v['accueil']) ? ($v['invitation'] ?? null) : null,
			'suite'   => $v['suite'] ?? null,
			'etapes'  => $ici ? $v['etapes'] : null,
		], fn ($x) => $x !== null);
	}
	return [
		'utilisateur' => (int) ($_SESSION['user_id'] ?? 0),
		'chemin'      => $chemin,
		'aide'        => $appBase . '/didacticiel',
		'visites'     => $visites,
	];
}

}
