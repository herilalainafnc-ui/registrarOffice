<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'init-landing.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formations - Université Adventiste Zurcher</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?=$app_base?>/landing/pages/pages.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                8 Mentions • 21 Programmes
            </div>
            <h1 class="hero-title">Nos Formations</h1>
            <p class="hero-subtitle">
                Découvrez nos programmes académiques d'excellence. L'UAZ vous offre une formation de qualité, 
                ancrée dans les valeurs adventistes et tournée vers l'avenir.
            </p>
        </div>
    </section>

    <!-- Stats Section -->
    <div class="stats-section">
        <div class="stat-box">
            <div class="stat-value">8</div>
            <div class="stat-label">Mentions</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">21</div>
            <div class="stat-label">Programmes</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">3</div>
            <div class="stat-label">Niveaux</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">95%</div>
            <div class="stat-label">Insertion</div>
        </div>
    </div>

    <!-- Formations Grid -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Nos Mentions</span>
            <h2 class="section-title">Programmes Académiques</h2>
            <p class="section-subtitle">
                Choisissez parmi nos 8 mentions pour construire votre avenir professionnel
            </p>
        </div>

        <div class="cards-grid">
            <!-- Théologie -->
            <div class="program-card animate-in">
                <div class="program-image-container">
                    <img src="https://actualites.adventiste.org/wp-content/uploads/sites/3/2019/09/Bible-etudde.jpg" alt="Théologie" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Théologie</h3>
                    <div class="program-duration">3 à 5 ans • Formation pastorale</div>
                    <p class="program-text">
                        Approfondissez votre compréhension des textes sacrés et de la théologie adventiste. 
                        Une formation spirituelle et académique pour servir l'Église.
                    </p>
                </div>
            </div>

            <!-- Gestion -->
            <div class="program-card animate-in delay-1">
                <div class="program-image-container">
                    <img src="https://zurcher.edu.mg/wp-content/uploads/2026/01/b.jpg" alt="Gestion" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Gestion</h3>
                    <div class="program-duration">3 ans • Management & Finance</div>
                    <p class="program-text">
                        Développez vos compétences en leadership et gestion d'entreprise. 
                        Formation des futurs dirigeants capables de relever les défis économiques.
                    </p>
                </div>
            </div>

            <!-- Informatique -->
            <div class="program-card animate-in delay-2">
                <div class="program-image-container">
                    <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=2070&auto=format&fit=crop" alt="Informatique" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Informatique</h3>
                    <div class="program-duration">3 ans • Développement & SI</div>
                    <p class="program-text">
                        Plongez dans le monde du numérique. Développement logiciel, systèmes d'information, 
                        réseaux - préparez-vous aux métiers de demain.
                    </p>
                </div>
            </div>

            <!-- Sciences Infirmières -->
            <div class="program-card animate-in delay-3">
                <div class="program-image-container">
                    <img src="https://latribune.cyber-diego.com/images/stories/2014/aout/formation-paramedicaux-antsiranana.jpg" alt="Sciences Infirmières" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Sciences Infirmières</h3>
                    <div class="program-duration">3 ans • Santé & Soins</div>
                    <p class="program-text">
                        Formez-vous aux métiers de la santé avec notre programme d'excellence. 
                        Un cursus complet alliant théorie et pratique clinique.
                    </p>
                </div>
            </div>

            <!-- Éducation -->
            <div class="program-card animate-in">
                <div class="program-image-container">
                    <img src="https://zurcher.edu.mg/wp-content/uploads/2026/01/Design-sans-titre-6.jpg" alt="Éducation" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Éducation</h3>
                    <div class="program-duration">3 ans • Pédagogie & Formation</div>
                    <p class="program-text">
                        Devenez un acteur du changement dans l'éducation. Formation des enseignants 
                        innovants et pédagogues passionnés.
                    </p>
                </div>
            </div>

            <!-- Communication -->
            <div class="program-card animate-in delay-1">
                <div class="program-image-container">
                    <img src="https://zurcher.edu.mg/wp-content/uploads/2026/01/Design-sans-titre-1.jpg" alt="Communication" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Communication</h3>
                    <div class="program-duration">3 ans • Médias & RP</div>
                    <p class="program-text">
                        Maîtrisez l'art de la communication dans un monde connecté. 
                        Journalisme, relations publiques, médias numériques.
                    </p>
                </div>
            </div>

            <!-- Études Anglophones -->
            <div class="program-card animate-in delay-2">
                <div class="program-image-container">
                    <img src="https://zurcher.edu.mg/wp-content/uploads/2026/01/EA.jpg" alt="Études Anglophones" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Études Anglophones</h3>
                    <div class="program-duration">3 ans • Langue & Culture</div>
                    <p class="program-text">
                        Explorez la richesse de la langue et de la culture anglophone. 
                        Littérature, linguistique et civilisation.
                    </p>
                </div>
            </div>

            <!-- Droit -->
            <div class="program-card animate-in delay-3">
                <div class="program-image-container">
                    <img src="https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=2070&auto=format&fit=crop" alt="Droit" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Droit</h3>
                    <div class="program-duration">3 ans • Juridique</div>
                    <p class="program-text">
                        Formez-vous aux fondements du droit et de la justice. 
                        Préparation des futurs juristes éthiques et compétents.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section section-alt">
        <div class="section-header">
            <span class="section-label">Pourquoi l'UAZ</span>
            <h2 class="section-title">Une Formation d'Excellence</h2>
            <p class="section-subtitle">
                Ce qui fait la différence de notre université
            </p>
        </div>

        <div class="cards-grid">
            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h3 class="card-title">Enseignement de Qualité</h3>
                <p class="card-text">
                    Des professeurs qualifiés et expérimentés, un programme académique rigoureux 
                    et des méthodes pédagogiques innovantes.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/>
                    </svg>
                </div>
                <h3 class="card-title">Valeurs Adventistes</h3>
                <p class="card-text">
                    Une éducation holistique qui développe le corps, l'esprit et l'âme, 
                    fondée sur les principes chrétiens adventistes.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                </div>
                <h3 class="card-title">Insertion Professionnelle</h3>
                <p class="card-text">
                    Des partenariats avec des entreprises et organisations pour faciliter 
                    l'insertion professionnelle de nos diplômés.
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script src="<?=$app_base?>/landing/pages/pages.js"></script>
</body>
</html>
