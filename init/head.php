<head>
	<script>
		// Apply saved theme immediately to prevent flash
		(function() {
			const savedTheme = localStorage.getItem('theme') || 'dark';
			document.documentElement.setAttribute('data-theme', savedTheme);
		})();
	</script>
	<?php 
	// Charger le middleware de sécurité (gère automatiquement la session)
	require_once('../data/backdb.php');
	require_once('../data/middleware.php');
	
	// Initialiser le middleware avec la connexion DB
	initMiddleware($dtb);
	
	// Vérifier l'authentification (redirige vers login si non connecté)
	requireAuth('../src/index.php');

	/*:::::::::::::::::::::::: SESSION COLORS - BLEU NUIT ::::::::::::::::::::::::*/

	$_SESSION['bg_one_color'] = 'bg-[#0a1628]';      /* Bleu nuit très foncé */
	$_SESSION['bg_two_color'] = 'bg-[#0d1f3c]';      /* Bleu nuit foncé */
	$_SESSION['bg_three_color'] = 'bg-[#0f2847]';    /* Bleu nuit */
	$_SESSION['bg_four_color'] = 'bg-[#1a3a5c]';     /* Bleu nuit moyen */
	$_SESSION['bg_five_color'] = 'bg-[#264d73]';     /* Bleu nuit clair */
	$_SESSION['bg_six_color'] = 'bg-[#3d6a8a]';      /* Bleu acier */
	$_SESSION['bg_seven_color'] = 'bg-[#5a8aa8]';    /* Bleu gris */
	$_SESSION['bg_eight_color'] = 'bg-[#8eb8d4]';    /* Bleu pâle */


	$_SESSION['br_two_color'] = 'border-[#1a3a5c]';
	$_SESSION['br_three_color'] = 'border-[#3d6a8a]';

	$_SESSION['simpleTbl'] = 'simpleTbl';
	
	$_SESSION['txt_one_color'] = 'text-[#e8f1f8]';   /* Texte clair */
	$_SESSION['txt_two_color'] = 'text-[#8eb8d4]';   /* Texte bleu pâle */
	$_SESSION['txt_three_color'] = 'text-[#0a1628]'; /* Texte foncé */

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

	$_SESSION['simpleTbl'] = 'simpleTblLight';

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

	<!-- backdb.php déjà inclus via middleware -->

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="shortcut icon" href="../file/logo-coldbloud.png" type="image/x-icon">


<!-- TAILWIND CSS -->
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>

	<!-- <link rel="stylesheet" href="./dist/tailwind.css"> -->
