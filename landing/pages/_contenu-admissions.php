<?php require_once __DIR__ . '/../../data/vitrine.config.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Inscriptions ouvertes 2026-2027
            </div>
            <h1 class="hero-title">Rejoignez l'UAZ</h1>
            <p class="hero-subtitle">
                Commencez votre parcours académique dans une université d'excellence. 
                Découvrez les étapes pour intégrer notre communauté étudiante.
            </p>
        </div>
    </section>

    <!-- Timeline Section -->
    <section class="section">
        <div class="section-header">
            <span class="section-label">Processus d'Admission</span>
            <h2 class="section-title">Les Étapes pour Rejoindre l'UAZ</h2>
            <p class="section-subtitle">
                Un processus simple et transparent pour votre inscription
            </p>
        </div>

        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 1 — facultative</span>
                    <h3 class="timeline-title">Pré-inscription en ligne</h3>
                    <p class="timeline-text">
                        Déposez vos informations à l'avance. Elles seront reprises le jour de
                        l'inscription : vous n'aurez pas à les saisir une seconde fois.
                        Cette étape n'est pas obligatoire pour se présenter au test.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 2</span>
                    <h3 class="timeline-title">Test d'admission</h3>
                    <p class="timeline-text">
                        Présentez-vous au test d'admission, organisé le <strong><?= vitrineDate('date.test', '13 octobre 2026') ?></strong>
                        sur le campus. Il conditionne l'accès à l'inscription.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 3</span>
                    <h3 class="timeline-title">Résultats</h3>
                    <p class="timeline-text">
                        Les résultats du test sont communiqués par le bureau du registrariat.
                        Les candidats admis peuvent procéder à leur inscription.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 4</span>
                    <h3 class="timeline-title">Dossier d'inscription</h3>
                    <p class="timeline-text">
                        Présentez vos pièces justificatives au registrariat : relevés de notes,
                        diplômes, certificats et pièce d'identité.
                    </p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-step">Étape 5</span>
                    <h3 class="timeline-title">Inscription définitive</h3>
                    <p class="timeline-text">
                        Finalisez votre inscription avec le paiement des frais. La rentrée de l'année
                        académique 2026-2027 a lieu le <strong><?= vitrineDate('date.rentree', '13 octobre 2026') ?></strong>.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Requirements Section -->
    <section class="section section-alt">
        <div class="section-header">
            <span class="section-label">Prérequis</span>
            <h2 class="section-title">Conditions d'Admission</h2>
            <p class="section-subtitle">
                Les documents et critères nécessaires pour candidater
            </p>
        </div>

        <div class="cards-grid">
            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <h3 class="card-title">Documents Requis</h3>
                <p class="card-text">
                    • Certificat de fin d'études secondaires (BACC ou équivalent)<br>
                    • Relevés de notes des 3 dernières années<br>
                    • Copie de la carte d'identité nationale<br>
                    • 4 photos d'identité récentes<br>
                    • Certificat médical<br>
                    • Lettre de recommandation
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="card-title">Critères d'Éligibilité</h3>
                <p class="card-text">
                    • Être titulaire du Baccalauréat ou équivalent<br>
                    • Bonne moralité et engagement éthique<br>
                    • Capacité à respecter les valeurs adventistes<br>
                    • Motivation pour le domaine d'études choisi
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="card-title">Frais de Scolarité</h3>
                <p class="card-text">
                    • Frais d'inscription et frais de scolarité annuels variables
                      selon la filière<br>
                    • Possibilité de paiement échelonné<br>
                    • Remboursements disponibles pour les étudiants méritants<br>
                    • Montants communiqués par le bureau du registrariat<br>
                </p>
            </div>

            <div class="feature-card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                </div>
                <h3 class="card-title">Calendrier</h3>
                <p class="card-text">
                    • Pré-inscription en ligne : dès maintenant<br>
                    • Test d'admission : <?= vitrineDate('date.test', '13 octobre 2026') ?><br>
                    • Inscription : à partir du <?= vitrineDate('date.rentree', '13 octobre 2026') ?><br>
                    • Rentrée académique <?= vitrineTxt('annee.academique', '2026-2027') ?> : <?= vitrineDate('date.rentree', '13 octobre 2026') ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Application Form Section -->
    <!-- L identifiant sert de cible au bouton « Se pre-inscrire » du bandeau
         d accueil : il mene au formulaire, non au haut de la rubrique. -->
    <section class="section" id="pre-inscription">
        <div class="section-header">
            <span class="section-label">Inscription</span>
            <h2 class="section-title">Demande de Pré-inscription</h2>
            <p class="section-subtitle">
                Remplissez ce formulaire pour commencer votre candidature
            </p>
        </div>

        <div class="form-container form-container--large">


            <?php /* Notification en toast : ce formulaire compte une vingtaine
                     de champs, et l'erreur revient tout en haut d'une page ou
                     le candidat se trouve deja beaucoup plus bas. Le module
                     vient de footer.php, charge apres ce bloc.

                     json_encode et non htmlspecialchars : l'erreur peut
                     contenir une valeur saisie par un visiteur non
                     authentifie, et seul json_encode echappe correctement les
                     guillemets, antislashs et retours a la ligne en contexte
                     de chaine JavaScript. Les options HEX ferment le contexte
                     HTML environnant. */ ?>
            <?php if ($error): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (!window.Toast) { return; }
                Toast.error(
                    <?= json_encode((string)$error, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
                                    | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    { duration: 9000 }
                );
            });
            </script>
            <?php endif; ?>
            
