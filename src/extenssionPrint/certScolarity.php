<?php 
$id = $_GET['id']; 
$student_id = $_GET['student_id']; 
$student_nom = $_GET['student_nom']; 
$student_prenom = $_GET['student_prenom']; 
$etude_envisage = $_GET['etude_envisage']; 
$etude_option = $_GET['etude_option']; 
$student_tel = $_GET['student_tel']; 
$image_student = $_GET['image_student']; 

$printName = $student_id."-CERTIFICAT_SCOLARITE";  

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"'); 
$stdA = $searchStd->fetch();

// Récupérer les infos de l'utilisateur connecté pour la signature
$infinit_pseudo = $_SESSION['infinit_pseudo'] ?? '';
$infinit_password = $_SESSION['infinit_password'] ?? '';

if(!empty($infinit_pseudo) && !empty($infinit_password)){
  $rg_utilisateur = $dtb->query("SELECT * FROM compt_utilisateur WHERE pseudo='".$infinit_pseudo."' AND password='".$infinit_password."' AND etat=1 limit 1");
  if($rg_utilisateur->rowCount() > 0){
    $rg_user = $rg_utilisateur->fetch();
    $user_nom = $rg_user['nom'];
    $user_prenom = $rg_user['prenom'];
    $user_post = $rg_user['post'];
  } else {
    $user_nom = 'Utilisateur';
    $user_prenom = '';
    $user_post = 'Secrétaire Académique';
  }
} else {
  $user_nom = 'Utilisateur';
  $user_prenom = '';
  $user_post = 'Secrétaire Académique';
}

// Fonction pour formater les dates en français
function formatDateFr($date) {
  $mois_fr = array(
    'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
    'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
    'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
    'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'
  );
  
  $dateObj = new DateTime($date);
  $jour = $dateObj->format('d');
  $mois_en = $dateObj->format('F');
  $annee = $dateObj->format('Y');
  
  $mois_fr_val = $mois_fr[$mois_en];
  
  return $jour . ' ' . $mois_fr_val . ' ' . $annee;
}
?>  

<style>
  body {
    font-family: 'Times New Roman', Times, serif;
    line-height: 1.6;
  }

  @font-face {
    font-family: 'Matura MT Script Capitals';
    src: local('Matura MT Script Capitals'), local('Matura-MT');
  }

  .certificate-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0px 40px 30px 40px;
    text-align: center;
  }

  .header-section {
    border-bottom: 3px solid #000;
    padding-bottom: 20px;
    margin-bottom: 30px;
  }

  .logo-section {
    margin-bottom: 15px;
    display: flex;
    justify-content: center;
  }

  .logo-section img {
    width: 80px;
    height: auto;
  }

  .university-name {
    font-size: 18px;
    font-weight: bold;
    margin: 10px 0 5px 0;
  }

  .university-info {
    font-size: 12px;
    line-height: 1.4;
    margin: 5px 0;
  }

  .motto {
    font-size: 15px;
    font-style: italic;
    margin-top: 10px;
    font-weight: bold;
    font-family: 'Matura MT Script Capitals';
  }

  .certificate-title {
    font-size: 24px;
    font-weight: bold;
    margin: 30px 0 25px 0;
    letter-spacing: 1px;
  }

  .certificate-intro {
    font-size: 14px;
    margin-bottom: 25px;
    text-align: center;
  }

  /* --- STYLE CORRIGÉ POUR LES LIGNES --- */
  .certificate-content {
    text-align: left;
    font-size: 13px;
    line-height: 1.8;
    margin: 25px 0;
  }

  .certificate-content .row {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 6px;
  }

  .certificate-content .label {
    width: 180px; /* largeur fixe pour aligner les libellés */
    text-align: left;
    font-style: italic;
    font-weight: normal;
    flex-shrink: 0;
  }

  .certificate-content .label::after {
    content: " :";
  }

  .certificate-content .value {
    flex: 1;
    font-weight: bold;
    text-align: left;
    word-wrap: break-word;
  }

  .paragraph-text {
    text-align: justify;
    margin: 20px 0;
    text-indent: 40px;
  }

  .signature-section {
    margin-top: 60px;
    text-align: center;
  }

  .date-location {
    margin-bottom: 40px;
    text-align: right;
    font-size: 13px;
  }

  .signature-line {
    margin-top: 60px;
    font-size: 13px;
  }

  .signature-name {
    margin-top: 10px;
    font-style: italic;
    font-weight: bold;
  }
</style>


<div class="certificate-container">

  <!-- Header Section -->
  <div class="header-section">
    <div class="logo-section">
      <img src="../file/UAZ Official Black Logo.jpg" alt="Logo">
    </div>
    <div class="university-name">Université Adventiste Zurcher</div>
    <div class="university-info">
      BP, 325 Antsirabe (110)<br>
      Tél : 034 46 000 08 / 034 38 180 81<br>
      Email : <a href="mailto:registraroffice@zurcher.edu.mg">registraroffice@zurcher.edu.mg</a>
    </div>
    <div class="motto">Préparer aujourd'hui les leaders de demain</div>
  </div>

  <!-- Certificate Title -->
  <div class="certificate-title">CERTIFICAT DE SCOLARITÉ</div>

  <!-- Introduction -->
  <div class="certificate-intro">
    Je, soussigné, secrétaire académique de l'Université Adventiste Zurcher, certifie que :
  </div>

  <!-- Certificate Content -->
  <div class="certificate-content">
    <div class="row">
      <span class="label">Nom et prénoms</span>
      <span class="value"><?= strtoupper($stdA['student_nom']) . " " . $stdA['student_prenom'] ?></span>
    </div>

    <div class="row">
      <span class="label">Date de naissance</span>
      <span class="value">
        <?= formatDateFr($stdA['dateNaissance']) ?>
      </span>
    </div>

    <div class="row">
      <span class="label">Lieu de naissance</span>
      <span class="value"><?= $stdA['lieuNaissance'] ?></span>
    </div>

    <div class="row">
      <span class="label">Fils/Fille de</span>
      <span class="value"><?= $stdA['father_name'] ?></span>
    </div>

    <div class="row">
      <span class="label">Et de</span>
      <span class="value"><?= $stdA['mother_name'] ?></span>
    </div>

    <div class="row">
      <span class="label">Adresse habituelle</span>
      <span class="value"><?= $stdA['student_adresse'] ?></span>
    </div>
  </div>

  <!-- Paragraph Text -->
  <div class="paragraph-text">
    est inscrit(e) dans la mention « <strong><?= $stdA['etude_envisage'] ?></strong> » de notre Institution,
    comme étudiant(e) régulièr(e), parcours « <strong><?= $etude_option ?></strong> »
    pendant l'année universitaire <strong><?= $stdA['annee_scolaire'] ?></strong>.
  </div>

  <!-- Student ID -->
  <div class="certificate-content">
    <div class="row">
      <span class="label">Numéro d'immatriculation</span>
      <span class="value"><?= $student_id ?></span>
    </div>
  </div>

  <!-- Final Paragraph -->
  <div class="paragraph-text">
    Ce certificat lui est délivré pour valoir et servir ce que de droit.
  </div>

  <!-- Date and Location -->
  <div class="date-location">
    Sambaina, le <?= formatDateFr(date('Y-m-d')) ?>
  </div>

  <!-- Signature Section -->
  <div class="signature-section">
    <div style="font-size: 13px;"><?= $user_post ?></div>
    <div class="signature-line">
      <div style="margin-bottom: 50px;">&nbsp;</div>
    </div>
    <div class="signature-name"><?= strtoupper($user_nom) . " " . $user_prenom ?></div>
  </div>

</div>
