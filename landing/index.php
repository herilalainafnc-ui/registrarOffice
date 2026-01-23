<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Université Adventiste Zurcher - Registrar</title>
    <link rel="shortcut icon" href="../file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link rel="stylesheet" href="./landing.css">
</head>
<body>
    <div class="indicator"></div>

    <nav>
        <div class="nav-left">
            <div class="logo-container">
                <img src="../file/UAZ Official.png" alt="UAZ Logo" class="nav-logo">
            </div>
            <div class="brand-name">Université Adventiste Zurcher</div>
        </div>
        <div class="nav-right">
            <a href="./" class="nav-link active">Accueil</a>
            <a href="./pages/formations" class="nav-link">Formations</a>
            <a href="./pages/admissions" class="nav-link">Admissions</a>
            <a href="./pages/campus" class="nav-link">Campus</a>
            <a href="./pages/contact" class="nav-link">Contact</a>
            <div class="svg-container search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <a href="../src/index.php" class="login-btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                </svg>
                <span>Connexion</span>
            </a>
        </div>
    </nav>

    <div id="demo"></div>

    <div class="details" id="details-even">
        <div class="place-box">
            <div class="text">Mention Théologie</div>
        </div>
        <div class="title-box-1"><div class="title-1">THÉOLOGIE</div></div>
        <div class="title-box-2" style="display: none;"><div class="title-2"></div></div>
        <div class="desc">
            Approfondissez votre compréhension des textes sacrés et de la théologie adventiste. Une formation spirituelle et académique pour ceux qui souhaitent servir l'Église et enseigner la Parole.
        </div>
        <div class="cta">
            <button class="bookmark">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M6.32 2.577a49.255 49.255 0 0111.36 0c1.497.174 2.57 1.46 2.57 2.93V21a.75.75 0 01-1.085.67L12 18.089l-7.165 3.583A.75.75 0 013.75 21V5.507c0-1.47 1.073-2.756 2.57-2.93z" clip-rule="evenodd"/>
                </svg>
            </button>
            <a href="../src/index.php" class="discover">Accéder à Infinit Registrar</a>
        </div>
    </div>

    <div class="details" id="details-odd">
        <div class="place-box">
            <div class="text">Mention Théologie</div>
        </div>
        <div class="title-box-1"><div class="title-1">THÉOLOGIE</div></div>
        <div class="title-box-2" style="display: none;"><div class="title-2"></div></div>
        <div class="desc">
            Approfondissez votre compréhension des textes sacrés et de la théologie adventiste. Une formation spirituelle et académique pour ceux qui souhaitent servir l'Église et enseigner la Parole.
        </div>
        <div class="cta">
            <button class="bookmark">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M6.32 2.577a49.255 49.255 0 0111.36 0c1.497.174 2.57 1.46 2.57 2.93V21a.75.75 0 01-1.085.67L12 18.089l-7.165 3.583A.75.75 0 013.75 21V5.507c0-1.47 1.073-2.756 2.57-2.93z" clip-rule="evenodd"/>
                </svg>
            </button>
            <a href="../src/index.php" class="discover">Accéder à Infinit Registrar</a>
        </div>
    </div>

    <div class="pagination" id="pagination">
        <div class="arrow arrow-left">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
            </svg>
        </div>
        <div class="arrow pause-btn" id="pause-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="pause-icon">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"/>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="play-icon hidden">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/>
            </svg>
        </div>
        <div class="arrow arrow-right">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
        </div>
        <div class="progress-sub-container">
            <div class="progress-sub-background">
                <div class="progress-sub-foreground"></div>
            </div>
        </div>
        <div class="slide-numbers" id="slide-numbers"></div>
    </div>

    <div class="cover"></div>

    <!-- Stats Section -->
    <div class="stats-container" id="stats">
        <div class="stat-item">
            <div class="stat-number" data-count="420">0</div>
            <div class="stat-label">Étudiants</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" data-count="40">0</div>
            <div class="stat-label">Enseignants</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" data-count="21">0</div>
            <div class="stat-label">Programmes</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" data-count="8">0</div>
            <div class="stat-label">Mentions</div>
        </div>
    </div>

    <script src="./landing.js"></script>
</body>
</html>
