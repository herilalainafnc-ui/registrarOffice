<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="navbar">
        <div class="nav-left">
            <div class="logo-container">
                <img src="../../file/UAZ Official.png" alt="UAZ Logo" class="nav-logo">
            </div>
            <div class="brand-name">Université Adventiste Zurcher</div>
        </div>
        <div class="nav-center">
            <a href="../" class="nav-link <?php echo ($currentPage == 'index') ? 'active' : ''; ?>">Accueil</a>
            <a href="./formations" class="nav-link <?php echo ($currentPage == 'formations') ? 'active' : ''; ?>">Formations</a>
            <a href="./admissions" class="nav-link <?php echo ($currentPage == 'admissions') ? 'active' : ''; ?>">Admissions</a>
            <a href="./campus" class="nav-link <?php echo ($currentPage == 'campus') ? 'active' : ''; ?>">Campus</a>
            <a href="./contact" class="nav-link <?php echo ($currentPage == 'contact') ? 'active' : ''; ?>">Contact</a>
        </div>
        <div class="nav-right">
            <a href="../../src/index.php" class="login-btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                </svg>
                <span>Connexion</span>
            </a>
        </div>
    </nav>