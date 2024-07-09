<head>
	<?php 
	session_start();

	/*:::::::::::::::::::::::: SESSION COLORS ::::::::::::::::::::::::*/
	
	$_SESSION['bg_one_color'] = 'bg-slate-800';
	$_SESSION['bg_two_color'] = 'bg-slate-700';
	$_SESSION['bg_three_color'] = 'bg-slate-600';
	$_SESSION['bg_four_color'] = 'bg-slate-500';
	$_SESSION['bg_five_color'] = 'bg-slate-400';
	$_SESSION['bg_six_color'] = 'bg-slate-300';
	$_SESSION['bg_seven_color'] = 'bg-slate-200';
	$_SESSION['bg_eight_color'] = 'bg-slate-100';

	$_SESSION['br_two_color'] = 'border-slate-700';
	$_SESSION['br_three_color'] = 'border-slate-200';
	
	$_SESSION['txt_one_color'] = 'text-slate-100';
	$_SESSION['txt_two_color'] = 'text-slate-400';
	$_SESSION['txt_three_color'] = 'text-slate-800';

	/*$_SESSION['bg_one_color'] = 'bg-slate-100';
	$_SESSION['bg_two_color'] = 'bg-slate-200';
	$_SESSION['bg_three_color'] = 'bg-slate-300';
	$_SESSION['bg_four_color'] = 'bg-slate-400';
	$_SESSION['bg_five_color'] = 'bg-slate-500';
	$_SESSION['bg_six_color'] = 'bg-slate-600';
	$_SESSION['bg_seven_color'] = 'bg-slate-700';
	$_SESSION['bg_eight_color'] = 'bg-slate-800';

	$_SESSION['br_two_color'] = 'border-slate-200';
	$_SESSION['br_three_color'] = 'border-slate-700';

	$_SESSION['txt_one_color'] = 'text-slate-800';
	$_SESSION['txt_two_color'] = 'text-slate-400';
	$_SESSION['txt_three_color'] = 'text-slate-100';*/

	$bg_one_color = $_SESSION['bg_one_color'];
	$bg_two_color = $_SESSION['bg_two_color'];
	$bg_three_color = $_SESSION['bg_three_color'];
	$bg_four_color = $_SESSION['bg_four_color'];
	$bg_five_color = $_SESSION['bg_five_color'];
	$bg_six_color = $_SESSION['bg_six_color'];
	$bg_seven_color = $_SESSION['bg_seven_color'];
	$bg_eight_color = $_SESSION['bg_eight_color'];
	
	$br_two_color = $_SESSION['br_two_color'];
	$br_three_color = $_SESSION['br_three_color'];

	$txt_one_color = $_SESSION['txt_one_color'];
	$txt_two_color = $_SESSION['txt_two_color'];
	$txt_three_color = $_SESSION['txt_three_color'];
	
	/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
	 ?>
	
	<?php/* require('../data/connectdb.php');*/ ?>

	<?php require('../data/backdb.php'); ?>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="shortcut icon" href="../file/logo-coldbloud.png" type="image/x-icon">


<!-- TAILWIND CSS -->
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>

	<!-- <link rel="stylesheet" href="./dist/tailwind.css"> -->
<!-- ------------ -->


	<link rel="stylesheet" type="text/css" href="./css/style.css">

</head>

<style type="text/css">
	.btnInactive{
		background: #7a93b2;
		color: white;
		pointer-events: none;
	}
	input{
		padding: 0px 10px 0px 10px;
	}
	select{
		border-radius: 0px;
	}
</style>