<?php
            /* Repopulation apres erreur. Le formulaire compte une vingtaine de
               champs : renvoyer le candidat sur une page vide parce qu'il a
               oublie son telephone serait le perdre pour de bon. */
            $vieux = static function (string $champ) : string {
                return htmlspecialchars((string)($_POST[$champ] ?? ''), ENT_QUOTES, 'UTF-8');
            };
            $choisi = static function (string $champ, string $valeur) : string {
                return (($_POST[$champ] ?? '') === $valeur) ? ' selected' : '';
            };
            ?>
            <?php /* action et method conserves : sans JavaScript, le formulaire
                     se soumet normalement et la page repond par une redirection
                     vers « ?envoye=1 ». Le script plus bas prend la main quand
                     il peut, mais il n'est pas la condition de l'envoi. */ ?>
            <form id="preadm-formulaire"
                  action="<?php echo htmlspecialchars(($app_base ?? '') . '/admissions'); ?>" method="POST">
                    <!-- Page d ou vient l envoi : la confirmation y revient,
                         qu il s agisse de la page unique ou de la page autonome. -->
                    <input type="hidden" name="origine" value="<?php echo htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/admissions', '?'), ENT_QUOTES); ?>">

                <!-- ===================== Informations personnelles ===================== -->
                <fieldset class="form-bloc">
                    <legend class="form-bloc__titre">Informations personnelles</legend>
                    <div class="form-grid">

                        <div class="form-group">
                            <label class="form-label" for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" class="form-input" value="<?php echo $vieux('nom'); ?>" placeholder="Nom de famille" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prenom">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" class="form-input" value="<?php echo $vieux('prenom'); ?>" placeholder="Prénom(s)" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="date_naissance">Date de naissance *</label>
                            <input type="date" id="date_naissance" name="date_naissance" class="form-input" value="<?php echo $vieux('date_naissance'); ?>">
                            <span class="form-hint">Si vous ne connaissez pas la date exacte, laissez vide et indiquez seulement l'année ci-contre.</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="annee_naissance">Année de naissance</label>
                            <input type="text" id="annee_naissance" name="annee_naissance" class="form-input" value="<?php echo $vieux('annee_naissance'); ?>" placeholder="Ex : 2004" inputmode="numeric" pattern="[0-9]{4}" maxlength="4">
                            <span class="form-hint">À remplir uniquement si la date complète vous est inconnue.</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="lieu_naissance">Lieu de naissance</label>
                            <input type="text" id="lieu_naissance" name="lieu_naissance" class="form-input" value="<?php echo $vieux('lieu_naissance'); ?>" placeholder="Ville de naissance">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="genre">Genre *</label>
                            <select id="genre" name="genre" class="form-select" required>
                                <option value="">Sélectionnez</option>
                                <option value="M"<?php echo $choisi('genre', 'M'); ?>>Masculin</option>
                                <option value="F"<?php echo $choisi('genre', 'F'); ?>>Féminin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="etat_civil">État civil</label>
                            <select id="etat_civil" name="etat_civil" class="form-select">
                                <option value="">Sélectionnez</option>
                                <option value="celibataire"<?php echo $choisi('etat_civil', 'celibataire'); ?>>Célibataire</option>
                                <option value="marie"<?php echo $choisi('etat_civil', 'marie'); ?>>Marié(e)</option>
                                <option value="divorce"<?php echo $choisi('etat_civil', 'divorce'); ?>>Divorcé(e)</option>
                                <option value="veuf"<?php echo $choisi('etat_civil', 'veuf'); ?>>Veuf / Veuve</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nationalite">Nationalité</label>
                            <input type="text" id="nationalite" name="nationalite" class="form-input" value="<?php echo $vieux('nationalite'); ?>" placeholder="Ex : Malagasy">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pays_origine">Pays d'origine</label>
                            <input type="text" id="pays_origine" name="pays_origine" class="form-input" value="<?php echo $vieux('pays_origine'); ?>" placeholder="Ex : Madagascar">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ville">Ville</label>
                            <input type="text" id="ville" name="ville" class="form-input" value="<?php echo $vieux('ville'); ?>" placeholder="Ville de résidence">
                        </div>

                        <div class="form-group form-group--pleine">
                            <label class="form-label" for="adresse">Adresse</label>
                            <input type="text" id="adresse" name="adresse" class="form-input" value="<?php echo $vieux('adresse'); ?>" placeholder="Adresse complète">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nom_pere">Nom du père</label>
                            <input type="text" id="nom_pere" name="nom_pere" class="form-input" value="<?php echo $vieux('nom_pere'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nom_mere">Nom de la mère</label>
                            <input type="text" id="nom_mere" name="nom_mere" class="form-input" value="<?php echo $vieux('nom_mere'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_parent">Contact des parents</label>
                            <input type="tel" id="contact_parent" name="contact_parent" class="form-input" value="<?php echo $vieux('contact_parent'); ?>" placeholder="+261 34 XX XXX XX">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="tuteur">Tuteur</label>
                            <input type="text" id="tuteur" name="tuteur" class="form-input" value="<?php echo $vieux('tuteur'); ?>" placeholder="Nom du tuteur">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_tuteur">Contact du tuteur</label>
                            <input type="tel" id="contact_tuteur" name="contact_tuteur" class="form-input" value="<?php echo $vieux('contact_tuteur'); ?>" placeholder="+261 34 XX XXX XX">
                        </div>

                    </div>
                </fieldset>

                <!-- ===================== Contact ===================== -->
                <fieldset class="form-bloc">
                    <legend class="form-bloc__titre">Contact</legend>
                    <div class="form-grid">

                        <div class="form-group">
                            <label class="form-label" for="email">Adresse email *</label>
                            <input type="email" id="email" name="email" class="form-input" value="<?php echo $vieux('email'); ?>" placeholder="votre.email@exemple.com" required
                                   data-verif-url="<?php echo htmlspecialchars(($app_base ?? '') . '/admissions/verifier-email'); ?>">
                            <span class="form-hint">C'est à cette adresse que le bureau des admissions vous écrira. Une adresse UAZ vous sera attribuée plus tard, à l'inscription.</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Numéro de téléphone *</label>
                            <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo $vieux('phone'); ?>" placeholder="+261 34 XX XXX XX" required>
                        </div>

                    </div>
                </fieldset>

                <!-- ===================== Choix d'etudes ===================== -->
                <fieldset class="form-bloc">
                    <legend class="form-bloc__titre">Choix d'études</legend>
                    <div class="form-grid">

                        <div class="form-group">
                            <label class="form-label" for="diplome_vise">Diplôme visé *</label>
                            <select id="diplome_vise" name="diplome_vise" class="form-select" required>
                                <option value="">Sélectionnez</option>
                                <option value="licence"<?php echo $choisi('diplome_vise', 'licence'); ?>>Licence</option>
                                <option value="master"<?php echo $choisi('diplome_vise', 'master'); ?>>Master</option>
                            </select>
                            <?php /* Pas de phrase d'aide ici : les mentions sans Master
                                     portent deja « — Licence seulement » dans leur propre
                                     liste. La repeter au-dessus disait deux fois la meme
                                     chose, plus loin de l'endroit ou elle sert. */ ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="mention">Mention (filière) *</label>
                            <select id="mention" name="mention" class="form-select" required>
