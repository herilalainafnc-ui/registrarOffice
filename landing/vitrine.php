<?php require_once __DIR__ . '/../data/vitrine.config.php'; ?>
<?php
/**
 * Vitrine en page unique.
 *
 * Rassemble les cinq rubriques — accueil, formations, admissions, campus,
 * contact — dans un seul document, parcouru au defilement. Le contenu vient
 * des memes fragments que les pages autonomes (landing/pages/_contenu-*.php) :
 * une seule source, donc pas de version qui derive.
 *
 * Les pages autonomes restent servies : elles donnent des adresses
 * partageables, restent indexables, et surtout ce sont elles qui recoivent
 * les envois des formulaires.
 *
 * Le traitement des formulaires est celui des pages d'origine ; il est
 * inclus ici pour que la modale de confirmation puisse s'afficher sur la
 * page unique apres une pre-inscription.
 */

// Cette page porte des formulaires : elle ne doit jamais etre servie depuis
// un cache, sous peine de reafficher un formulaire perime.
if (!headers_sent()) {
    header('Cache-Control: no-cache, must-revalidate');
}

// Signal d'affichage de la confirmation, pose par la redirection qui suit un
// envoi reussi (voir landing/pages/admissions.php).
$envoye = !empty($_GET['envoye']);

// Les fragments de contenu affichent le resultat d'un envoi lorsqu'ils sont
// rendus par leur page autonome, qui traite le POST. Ici c'est la page
// autonome qui s'en charge puis redirige : ces variables n'ont pas de
// valeur a poser, mais elles doivent exister.
$success = false;
$error   = '';

/* Contexte : cette page reunit les cinq rubriques, les pages autonomes n'en
   portent qu'une. Certains fragments doivent donc s'adapter — la rubrique
   Campus se termine par un bloc « Comment nous trouver » (adresse, acces,
   horaires, carte) qui fait doublon ici, la rubrique Contact juste en dessous
   disant la meme chose. Sur /campus, en revanche, c'est le SEUL endroit qui
   porte ces informations : on ne peut pas le retirer partout. */
