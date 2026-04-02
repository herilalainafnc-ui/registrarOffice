<head>
	<?php/* require('../data/connectdb.php');*/ ?>

	<?php require('../data/backdb.php');
	// Calculer le chemin racine de l'application (fiable: basé sur DOCUMENT_ROOT)
	$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
	$app_base = substr($_app_root, strlen($_doc_root));
	if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
	if (!function_exists('encodeFilePath')) {
		function encodeFilePath($path) {
			if (empty($path)) return '';
			return implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
		}
	}
	?>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">


<!-- TAILWIND CSS -->
	<script>
	(() => {
		const originalWarn = console.warn;
		console.warn = function (...args) {
			const msg = String(args[0] || "");
			if (
				msg.includes("cdn.tailwindcss.com should not be used in production") ||
				msg.includes("@tailwindcss/line-clamp")
			) {
				return;
			}
			return originalWarn.apply(console, args);
		};
	})();
	</script>
	<script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>

	<!-- <link rel="stylesheet" href="./dist/tailwind.css"> -->
<!-- ------------ -->


	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/style.css">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/responsive.css">

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