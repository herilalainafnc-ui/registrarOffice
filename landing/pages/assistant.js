/* =============================================================================
   Assistant de la vitrine
   =============================================================================
   Pas d'intelligence artificielle : un jeu de reponses preconfigurees,
   choisies par un score de mots-cles tolerant aux fautes de frappe et aux
   variantes de conjugaison. Le panneau le dit des l'ouverture.

   Reconnaissance : chaque question porte des radicaux courts (« inscri »
   plutot que « inscription ») qui couvrent d'un coup toute une famille —
   inscrire, inscription, inscrit, s'inscrire. Un radical mal orthographie
   par l'utilisateur est encore reconnu par une distance d'edition tolerante,
   qui absorbe une lettre en trop, en moins, ou deux lettres inversees.

   Charge par footer.php : present sur la page unique comme sur les cinq
   pages autonomes.
   ========================================================================== */

(function () {
    'use strict';

    /* =====================================================================
       1. Base de connaissance

       Chaque entree porte un identifiant, des radicaux de reconnaissance,
       un libelle court pour la puce de suggestion, et la reponse. Les liens
       de la reponse pointent vers les rubriques reelles du site.
       ===================================================================== */

    var B = window.APP_BASE || '';

    var SAVOIR = [
        {
            id: 'admission-etapes',
            radicaux: ['etape', 'inscri', 'demarche', 'candidat', 'proced', 'rejoindre', 'admission'],
            puce: "Comment s'inscrire ?",
            reponse:
                "Cinq étapes mènent à l'inscription à l'UAZ :\n" +
                "1. Pré-inscription en ligne (facultative)\n" +
                "2. Test d'admission, le 13 octobre 2026 sur le campus\n" +
                "3. Résultats communiqués par le registrariat\n" +
                "4. Dépôt du dossier (pièces justificatives)\n" +
                "5. Inscription définitive et paiement des frais\n" +
                'Le détail est ici : <a href="' + B + '/#pre-inscription">se pré-inscrire</a>.'
        },
        {
            id: 'preinscription',
            radicaux: ['prein', 'pre-in', 'facultat', 'avance', 'reserv'],
            puce: 'La pré-inscription, à quoi ça sert ?',
            reponse:
                "La pré-inscription est facultative. Elle permet de déposer vos informations à l'avance : " +
                "elles seront reprises le jour de l'inscription, sans qu'il faille les ressaisir. " +
                "Elle ne remplace pas le test d'admission.\n" +
                'Formulaire ici : <a href="' + B + '/#pre-inscription">se pré-inscrire</a>.'
        },
        {
            id: 'test-admission',
            radicaux: ['test', 'examen', 'concour', 'epreuve', 'evalu', 'positionnement', 'admission'],
            puce: "Le test d'admission",
            reponse:
                "Le test d'admission a lieu le 13 octobre 2026, sur le campus de l'UAZ à Antsirabe. " +
                'Il conditionne l\'accès à l\'inscription. Détails sur <a href="' + B + '/#admissions">la page Admissions</a>.'
        },
        {
            id: 'rentree',
            radicaux: ['rentr', 'debut', 'commenc', 'date', 'annee academ'],
            puce: 'La rentrée, c\'est quand ?',
            reponse:
                "La rentrée de l'année académique 2026-2027 a lieu le 13 octobre 2026, " +
                "le même jour que le test d'admission."
        },
        {
            id: 'frais',
            radicaux: ['frai', 'prix', 'cout', 'tarif', 'payer', 'paiement', 'combien coute', 'bours', 'remboursement', 'echelonn'],
            puce: 'Combien coûtent les études ?',
            reponse:
                "Les frais d'inscription et les frais de scolarité annuels varient selon la filière ; " +
                "aucun montant n'est fixe pour toutes les mentions. Un paiement échelonné est possible, " +
                "et des remboursements existent pour les étudiants méritants.\n" +
                'Pour un montant précis, contactez le registrariat : <a href="' + B + '/#contact">nous contacter</a>.'
        },
        {
            id: 'documents',
            radicaux: ['document', 'piece', 'dossier', 'justifica', 'fournir', 'apporter'],
            puce: "Quels documents fournir ?",
            reponse:
                "Le dossier d'inscription comprend : le certificat de fin d'études secondaires " +
                "(BACC ou équivalent), les relevés de notes des trois dernières années, une copie de " +
                "la carte d'identité nationale, quatre photos d'identité récentes, un certificat médical " +
                "et une lettre de recommandation."
        },
        {
            id: 'theologie',
            radicaux: ['theolog', 'pasteur', 'biblique', 'pastoral'],
            puce: 'La mention Théologie',
            reponse:
                "La mention Théologie propose deux parcours : Théologie biblique et Théologie pastorale. " +
                "La théologie pastorale exige, au-delà des critères généraux, un baptême d'au moins trois ans " +
                "et des lettres de recommandation du pasteur de district et de la Fédération d'origine."
        },
        {
            id: 'gestion',
            radicaux: ['gestion', 'comptab', 'management', 'finance', 'business'],
            puce: 'La mention Gestion',
            reponse: "La mention Gestion propose deux parcours : Management des affaires, et Finance et comptabilité."
        },
        {
            id: 'informatique',
            radicaux: ['informatique', 'reseau', 'logiciel', 'developpeur', 'programmation', 'genie logiciel'],
            puce: 'La mention Informatique',
            reponse:
                "La mention Informatique propose deux parcours : Système d'information et génie logiciel, " +
                "et Maintenance et administration de réseau."
        },
        {
            id: 'infirmier',
            radicaux: ['infirm', 'sante', 'soin', 'maieutiq', 'sage-femme', 'medic'],
            puce: 'La mention Sciences Infirmières',
            reponse: "La mention Sciences Infirmières propose deux parcours : Infirmerie générale et Maïeutique (sage-femme). L'admission en première année se fait par concours."
        },
        {
            id: 'droit',
            radicaux: ['droit', 'juridi', 'juriste', 'avocat', 'magistrat'],
            puce: 'La mention Droit',
            reponse: "La mention Droit propose deux parcours : droit privé et droit public."
        },
        {
            id: 'communication',
            radicaux: ['communication', 'journalis', 'media', 'audiovisuel', 'relations publiq'],
            puce: 'La mention Communication',
            reponse: "La mention Communication propose deux parcours : communication d'entreprise, et médias et journalisme."
        },
        {
            id: 'anglophones',
            radicaux: ['anglophon', 'anglais', 'traduc', 'interpret', 'langue et litt'],
            puce: 'Études Anglophones',
            reponse: "La mention Études Anglophones propose deux parcours : traduction et interprétation, et langue et littérature."
        },

        /* Les deux entrees generales viennent APRES les mentions
           nommees. « la mention gestion » touche « mention » et
           « gestion » avec le meme poids : a egalite, c'est l'ordre qui
           tranche, et la reponse precise vaut mieux que la liste des
           sept. Une question qui ne cite aucune mention ne touche que
           l'entree generale, et la trouve quand meme. */
        {
            id: 'mentions',
            radicaux: ['mention', 'filiere', 'discipline', 'matiere', 'specialit', 'domaine'],
            puce: 'Quelles mentions ?',
            reponse:
                "L'UAZ compte sept mentions, réparties en quatre domaines : Théologie, Communication et " +
                "Études Anglophones (Arts et Lettres), Gestion et Droit (Sciences Sociales), " +
                "Sciences Infirmières (Santé), Informatique (Ingénierie).\n" +
                'Détails ici : <a href="' + B + '/#formations">nos formations</a>.'
        },
        {
            id: 'parcours',
            radicaux: ['parcour', 'programme', 'option'],
            puce: 'Un parcours, c\'est quoi ?',
            reponse:
                "Un parcours est la spécialisation choisie à l'intérieur d'une mention : par exemple, " +
                "en Droit, parcours droit privé ou droit public. L'UAZ compte quatorze parcours de Licence " +
                "au total, deux par mention en général.\n" +
                'La liste complète : <a href="' + B + '/#formations">nos formations</a>.'
        },
        {
            id: 'contact',
            radicaux: ['contact', 'telephon', 'appel', 'joindre', 'mail', 'email', 'adress', 'ou etes', 'localis'],
            puce: 'Comment vous contacter ?',
            reponse:
                "Adresse : BP 325, Antsirabe 110, MADAGASCAR.\n" +
                "Téléphone : +261 34 38 180 81 ou +261 34 76 076 98.\n" +
                "Email : registrar@zurcher.edu.mg.\n" +
                'Formulaire : <a href="' + B + '/#contact">nous écrire</a>.'
        },
        {
            id: 'horaires',
            radicaux: ['horaire', 'ouvert', 'ferme', 'sabbat', 'heure'],
            puce: 'Vos horaires',
            reponse:
                "Le registrariat reçoit du lundi au jeudi de 8h00 à 12h00 puis de 13h30 à 17h30, " +
                "et le vendredi de 8h00 à 12h00. Fermé le Sabbat et le dimanche."
        },
        {
            id: 'campus',
            radicaux: ['campus', 'situe', 'ou est', 'plan', 'acces', 'route', 'antsirabe', 'antananarivo'],
            puce: 'Où se trouve le campus ?',
            reponse:
                "Le campus est à Vohitsoa, commune d'Antsampanimahazo, à environ 138 km d'Antananarivo, " +
                'sur les hauts plateaux. Photos et plan sur <a href="' + B + '/#campus">la page Campus</a>.'
        },
        {
            id: 'connexion',
            radicaux: ['connexion', 'login', 'espace etudiant', 'mot de passe', 'identifiant', 'compte'],
            puce: 'Espace étudiant',
            reponse:
                "L'espace de connexion est réservé aux étudiants et au personnel déjà inscrits dans le " +
                'système. Accès ici : <a href="' + B + '/login">se connecter</a>. ' +
                "Si vous candidatez pour la première fois, commencez plutôt par la pré-inscription."
        },
        {
            id: 'salutation',
            radicaux: ['bonjour', 'bonsoir', 'salut', 'hello', 'coucou', 'hey'],
            puce: 'Bonjour',
            reponse:
                "Bonjour ! Je suis l'assistant du registrariat. Posez votre question, ou choisissez un sujet " +
                'ci-dessous.'
        },
        {
            id: 'remerciement',
            radicaux: ['merci', 'sympa', 'genial', 'top', 'parfait'],
            puce: null,
            reponse: "Avec plaisir. Autre chose à demander ?"
        },
        {
            id: 'humain',
            radicaux: ['humain', 'personne reelle', 'vrai personne', 'parler a quelqu'],
            puce: 'Parler à quelqu\'un',
            reponse:
                'Pour échanger avec le registrariat directement : <a href="' + B + '/#contact">formulaire de contact</a>, ' +
                "ou par téléphone au +261 34 38 180 81, du lundi au vendredi."
        },

        /* -----------------------------------------------------------------
           Presentation generale et vie sur le campus
           ----------------------------------------------------------------- */

        {
            id: 'presentation',
            radicaux: ['uaz', 'zurcher', 'presentation', 'presentez', 'parlez', 'etablissement', 'adventiste'],
            puce: "L'UAZ, en bref",
            reponse:
                "L'Université Adventiste Zurcher est un établissement d'enseignement supérieur privé, " +
                "situé à Antsirabe, sur les hauts plateaux de Madagascar. Elle propose sept mentions " +
                "réparties en quatre domaines, toutes habilitées par le MESupRES, et forme ses étudiants " +
                "dans une éducation ancrée dans les valeurs adventistes.\n" +
                'Les formations : <a href="' + B + '/#formations">voir les mentions</a>.'
        },
        {
            id: 'logement',
            radicaux: ['logement', 'loger', 'dortoir', 'chambre', 'residence', 'heberg', 'internat'],
            puce: 'Peut-on loger sur place ?',
            reponse:
                "Oui. Le campus dispose de résidences universitaires : des dortoirs séparés pour garçons " +
                "et filles, des chambres confortables, des sanitaires modernes et des espaces communs.\n" +
                'Voir le campus : <a href="' + B + '/#campus">la vie sur le campus</a>.'
        },
        {
            id: 'restauration',
            radicaux: ['cafeteria', 'manger', 'repas', 'restaurant', 'refectoire', 'cantine', 'nourriture', 'vegetarien'],
            puce: null,
            reponse:
                "Le campus dispose d'un réfectoire proposant des repas équilibrés et végétariens, " +
                "conformes aux principes de santé adventistes."
        },
        {
            id: 'spirituel',
            radicaux: ['spirituel', 'culte', 'eglise', 'priere', 'religion', 'foi', 'chapelle', 'biblique'],
            puce: null,
            reponse:
                "La vie spirituelle fait partie du quotidien : cultes quotidiens, groupes d'étude biblique " +
                "et programmes spéciaux du sabbat. Une chapelle se trouve au cœur du campus."
        },

        /* -----------------------------------------------------------------
           Echanges de politesse

           Un visiteur qui ecrit « ca va ? » ou « merci » n'attend pas une
           fiche d'information. Sans ces entrees, il recevait le message
           d'echec, ce qui donnait l'impression d'un assistant en panne alors
           qu'il n'avait simplement rien a dire sur ce registre.
           ----------------------------------------------------------------- */

        {
            id: 'ca-va',
            radicaux: ['ca va', 'comment ca va', 'vas tu', 'allez vous', 'ca roule', 'quoi de neuf'],
            puce: null,
            reponse:
                "Tout va bien, merci — je suis un programme, alors je ne me plains jamais. " +
                "Et vous, que puis-je vous apprendre sur l'UAZ ?"
        },
        {
            id: 'compliment',
            radicaux: ['aime', 'adore', 'beau', 'belle', 'magnifique', 'super', 'cool', 'bravo', 'excellent'],
            puce: null,
            reponse:
                "C'est gentil ! Je transmets au registrariat. " +
                "Si vous avez une question sur l'université, je suis là pour ça."
        },
        {
            id: 'identite',
            radicaux: ['qui es tu', 'tu es qui', 'ton nom', 'robot', 'machine', 'intelligence artificielle', 'chatbot'],
            puce: null,
            reponse:
                "Je suis l'assistant de la vitrine de l'UAZ : un programme simple, avec une liste de " +
                "réponses préparées à l'avance par le registrariat. Aucune intelligence artificielle " +
                "derrière — si je ne trouve pas, je vous oriente vers une personne."
        },
        {
            id: 'capacites',
            radicaux: ['aide', 'aider', 'sujets', 'peux tu', 'sais tu', 'sers a quoi', 'questions possibles'],
            puce: 'Que savez-vous faire ?',
            reponse:
                "Je réponds sur l'inscription et ses étapes, le test d'admission, la rentrée, les frais, " +
                "les mentions et parcours, le campus, le logement, les horaires et les coordonnées. " +
                "Posez votre question, ou choisissez un sujet."
        },
        {
            id: 'au-revoir',
            radicaux: ['au revoir', 'aurevoir', 'bye', 'ciao', 'bientot', 'bonne journee', 'bonne soiree'],
            puce: null,
            reponse: "Au revoir, et bonne continuation dans vos démarches !"
        }
    ];

    var MENU_PRINCIPAL = ['admission-etapes', 'frais', 'mentions', 'campus', 'contact'];

    /* =====================================================================
       2. Normalisation et reconnaissance approximative

       Les accents et la ponctuation sont retires avant comparaison, pour
       qu'« école » et « ecole » se vaillent. La distance de Levenshtein
       mesure l'ecart entre deux mots ; elle absorbe une faute isolee sans
       laisser passer un mot sans rapport.
       ===================================================================== */

    var TABLE_ACCENTS = {
        'à': 'a', 'â': 'a', 'ä': 'a',
        'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e',
        'î': 'i', 'ï': 'i',
        'ô': 'o', 'ö': 'o',
        'ù': 'u', 'û': 'u', 'ü': 'u',
        'ç': 'c',
        'œ': 'oe',
        'æ': 'ae'
    };

    // Table de correspondance plutot qu une plage Unicode : plus facile a
    // relire, et sans risque qu un editeur re-encode mal des caracteres
    // combinants ecrits en toutes lettres.
    function retirerAccents(texte) {
        var s = '';
        for (var i = 0; i < texte.length; i++) {
            var c = texte[i];
            s += TABLE_ACCENTS[c] || c;
        }
        return s;
    }

    // Mots-outils exclus de la comparaison mot a mot. Sans ce filtre, « de »
    // — deux lettres — se retrouvait prefixe de « demarche »,
    // « developpeur » et « debut » a la fois, et faisait sortir des reponses
    // sans rapport avec la question posee.
    //
    // Les mots interrogatifs en font partie : ils annoncent une question sans
    // dire de quoi elle traite. « comment » se trouvait a une seule lettre de
    // « commenc » (commencer) : « comment est l'UAZ ? » repondait la date de
    // la rentree. Ils restent lisibles dans la phrase complete, ou les
    // radicaux a plusieurs mots (« comment ca va ») les retrouvent.
    var MOTS_OUTILS = {
        de: 1, du: 1, des: 1, un: 1, une: 1, le: 1, la: 1, les: 1, l: 1, d: 1,
        et: 1, ou: 1, a: 1, en: 1, que: 1, qui: 1, est: 1, sont: 1, ce: 1, cette: 1,
        je: 1, tu: 1, il: 1, elle: 1, on: 1, nous: 1, vous: 1, ils: 1, elles: 1,
        mon: 1, ma: 1, mes: 1, ton: 1, ta: 1, tes: 1, son: 1, sa: 1, ses: 1,
        votre: 1, vos: 1, notre: 1, nos: 1, leur: 1, leurs: 1,
        pour: 1, avec: 1, sur: 1, dans: 1, par: 1, si: 1, ne: 1, pas: 1, se: 1,
        comment: 1, quand: 1, pourquoi: 1, combien: 1, quoi: 1, quel: 1,
        quelle: 1, quels: 1, quelles: 1, faire: 1, etre: 1, avoir: 1
    };

    // Normalise sans filtrer : les radicaux a plusieurs mots (« mot de
    // passe », « ou est ») contiennent eux-memes des mots-outils, et les
    // retirer casserait la phrase qu'ils cherchent a reconnaitre. Le filtre
    // s'applique plus loin, seulement pour la comparaison mot a mot.
    function normaliser(texte) {
        return retirerAccents((texte || '').toLowerCase())
            .replace(/[^a-z0-9\s]/g, ' ')
            .split(/\s+/)
            .filter(Boolean);
    }

    // Distance de Damerau-Levenshtein : comme Levenshtein, mais compte la
    // transposition de deux lettres voisines pour une seule erreur plutot
    // que deux. Sans elle, « incsri » (s et c invertis) se trouvait a
    // distance 2 d'« inscri » et passait sous le seuil de tolerance.
    function distance(a, b) {
        if (a === b) { return 0; }
        var m = a.length, n = b.length;
        if (!m) { return n; }
        if (!n) { return m; }
        var d = [];
        for (var i = 0; i <= m; i++) { d[i] = [i]; }
        for (var j = 0; j <= n; j++) { d[0][j] = j; }
        for (i = 1; i <= m; i++) {
            for (j = 1; j <= n; j++) {
                var cout = a[i - 1] === b[j - 1] ? 0 : 1;
                d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cout);
                if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                    d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
                }
            }
        }
        return d[m][n];
    }

    // Tolerance proportionnelle a la longueur : un mot court supporte moins
    // d'erreurs qu'un mot long, sinon deux mots courts distincts finissent
    // par se confondre. Calee sur des cas reels : « informatik » a distance
    // 3 d'« informatique », « comen » a distance 2 de « comment ».
    function toleranceDe(mot) {
        if (mot.length <= 3) { return 0; }
        if (mot.length <= 6) { return 1; }
        if (mot.length <= 9) { return 2; }
        return 3;
    }

    // Le poids d'une correspondance depend de sa certitude, pas seulement de
    // la longueur du radical. Un mot ecrit a l'identique — « prix », quatre
    // lettres — est une preuve aussi solide qu'un radical deux fois plus
    // long : plus solide, meme, qu'un rapprochement approximatif sur un mot
    // long. Une correspondance approximative sur un radical trop court
    // (trois lettres ou moins) ne compte pas : la coincidence y est trop
    // facile.
    function poidsDirect(motSaisi, radical) {
        // Un mot ecrit a l'identique vaut plein poids meme s'il est court :
        // « UAZ » ne fait que trois lettres mais ne se rencontre pas par
        // hasard. La longueur ne sert qu'a juger les rapprochements
        // approximatifs, ou elle mesure le risque de coincidence.
        if (motSaisi === radical) { return radical.length >= 3 ? 2 : 1; }
        if (radical.length <= 3) { return 0; }
        if (motSaisi.indexOf(radical) === 0) { return radical.length >= 5 ? 2 : 1; }
        // Sens inverse — le radical commence par le mot saisi, pour une
        // saisie tronquee — restreint aux mots d'au moins quatre lettres :
        // en dessous, trop de mots-outils redevenus courants (« sur », « les »
        // hors liste, sigles...) se retrouvent prefixes d'un radical par pur
        // hasard.
        if (motSaisi.length >= 4 && radical.indexOf(motSaisi) === 0) { return radical.length >= 5 ? 2 : 1; }
        if (distance(motSaisi, radical) <= toleranceDe(radical)) { return radical.length >= 5 ? 2 : 1; }
        return 0;
    }

    // Elisions collees. Une apostrophe tapee devient une espace et separe
    // proprement les mots, mais beaucoup ecrivent vite et l'omettent :
    // « luaz » pour « l'UAZ », « taime » pour « t'aime ». On reessaie donc
    // le mot ampute de sa lettre d'elision, jusqu'a deux fois pour les
    // enchainements du type « jtaime ».
    var LETTRES_ELISION = { l: 1, d: 1, j: 1, t: 1, m: 1, s: 1, n: 1, c: 1 };

    function poidsDeLaCorrespondance(motSaisi, radical) {
        // On garde le meilleur des deux lectures, sans s'arreter a la
        // premiere : « taime » ressemble vaguement a « aime » (une lettre
        // d'ecart, poids faible), mais ampute de son elision il lui est
        // identique (poids fort). S'arreter au premier resultat non nul
        // retenait la lecture la plus faible et laissait la phrase sous le
        // seuil de reponse.
        var poids = poidsDirect(motSaisi, radical);

        var forme = motSaisi;
        for (var i = 0; i < 2 && poids < 2; i++) {
            if (forme.length < 4 || !LETTRES_ELISION[forme.charAt(0)]) { break; }
            forme = forme.slice(1);
            // Sur une forme deduite, on n'accepte que l'egalite ou le prefixe :
            // ajouter l'a-peu-pres par-dessus une lettre retiree ferait deux
            // suppositions empilees, et n'importe quel mot finirait par
            // ressembler a n'importe quel radical.
            if (forme === radical) {
                poids = Math.max(poids, radical.length >= 3 ? 2 : 1);
            } else if (radical.length > 3 && forme.indexOf(radical) === 0) {
                poids = Math.max(poids, radical.length >= 5 ? 2 : 1);
            }
        }
        return poids;
    }

    function meilleureReponse(saisie) {
        var motsBruts = normaliser(saisie);
        if (!motsBruts.length) { return null; }
        // La phrase complete sert aux radicaux a plusieurs mots ; la liste
        // filtree sert a la comparaison mot a mot, ou les mots-outils ne
        // font que provoquer de faux rapprochements.
        var phrase = ' ' + motsBruts.join(' ') + ' ';
        var mots = motsBruts.filter(function (mot) { return !MOTS_OUTILS[mot]; });
        if (!mots.length) { mots = motsBruts; }   // une saisie faite uniquement de mots-outils reste comparable

        var meilleure = null, meilleurScore = 0, meilleurNbTouches = 0;

        SAVOIR.forEach(function (entree) {
            var radicauxTouches = {};
            entree.radicaux.forEach(function (radical) {
                // Un radical a plusieurs mots (« mot de passe », « genie
                // logiciel ») est une expression toute faite : on la cherche
                // telle quelle dans la phrase, sans tolerance aux fautes —
                // une expression entiere se reconnait rarement par accident,
                // ce qui vaut un poids fort.
                if (radical.indexOf(' ') > 0) {
                    if (phrase.indexOf(radical) >= 0) {
                        radicauxTouches[radical] = 3;
                    }
                    return;
                }
                var meilleurPoids = 0;
                for (var i = 0; i < mots.length; i++) {
                    var poids = poidsDeLaCorrespondance(mots[i], radical);
                    if (poids > meilleurPoids) { meilleurPoids = poids; }
                }
                if (meilleurPoids > 0) { radicauxTouches[radical] = meilleurPoids; }
            });
            var score = 0, nbTouches = 0;
            for (var k in radicauxTouches) { score += radicauxTouches[k]; nbTouches++; }
            // A egalite de score, l'entree appuyee sur davantage de radicaux
            // distincts l'emporte : deux indices convergents valent mieux
            // qu'un seul mot compte double.
            if (score > meilleurScore || (score === meilleurScore && score > 0 && nbTouches > meilleurNbTouches)) {
                meilleurScore = score; meilleure = entree; meilleurNbTouches = nbTouches;
            }
        });

        // En dessous de ce seuil, la coincidence est trop mince pour qu'on
        // affirme avoir compris la question.
        return meilleurScore >= 2 ? meilleure : null;
    }

    /* =====================================================================
       3. Interface
       ===================================================================== */

    function init() {
        var bouton = document.getElementById('aide-bouton');
        var panneau = document.getElementById('aide-panneau');
        if (!bouton || !panneau) { return; }

        var fil = panneau.querySelector('.aide-fil');
        var champ = panneau.querySelector('.aide-saisie textarea');
        var envoyer = panneau.querySelector('.aide-envoyer');
        var fermer = panneau.querySelector('.aide-fermer');

        var dejaOuvert = false;

        function defiler() { fil.scrollTop = fil.scrollHeight; }

        function ajouterMessage(texte, cote) {
            var m = document.createElement('div');
            m.className = 'aide-msg aide-msg--' + cote;
            m.innerHTML = texte;   // le texte des reponses est ecrit dans ce fichier, pas saisi par un tiers
            fil.appendChild(m);
            defiler();
            return m;
        }

        function ajouterMessageTexte(texte, cote) {
            var m = document.createElement('div');
            m.className = 'aide-msg aide-msg--' + cote;
            m.textContent = texte;   // la saisie du visiteur ne doit jamais etre interpretee comme du HTML
            fil.appendChild(m);
            defiler();
            return m;
        }

        function ajouterPuces(ids) {
            var zone = document.createElement('div');
            zone.className = 'aide-puces';
            ids.forEach(function (id) {
                var entree = SAVOIR.filter(function (e) { return e.id === id; })[0];
                if (!entree || !entree.puce) { return; }
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'aide-puce';
                b.textContent = entree.puce;
                b.addEventListener('click', function () { poserQuestion(entree.puce, entree); });
                zone.appendChild(b);
            });
            fil.appendChild(zone);
            defiler();
        }

        function repondreApres(delaiMs, callback) {
            var attente = document.createElement('div');
            attente.className = 'aide-attente';
            attente.innerHTML = '<span></span><span></span><span></span>';
            fil.appendChild(attente);
            defiler();
            setTimeout(function () {
                attente.remove();
                callback();
            }, delaiMs);
        }

        function autresSuggestions(idExclu) {
            return MENU_PRINCIPAL.filter(function (id) { return id !== idExclu; }).slice(0, 4);
        }

        function poserQuestion(texteAffiche, entreeConnue) {
            ajouterMessageTexte(texteAffiche, 'moi');
            champ.value = '';
            champ.style.height = 'auto';

            repondreApres(450 + Math.random() * 350, function () {
                var entree = entreeConnue || meilleureReponse(texteAffiche);
                if (entree) {
                    ajouterMessage(entree.reponse, 'bot');
                    ajouterPuces(autresSuggestions(entree.id));
                } else {
                    ajouterMessage(
                        "Je n'ai pas de réponse toute prête pour cette question. " +
                        'Essayez en la reformulant, ou choisissez un sujet ci-dessous — pour le reste, ' +
                        '<a href="' + B + '/#contact">le registrariat</a> répond directement.',
                        'bot'
                    );
                    ajouterPuces(MENU_PRINCIPAL);
                }
            });
        }

        function envoyerSaisie() {
            var texte = champ.value.trim();
            if (!texte) { return; }
            poserQuestion(texte, null);
        }

        function ouvrir() {
            panneau.classList.add('est-ouvert');
            panneau.setAttribute('aria-hidden', 'false');
            bouton.setAttribute('aria-expanded', 'true');
            bouton.classList.add('est-vue');
            if (!dejaOuvert) {
                dejaOuvert = true;
                ajouterMessage(
                    "Bonjour ! Je suis l'assistant du registrariat de l'UAZ. Je ne suis pas une " +
                    'intelligence artificielle : mes réponses viennent d\'une liste préparée à l\'avance, ' +
                    'sur les sujets les plus demandés. Posez votre question, ou choisissez un sujet.',
                    'bot'
                );
                ajouterPuces(MENU_PRINCIPAL);
            }
            setTimeout(function () { champ.focus(); }, 200);
        }

        function fermerPanneau() {
            panneau.classList.remove('est-ouvert');
            panneau.setAttribute('aria-hidden', 'true');
            bouton.setAttribute('aria-expanded', 'false');
            bouton.focus();
        }

        bouton.addEventListener('click', function () {
            if (panneau.classList.contains('est-ouvert')) { fermerPanneau(); } else { ouvrir(); }
        });
        fermer.addEventListener('click', fermerPanneau);

        // Echap referme, comme toute fenetre superposee.
        panneau.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { fermerPanneau(); }
        });

        envoyer.addEventListener('click', envoyerSaisie);
        champ.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); envoyerSaisie(); }
        });
        // La zone grandit avec le texte, jusqu'a une limite fixee dans le CSS.
        champ.addEventListener('input', function () {
            champ.style.height = 'auto';
            champ.style.height = champ.scrollHeight + 'px';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