$vitrinePageUnique = true;
?><!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/pages/init-landing.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Université Adventiste Zurcher</title>
    <meta name="description" content="Université Adventiste Zurcher — formations, admissions, campus et contact. Antsirabe, Madagascar.">
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/pages.css?v=<?=@filemtime(__DIR__.'/pages/pages.css')?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/effets-gsap.css?v=<?=@filemtime(__DIR__.'/pages/effets-gsap.css')?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/vitrine.css?v=<?=@filemtime(__DIR__.'/pages/vitrine.css')?>">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/vitrine-clair.css?v=<?=@filemtime(__DIR__.'/pages/vitrine-clair.css')?>">
</head>
<body class="vitrine-unique">

    <!-- Charge des l'ouverture du corps, pas en fin de page : au retour
         depuis la connexion, le rideau doit etre trace avant le premier
         affichage, sinon la page paraitrait avant d'etre recouverte. -->
    <script src="<?=$app_base?>/landing/pages/stinger.js?v=<?=@filemtime(__DIR__.'/pages/stinger.js')?>"></script>

    <!-- Ecran de chargement : il s'efface des que les images sont pretes, et
         de toute facon au bout de quelques secondes (voir vitrine.js). Une
         connexion lente ne doit jamais laisser un visiteur devant du noir. -->
    <div class="chargement" id="chargement" aria-hidden="true">
        <div class="chargement__centre">
            <img src="<?=$app_base?>/file/logo-coldbloud.png" alt="" class="chargement__logo">
            <p class="chargement__texte" id="chargement-texte">0 %</p>
        </div>
    </div>


    <?php
        // La navigation partagee met en avant la rubrique courante ; sur la
        // page unique, c'est le defilement qui la determine.
        $_nav_route = '/';
        include __DIR__ . '/pages/navigation.php';
    ?>

    <!-- Progression de lecture -->
    <div class="vitrine-progression" aria-hidden="true"><span id="vitrine-barre"></span></div>

    <main class="vitrine">

        <!-- ================= ACCUEIL ================= -->
        <section id="accueil" class="vitrine-section vitrine-accueil" data-rubrique="accueil">
            <!-- Quatre vues du campus en arriere-plan. Aucune source n est posee
                 ici : c est le script qui choisit la taille, ou renonce aux
                 videos, selon la largeur de l ecran et l etat de la connexion.
                 Sans JavaScript, ou sur une connexion menagee, l affiche seule
                 s affiche — 80 Ko au lieu de 16,1 Mo.

                 L affiche reprend la premiere image du premier plan : montrer
                 autre chose ferait sauter l image au demarrage de la lecture.

                 Muettes, les videos ne demandent aucune interaction ;
                 playsinline evite le passage en plein ecran sur iOS. -->
            <div class="vitrine-fond" aria-hidden="true">
                <!-- Les plans se succedent dans l'ordre ou ils paraissent ici.
                     Le premier est choisi pour ce qu'il montre — il situe le lieu,
                     et l'affiche en reprend la premiere image — ; c'est le seul qui
                     retarde l'affichage. Les autres se chargent pendant la lecture
                     du precedent, et le plus lourd vient en dernier pour n'etre
                     demande qu'apres une minute de visite, si tant est qu'on reste. -->
                <div class="vitrine-fond__plans">
                    <!-- la descente du ciel vers le plateau — 1,1 Mo ; elle ouvre parce qu'elle situe le lieu, et que l'affiche en reprend la premiere image -->
                    <video class="vitrine-fond__video est-visible" muted playsinline preload="none"
                           poster="<?=$app_base?>/file/video/campus-hero.jpg"
                           data-large="<?=$app_base?>/file/video/campus-ciel.mp4"
                           data-medium="<?=$app_base?>/file/video/campus-ciel-1200.mp4"
                           data-small="<?=$app_base?>/file/video/campus-ciel-854.mp4"></video>
                    <!-- des etudiants dans la salle — 0,35 Mo -->
                    <video class="vitrine-fond__video" muted playsinline preload="none"
                           data-large="<?=$app_base?>/file/video/campus-etudiants.mp4"
                           data-medium="<?=$app_base?>/file/video/campus-etudiants-1200.mp4"
                           data-small="<?=$app_base?>/file/video/campus-etudiants-854.mp4"></video>
                    <!-- le panneau d'entree — 1,9 Mo -->
                    <video class="vitrine-fond__video" muted playsinline preload="none"
                           data-large="<?=$app_base?>/file/video/campus-panneau.mp4"
                           data-medium="<?=$app_base?>/file/video/campus-panneau-1200.mp4"
                           data-small="<?=$app_base?>/file/video/campus-panneau-854.mp4"></video>
                    <!-- la salle de lecture — 1,9 Mo -->
                    <video class="vitrine-fond__video" muted playsinline preload="none"
                           data-large="<?=$app_base?>/file/video/campus-biblio.mp4"
                           data-medium="<?=$app_base?>/file/video/campus-biblio-1200.mp4"
                           data-small="<?=$app_base?>/file/video/campus-biblio-854.mp4"></video>
                    <!-- un match sur le terrain — 2,2 Mo -->
                    <video class="vitrine-fond__video" muted playsinline preload="none"
                           data-large="<?=$app_base?>/file/video/campus-basket.mp4"
                           data-medium="<?=$app_base?>/file/video/campus-basket-1200.mp4"
                           data-small="<?=$app_base?>/file/video/campus-basket-854.mp4"></video>
                    <!-- le survol de la route — 8,7 Mo, la plus lourde, donc la derniere -->
                    <video class="vitrine-fond__video" muted playsinline preload="none"
                           data-large="<?=$app_base?>/file/video/campus-drone.mp4"
                           data-medium="<?=$app_base?>/file/video/campus-drone-1200.mp4"
                           data-small="<?=$app_base?>/file/video/campus-drone-854.mp4"></video>
                </div>
                <span class="vitrine-fond__voile"></span>
            </div>

            <!-- Le bouton reste masque tant qu'aucune video n'a ete chargee :
                 sur une connexion menagee, il n'y a rien a mettre en pause. Il
                 est hors du bloc aria-hidden, sans quoi il serait invisible
                 pour un lecteur d'ecran tout en restant atteignable au clavier. -->
            <button type="button" class="vitrine-pause" id="vitrine-pause" hidden
                    aria-label="Mettre la vidéo d'arrière-plan en pause">
                <svg class="vitrine-pause__pause" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <rect x="6" y="5" width="4" height="14" rx="1"></rect>
                    <rect x="14" y="5" width="4" height="14" rx="1"></rect>
                </svg>
                <svg class="vitrine-pause__lecture" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M8 5.5v13a1 1 0 0 0 1.53.85l10-6.5a1 1 0 0 0 0-1.7l-10-6.5A1 1 0 0 0 8 5.5z"></path>
                </svg>
            </button>
            <div class="vitrine-accueil__contenu">
                <p class="vitrine-eyebrow">Antsirabe · Madagascar</p>
                <!-- Le titre alterne entre cinq formules. Seul le nom de
                     l’universite compte pour un lecteur d’ecran ou un moteur de
                     recherche : il est donc ecrit une fois, hors du dispositif
                     anime, lequel est marque aria-hidden. Sans quoi le titre de
                     la page changerait toutes les quelques secondes. -->
                <!-- Le titre se frappe lettre a lettre, puis s’efface et laisse
                     la formule suivante. Seul le nom de l’universite compte pour
                     un lecteur d’ecran ou un moteur de recherche : il est ecrit
                     une fois, hors du dispositif anime, lequel est marque
                     aria-hidden. Sans quoi le titre de la page changerait toutes
                     les quelques secondes. -->
                <h1 class="vitrine-titre" id="vitrine-titre">
                    <span class="vitrine-titre__ecran" aria-hidden="true">
                        <span class="vitrine-titre__texte" id="titre-texte">Université Adventiste Zurcher</span><span class="vitrine-titre__curseur" id="titre-curseur"></span>
                    </span>
                    <span class="vitrine-hors-ecran">Université Adventiste Zurcher</span>
                </h1>
                <?php /* Date et annee lues dans les reglages de la vitrine : le
                         texte reste juste d'une annee sur l'autre. Elles ne sont
                         citees qu'une fois, dans le rendez-vous. */ ?>
                <p class="vitrine-chapo">
                    Sept mentions, un campus niché sur les hauts plateaux et une communauté
                    réunie autour de valeurs partagées. Une nouvelle année universitaire
                    s’annonce, portée par l’engagement, le partage et l’ambition de
                    construire ensemble un avenir riche en apprentissages et en opportunités.
                </p>
                <p class="vitrine-rdv">
                    Rendez-vous le <strong><?= vitrineDate('date.rentree', '13 octobre 2026') ?></strong>
                    pour la rentrée <?= vitrineTxt('annee.academique', '2026-2027') ?>&nbsp;!
                </p>
                <div class="vitrine-actions">
                    <a href="#pre-inscription" class="vitrine-btn vitrine-btn--plein">Se pré-inscrire</a>
                    <a href="#formations" class="vitrine-btn">Voir les formations</a>
                </div>
            </div>
            <?php /* L'invite etait un simple texte decoratif (aria-hidden,
                     pointer-events: none). C'est desormais un lien : la
                     fleche annonce un geste possible, autant qu'elle le
                     soit reellement. Elle mene a la premiere rubrique, ce
                     que « defilez pour decouvrir » promet.

                     Le libelle reste aria-hidden — il decrit le geste, pas la
                     destination — et le lien porte son propre intitule, qui
                     dit ou il conduit. */ ?>
            <?php /* data-sans-rideau : pas de stinger sur ce lien, juste un
                     defilement. La rubrique visee est immediatement en
                     dessous ; couvrir l'ecran pour descendre d'un ecran nierait
                     le geste que la fleche annonce (voir vitrine.js). */ ?>
            <a href="#formations" class="vitrine-invite" data-sans-rideau
               aria-label="Aller à la première rubrique">
                <span class="vitrine-invite__texte" aria-hidden="true">Défilez pour découvrir</span>
                <span class="vitrine-invite__fleche" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
        </section>

        <!-- ================= FORMATIONS ================= -->
        <section id="formations" class="vitrine-section" data-rubrique="formations">
            <!-- Silhouette de fond. Decorative : elle ne dit rien que le
                 texte ne dise, et sort donc de l'arbre d'accessibilite. -->
            <span class="figure-cote figure-cote--droite" aria-hidden="true"
                  data-vitesse="42"
                  style="--teinte:#907848; --taille:clamp(20rem, 34vw, 42rem); --haut:15%">
                <span class="figure-cote__sujet">
                    <span class="figure-cote__disque"></span>
                    <img src="<?=$app_base?>/file/vitrine/portraits/diplome-or.webp"
                         alt="" loading="lazy" decoding="async">
                </span>
            </span>
            </span>
            <?php include __DIR__ . '/pages/_contenu-formations.php'; ?>

            <!-- Le cursus prolonge naturellement les formations : le visiteur
                 vient d'apprendre quelles mentions existent, il veut savoir a
                 quoi ressemblent les trois annees. -->
            <?php include __DIR__ . '/pages/_contenu-cursus.php'; ?>
        </section>


        <!-- Galerie horizontale : le sens alterne d une galerie a l autre. -->
        <section class="galerie-horizontale" aria-label="Les mentions en bref">
            <!-- Silhouette de fond. Decorative : elle ne dit rien que le
                 texte ne dise, et sort donc de l'arbre d'accessibilite. -->
            <span class="figure-cote figure-cote--gauche" aria-hidden="true"
                  data-vitesse="-34"
                  style="--teinte:#b8735e; --taille:clamp(15rem, 24vw, 30rem); --haut:20%">
                <span class="figure-cote__sujet">
                    <span class="figure-cote__disque"></span>
                    <img src="<?=$app_base?>/file/vitrine/portraits/diplomee-peche.webp"
                         alt="" loading="lazy" decoding="async">
                </span>
            </span>
            </span>
            <h2 class="galerie-horizontale__titre">Sept mentions</h2>
            <div class="galerie-horizontale__piste">
                <article class="galerie-carte">
                    <span class="galerie-carte__num">01</span>
                    <h3>Théologie</h3>
                    <p>Former des pasteurs et des enseignants ancrés dans une lecture rigoureuse des textes.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">02</span>
                    <h3>Gestion</h3>
                    <p>Comptabilité, finance et management, du premier cycle au master.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">03</span>
                    <h3>Informatique</h3>
                    <p>Génie logiciel, systèmes d'information et administration réseau.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">04</span>
                    <h3>Sciences infirmières</h3>
                    <p>Infirmerie générale et maïeutique, une formation clinique adossée aux structures de santé de la région.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">05</span>
                    <h3>Communication</h3>
                    <p>Communication d'entreprise, médias et journalisme.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">06</span>
                    <h3>Études anglophones</h3>
                    <p>Traduction, interprétation, langue et littérature.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">07</span>
                    <h3>Droit</h3>
                    <p>Droit privé et droit public, avec une attention portée à l'éthique.</p>
                </article>
            </div>
        </section>

        <!-- ================= ADMISSIONS ================= -->
        <section id="admissions" class="vitrine-section" data-rubrique="admissions">
            <!-- Silhouette de fond. Decorative : elle ne dit rien que le
                 texte ne dise, et sort donc de l'arbre d'accessibilite. -->
            <span class="figure-cote figure-cote--gauche" aria-hidden="true"
                  data-vitesse="38"
                  style="--teinte:#2a4a86; --taille:clamp(20rem, 34vw, 42rem); --haut:14%">
                <span class="figure-cote__sujet">
                    <span class="figure-cote__disque"></span>
                    <img src="<?=$app_base?>/file/vitrine/portraits/diplomee-argent.webp"
                         alt="" loading="lazy" decoding="async">
                </span>
            </span>
            </span>
            <?php include __DIR__ . '/pages/_contenu-admissions.php'; ?>
        </section>


        <!-- Deuxieme galerie : elle part de la droite. -->
        <section class="galerie-horizontale" data-sens="gauche" aria-label="Le parcours en un coup d oeil">
            <!-- Silhouette de fond. Decorative : elle ne dit rien que le
                 texte ne dise, et sort donc de l'arbre d'accessibilite. -->
            <span class="figure-cote figure-cote--droite" aria-hidden="true"
                  data-vitesse="-30"
                  style="--teinte:#183060; --taille:clamp(15rem, 24vw, 30rem); --haut:18%">
                <span class="figure-cote__sujet">
                    <span class="figure-cote__disque"></span>
                    <img src="<?=$app_base?>/file/vitrine/portraits/diplomee-rose.webp"
                         alt="" loading="lazy" decoding="async">
                </span>
            </span>
            </span>
            <h2 class="galerie-horizontale__titre">Votre parcours, étape par étape</h2>
            <div class="galerie-horizontale__piste">
                <article class="galerie-carte">
                    <span class="galerie-carte__num">01</span>
                    <h3>Pré-inscription</h3>
                    <p>Facultative. Déposez vos informations à l'avance : elles seront reprises le jour de l'inscription.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">02</span>
                    <h3>Test d'admission</h3>
                    <p>Le <?= vitrineDate('date.test', '13 octobre 2026') ?>, sur le campus. Il conditionne l'accès à l'inscription.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">03</span>
                    <h3>Résultats</h3>
                    <p>Communiqués par le bureau du registrariat.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">04</span>
                    <h3>Dossier</h3>
                    <p>Relevés de notes, diplômes, certificats et pièce d'identité.</p>
                </article>
                <article class="galerie-carte">
                    <span class="galerie-carte__num">05</span>
                    <h3>Inscription</h3>
                    <p>Paiement des frais, puis rentrée le <?= vitrineDate('date.rentree', '13 octobre 2026') ?>.</p>
                </article>
            </div>
        </section>

        <!-- ================= CAMPUS ================= -->
        <section id="campus" class="vitrine-section" data-rubrique="campus">
            <!-- Silhouette de fond. Decorative : elle ne dit rien que le
                 texte ne dise, et sort donc de l'arbre d'accessibilite. -->
            <span class="figure-cote figure-cote--droite" aria-hidden="true"
                  data-vitesse="46"
                  style="--teinte:#1f7a5a; --taille:clamp(17rem, 28vw, 34rem); --haut:8%">
                <span class="figure-cote__sujet">
                    <span class="figure-cote__disque"></span>
                    <img src="<?=$app_base?>/file/vitrine/portraits/diplomee-emeraude.webp"
                         alt="" loading="lazy" decoding="async">
                </span>
            </span>
            </span>
            <?php include __DIR__ . '/pages/_contenu-campus.php'; ?>
        </section>

        <!-- ================= CONTACT ================= -->
        <section id="contact" class="vitrine-section" data-rubrique="contact">
            <!-- Silhouette de fond. Decorative : elle ne dit rien que le
                 texte ne dise, et sort donc de l'arbre d'accessibilite. -->
            <span class="figure-cote figure-cote--gauche" aria-hidden="true"
                  data-vitesse="-40"
                  style="--teinte:#c9a961; --taille:clamp(17rem, 28vw, 34rem); --haut:20%">
                <span class="figure-cote__sujet">
                    <span class="figure-cote__disque"></span>
                    <img src="<?=$app_base?>/file/vitrine/portraits/diplome-rouge.webp"
                         alt="" loading="lazy" decoding="async">
                </span>
            </span>
            </span>
            <?php include __DIR__ . '/pages/_contenu-contact.php'; ?>
        </section>

    </main>

    <?php include __DIR__ . '/pages/footer.php'; ?>

    <?php /* Toujours incluse, masquee par defaut : l'envoi sans rechargement
             l'ouvre a la demande. Conditionner l'inclusion a « envoye »
             empechait de l'afficher apres une soumission en arriere-plan. */ ?>
    <?php include __DIR__ . '/pages/_modale-preinscription.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="<?=$app_base?>/landing/pages/effets-gsap.js?v=<?=@filemtime(__DIR__.'/pages/effets-gsap.js')?>"></script>
    <script src="<?=$app_base?>/landing/pages/vitrine.js?v=<?=@filemtime(__DIR__.'/pages/vitrine.js')?>"></script>
</body>
</html>
