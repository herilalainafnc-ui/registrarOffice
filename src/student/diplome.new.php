<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); // notes : réservées au personnel
/**
 * Diplome — NOUVEAU MODELE (a partir de 2025), calque sur "Diplôme 2026.docx".
 *
 * Imprime sur le papier pre-imprime (matrice securisee) : l'export ne contient
 * que le texte et les signatures, sans fond. Le calage (offsets X/Y en mm) se
 * regle dans Parametres > Diplome.
 *
 * Geometrie reprise du document Word : A4 paysage 297 x 210 mm,
 * marges haut 12.5 / bas 25 / gauche 37.5 / droite 59.5 mm.
 *
 * Variables attendues : $dtb, $app_base, $profil, $student_id, $student_nom,
 * $student_prenom, $level, $etude_envisage, $etude_option, $annee_scolaire,
 * $totalGenCumul, $mentionPromFR.
 */

require_once(__DIR__ . '/../../data/diplome.config.php');
$cfg = diplomeConfig($dtb);

// ------------------------------------------------------------- Accords
// sex : 1 = masculin, 0 = feminin. La civilite (M. / Mme / Mlle) n'est pas
// imprimee sur le diplome, mais le genre reste necessaire pour les accords.
$estFeminin = isset($profil['sex']) && (string)$profil['sex'] === '0';
$accordNe   = $estFeminin ? 'née' : 'né';
$accordInt  = $estFeminin ? "l'intéressée" : "l'intéressé";

// Libelle de la signature de l'etudiant : accorde au feminin ("L'étudiante").
// Le libelle etant parametrable, on n'ajoute le "e" que s'il n'y est pas deja.
$libelleEtudiant = trim((string)$cfg['diplome_etudiant_libelle']);
if ($estFeminin && $libelleEtudiant !== '' && mb_substr($libelleEtudiant, -1, 1, 'UTF-8') !== 'e') {
	$libelleEtudiant .= 'e';
}

// Nom affiche au centre du diplome : NOM de famille en capitales, prenoms en casse normale
$nomComplet = trim(strtoupper($student_nom) . ' ' . ucwords(strtolower($student_prenom)));
// Nom cite dans les considerants ("Vu les pieces justificatives...") : tout en capitales
$nomMajuscule = trim(mb_strtoupper($student_nom . ' ' . $student_prenom, 'UTF-8'));

// ------------------------------------------------------------- Programme
$estMaster = ($level > 3);
$grade = $estMaster ? 'Master' : 'Licence';
// Titre en haut du diplome : le grade seul, en capitales ("LICENCE" / "MASTER")
$gradeTitre = mb_strtoupper($grade, 'UTF-8');
// Libelle complet (casse normale), utilise dans les considerants et l'attribution
$programme = $grade . ' en ' . $etude_envisage;
// "La Licence ... est décernée" (feminin) / "Le Master ... est décerné" (masculin)
$articleDiplome = $estMaster ? 'Le' : 'La';
$accordDecerne  = $estMaster ? 'décerné' : 'décernée';

// ------------------------------------------------------------- Naissance
$dateNaissance = !empty($profil['dateNaissance']) ? date('d/m/Y', strtotime($profil['dateNaissance'])) : '';
$lieuNaissance = !empty($profil['lieuNaissance']) ? $profil['lieuNaissance'] : '';

// ------------------------------------------------------------- Arretes
// Les arretes different selon la filiere et le niveau (table diplome_arretes).
$arr = diplomeArretes($dtb, $estMaster, $etude_envisage);
$arrete1 = $arr['trouve'] && $arr['mesupres'] ? $arr['mesupres'] : $cfg['diplome_arrete_1'];
$arrete2 = $arr['trouve'] && $arr['cneat']    ? $arr['cneat']    : $cfg['diplome_arrete_2'];

