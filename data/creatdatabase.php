<?php  
	try {
		$dtb = new PDO('mysql:host=localhost','herilalaina','J8wFF(FOy1KI(nay');
		$dtb->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
	}catch(PDOException $e) {
		die('Incorrect connexion :'. $e->getMessage());
	}

	$creationdb = ("CREATE DATABASE student_db");
	
	$dtb->exec($creationdb);

	$dtb = new PDO('mysql:host=localhost;dbname=student_db','herilalaina','J8wFF(FOy1KI(nay');
	$dtb->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

	$rg_user = ('CREATE TABLE rg_user(
		id INT AUTO_INCREMENT PRIMARY KEY,
		user_name VARCHAR(50),
		user_last_name VARCHAR(50),
		user_pseudo VARCHAR(50),
		user_password VARCHAR(50),
		user_photos VARCHAR(100),
		date_entry date,
		modification_id INT,
		modification_date date
	)');

	$dtb->exec($rg_user);

	$defaultUser = $dtb->prepare('INSERT INTO rg_user(
		user_name,
		user_last_name,
		user_pseudo,
		user_password
	
	) VALUES ( 
		"Ramahoherilalaina",
		"Lovasoa Jimmy",
		"Herilalaina",
		"Herilalaina2804"
	)');

	$defaultUser->execute();
?>