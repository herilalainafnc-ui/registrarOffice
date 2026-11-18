<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'init-landing.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus - Université Adventiste Zurcher</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/pages.css?v=<?=@filemtime(__DIR__."/pages.css")?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/effets-gsap.css?v=<?=@filemtime(__DIR__."/effets-gsap.css")?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/vitrine-clair.css?v=<?=@filemtime(__DIR__."/vitrine-clair.css")?>">
</head>
<body class="vitrine-claire">
    <!-- Rideau de transition et curseur (voir landing/pages/effets-gsap.js). -->
    <div class="rideau-page" aria-hidden="true"><span class="rideau-page__bande"></span><span class="rideau-page__bande"></span><span class="rideau-page__bande"></span></div>
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <?php /* Contenu partage avec la vitrine en page unique. */ include '_contenu-campus.php'; ?>

    <?php include 'footer.php'; ?>

    <script src="<?=$app_base?>/landing/pages/pages.js?v=<?=@filemtime(__DIR__."/pages.js")?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="<?=$app_base?>/landing/pages/effets-gsap.js?v=<?=@filemtime(__DIR__."/effets-gsap.js")?>"></script>
</body>
</html>