// ------------------------------------------------------------- Numero de serie
// Format du publipostage : Année + Accronyme filière + Accronyme parcours + Matricule
$anneeRef = date('Y');
if (preg_match_all('/(\d{4})/', (string)$annee_scolaire, $mm) && !empty($mm[1])) {
	$anneeRef = end($mm[1]);
}
$accFiliere = $arr['accronyme'];
if (!$accFiliere) { // repli : 2 premieres lettres de la filiere
	$accFiliere = mb_strtoupper(mb_substr(preg_replace('/\s+/', '', (string)$etude_envisage), 0, 2, 'UTF-8'), 'UTF-8');
}
// Accronyme du parcours : initiales des mots significatifs ("Média et journalisme" -> MJ)
$accParcours = '';
foreach (preg_split('/\s+/', (string)$etude_option) as $mot) {
	if (mb_strlen($mot, 'UTF-8') > 2 && !in_array(mb_strtolower($mot, 'UTF-8'), ['et', 'de', 'des', 'du', 'en', 'la', 'le', 'les'], true)) {
		$accParcours .= mb_strtoupper(mb_substr($mot, 0, 1, 'UTF-8'), 'UTF-8');
	}
}
$numeroSerie = $anneeRef . $accFiliere . $accParcours . $student_id;

// ------------------------------------------------------------- Mention
// La mention officiellement enregistree prime sur la mention recalculee.
$mentionTexte = '';
try {
	$stM = $dtb->prepare('SELECT mention FROM graduating_std WHERE student_id = :sid AND mention <> "" ORDER BY last_change_datetime DESC LIMIT 1');
	$stM->execute([':sid' => $student_id]);
	if ($rowM = $stM->fetch()) { $mentionTexte = $rowM['mention']; }
} catch (Exception $e) { /* ignore */ }
if ($mentionTexte === '') { $mentionTexte = (string)$mentionPromFR; }
// Nettoyage : "Mention : Très Bien" / "Mention Assez-bien" -> "Très Bien" / "Assez-bien"
$mentionTexte = trim(preg_replace('/^\s*mention\s*:?\s*/iu', '', $mentionTexte));

// ------------------------------------------- Ligne "Vu les pieces justificatives"
// Les noms tres longs font deborder cette ligne sur 200 mm de large, et le "né"
// final se retrouve seul sur la ligne suivante. On reduit legerement le corps
// pour que la ligne tienne d'un seul tenant ; un espace insecable avant "né"
// garantit qu'il ne soit jamais orphelin si un retour a la ligne subsiste.
$ligneVu = 'Vu les pièces justificatives produites par ' . $nomMajuscule . ', ' . $accordNe;
$longueurVu = mb_strlen($ligneVu, 'UTF-8');
if ($longueurVu > 104)      { $tailleLigneVu = '10pt'; }
elseif ($longueurVu > 92)   { $tailleLigneVu = '11pt'; }
else                        { $tailleLigneVu = '12pt'; }

// ------------------------------------------------------------- Date de remise
// C'est la date de fin du semestre en cours : colonne graduation_date de la
// session active (selected = 1) dans t_2023_session. Une valeur saisie dans
// Parametres > Diplome prend le dessus ; sinon repli sur la date du jour.
$moisFr = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',
           7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
$formateDate = function($ts) use ($moisFr) {
	return date('d', $ts) . ' ' . $moisFr[(int)date('n', $ts)] . ' ' . date('Y', $ts);
};

$dateTexte = trim((string)$cfg['diplome_date_remise']);
if ($dateTexte === '') {
	try {
		$stS = $dtb->query("SELECT graduation_date FROM t_2023_session WHERE selected = 1 AND graduation_date IS NOT NULL ORDER BY session_id DESC LIMIT 1");
		if ($rowS = $stS->fetch()) {
			$dateTexte = $formateDate(strtotime($rowS['graduation_date']));
		}
	} catch (Exception $ex) { /* ignore */ }
}
if ($dateTexte === '') { $dateTexte = $formateDate(time()); }
$dateRemise = $cfg['diplome_lieu'] . ', le ' . $dateTexte;

// ------------------------------------------------------------- Calage
// Positions reprises du rendu reel du document Word : le texte est centre dans
// la zone de texte (37.5 -> 237.5 mm) et le milieu de la page reste vide pour
// laisser apparaitre le sceau de la matrice pre-imprimee.
$offX = (float)$cfg['diplome_offset_x'];
$offY = (float)$cfg['diplome_offset_y'];
$sigDir = $app_base . '/file/signatures/';

$e = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
?>

