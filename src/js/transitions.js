/* =========================================================================
   Transitions de page + micro-animations de contenu.

   Portage du systeme du projet ABC, adapte a Registrar.

   L'application reste multi-pages. Ce script masque le rechargement derriere
   un balayage (« stinger ») : on couvre l'ecran, on navigue, et la page
   suivante se decouvre. L'oeil ne voit plus le blanc du rechargement, ce qui
   donne la continuite d'une application monopage.

   REGLE DE SURETE : la navigation ne doit JAMAIS dependre de la reussite
   d'une animation. Un minuteur de secours declenche le changement de page
   meme si l'evenement de fin d'animation ne vient pas.

   A inclure en DEBUT de <body> (via init/topbar.php) : le script a besoin que
   document.body existe pour poser le rideau avant le premier rendu.
   ========================================================================= */
(function initPageTransitions() {
  'use strict';

  var COVER_MS = 380;   // duree de la couverture avant navigation
  var REVEAL_MS = 620;  // duree du devoilement a l'arrivee
  var NAV_FLAG = 'rg_pt_nav'; // marque laissee a la page suivante

  /* Le rideau reste baisse jusqu'a ce que la page d'arrivee soit chargee.
     Quatre bornes encadrent cette attente :
       - un minimum, pour qu'un chargement instantane ne produise pas un
         clignotement illisible ;
       - l'evenement load, qui marque la fin du chargement des ressources ;
       - la page construite (DOMContentLoaded) : on n'attend plus alors les
         images et les polices que APRES_DOM_MS au plus — une photo lente ne
         doit pas retenir un ecran deja pret ;
       - un maximum absolu, parce qu'un script tiers injoignable ne doit
         jamais laisser l'utilisateur devant un ecran couvert. Il etait de
         5 s, compte des le debut du chargement : sur une connexion lente, le
         rideau se levait sur une page encore en construction. */
  var HOLD_MIN_MS = 700;
  var APRES_DOM_MS = 1200;
  var HOLD_MAX_MS = 15000;

  // Chemin de base : defini par init/head.php. Repli sur la racine si absent.
  var BASE = (typeof window.APP_BASE === 'string') ? window.APP_BASE : '';

  function prefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
      || document.documentElement.classList.contains('reduce-motion');
  }

  // ---------------------------------------------------------------------
  // Le rideau
  // ---------------------------------------------------------------------

  /* Sur une connexion lente, l'attente se dit : un texte apres ATTENTE_MS,
     puis apres SORTIE_MS un bouton pour rester sur la page (il annule le
     chargement en cours). */
  var ATTENTE_MS = 2500;
  var SORTIE_MS = 6000;

  var stinger = document.createElement('div');
  stinger.className = 'pt-stinger';
  stinger.setAttribute('aria-hidden', 'true');
  stinger.innerHTML =
    '<div class="pt-band"></div><div class="pt-band"></div><div class="pt-band"></div>' +
    '<div class="pt-mark">' +
      '<img src="' + BASE + '/file/logo-coldbloud.png" alt="" />' +
      '<span class="pt-loader"><span class="pt-loader-inner"></span></span>' +
      '<p class="pt-attente">Chargement de la page…</p>' +
      '<button type="button" class="pt-rester">Rester sur cette page</button>' +
    '</div>';

  function mountStinger() {
    if (document.body && !document.body.contains(stinger)) {
      document.body.appendChild(stinger);
    }
  }

  function couvert() {
    return stinger.classList.contains('is-covering') || stinger.classList.contains('is-covered');
  }

  var minuteursAttente = [];
  function oublierAttente() {
    minuteursAttente.forEach(function (m) { window.clearTimeout(m); });
    minuteursAttente = [];
    stinger.setAttribute('aria-hidden', 'true');
  }

  function reveal() {
    oublierAttente();
    if (prefersReducedMotion()) {
      stinger.className = 'pt-stinger';
      return;
    }

    mountStinger();
    stinger.className = 'pt-stinger is-active is-revealing';

    window.setTimeout(function () {
      stinger.className = 'pt-stinger';
    }, REVEAL_MS);
  }

  /**
   * Le depart est engage, la page suivante n'est pas encore la.
   *
   * Le navigateur garde l'ancienne page affichee tant que le serveur n'a pas
   * repondu : sur une connexion lente, plusieurs secondes. Le rideau RESTE
   * donc baisse jusqu'a ce que la page suivante le remplace — le lever plus
   * tot montrait l'ancienne page, comme si le clic n'avait rien fait.
   * L'attente se dit, puis une sortie est offerte (pas a l'arrivee : la page
   * precedente n'existe plus, il n'y a plus ou rester).
   */
  function attendre(avecSortie) {
    oublierAttente();
    minuteursAttente.push(window.setTimeout(function () {
      if (!couvert()) return;
      stinger.classList.add('is-lent');
      stinger.removeAttribute('aria-hidden'); // le texte d'attente est lu
    }, ATTENTE_MS));
    if (avecSortie === false) return;
    minuteursAttente.push(window.setTimeout(function () {
      if (couvert()) stinger.classList.add('is-tres-lent');
    }, SORTIE_MS));
  }

  /* « Rester sur cette page » : annule le chargement en cours (window.stop)
     et leve le rideau sur la page qu'on n'a pas quittee. */
  stinger.querySelector('.pt-rester').addEventListener('click', function () {
    try { window.stop(); } catch (e) { /* navigateur ancien */ }
    try { sessionStorage.removeItem(NAV_FLAG); } catch (e) { /* stockage indisponible */ }
    reveal();
  });

  /**
   * Baisse le rideau et previent la page suivante qu'elle arrivera derriere.
   *
   * Renvoie false quand les animations sont desactivees : l'appelant sait
   * alors qu'aucun rideau ne le couvre et qu'il peut naviguer sans attendre.
   */
  function cover() {
    if (prefersReducedMotion()) {
      return false;
    }

    mountStinger();
    stinger.className = 'pt-stinger is-active is-covering';

    try { sessionStorage.setItem(NAV_FLAG, '1'); } catch (e) { /* stockage indisponible */ }

    return true;
  }

  function coverThen(action) {
    if (!cover()) {
      action();
      return;
    }

    var done = false;
    var go = function () {
      if (done) return;
      done = true;

      /* Une page peut demander « Quitter cette page ? » (beforeunload) : la
         page de gestion des cours offerts en pose un, pour ne pas perdre six
         cents cases cochees. Repondre « Rester » annule le depart, et le
         rideau resterait baisse sur une page qu'on ne quitte plus.

         On l'apprend en ecoutant, en DERNIER, le beforeunload que provoque ce
         depart (le navigateur le declenche pendant l'affectation de
         location.href) : un gestionnaire qui reclame la confirmation l'a
         marque (preventDefault ou returnValue). */
      var confirmation = false;
      var guetteur = function (e) {
        if (e.defaultPrevented || (typeof e.returnValue === 'string' && e.returnValue !== '')) {
          confirmation = true;
        }
      };
      window.addEventListener('beforeunload', guetteur);
      try {
        action();
      } finally {
        window.removeEventListener('beforeunload', guetteur);
      }

      if (confirmation) {
        /* Reponse inconnue. Si la page est toujours la un peu plus tard,
           c'est « Rester » : on leve le rideau. (Avant, ce filet servait a
           TOUS les departs : sur une connexion lente, il levait le rideau sur
           l'ancienne page pendant que la suivante chargeait encore.) */
        window.setTimeout(function () {
          if (couvert()) reveal();
        }, 1500);
        return;
      }

      attendre();
    };

    // Filet de securite : on part de toute facon.
    window.setTimeout(go, COVER_MS);
  }

  /**
   * Points d'entree pour les navigations que ce script ne peut pas intercepter
   * lui-meme, et pour les changements internes a une page.
   *
   *   cover()        baisse le rideau, l'appelant navigue quand il est pret
   *   navigate(url)  baisse le rideau puis navigue
   *   swap(action)   couvre, execute `action` a l'abri, puis devoile.
   *                  Pour les changements SANS rechargement : etapes du module
   *                  inscription, bascule d'onglet, remplacement de zone.
   */
  window.rgPageTransition = {
    cover: cover,
    navigate: function (url) {
      coverThen(function () { window.location.href = url; });
    },
    swap: function (action) {
      if (prefersReducedMotion()) {
        if (action) { action(); }
        return;
      }

      mountStinger();
      stinger.className = 'pt-stinger is-active is-covering';

      // Pas de drapeau de navigation ici : on ne quitte pas la page.
      window.setTimeout(function () {
        stinger.className = 'pt-stinger is-active is-covered';
        try { if (action) { action(); } }
        catch (e) { console.error('Transition interne :', e); }
        window.setTimeout(reveal, 140);
      }, COVER_MS);
    }
  };

  /**
   * Arrivee depuis une navigation interne : on masque l'ecran TOUT DE SUITE,
   * sans attendre DOMContentLoaded. Sinon la page s'afficherait d'abord, et
   * le rideau viendrait la recouvrir avant de repartir -- un clignotement.
   *
   * Le script est inclus en debut de <body> : document.body existe deja.
   */
  var arrivedFromNavigation = false;
  try {
    arrivedFromNavigation = sessionStorage.getItem(NAV_FLAG) === '1';
    sessionStorage.removeItem(NAV_FLAG);
  } catch (e) {
    arrivedFromNavigation = false;
  }

  var coveredSince = 0;

  if (arrivedFromNavigation && !prefersReducedMotion() && document.body) {
    mountStinger();
    stinger.className = 'pt-stinger is-active is-covered';
    coveredSince = Date.now();
    attendre(false); // une page longue a construire dit qu'elle charge

    // Securite absolue : si ni load ni DOMContentLoaded n'arrivaient, l'ecran
    // resterait masque. On leve le rideau quoi qu'il arrive.
    window.setTimeout(function () {
      if (stinger.classList.contains('is-covered')) reveal();
    }, HOLD_MAX_MS);
  }

  /**
   * Leve le rideau quand la page est prete, en respectant la duree minimale.
   * Appelable plusieurs fois : seul le premier appel agit.
   */
  var revealed = false;
  function revealWhenReady() {
    if (revealed) return;
    revealed = true;

    var ecoule = coveredSince ? Date.now() - coveredSince : HOLD_MIN_MS;
    var reste = Math.max(0, HOLD_MIN_MS - ecoule);

    window.setTimeout(reveal, reste);
  }

  // ---------------------------------------------------------------------
  // Interception des liens internes
  // ---------------------------------------------------------------------

  function isPlainLeftClick(event) {
    return event.button === 0
      && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
      && !event.defaultPrevented;
  }

  /** Vrai si le lien doit declencher une transition de page. */
  function isInternalNavigation(link) {
    if (!link || !link.href) return false;
    if (link.hasAttribute('download')) return false;          // telechargement
    if (link.target && link.target !== '_self') return false; // nouvel onglet

    /* Lien pilote en JS par la page elle-meme, qui remplace un fragment au
       lieu de naviguer. Meme convention que pour les formulaires, plus bas :
       data-pt-skip="1".

       Sans cette sortie, ce gestionnaire gagnait la course : il est enregistre
       en debut de <body> par init/topbar.php, donc AVANT le script de la page,
       et son preventDefault suivi de window.location.href partait en
       navigation complete avant que la page n'ait pu intercepter le clic. Les
       onglets de semestre de /finance rechargeaient ainsi toute la page malgre
       leur gestionnaire AJAX.

       Le lien garde son href : sans JavaScript, ou sur un clic avec Ctrl ou
       molette, la navigation classique fonctionne toujours. */
    if (link.dataset && link.dataset.ptSkip === '1') return false;

    var href = link.getAttribute('href') || '';
    if (href === '' || href.charAt(0) === '#') return false;  // ancre interne
    if (/^(mailto|tel|javascript|blob|data):/i.test(href)) return false;

    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return false;
    }

    if (url.origin !== window.location.origin) return false;  // site externe

    /* Specifique a Registrar : ces chemins ne rendent pas une page.
         /app/...            points d'entree AJAX (ecritures, fragments)
         /inscription/app/   idem pour le module inscription
         data.topdf          generation de PDF (releves, bulletins, diplomes)
         ?ptype=             meme generation, appelee par parametre
       Le rideau resterait baisse sur une page qui ne change jamais. */
    if (/\/app\//.test(url.pathname)) return false;
    if (/data\.topdf/.test(url.pathname)) return false;
    if (/[?&]ptype=/.test(url.search)) return false;

    // Fichiers : un export produit un fichier, pas une page.
    if (/\.(csv|xlsx|xls|pdf|zip|png|jpe?g|gif|webp|docx?)$/i.test(url.pathname)) return false;

    /* Telechargements servis par une route ou un script : le navigateur
       enregistre le fichier et la page ne change pas (le Word d'origine du
       bulletin academique, /reglement/telecharger ; les exports, les
       conversions en Excel). Le rideau attendrait une page qui ne vient pas. */
    if (/\/(telecharger|export)(\.php)?$/i.test(url.pathname)) return false;
    if (/\/gen\.[\w-]+(\.php)?$/i.test(url.pathname) || /data\.toxlsx/.test(url.pathname)) return false;

    // Meme page, simple changement d'ancre.
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
      return url.hash === '';
    }

    return true;
  }

  document.addEventListener('click', function (event) {
    if (!isPlainLeftClick(event)) return;

    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!isInternalNavigation(link)) return;

    event.preventDefault();
    var destination = link.href;
    coverThen(function () { window.location.href = destination; });
  });

  /* Leve quand le depart en cours ne doit PAS etre habille. Lu par le
     gestionnaire pagehide, tout en bas : celui-ci marque autrement chaque
     sortie de page, y compris celles qu'on vient d'ecarter ici. */
  var departNu = false;

  // Formulaires qui rechargent la page (soumission classique, sans fetch).
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.dataset.ptSkip === '1') return;
    if (event.defaultPrevented) return; // formulaire pilote en JS : rien a faire

    /* Registrar compte de nombreux formulaires d'impression en
       target="_blank" : la page courante ne bouge pas, le rideau resterait
       baisse sur elle indefiniment. */
    if (form.target && form.target !== '_self') return;

    var method = String(form.method || 'get').toLowerCase();
    if (method !== 'get' && method !== 'post') return;

    if (prefersReducedMotion()) return;

    /* ---------------------------------------------------------------------
       Une ECRITURE ne merite pas de rideau.

       Le stinger existe pour masquer un changement de page. Or un
       enregistrement ramene sur la meme page, au meme endroit : le rideau y
       annoncait un voyage qui n'a pas lieu. Repete a chaque sauvegarde — et
       Registrar en compte beaucoup — l'effet cesse d'etre une transition pour
       devenir une attente imposee de presque une seconde (COVER_MS puis
       HOLD_MIN_MS a l'arrivee).

       On sort donc sans rien couvrir. Et surtout on leve departNu : sinon le
       gestionnaire pagehide reposerait le drapeau au moment ou le navigateur
       quitte la page, et la page d'arrivee jouerait le devoilement toute
       seule. C'etait la vraie cause du rideau vu apres chaque mise a jour :
       il ne venait pas du depart, mais de l'arrivee.

       Les GET gardent l'effet : un filtre ou une recherche produit bien une
       autre page, et le rideau y couvre un vrai temps de chargement.
       --------------------------------------------------------------------- */
    if (method === 'post') {
      departNu = true;
      try { sessionStorage.removeItem(NAV_FLAG); } catch (e) { /* stockage indisponible */ }
      return;
    }

    mountStinger();
    stinger.className = 'pt-stinger is-active is-covering';
    try { sessionStorage.setItem(NAV_FLAG, '1'); } catch (e) { /* stockage indisponible */ }
    attendre(); // la page de resultats peut tarder, comme apres un clic
  });

  // ---------------------------------------------------------------------
  // Arrivee sur la page
  // ---------------------------------------------------------------------

  function onArrive() {
    document.documentElement.classList.add('pt-enter');

    // La classe d'entree ne sert qu'une fois : la laisser ferait rejouer
    // l'animation sur tout element ajoute plus tard.
    window.setTimeout(function () {
      document.documentElement.classList.remove('pt-enter');
    }, 1200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onArrive, { once: true });
  } else {
    onArrive();
  }

  // On ne leve un rideau que s'il a ete baisse : sur un chargement direct
  // (saisie de l'URL, premiere visite), il n'y a rien a devoiler.
  //
  // L'attente va jusqu'a « load » : c'est bien le chargement de la page qui
  // doit se terminer derriere le rideau, images et feuilles comprises, sinon
  // l'utilisateur decouvre une page qui se remplit encore sous ses yeux. Une
  // fois la page construite (DOMContentLoaded), on n'attend plus les images
  // que APRES_DOM_MS au plus.
  if (arrivedFromNavigation) {
    if (document.readyState === 'complete') {
      revealWhenReady();
    } else {
      window.addEventListener('load', revealWhenReady, { once: true });
      var apresConstruction = function () { window.setTimeout(revealWhenReady, APRES_DOM_MS); };
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apresConstruction, { once: true });
      } else {
        apresConstruction();
      }
    }
  }

  /**
   * Departs que ce script n'intercepte pas lui-meme : bouton Precedent ou
   * Suivant du navigateur, redirection declenchee en JavaScript (reponse 401,
   * deconnexion), URL saisie a la main.
   *
   * A ce stade la navigation est deja engagee : on ne peut plus animer la
   * couverture. Mais on peut prevenir la page d'arrivee, qui se chargera
   * derriere le rideau et le levera une fois prete. La transition est donc
   * portee par l'arrivee plutot que par le depart.
   */
  window.addEventListener('pagehide', function () {
    if (prefersReducedMotion()) return;
    if (departNu) return;   // ecriture : l'arrivee ne joue pas de transition
    try { sessionStorage.setItem(NAV_FLAG, '1'); } catch (e) { /* stockage indisponible */ }
  });

  /**
   * Retour depuis le cache du navigateur (Precedent/Suivant) : la page revient
   * telle qu'elle etait, sans reexecuter le script. Le drapeau ci-dessus n'y
   * changerait rien puisque rien ne le relit. C'est donc ici, et seulement
   * ici, qu'on peut jouer la transition dans ce cas.
   *
   * On couvre puis on balaie immediatement : le contenu est deja la, il n'y a
   * rien a attendre.
   */
  window.addEventListener('pageshow', function (event) {
    if (!event.persisted) return;

    if (prefersReducedMotion()) {
      stinger.className = 'pt-stinger';
      return;
    }

    mountStinger();
    stinger.className = 'pt-stinger is-active is-covered';

    // Un tick pour que l'etat couvert soit peint avant d'etre balaye : sans
    // lui, le navigateur fusionne les deux etats et rien n'est visible.
    window.setTimeout(reveal, 60);
  });

  // ---------------------------------------------------------------------
  // Micro-animations : ce qui apparait dans une page deja ouverte
  // ---------------------------------------------------------------------

  // On n'observe que les conteneurs qui se rechargent vraiment. Observer tout
  // le document ferait clignoter des zones sans raison et couterait cher sur
  // les grandes pages de Registrar.
  var WATCHED = [
    'tbody',
    '#five-stages',
    '#contentMain',
    '[data-anim-list]'
  ].join(',');

  // Zones qui gerent deja leurs propres animations.
  var EXCLUDED = '.pt-stinger, .dropdown-menu, .modal, #notifAffichIMG';

  function animateEntrance(node) {
    if (node.nodeType !== 1) return;
    if (node.closest && node.closest(EXCLUDED)) return;

    node.classList.add('pt-in');
    window.setTimeout(function () {
      node.classList.remove('pt-in');
    }, 400);
  }

  if ('MutationObserver' in window) {
    var observer = new MutationObserver(function (mutations) {
      if (prefersReducedMotion()) return;

      mutations.forEach(function (mutation) {
        var target = mutation.target;
        if (!target || !target.closest) return;
        if (!target.matches(WATCHED) && !target.closest(WATCHED)) return;
        if (target.closest(EXCLUDED)) return;

        // Au-dela d'une poignee d'elements, la cascade devient du bruit :
        // on anime les premiers et on laisse le reste apparaitre.
        var count = 0;
        mutation.addedNodes.forEach(function (node) {
          if (count >= 12) return;
          count++;
          animateEntrance(node);
        });
      });
    });

    var startObserving = function () {
      observer.observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', startObserving, { once: true });
    } else {
      startObserving();
    }
  }

  /**
   * Battement discret sur une valeur qui vient de changer.
   * Utilisable depuis n'importe quelle page : window.ptBump(element).
   */
  window.ptBump = function (element) {
    if (!element || prefersReducedMotion()) return;
    element.classList.remove('pt-bump');
    void element.offsetWidth; // force le redemarrage de l'animation
    element.classList.add('pt-bump');
    window.setTimeout(function () {
      element.classList.remove('pt-bump');
    }, 500);
  };
})();