<?php
                            /* Les mentions viennent de /settings/mentions : la table
                               `filiere`, filtrée sur celles qui sont ouvertes. Elles
                               étaient écrites en dur ici, si bien qu'une mention créée
                               par le registrariat n'apparaissait pas, et qu'une mention
                               fermée continuait d'être proposée — seul un contrôle
                               serveur refusait ensuite la candidature, après que le
                               candidat avait tout saisi. */
                            require_once __DIR__ . '/../../data/parcours.php';
                            $_mentions = parcoursLibellesMentions();
                            ?>
                                <option value=""><?php echo $_mentions === []
                                    ? 'Aucune mention ne recrute actuellement'
                                    : 'Sélectionnez une mention'; ?></option>
<?php foreach ($_mentions as $_cle => $_libelle) { ?>
                                <option value="<?php echo htmlspecialchars($_cle, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $choisi('mention', $_cle); ?>><?php echo htmlspecialchars($_libelle, ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="parcours">Parcours *</label>
                            <select id="parcours" name="parcours" class="form-select" required disabled data-choisi="<?php echo $vieux('parcours'); ?>">
                                <option value="">Choisissez d'abord une mention</option>
                            </select>
                            <span class="form-hint">La liste se remplit selon la mention et le diplôme choisis.</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="bac_year">Année d'obtention du BACC</label>
                            <input type="text" id="bac_year" name="bac_year" class="form-input" value="<?php echo $vieux('bac_year'); ?>" placeholder="Ex : 2025" inputmode="numeric" pattern="[0-9]{4}" maxlength="4">
                        </div>

                        <div class="form-group form-group--pleine">
                            <label class="form-label" for="message">Motivation *</label>
                            <textarea id="message" name="message" class="form-textarea" placeholder="Décrivez brièvement vos motivations pour rejoindre l'UAZ..." required><?php echo $vieux('message'); ?></textarea>
                        </div>

                    </div>
                </fieldset>

                <p class="form-hint" style="margin-bottom:18px;">
                    Les pièces justificatives (photo, CIN ou acte de naissance, diplômes, relevés de notes,
                    certificats) vous seront demandées <strong>après</strong> l'enregistrement de cette demande,
                    par un lien personnel envoyé à votre adresse email.
                </p>

                <button type="submit" class="submit-btn">Soumettre ma pré-inscription</button>
            </form>

            <script>
            /* =================================================================
               Envoi de la pré-inscription sans rechargement.

               Le formulaire se soumettait a l'ancienne : la page repartait, le
               serveur redirigeait vers « ?envoye=1#admissions », et le candidat
               revenait en haut d'une page rechargee. Sur une connexion lente —
               le cas de la plupart des candidats — l'ecran restait blanc
               plusieurs secondes sans rien indiquer.

               Ici la page ne bouge pas : le bouton dit qu'il travaille, puis la
               confirmation s'ouvre par-dessus. En cas d'erreur, le formulaire
               reste tel quel, avec les vingt champs deja remplis — ce que la
               repopulation cote serveur reconstituait a grand-peine.

               La soumission classique reste le repli : si ce script ne
               s'execute pas, ou si fetch() est absent, rien n'est intercepte et
               le serveur redirige comme avant.
               ================================================================= */
            (function () {
                var form = document.getElementById('preadm-formulaire');
                if (!form || typeof window.fetch !== 'function') { return; }

                var bouton = form.querySelector('button[type="submit"]');
                var enCours = false;

                /* =============================================================
                   Surbrillance des champs obligatoires laissés vides

                   Le navigateur n'affiche sa bulle que sur le PREMIER champ
                   invalide : sur ce formulaire de vingt champs répartis sur
                   plusieurs écrans, on corrigeait donc un champ pour découvrir
                   le suivant au clic d'après. On marque ici tous les champs en
                   cause d'un coup.

                   noValidate est posé PAR LE SCRIPT et non dans le HTML : sans
                   lui, la bulle native s'afficherait par-dessus nos marques.
                   Et si ce script ne s'exécute pas, l'attribut n'est pas posé
                   et le navigateur reprend sa validation habituelle — on ne
                   retire jamais un filet sans le remplacer.
                   ============================================================= */
                form.noValidate = true;

                function groupe(champ) {
                    return champ.closest ? champ.closest('.form-group') : null;
                }

                function marquer(champ, texte) {
                    champ.classList.add('champ-manquant');
                    champ.setAttribute('aria-invalid', 'true');

                    var g = groupe(champ);
                    if (g) { g.classList.add('form-group--manquant'); }

                    // Message sous le champ : le seul repère qui ne dépende pas
                    // de la couleur.
                    var id = (champ.id || champ.name) + '-alerte';
                    var alerte = document.getElementById(id);
                    if (!alerte) {
                        alerte = document.createElement('span');
                        alerte.className = 'champ-alerte';
                        alerte.id = id;
                        (g || champ.parentNode).appendChild(alerte);
                    }
                    alerte.textContent = texte;
                    champ.setAttribute('aria-describedby', id);
                }

                function demarquer(champ) {
                    champ.classList.remove('champ-manquant');
                    champ.removeAttribute('aria-invalid');

                    var g = groupe(champ);
                    if (g) { g.classList.remove('form-group--manquant'); }

                    var alerte = document.getElementById((champ.id || champ.name) + '-alerte');
                    if (alerte) { alerte.remove(); }
                    champ.removeAttribute('aria-describedby');
                }

                function toutDemarquer() {
                    form.querySelectorAll('.champ-manquant').forEach(demarquer);
                }

                /** Motif du refus, dit dans les termes du champ concerné. */
                function motif(champ) {
                    if (champ.validity.valueMissing) {
                        return champ.tagName === 'SELECT'
                            ? 'Choisissez une valeur.'
                            : 'Ce champ est obligatoire.';
                    }
                    if (champ.validity.typeMismatch && champ.type === 'email') {
                        return 'Cette adresse email n’est pas valide.';
                    }
                    if (champ.validity.customError && champ.type === 'email') {
                        // La raison est deja ecrite juste au-dessus (verification pendant la frappe).
                        return 'Corrigez cette adresse avant d’envoyer.';
                    }
                    if (champ.validity.patternMismatch) { return 'Le format attendu n’est pas respecté.'; }
                    return champ.validationMessage || 'Cette valeur n’est pas acceptée.';
                }

                /**
                 * Marque tout ce qui bloque l'envoi.
                 * @returns {number} nombre de champs marqués
                 */
                function marquerLesManquants() {
                    toutDemarquer();

                    var fautifs = [];
                    form.querySelectorAll('input, select, textarea').forEach(function (champ) {
                        // Les champs désactivés — le parcours avant le choix de
                        // la mention — ne sont pas soumis, donc pas en cause.
                        if (champ.disabled || champ.type === 'hidden') { return; }
                        if (champ.checkValidity && !champ.checkValidity()) { fautifs.push(champ); }
                    });

                    fautifs.forEach(function (champ) { marquer(champ, motif(champ)); });

                    if (fautifs.length) {
                        var premier = fautifs[0];
                        /* On amène le premier champ fautif à l'écran avant de
                           lui donner le focus : un focus seul fait sauter la
                           page d'un coup, sans que l'on comprenne où l'on
                           vient d'être emmené. */
                        if (premier.scrollIntoView) {
                            premier.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        setTimeout(function () { premier.focus({ preventScroll: true }); }, 120);
                    }
                    return fautifs.length;
                }

                /* Marque posée par le serveur : il voit des cas que le
                   navigateur ignore — la date de naissance n'est pas
                   « required » puisque l'année seule suffit, et le couple
                   mention/parcours est revérifié en base. */
                function marquerDepuisServeur(noms, message) {
                    if (!noms || !noms.length) { return; }
                    toutDemarquer();

                    var premier = null;
                    noms.forEach(function (nom) {
                        var champ = form.querySelector('[name="' + nom + '"]');
                        if (!champ) { return; }
                        marquer(champ, champ.value === '' ? 'Ce champ est obligatoire.' : message);
                        if (!premier) { premier = champ; }
                    });

                    if (premier && premier.scrollIntoView) {
                        premier.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }

                /* La marque disparaît dès que le champ devient acceptable :
                   la garder jusqu'au prochain envoi ferait douter d'une
                   correction déjà faite. */
                ['input', 'change'].forEach(function (evenement) {
                    form.addEventListener(evenement, function (e) {
                        var champ = e.target;
                        if (!champ.classList || !champ.classList.contains('champ-manquant')) { return; }
                        if (champ.checkValidity && champ.checkValidity()) { demarquer(champ); }
                    });
                });

                function dire(message, type) {
                    /* Toujours un toast, jamais la boite du navigateur. Le
                       module est charge en fin de ce contenu (init/toast.php) :
                       il est la des qu'une action a lieu. */
                    if (window.Toast && typeof Toast.show === 'function') {
                        Toast.show(message, type, { duration: type === 'error' ? 10000 : 6000 });
                    } else if (window.console) {
                        console.warn(message);
                    }
                }

                form.addEventListener('submit', function (e) {
                    /* On ne part jamais avec un formulaire incomplet : le
                       serveur le refuserait par un message générique alors que
                       le navigateur sait déjà, champ par champ, ce qui manque.
                       preventDefault() dans tous les cas — noValidate est posé,
                       le navigateur ne bloquerait plus rien de lui-même. */
                    e.preventDefault();

                    var manquants = marquerLesManquants();
                    if (manquants > 0) {
                        dire(manquants === 1
                            ? 'Un champ obligatoire n’est pas rempli. Il est signalé en rouge.'
                            : manquants + ' champs obligatoires ne sont pas remplis. '
                              + 'Ils sont signalés en rouge.', 'error');
                        return;
                    }

                    if (enCours) { return; }
                    enCours = true;

                    var libelle = bouton ? bouton.textContent : '';
                    if (bouton) {
                        bouton.disabled = true;
                        bouton.textContent = 'Envoi en cours…';
                    }

                    function rendreLeBouton() {
                        enCours = false;
                        if (bouton) {
                            bouton.disabled = false;
                            bouton.textContent = libelle;
                        }
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        // En-tete lu par preadmEnvoiSansRechargement() dans
                        // admissions.php : il fait repondre en JSON plutot que
                        // par une redirection vers une page entiere.
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    })
                    .then(function (reponse) {
                        return reponse.json().catch(function () {
                            throw new Error('reponse illisible');
                        });
                    })
                    .then(function (r) {
                        if (r && r.ok) {
                            /* Le formulaire est vide avant l'ouverture de la
                               confirmation : on ne veut pas qu'un second clic
                               renvoie la meme candidature. */
                            toutDemarquer();
                            form.reset();
                            var parcours = document.getElementById('parcours');
                            if (parcours) {
                                // Il depend de la mention : remis a son etat initial.
                                parcours.innerHTML = '<option value="">Choisissez d’abord une mention</option>';
                                parcours.disabled = true;
                            }

                            if (window.preadmModale && typeof preadmModale.ouvrir === 'function') {
                                preadmModale.ouvrir();
                            } else {
                                dire('Votre pré-inscription a bien été enregistrée.', 'success');
                            }
                            return;
                        }
                        /* Refus du serveur : il nomme les champs en cause, on
                           les signale comme ceux repérés côté navigateur. */
                        var message = (r && r.message) || "L'envoi n'a pas abouti.";
                        marquerDepuisServeur(r && r.champs, message);
                        dire(message, 'error');
                    })
                    .catch(function () {
                        dire('Le serveur n’a pas répondu. Votre demande n’a pas été envoyée — '
                           + 'vérifiez votre connexion et réessayez.', 'error');
                    })
                    .then(rendreLeBouton);
                });
            })();
            </script>

            <script>
            /* Adresse email verifiee pendant la frappe (data/email.verification.php) :
               forme, domaine qui recoit les emails, faute de frappe probable
               (« gmial.com »). La boite elle-meme ne peut pas etre verifiee : le
               message le rappelle quand tout le reste est bon.

               Une adresse qui ne peut pas recevoir d'email bloque l'envoi
               (setCustomValidity, lu par le marquage des champs ci-dessus). Une
               faute de frappe probable aussi, tant que le candidat n'a ni
               corrige ni choisi « Garder mon adresse » : un domaine de faute de
               frappe peut accepter le courrier, et le mail d'acceptation, qui
               porte des identifiants, partirait chez un inconnu. Le serveur
               revalide a l'envoi. */
            (function () {
                var champ = document.getElementById('email');
                if (!champ || typeof window.fetch !== 'function') { return; }
                var url = champ.getAttribute('data-verif-url');
                var groupe = champ.closest('.form-group');
                if (!url || !groupe) { return; }

                var etat = document.createElement('div');
                etat.className = 'email-verif';
                etat.id = 'email-verif';
                etat.setAttribute('aria-live', 'polite');
                etat.hidden = true;
                groupe.insertBefore(etat, groupe.querySelector('.form-hint'));

                var gardee = '';      // adresse gardee malgre une suggestion
                var verifiee = '';    // derniere adresse envoyee a la verification
                var numero = 0;       // seule la reponse la plus recente compte
                var minuterie = null;

                function bouton(texte, action) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'email-verif__action';
                    b.textContent = texte;
                    b.addEventListener('click', action);
                    return b;
                }

                function montrer(r, adresse) {
                    var aCorriger = r.bloquant || (r.etat === 'suggestion' && gardee !== adresse);
                    champ.setCustomValidity(aCorriger ? (r.message || 'Adresse à corriger.') : '');

                    etat.textContent = '';
                    if (!r.message) { etat.hidden = true; return; }
                    etat.hidden = false;
                    etat.className = 'email-verif email-verif--'
                        + (r.bloquant ? 'erreur' : (r.etat === 'ok' ? 'ok' : 'alerte'));

                    var texte = document.createElement('span');
                    texte.textContent = (r.bloquant ? '✗ ' : (r.etat === 'ok' ? '✓ ' : '⚠ '))
                        + (r.etat === 'suggestion' && gardee === adresse ? 'Adresse gardée telle quelle.' : r.message);
                    etat.appendChild(texte);

                    if (r.suggestion && gardee !== adresse) {
                        etat.appendChild(bouton('Utiliser ' + r.suggestion, function () {
                            champ.value = r.suggestion;
                            champ.dispatchEvent(new Event('input', { bubbles: true }));
                            verifier();
                            champ.focus();
                        }));
                    }
                    if (r.etat === 'suggestion' && gardee !== adresse) {
                        etat.appendChild(bouton('Garder mon adresse', function () {
                            gardee = adresse;
                            montrer(r, adresse);
                            champ.dispatchEvent(new Event('input', { bubbles: true }));
                        }));
                    }
                }

                function verifier(enSortant) {
                    clearTimeout(minuterie);
                    var adresse = champ.value.trim();
                    if (adresse === verifiee) { return; }

                    /* Adresse vide ou pas encore complete : pendant la frappe, on
                       attend la suite. En quittant le champ, une adresse avec @
                       est verifiee telle quelle (« nom@gm » reste a corriger). */
                    var domaine = adresse.split('@')[1] || '';
                    var incomplete = domaine.indexOf('.') < 1 || /\.$/.test(domaine);
                    if (adresse === '' || adresse.indexOf('@') < 1 || (incomplete && enSortant !== true)) {
                        verifiee = '';
                        champ.setCustomValidity('');
                        etat.hidden = true;
                        return;
                    }
                    verifiee = adresse;

                    var n = ++numero;
                    etat.hidden = false;
                    etat.className = 'email-verif email-verif--attente';
                    etat.textContent = 'Vérification de l’adresse…';
                    fetch(url + '?adresse=' + encodeURIComponent(adresse), { credentials: 'same-origin' })
                        .then(function (reponse) { return reponse.json(); })
                        .then(function (r) { if (n === numero) { montrer(r, adresse); } })
                        .catch(function () {
                            // Pas de reponse : on ne bloque pas, le serveur revalide a l'envoi.
                            if (n === numero) { champ.setCustomValidity(''); etat.hidden = true; }
                        });
                }

                champ.addEventListener('input', function () {
                    /* Le verdict precedent ne vaut plus pour la nouvelle saisie. */
                    if (champ.value.trim() !== verifiee) { champ.setCustomValidity(''); }
                    clearTimeout(minuterie);
                    minuterie = setTimeout(verifier, 700);
                });
                champ.addEventListener('change', function () { verifier(true); });
                if (champ.value.trim() !== '') { verifier(true); }
            })();
            </script>

            <script>
            /* Parcours dependant de la mention.
               La liste est ecrite ici plutot que chargee par requete : sept
               mentions et quatorze parcours tiennent en quelques lignes, et le
               champ reste utilisable meme si le reseau est mauvais — ce qui
               est frequent chez les candidats. Les libelles suivent ceux de la
               page Formations ; toute modification doit se faire des deux
               cotes. Le serveur revalide de toute facon ce couple.  */
            (function () {
                /* Liste issue de `filiere_parcours` (voir admissions.php), donc
                   identique a celle du formulaire d'inscription du personnel.
                   C'est ce qui permet a la candidature convertie de retrouver
                   son parcours a l'identique. */
<?php
                /* Le fragment se procure la liste lui-meme : il est inclus aussi bien
                   par admissions.php (page autonome) que par vitrine.php (page unique),
                   et seule la premiere la construisait. Sur la page d'accueil, le script
                   recevait donc un tableau vide et la liste des parcours ne se remplissait
                   jamais.

                   Les parcours sont ranges PAR CYCLE : { cle: { L: [...], M: [...] } }.
                   Ils etaient servis cycles confondus, si bien qu'un candidat au Master
                   se voyait proposer des parcours de licence. Le cycle se regle dans
                   /settings/mentions, parcours par parcours. */
                require_once __DIR__ . '/../../data/parcours.php';
                ?>
                var PARCOURS = <?php echo json_encode(parcoursParMentionEtCycle(), JSON_UNESCAPED_UNICODE); ?>;

                var mention  = document.getElementById('mention');
                var parcours = document.getElementById('parcours');
                if (!mention || !parcours) { return; }

                /* ---------------------------------------------------------
                   Le diplôme décide de ce qui est proposé

                   Le formulaire offrait toutes les mentions quel que soit le
                   diplôme visé. Un candidat pouvait donc demander un Master de
                   Théologie ou de Droit, qui n'existent pas : la candidature
                   partait et c'est le registrariat qui découvrait l'erreur.

                   La règle ne s'écrit plus nulle part : une mention prépare un
                   diplôme si l'un de ses parcours porte le cycle correspondant,
                   réglé dans /settings/mentions. PARCOURS suffit donc à tout
                   dire, et le serveur valide sur la même donnée.
                   --------------------------------------------------------- */
                var diplome = document.getElementById('diplome_vise');

                /** Le cycle visé : M pour un Master, L sinon. */
                function cycle() {
                    return diplome && diplome.value === 'master' ? 'M' : 'L';
                }

                /** Les parcours d'une mention pour le cycle visé. */
                function liste(cle, quelCycle) {
                    var m = PARCOURS[cle];
                    if (!m) { return []; }
                    return m[quelCycle || cycle()] || [];
                }

                /* On DÉSACTIVE les mentions qui ne préparent pas le diplôme au
                   lieu de les retirer : une option qui disparaît laisse croire à
                   un bug, tandis qu'une option grisée et suffixée dit ce qu'elle
                   est. La liste ne change pas de longueur, donc rien ne saute
                   sous le curseur. */
                function appliquerDiplome() {
                    if (!diplome) { return; }
                    var vise = cycle();

                    Array.prototype.forEach.call(mention.options, function (o) {
                        if (o.value === '') { return; }

                        // Le libellé d'origine est mémorisé au premier passage.
                        if (!o.dataset.libelle) { o.dataset.libelle = o.textContent.trim(); }

                        var permise = liste(o.value, vise).length > 0;
                        o.disabled = !permise;

                        /* Le suffixe dit ce que la mention prépare vraiment,
                           plutôt que l'inverse de ce qui est demandé : « Licence
                           seulement » sur une mention qui n'a aucun parcours
                           serait faux. */
                        var suffixe = '';
                        if (!permise) {
                            if (liste(o.value, 'L').length > 0) { suffixe = ' — Licence seulement'; }
                            else if (liste(o.value, 'M').length > 0) { suffixe = ' — Master seulement'; }
                            else { suffixe = ' — aucun parcours ouvert'; }
                        }
                        o.textContent = o.dataset.libelle + suffixe;
                    });

                    /* La mention déjà choisie ne convient plus : on la vide, et
                       on le dit. La laisser sélectionnée alors qu'elle est
                       désactivée enverrait au serveur une valeur qu'il refuse,
                       sans que le visiteur comprenne laquelle corriger. */
                    if (mention.value !== '' && liste(mention.value, vise).length === 0) {
                        var perdue = mention.options[mention.selectedIndex].dataset.libelle || '';
                        var nom = vise === 'M' ? 'Master' : 'Licence';
                        mention.value = '';
                        synchroniser('');
                        if (window.Toast && typeof Toast.warning === 'function') {
                            Toast.warning(perdue + ' ne propose pas de ' + nom + '. Choisissez une autre mention.',
                                          { duration: 8000 });
                        }
                    } else {
                        // Le cycle a pu changer sans que la mention bouge.
                        synchroniser(parcours.value);
                    }
                }

                function remplir(garder) {
                    var choix = liste(mention.value);
                    parcours.innerHTML = '';

                    if (!choix.length) {
                        parcours.appendChild(new Option(
                            mention.value === ''
                                ? 'Choisissez d\'abord une mention'
                                : 'Aucun parcours pour ce diplôme', ''));
                        parcours.disabled = true;
                        return;
                    }

                    parcours.appendChild(new Option('Sélectionnez un parcours', ''));
                    choix.forEach(function (nom) {
                        parcours.appendChild(new Option(nom, nom));
                    });
                    parcours.disabled = false;

                    /* Apres une erreur de validation, on restitue le choix — mais
                       seulement s'il appartient encore au cycle affiche. */
                    if (garder && choix.indexOf(garder) !== -1) { parcours.value = garder; }
                }

                var dernierEtat = null;

                /* Synchronise la liste avec ce qui est affiche, quelle que soit
                   la facon dont mention et diplome ont ete choisis. On ne
                   reconstruit que si l'un des deux a reellement change, pour ne
                   pas effacer le parcours deja selectionne par le visiteur a
                   chaque appel. */
                function synchroniser(garder) {
                    var etat = mention.value + '|' + cycle();
                    if (etat === dernierEtat) { return; }
                    dernierEtat = etat;
                    remplir(garder);
                }

                /* « change » ne suffit pas : le remplissage automatique du
                   navigateur (Chrome, gestionnaires de mots de passe) renseigne
                   la mention APRES le chargement, sans toujours emettre
                   l'evenement. La liste restait alors bloquee sur « Choisissez
                   d'abord une mention » alors qu'une mention etait bien
                   affichee. On surveille donc aussi « input », le retour par
                   l'historique, et on repasse un coup apres le chargement
                   complet, moment ou l'auto-remplissage a eu lieu. */
                mention.addEventListener('change', function () { synchroniser(''); });
                mention.addEventListener('input',  function () { synchroniser(''); });

                var choixInitial = parcours.getAttribute('data-choisi') || '';
                synchroniser(choixInitial);

                /* Le diplôme suit les mêmes précautions que la mention : le
                   remplissage automatique du navigateur le renseigne parfois
                   après le chargement, sans émettre « change ». */
                if (diplome) {
                    diplome.addEventListener('change', appliquerDiplome);
                    diplome.addEventListener('input', appliquerDiplome);
                }
                appliquerDiplome();

                window.addEventListener('load', function () { synchroniser(choixInitial); appliquerDiplome(); });
                window.addEventListener('pageshow', function () { synchroniser(choixInitial); appliquerDiplome(); });

                // Filet pour les remplissages tardifs, qui surviennent apres
                // « load » sur certains navigateurs.
                setTimeout(function () { synchroniser(choixInitial); appliquerDiplome(); }, 400);
            })();
            </script>
        </div>
    </section>
<?php /* Les messages du formulaire (manques, envoi, erreurs) sont des toasts :
         le site public ne charge pas init/footer.php, qui les apporte au
         back-office. include_once : la vitrine en page unique inclut aussi ce
         contenu. */ include_once __DIR__ . '/../../init/toast.php'; ?>