<style type="text/css">
/* ===== Feuille A4 paysage (297 x 210 mm) — nouveau modele de diplome ===== */
.dip-sheet{
	position: relative;
	width: 297mm; height: 210mm;
	background: #fff; color: #000;
	font-family: 'Times New Roman', Times, serif;
	overflow: hidden;
	margin: 0 auto;
}
.dip-inner{ position:absolute; inset:0; transform: translate(<?=$offX?>mm, <?=$offY?>mm); }
.dip-sheet .dip-abs{ position:absolute; }
.dip-sheet input{
	font-family: inherit; color:#000; background:transparent;
	border:0; padding:0; margin:0; outline:none;
}

/* Repere de zone imprimable — ecran uniquement, jamais exporte */
.dip-guide{
	position:absolute; border:1px dashed rgb(var(--c-line-strong)); opacity:.5;
	left:37.5mm; top:12.5mm; width:200mm; height:172.5mm; pointer-events:none;
}
.dip-exporting .dip-guide{ display:none; }

/* --- Textes verticaux du bord gauche (rotation -90°, lecture bas vers haut) ---
   L'origine est en haut a gauche : apres rotation le texte monte depuis ce point,
   donc `top` correspond au BAS du texte. */
.dip-vrt{
	position:absolute;
	transform-origin: 0 0;
	transform: rotate(-90deg);
	white-space: nowrap;
	font-size: 11pt;
	font-style: italic;
}
.dip-vrt input{ font-style:italic; font-size:11pt; }

/* --- Titre : programme suivi (24 pt gras, centre) --- */
.dip-title{ left:22.5mm; top:13mm; width:200mm; }
.dip-title input{ font-size:24pt; font-weight:bold; text-align:center; width:100%; }

/* --- Considerants : CENTRES, interligne serre (comme le Word) --- */
.dip-body{ left:37.5mm; top:24mm; width:200mm; font-size:12pt; line-height:1.3; text-align:center; }
.dip-body p{ margin:0; }

/* --- Attribution : bas de page, centree (le milieu reste vide pour le sceau) --- */
.dip-award{ left:37.5mm; top:146mm; width:200mm; font-size:12pt; line-height:1.1; text-align:center; }
.dip-award p{ margin:0; }
/* Diplôme et parcours : gras seul. Seule la mention est en gras italique. */
.dip-award .dip-field{ font-weight:bold; }
.dip-award .dip-field-mention{ font-weight:bold; font-style:italic; }

/* Champ modifiable en ligne (mention) : repère discret à l'écran, rien à l'export.
   contenteditable plutôt qu'un <input> pour que le texte reste dans le fil de la
   phrase et s'adapte à sa longueur. */
.dip-edit{ outline:none; cursor:text; }
.dip-edit:hover{ background:rgb(var(--c-accent) / .12); }
.dip-edit:focus{ background:rgb(var(--c-accent) / .20); }
.dip-edit:empty::before{ content:'(mention)'; color:rgb(var(--c-text-3)); font-weight:normal; }
.dip-exporting .dip-edit{ background:transparent !important; }
.dip-exporting .dip-edit:empty::before{ content:''; }
/* Mention vide : on n'imprime pas ", mention" (classe posée à l'export par le JS) */
.dip-mention-wrap.dip-hidden{ display:none; }
.dip-award .dip-name input{ font-size:14pt; font-weight:bold; text-align:center; width:100%; }

/* --- Signatures (11 pt) : titre + nom, puis la signature EN DESSOUS --- */
.dip-sign{ top:162mm; text-align:center; font-size:11pt; line-height:1.25; }
.dip-sign .dip-sign-role{ font-style:italic; }
.dip-sign .dip-sign-img{ display:block; margin:0.5mm auto 0 auto; }

