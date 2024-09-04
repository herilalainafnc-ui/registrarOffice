<?php 
require ('../../data/backdb.php');
	$nom = $_POST['nom'];
	$prenom = $_POST['prenom'];
	$pseudo = $_POST['pseudo'];
	$password = $_POST['password'];
	$confirmPass = $_POST['confirmpass'];
	$photos = $_FILES['photos']['name'];
	$photos_tmp = $_FILES['photos']['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG');
	$extension_photos = strrchr($photos,".");
	$photos_dest = '../photosuser/';
	$etat = 1;
	$photosname = $prenom.$extension_photos;
	$privilege = $_POST['privilege'];
	$theme = 'Blue';
	
if(isset($_POST['pseudo']) and isset($_POST['password']) AND !empty($password) and !empty($confirmPass)){
	if($password == $confirmPass){
	in_array($extension_photos, $extension);
	move_uploaded_file($photos_tmp, $photos_dest.$photosname);

	$insertuser = $dtb->prepare("INSERT INTO compt_utilisateur(
			nom,
			prenom,
			pseudo,
			password,
			privilege,
			photos,
			etat,
			theme
		) VALUES(
			:nom,
			:prenom,
			:pseudo,
			:password,
			:privilege,
			:photos,
			:etat,
			:theme
)");$insertuser->execute(array(
			'nom' => $nom,
			'prenom' => $prenom,
			'pseudo' => $pseudo,
			'password' => $password,
			'privilege' => $privilege,
			'photos' => $photosname,
			'etat' => $etat,
			'theme' => $theme
));
header('location:../../src/creat.account.php');
	}else{
 	echo "Votre confirmation de mot de passe n'est pas identique à l'origine!!";
	}
}
?>
