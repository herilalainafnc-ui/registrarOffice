<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul (un compte etudiant les lancait)
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require ('../../data/backdb.php');

	$name = $_POST['name'];
	$lastName = $_POST['lastName'];
	$birthday = $_POST['birthday'];
	$lieuN = $_POST['lieuN'];
	$address = $_POST['address'];
	$sex = $_POST['sex'];
	$blood_group = $_POST['blood_group'];
	$phone = $_POST['phone'];
	$email = $_POST['email'];
	$diplome = $_POST['diplome'];
	$religion = $_POST['religion'];
	$position = $_POST['position'];

	/*:::::::::::::::::::: UID GENERATE ::::::::::::::::::::*/

	$uid = rand(1000,9999);

	/*:::::::::::::::::::: IMAGE GENERATE ::::::::::::::::::::*/

	/* Photo : contenu verifie et reencode, nom fabrique par le serveur
	   (data/photo.php). Le controle d'extension n'etait pas lu — tout fichier
	   passait, un script PHP compris —, et le dossier, relatif a app/.prof,
	   n'etait pas celui qu'affichent les pages (app/photosenseignants). Une
	   photo refusee n'empeche pas la creation de la fiche. */
	require_once __DIR__ . '/../../data/photo.php';
	$photo = photoEnregistrer($_FILES['teacher_image'] ?? null, __DIR__ . '/../photosenseignants', 't' . $uid);
	$teacher_image = $photo['nom'] ?? '';


	$insertProf = $dtb->prepare('INSERT INTO teacher(
		uid,
		name,
		lastName,
		birthday,
		lieuN,
		address,
		sex,
		blood_group,
		teacher_image,
		phone,
		email,
		diplome,
		religion,
		position

		) VALUES (
		:uid,
		:name,
		:lastName,
		:birthday,
		:lieuN,
		:address,
		:sex,
		:blood_group,
		:teacher_image,
		:phone,
		:email,
		:diplome,
		:religion,
		:position

		)');$insertProf->execute(array(

		'uid' => $uid,
		'name' => $name,
		'lastName' => $lastName,
		'birthday' => $birthday,
		'lieuN' => $lieuN,
		'address' => $address,
		'sex' => $sex,
		'blood_group' => $blood_group,
		'teacher_image' => $teacher_image,
		'phone' => $phone,
		'email' => $email,
		'diplome' => $diplome,
		'religion' => $religion,
		'position' => $position

		));

		header('location:' . $app_base . '/professors' . ($photo['erreur'] !== null ? '?error=photo' : ''))
 ?>