/* Ecran : cadre autour de la feuille */
.dip-frame{ overflow:auto; padding:6px; background:#e2e8f0; border-radius:6px; }
.dip-frame .dip-sheet{ box-shadow:0 2px 12px rgba(0,0,0,.35); }
</style>

<div class="dip-frame">
	<div class="dip-sheet" id="diplomeNouveau">
		<div class="dip-inner">

			<div class="dip-guide"></div>

			<!-- Bord gauche : numero de serie (vertical, partie haute ; `top` = bas du texte).
			     Meme `left` que la date pour que les deux soient alignes verticalement. -->
			<div class="dip-vrt" style="left:15mm; top:58mm;">
				<input type="text" name="numero_serie" value="<?=$e($numeroSerie)?>" style="width:58mm;">
			</div>

			<!-- Bord gauche : lieu et date de remise (vertical, partie basse).
			     Remonte de 12 mm avec le bloc attribution / signatures. -->
			<div class="dip-vrt" style="left:15mm; top:189mm;">
				<input type="text" name="date_remise" value="<?=$e($dateRemise)?>" style="width:62mm;">
			</div>

			<!-- Titre : le grade seul (Licence / Master) -->
			<div class="dip-abs dip-title">
				<input type="text" name="programme_suivi" value="<?=$e($gradeTitre)?>">
			</div>

			<!-- Considerants : un paragraphe centre par ligne, comme dans le Word -->
			<div class="dip-abs dip-body">
				<p>Vu l'arrêté ministériel <?=$e($arrete1)?> ;</p>
				<p>Vu l'arrêté ministériel <?=$e($arrete2)?> ;</p>
				<p class="dip-nowrap-fit" style="font-size:<?=$tailleLigneVu?>;">Vu les pièces justificatives produites par <?=$e($nomMajuscule)?>,&nbsp;<?=$accordNe?></p>
				<p>le <?=$e($dateNaissance)?> à <?=$e($lieuNaissance)?>, en vue de son inscription en <?=$e($programme)?> ;</p>
				<p>Vu le procès-verbal du comité académique attestant que <?=$accordInt?> a satisfait au contrôle des connaissances et des aptitudes prévu par les textes réglementaires ;</p>
			</div>

			<!-- Attribution (bas de page) -->
			<div class="dip-abs dip-award">
				<p><?=$articleDiplome?> <span class="dip-field"><?=$e($programme)?></span><?php if (trim((string)$etude_option) !== ''): ?>, parcours <span class="dip-field"><?=$e($etude_option)?></span><?php endif; ?><span class="dip-mention-wrap">, mention <span class="dip-field-mention dip-edit" contenteditable="true" spellcheck="false" title="Cliquer pour modifier la mention"><?=$e($mentionTexte)?></span></span> est <?=$accordDecerne?> à</p>
				<p class="dip-name"><input type="text" name="nom_diplome" value="<?=$e($nomMajuscule)?>"></p>
				<p>au titre de l'année universitaire <?=$e($annee_scolaire)?> pour en jouir avec les droits et prérogatives qui y sont attachés.</p>
			</div>

			<!-- Rangee de signatures — positions reprises du document Word :
			     etudiant 45 mm (l.44), president 139.3 mm (l.59), recteur 199.7 mm (l.40.7).
			     Les images de signature ont leur propre calage dans le bloc (ancres Word). -->

			<!-- Signature : l'etudiant -->
			<div class="dip-abs dip-sign" style="left:45mm; width:44mm;">
				<div class="dip-sign-role"><?=$e($libelleEtudiant)?></div>
			</div>

			<!-- Signature : president du comite directeur (signature sous le nom) -->
			<div class="dip-abs dip-sign" style="left:139.3mm; width:59mm;">
				<div class="dip-sign-role"><?=$e($cfg['diplome_president_titre'])?></div>
				<div class="dip-sign-name"><?=$e($cfg['diplome_president_nom'])?></div>
				<?php if (!empty($cfg['diplome_president_signature'])): ?>
					<img class="dip-sign-img" src="<?=$sigDir.rawurlencode($cfg['diplome_president_signature'])?>" alt="" style="width:32mm; margin-left:9.75mm;">
				<?php endif; ?>
			</div>

			<!-- Signature : recteur (signature sous le nom) -->
			<div class="dip-abs dip-sign" style="left:199.7mm; width:40.7mm;">
				<div class="dip-sign-role"><?=$e($cfg['diplome_recteur_titre'])?></div>
				<div class="dip-sign-name"><?=$e($cfg['diplome_recteur_nom'])?></div>
				<?php if (!empty($cfg['diplome_recteur_signature'])): ?>
					<img class="dip-sign-img" src="<?=$sigDir.rawurlencode($cfg['diplome_recteur_signature'])?>" alt="" style="width:22mm; margin-left:7.3mm;">
				<?php endif; ?>
			</div>

		</div>
	</div>
</div>
