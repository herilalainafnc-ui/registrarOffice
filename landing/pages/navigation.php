<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
// Calculer le chemin de base si non défini
if (!isset($app_base)) {
    $_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
    $app_base = substr($_app_root, strlen($_doc_root));
    if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
}
// MVC: détecter la route actuelle
$_nav_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_nav_route = $_nav_uri;
if ($app_base && strpos($_nav_route, $app_base) === 0) {
    $_nav_route = substr($_nav_route, strlen($app_base));
}
$_nav_route = '/' . ltrim($_nav_route ?: '', '/');

/* Rubriques de la barre. Declarees une fois et parcourues deux fois : la
   barre horizontale (grand ecran) et le panneau deroulant (petit ecran).
   Les dupliquer aurait garanti qu'elles finissent par diverger. */
$_nav_rubriques = [
    '/'           => 'Accueil',
    '/formations' => 'Formations',
    '/admissions' => 'Admissions',
    '/campus'     => 'Campus',
    '/contact'    => 'Contact',
];
?>
<nav class="navbar">
        <div class="nav-left">
            <div class="logo-container">
                <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ Logo" class="nav-logo">
            </div>
            <div class="brand-name">Université Adventiste Zurcher</div>
        </div>
        <div class="nav-center">
<?php foreach ($_nav_rubriques as $_r => $_libelle): ?>
            <a href="<?=$app_base . ($_r === '/' ? '/' : $_r)?>" class="nav-link <?php echo ($_nav_route === $_r) ? 'active' : ''; ?>"><?=$_libelle?></a>
<?php endforeach; ?>
        </div>
        <div class="nav-right">
            <!-- data-stinger : le passage vers l'application se fait sous le
                 rideau, plutot que par un ecran blanc (voir stinger.js). -->
            <a href="<?=$app_base?>/login" class="login-btn" data-stinger>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                </svg>
                <span>Connexion</span>
            </a>

            <!-- Le bouton n'existe qu'en dessous de 1024px, la ou .nav-center
                 est masquee. Il reste a cote de Connexion plutot que de
                 l'absorber : se connecter est une action frequente pour le
                 personnel, pas une rubrique du site. Style volontairement
                 different (fantome) pour qu'on ne confonde pas les deux. -->
            <button type="button" class="nav-burger" id="nav-burger"
                    aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="nav-panneau">
                <span class="nav-burger__trait"></span>
                <span class="nav-burger__trait"></span>
                <span class="nav-burger__trait"></span>
            </button>
        </div>

        <!-- Panneau des petits ecrans, place DANS la barre : « top: 100% » suit
             alors sa hauteur reelle, qui change avec la largeur d'ecran.
             Ses liens sont ceux de la barre ; sur la page unique, vitrine.js
             les convertit en ancres comme les autres (il parcourt tous les
             a[href]). -->
        <div class="nav-panneau" id="nav-panneau" hidden>
<?php foreach ($_nav_rubriques as $_r => $_libelle): ?>
            <a href="<?=$app_base . ($_r === '/' ? '/' : $_r)?>" class="nav-panneau__lien <?php echo ($_nav_route === $_r) ? 'active' : ''; ?>"><?=$_libelle?></a>
<?php endforeach; ?>
        </div>
    </nav>

    <div class="nav-voile" id="nav-voile" hidden></div>

    <script>
    /* Menu des petits ecrans.
       Ecrit ici plutot que dans vitrine.js : la barre est partagee par la page
       unique ET les cinq pages autonomes, qui ne chargent pas ce script. */
    (function () {
        var bouton  = document.getElementById('nav-burger');
        var panneau = document.getElementById('nav-panneau');
        var voile   = document.getElementById('nav-voile');
        if (!bouton || !panneau) { return; }

        function ouvrir() {
            panneau.hidden = false;
            if (voile) { voile.hidden = false; }
            // Le retrait de [hidden] et l'ajout de la classe doivent tomber sur
            // deux images distinctes, sinon la transition ne demarre pas.
            requestAnimationFrame(function () {
                panneau.classList.add('est-ouvert');
                if (voile) { voile.classList.add('est-ouvert'); }
            });
            bouton.classList.add('est-actif');
            bouton.setAttribute('aria-expanded', 'true');
            bouton.setAttribute('aria-label', 'Fermer le menu');
        }

        function fermer() {
            panneau.classList.remove('est-ouvert');
            if (voile) { voile.classList.remove('est-ouvert'); }
            bouton.classList.remove('est-actif');
            bouton.setAttribute('aria-expanded', 'false');
            bouton.setAttribute('aria-label', 'Ouvrir le menu');

            // On attend la fin de la transition pour rendre le panneau
            // reellement inerte — [hidden] pose trop tot couperait l'animation
            // et laisserait le contenu focusable au clavier entre-temps.
            /* Deux chemins vers la fermeture, et un verrou pour n'agir qu'une
               fois. « transitionend » ne se declenche pas toujours : si le
               panneau est deja a son etat final, ou si la transition est
               interrompue, l'evenement n'arrive jamais et [hidden] n'est
               jamais repose. Le panneau reste alors dans le document —
               invisible, mais toujours cliquable. C'est ce qui detournait des
               clics vers les rubriques du menu. */
            var acheve = false;
            var fini = function () {
                if (acheve) { return; }
                acheve = true;
                panneau.hidden = true;
                if (voile) { voile.hidden = true; }
                panneau.removeEventListener('transitionend', fini);
            };
            var duree = parseFloat(getComputedStyle(panneau).transitionDuration) || 0;
            if (duree > 0) {
                panneau.addEventListener('transitionend', fini);
                setTimeout(fini, duree * 1000 + 120);   // filet
            } else {
                fini();
            }
        }

        function basculer() {
            if (bouton.getAttribute('aria-expanded') === 'true') { fermer(); } else { ouvrir(); }
        }

        bouton.addEventListener('click', basculer);
        if (voile) { voile.addEventListener('click', fermer); }

        // Sur la page unique, le clic ne change pas d'adresse : le panneau
        // resterait ouvert par-dessus la rubrique qu'on vient de demander.
        Array.prototype.forEach.call(
            panneau.querySelectorAll('a'),
            function (a) { a.addEventListener('click', fermer); }
        );

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && bouton.getAttribute('aria-expanded') === 'true') {
                fermer();
                bouton.focus();
            }
        });

        // Au retour vers le grand ecran, la barre horizontale reparait : un
        // panneau reste ouvert deviendrait un bloc flottant sans rapport.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024 && bouton.getAttribute('aria-expanded') === 'true') {
                fermer();
            }
        });
    })();
    </script>