<!-- ------------ -->


	<link rel="stylesheet" type="text/css" href="./css/style.css">
	<link rel="stylesheet" type="text/css" href="./css/button.css">

	<!-- ===== GLOBAL THEME STYLES ===== -->
	<style>
		/* Prevent body scroll - app should be contained */
		html, body {
			margin: 0;
			padding: 0;
			overflow: hidden;
			height: 100vh;
			max-height: 100vh;
		}
		
		/* Transition for smooth theme change */
		*, *::before, *::after {
			transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
		}
		
		/* ===== LIGHT MODE - Body & Main Containers ===== */
		[data-theme="light"] body {
			background-color: #e8f1f8 !important;
			color: #0a1628 !important;
		}
		
		/* Background colors - Dark to Light conversion */
		[data-theme="light"] .bg-\[\#0a1628\],
		[data-theme="light"] .bg-slate-800 {
			background-color: #e0eef7 !important;
		}
		
		[data-theme="light"] .bg-\[\#0d1f3c\],
		[data-theme="light"] .bg-slate-700 {
			background-color: #d1e5f4 !important;
		}
		
		[data-theme="light"] .bg-\[\#0f2847\],
		[data-theme="light"] .bg-slate-600 {
			background-color: #c2dcf0 !important;
		}
		
		[data-theme="light"] .bg-\[\#1a3a5c\],
		[data-theme="light"] .bg-slate-500 {
			background-color: #b3d3ec !important;
		}
		
		[data-theme="light"] .bg-\[\#264d73\],
		[data-theme="light"] .bg-slate-400 {
			background-color: #a4cae8 !important;
		}
		
		/* Hover background colors */
		[data-theme="light"] .hover\:bg-slate-700:hover {
			background-color: #c2dcf0 !important;
		}
		
		[data-theme="light"] .hover\:bg-slate-600:hover {
			background-color: #b3d3ec !important;
		}
		
		[data-theme="light"] .hover\:\[\#0f2847\]:hover,
		[data-theme="light"] .hover\:bg-\[\#0d1f3c\]:hover {
			background-color: #c2dcf0 !important;
		}
		
		/* ===== LIGHT MODE - Text Colors ===== */
		[data-theme="light"] .text-slate-100,
		[data-theme="light"] .text-slate-200,
		[data-theme="light"] .text-\[\#e8f1f8\] {
			color: #0a1628 !important;
		}
		
		[data-theme="light"] .text-slate-300,
		[data-theme="light"] .text-slate-400,
		[data-theme="light"] .text-slate-500,
		[data-theme="light"] .text-\[\#8eb8d4\] {
			color: #1a3a5c !important;
		}
		
		[data-theme="light"] .text-white {
			color: #0a1628 !important;
		}
		
		/* Keep specific colors in light mode */
		[data-theme="light"] .text-cyan-400,
		[data-theme="light"] .text-cyan-500 {
			color: #0891b2 !important;
		}
		
		/* ===== LIGHT MODE - Borders ===== */
		[data-theme="light"] .border-slate-700,
		[data-theme="light"] .border-slate-800,
		[data-theme="light"] .border-slate-600,
		[data-theme="light"] .border-\[\#1a3a5c\] {
			border-color: #8eb8d4 !important;
		}
		
		[data-theme="light"] .hover\:border-cyan-500:hover {
			border-color: #4e9ede !important;
		}
		
		/* ===== LIGHT MODE - Input Fields ===== */
		[data-theme="light"] .inscInput,
		[data-theme="light"] input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),
		[data-theme="light"] select,
		[data-theme="light"] textarea {
			background-color: #ffffff !important;
			color: #0a1628 !important;
			border-color: #8eb8d4 !important;
		}
		
		[data-theme="light"] .inscInput::placeholder,
		[data-theme="light"] input::placeholder,
		[data-theme="light"] textarea::placeholder {
			color: #5a8aa8 !important;
		}
		
		/* ===== LIGHT MODE - Buttons ===== */
		[data-theme="light"] .bg-cyan-700 {
			background-color: #4e9ede !important;
			color: #ffffff !important;
		}
		
		[data-theme="light"] .bg-cyan-700:hover,
		[data-theme="light"] .hover\:bg-cyan-600:hover {
			background-color: #3d8ed0 !important;
		}
		
		[data-theme="light"] .bg-cyan-500,
		[data-theme="light"] .bg-blue-500,
		[data-theme="light"] .bg-blue-600 {
			background-color: #4e9ede !important;
			color: #ffffff !important;
		}
		
		/* ===== LIGHT MODE - Tables ===== */
		[data-theme="light"] .simpleTbl th,
		[data-theme="light"] .simpleTbl td {
			border-color: #8eb8d4 !important;
		}
		
		[data-theme="light"] table thead,
		[data-theme="light"] table thead th {
			background-color: #4e9ede !important;
			color: #ffffff !important;
		}
		
		[data-theme="light"] table tbody tr:hover {
			background-color: #d1e5f4 !important;
		}
		
		[data-theme="light"] table tbody tr:nth-child(even) {
			background-color: #e8f1f8 !important;
		}
		
		/* ===== LIGHT MODE - Cards & Panels ===== */
		[data-theme="light"] .rounded-md,
		[data-theme="light"] .rounded-lg {
			border-color: #8eb8d4 !important;
		}
		
		/* ===== LIGHT MODE - Scrollbar ===== */
		[data-theme="light"] *::-webkit-scrollbar-thumb {
			background-color: #8eb8d4 !important;
		}
		
		[data-theme="light"] *::-webkit-scrollbar-track {
			background-color: #e0eef7 !important;
		}
		
		/* ===== LIGHT MODE - Alerts & Messages ===== */
		[data-theme="light"] .text-red-500,
		[data-theme="light"] .text-red-400 {
			color: #dc2626 !important;
		}
		
		[data-theme="light"] .text-green-500,
		[data-theme="light"] .text-green-400 {
			color: #16a34a !important;
		}
		
		[data-theme="light"] .text-yellow-400,
		[data-theme="light"] .text-yellow-500 {
			color: #ca8a04 !important;
		}
		
		/* ===== LIGHT MODE - Dropdown Menus ===== */
		[data-theme="light"] .dropdown-menu {
			background-color: #ffffff !important;
			border-color: #8eb8d4 !important;
		}
		
		[data-theme="light"] .dropdown-menu a,
		[data-theme="light"] .dropdown-menu li,
		[data-theme="light"] .dropdown-menu span {
			color: #0a1628 !important;
		}
		
		[data-theme="light"] .dropdown-menu a:hover,
		[data-theme="light"] .dropdown-menu li:hover {
			background-color: #e0eef7 !important;
		}
		
		/* ===== LIGHT MODE - Modal/Dialog ===== */
		[data-theme="light"] .modal-content {
			background-color: #ffffff !important;
			color: #0a1628 !important;
			border-color: #8eb8d4 !important;
		}
		
		/* ===== LIGHT MODE - Gradient fixes ===== */
		[data-theme="light"] .bg-gradient-to-br.from-cyan-500.to-blue-600 {
			background: linear-gradient(to bottom right, #4e9ede, #3d8ed0) !important;
		}
		
		[data-theme="light"] .bg-gradient-to-r.from-cyan-400.to-blue-500 {
			background: linear-gradient(to right, #4e9ede, #3d8ed0) !important;
		}
		
		/* ===== LIGHT MODE - Specific component fixes ===== */
		[data-theme="light"] .back {
			background-color: #e8f1f8 !important;
		}
		
		[data-theme="light"] [class*="hover:bg-slate"]:hover {
			background-color: #c2dcf0 !important;
		}
		
		/* ===== LIGHT MODE - Ring/Focus states ===== */
		[data-theme="light"] .ring-cyan-400\/50,
		[data-theme="light"] .hover\:ring-cyan-400\/50:hover {
			--tw-ring-color: rgba(78, 158, 222, 0.5) !important;
		}
		
		/* ===== LIGHT MODE - Fix icons in buttons ===== */
		[data-theme="light"] .bg-cyan-700 i,
		[data-theme="light"] .bg-cyan-500 i,
		[data-theme="light"] .bg-blue-500 i,
		[data-theme="light"] table thead i {
			color: #ffffff !important;
		}
		
		/* ===== LIGHT MODE - User dropdown specific ===== */
		[data-theme="light"] .dropdown-menu .bg-gradient-to-br,
		[data-theme="light"] .dropdown-menu [class*="bg-gradient"] {
			background: linear-gradient(to bottom right, #d1e5f4, #e0eef7) !important;
		}
		
		[data-theme="light"] .dropdown-menu .text-white {
			color: #0a1628 !important;
		}
		
		[data-theme="light"] .dropdown-menu .text-cyan-400 {
			color: #0891b2 !important;
		}
		
		/* Fix for hover states on cards */
		[data-theme="light"] a:hover .text-white {
			color: #0a1628 !important;
		}
	</style>

</head>
<script>
        $(document).ready(function() {
            function fetchData() {
                $.ajax({
                    url: '../data/data.php',
                    method: 'GET',
                    success: function(response) {
                        $('#content').text(response.message);
                    },
                    error: function() {
                        console.error('Erreur lors de la récupération des données.');
                    }
                });
            }

            // Appel initial pour charger les données
            fetchData();

            // Mettre à jour les données toutes les 5 secondes
            setInterval(fetchData, 1000);
        });
    </script>