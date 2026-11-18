
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                7 mentions • 14 parcours
            </div>
            <h1 class="hero-title">Nos Formations</h1>
            <p class="hero-subtitle">
                Découvrez nos parcours de Licence, habilités par le MESupRES. L'UAZ vous offre une formation de qualité,
                ancrée dans les valeurs adventistes et tournée vers l'avenir.
            </p>
        </div>
    </section>

    <!-- Stats Section
         Chaque chiffre est un bouton : au survol, au clavier ou au toucher,
         il decouvre ce que le mot veut dire. « Mention », « parcours »,
         « domaine » ne parlent pas d'eux-memes a qui decouvre l'universite. -->
    <div class="stats-section">
        <button type="button" class="stat-box" aria-describedby="def-mentions">
            <span class="stat-value">7</span>
            <span class="stat-label">Mentions<span class="stat-info" aria-hidden="true">i</span></span>
            <span class="stat-def" id="def-mentions" role="tooltip">
                La grande discipline que vous choisissez : Théologie, Droit,
                Informatique… L'UAZ en compte sept.
            </span>
        </button>
        <button type="button" class="stat-box" aria-describedby="def-parcours">
            <span class="stat-value">14</span>
            <span class="stat-label">Parcours<span class="stat-info" aria-hidden="true">i</span></span>
            <span class="stat-def" id="def-parcours" role="tooltip">
                La spécialisation à l'intérieur d'une mention. En Droit, par
                exemple : droit privé ou droit public.
            </span>
        </button>
        <button type="button" class="stat-box" aria-describedby="def-domaines">
            <span class="stat-value">4</span>
            <span class="stat-label">Domaines<span class="stat-info" aria-hidden="true">i</span></span>
            <span class="stat-def" id="def-domaines" role="tooltip">
                Le regroupement des mentions par le ministère : Arts et Lettres,
                Sciences Sociales, Santé, Ingénierie.
            </span>
        </button>
        <button type="button" class="stat-box" aria-describedby="def-niveaux">
            <span class="stat-value">3</span>
            <span class="stat-label">Niveaux<span class="stat-info" aria-hidden="true">i</span></span>
            <span class="stat-def" id="def-niveaux" role="tooltip">
                Les trois années de la Licence : L1, L2 et L3.
            </span>
        </button>
    </div>

    <!-- Formations Grid -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Nos Mentions</span>
            <h2 class="section-title">Mentions et parcours</h2>
            <p class="section-subtitle">
                Choisissez parmi nos 7 mentions pour construire votre avenir professionnel
            </p>
        </div>

        <div class="cards-grid">
            <!-- Théologie -->
            <div class="program-card animate-in">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/theologie.jpg" alt="Théologie" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Théologie</h3>
                    <div class="program-duration">Parcours : Théologie biblique · Théologie pratique</div>
                    <p class="program-text">
                        Approfondissez votre compréhension des textes sacrés et de la théologie adventiste. 
                        Une formation spirituelle et académique pour servir l'Église.
                    </p>
                </div>
            </div>

            <!-- Gestion -->
            <div class="program-card animate-in delay-1">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/gestion.jpg" alt="Gestion" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Gestion</h3>
                    <div class="program-duration">Parcours : Management des affaires · Finance et comptabilité</div>
                    <p class="program-text">
                        Développez vos compétences en leadership et gestion d'entreprise. 
                        Formation des futurs dirigeants capables de relever les défis économiques.
                    </p>
                </div>
            </div>

            <!-- Informatique -->
            <div class="program-card animate-in delay-2">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/informatique.jpg" alt="Informatique" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Informatique</h3>
                    <div class="program-duration">Parcours : Génie logiciel et SI · Administration réseau</div>
                    <p class="program-text">
                        Plongez dans le monde du numérique. Développement logiciel, systèmes d'information, 
                        réseaux - préparez-vous aux métiers de demain.
                    </p>
                </div>
            </div>

            <!-- Sciences Infirmières -->
            <div class="program-card animate-in delay-3">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/sciences-infirmieres.jpg" alt="Sciences Infirmières" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Sciences Infirmières</h3>
                    <div class="program-duration">Parcours : Infirmerie générale · Maïeutique</div>
                    <p class="program-text">
                        Formez-vous aux métiers de la santé avec un cursus d'excellence.
                        Un cursus complet alliant théorie et pratique clinique.
                    </p>
                </div>
            </div>

            <!-- Communication -->
            <div class="program-card animate-in delay-1">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/communication.jpg" alt="Communication" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Communication</h3>
                    <div class="program-duration">Parcours : Communication d'entreprise · Média et journalisme</div>
                    <p class="program-text">
                        Maîtrisez l'art de la communication dans un monde connecté. 
                        Journalisme, relations publiques, médias numériques.
                    </p>
                </div>
            </div>

            <!-- Études Anglophones -->
            <div class="program-card animate-in delay-2">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/etudes-anglophones.jpg" alt="Études Anglophones" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Études Anglophones</h3>
                    <div class="program-duration">Parcours : Traduction et interprétation · Langue et littérature</div>
                    <p class="program-text">
                        Explorez la richesse de la langue et de la culture anglophone. 
                        Littérature, linguistique et civilisation.
                    </p>
                </div>
            </div>

            <!-- Droit -->
            <div class="program-card animate-in delay-3">
                <div class="program-image-container">
                    <img src="<?=$app_base?>/file/vitrine/droit.jpg" alt="Droit" class="program-image">
                    <span class="program-badge">Licence</span>
                </div>
                <div class="program-content">
                    <h3 class="program-title">Droit</h3>
                    <div class="program-duration">Parcours : Droit privé · Droit public</div>
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
                    Des professeurs qualifiés et expérimentés, un cursus académique rigoureux
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

            <?php /* Anciennement « Insertion Professionnelle », dont le propos —
                     des partenariats d'entreprise — se recoupait largement avec
                     celui-ci. La carte parle desormais des diplomes eux-memes :
                     c'est le reseau qu'ils forment qui ouvre les portes, et
                     c'est verifiable, la ou une promesse d'insertion ne l'est
                     pas. L'icone suit : un groupe, plus une mallette. */ ?>
            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                </div>
                <h3 class="card-title">Réseau des Alumni</h3>
                <p class="card-text">
                    Des diplômés présents dans les entreprises, les institutions partout
                    à Madagascar et d'ailleurs — un réseau professionnel qui
                    accompagne chaque nouvelle promotion.
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->