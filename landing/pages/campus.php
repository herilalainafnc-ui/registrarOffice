<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus - Université Adventiste Zurcher</title>
    <link rel="shortcut icon" href="../../file/logo-coldbloud.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./pages.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Vohitsoa, Sambaina
            </div>
            <h1 class="hero-title">Notre Campus</h1>
            <p class="hero-subtitle">
                Un environnement d'apprentissage exceptionnel au cœur des hauts plateaux de Madagascar. 
                Découvrez nos installations modernes et notre cadre de vie inspirant.
            </p>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Visite Virtuelle</span>
            <h2 class="section-title">Galerie du Campus</h2>
            <p class="section-subtitle">
                Explorez les différents espaces de notre université
            </p>
        </div>

        <div class="gallery-grid">
            <div class="gallery-item">
                <img src="https://zurcher.edu.mg/wp-content/uploads/2026/01/Design-sans-titre-6.jpg" alt="Bibliothèque">
                <div class="gallery-overlay">
                    <h4 class="gallery-title">Bibliothèque</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="https://torohay.xyz/wp-content/uploads/2013/10/Universite-Adventiste-Zurcher-examen.jpg" alt="Salle de classe">
                <div class="gallery-overlay">
                    <h4 class="gallery-title">Salles de Classe</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="https://images.squarespace-cdn.com/content/v1/5f3e4a9122b8c82e172c098d/fb2604bf-a166-4e20-999e-5717fed6dbba/photo+2.jpg" alt="Entrée">
                <div class="gallery-overlay">
                    <h4 class="gallery-title">Entrée</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEj-9t2xM38iklA2brDmBkrPIAZj-YITurhUDyCrOK6JvvLYUfkTJYEmt9t41YihnNeq9j9scw4PkJ6PCVViMMaCwwMf9AfxK2e-hZ-1A4_RYFr_pRFMyQsWJCYrL9EarFpGoY3Q/s280/DSCF8124.JPG" alt="Chapelle">
                <div class="gallery-overlay">
                    <h4 class="gallery-title">Chapelle</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="https://zurcher.edu.mg/wp-content/uploads/2025/11/INS5133-scaled.jpg" alt="Campus verdoyant">
                <div class="gallery-overlay">
                    <h4 class="gallery-title">Espaces Verts</h4>
                </div>
            </div>
        </div>
    </section>

    <!-- Facilities Section -->
    <section class="section section-alt">
        <div class="section-header">
            <span class="section-label">Infrastructures</span>
            <h2 class="section-title">Nos Installations</h2>
            <p class="section-subtitle">
                Des équipements modernes pour une formation de qualité
            </p>
        </div>

        <div class="cards-grid">
            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                    </svg>
                </div>
                <h3 class="card-title">Bibliothèque</h3>
                <p class="card-text">
                    Une bibliothèque moderne avec plus de 20 000 ouvrages, des ressources numériques, 
                    et des espaces de travail individuel et en groupe.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>
                    </svg>
                </div>
                <h3 class="card-title">Laboratoire Informatique</h3>
                <p class="card-text">
                    Des salles informatiques équipées d'ordinateurs récents, connexion internet haut débit, 
                    et logiciels professionnels pour la formation pratique.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                    </svg>
                </div>
                <h3 class="card-title">Laboratoire de Sciences</h3>
                <p class="card-text">
                    Un laboratoire équipé pour les travaux pratiques en biologie, 
                    particulièrement pour la formation en sciences infirmières.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                </div>
                <h3 class="card-title">Résidences Universitaires</h3>
                <p class="card-text">
                    Des dortoirs séparés pour garçons et filles, chambres confortables, 
                    sanitaires modernes et espaces communs conviviaux.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.87c1.355 0 2.697.055 4.024.165C17.155 8.51 18 9.473 18 10.608v2.513m-3-4.87v-1.5m-6 1.5v-1.5m12 9.75l-1.5.75a3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0L3 16.5m15-3.38a48.474 48.474 0 00-6-.37c-2.032 0-4.034.125-6 .37m12 0c.39.049.777.102 1.163.16 1.07.16 1.837 1.094 1.837 2.175v5.17c0 .62-.504 1.124-1.125 1.124H4.125A1.125 1.125 0 013 20.625v-5.17c0-1.08.768-2.014 1.837-2.174A47.78 47.78 0 016 13.12M12.265 3.11a.375.375 0 11-.53 0L12 2.845l.265.265zm-3 0a.375.375 0 11-.53 0L9 2.845l.265.265zm6 0a.375.375 0 11-.53 0L15 2.845l.265.265z"/>
                    </svg>
                </div>
                <h3 class="card-title">Cafétéria</h3>
                <p class="card-text">
                    Un réfectoire proposant des repas équilibrés et végétariens, 
                    conformes aux principes de santé adventistes.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/>
                    </svg>
                </div>
                <h3 class="card-title">Chapelle</h3>
                <p class="card-text">
                    Un lieu de culte au cœur du campus pour les services religieux hebdomadaires, 
                    la méditation et la vie spirituelle de la communauté.
                </p>
            </div>
        </div>
    </section>

    <!-- Life on Campus Section -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Vie Étudiante</span>
            <h2 class="section-title">La Vie sur le Campus</h2>
            <p class="section-subtitle">
                Une expérience universitaire enrichissante et équilibrée
            </p>
        </div>

        <div class="cards-grid">
            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                </div>
                <h3 class="card-title">Clubs et Associations</h3>
                <p class="card-text">
                    Rejoignez nos clubs étudiants : chorale, club anglais, club informatique, 
                    club de débat et bien d'autres activités parascolaires.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                    </svg>
                </div>
                <h3 class="card-title">Vie Spirituelle</h3>
                <p class="card-text">
                    Participez aux cultes quotidiens, aux groupes d'étude biblique, 
                    et aux programmes spéciaux du sabbat pour nourrir votre foi.
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                </div>
                <h3 class="card-title">Événements</h3>
                <p class="card-text">
                    Des événements tout au long de l'année : journées sportives, 
                    semaines spirituelles, concerts, conférences et cérémonies de remise de diplômes.
                </p>
            </div>
        </div>
    </section>

    <!-- Location Section -->
    <section class="section section-alt">
        <div class="section-header">
            <span class="section-label">Localisation</span>
            <h2 class="section-title">Comment Nous Trouver</h2>
            <p class="section-subtitle">
                Notre campus est situé à Vohitsoa, dans le commune d'Antsampanimahazo
            </p>
        </div>

        <div class="contact-grid">
            <div class="contact-info-list">
                <div class="contact-item">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                        </svg>
                    </div>
                    <div class="contact-details">
                        <h4>Adresse</h4>
                        <p>BP 325, PK 135 <br>Sambaina, Antsirabe 110<br>MADAGASCAR</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        </svg>
                    </div>
                    <div class="contact-details">
                        <h4>Accès</h4>
                        <p>À 94 km d'Antananarivo<br>Route nationale 7<br>Direction Antsirabe</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="contact-details">
                        <h4>Horaires d'Ouverture</h4>
                        <p>Lundi - Jeudi : 8h00 - 12h00 | 13h30 - 17h30<br>Vendredi : 8h00 - 12h00<br>Fermé le Sabbat et dimanche</p>
                    </div>
                </div>
            </div>

            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3780.5!2d47.1825!3d-19.4875!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x21f07e8c8c8c8c8c%3A0x0!2s85Q7%2BM95%2C%20Antsampanimahazo!5e0!3m2!1sfr!2smg!4v1706000000000"
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script src="./pages.js"></script>
</body>
</html>
