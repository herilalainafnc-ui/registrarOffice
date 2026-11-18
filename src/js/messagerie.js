/*
 * Messagerie instantanee.
 *
 * Deux interfaces, un meme moteur :
 *   - « page »  : /messagerie (src/messagerie.php) — discussions, fil,
 *                 informations ; une seule colonne a la fois sur telephone ;
 *   - « dock »  : au-dessus de toutes les autres pages (init/messagerie.dock.php),
 *                 comme Facebook ou LinkedIn — une barre en bas a droite avec
 *                 la liste des discussions, et de petites fenetres de discussion
 *                 qui restent ouvertes d'une page a l'autre, et d'une connexion
 *                 a l'autre sur le meme navigateur (localStorage, par compte).
 *
 * Un « fil » (creerFil) affiche une discussion : la page en a un, le dock un
 * par fenetre. Les donnees (discussions, messages, profils, lectures) sont
 * communes, et une seule interrogation les tient a jour pour tous les fils.
 *
 * Temps reel : interrogation courte de app/messagerie.api.php (2,5 s sur la
 * page, 3 s avec une fenetre ouverte, 15 s sinon) ; le serveur rend ce qui a
 * change depuis le dernier evenement connu.
 *
 * Un message deja affiche n'est jamais deplace (un vocal ou une video en
 * cours de lecture s'arreterait) : seuls ses voisins et ses reactions changent.
 * Chaque envoi porte une cle tiree au hasard : repete apres une coupure, il
 * n'est pas cree deux fois. Les fichiers partent par morceaux d'un mega-octet.
 */
(function () {
    'use strict';

    var cfgEl = document.getElementById('msConfig');
    if (!cfgEl || !window.XMLHttpRequest || !window.FormData || !window.Promise) { return; }

    var CFG = JSON.parse(cfgEl.textContent);
    var MODE = CFG.mode === 'dock' ? 'dock' : 'page';
    var jeton = CFG.csrf || window.CSRF_TOKEN || '';
    var MOI = CFG.moi;
    var titreBase = document.title;
    var FILS = [];   // fils affiches : celui de la page, ou les fenetres du dock

    /* ------------------------------------------------------------------ etat */

    var S = {
        reglages: CFG.reglages || {},
        conv: new Map(),          // id -> resume de discussion
        listeChargee: false,
        nonLus: CFG.non_lus || 0, // donne par le serveur tant que la liste n'est pas chargee
        profils: new Map(),       // id -> profil (nom, avatar, role)
        fils: new Map(),          // id -> { messages: [], parId: Map, encore, charge }
        details: new Map(),       // id -> membres, roles, lectures
        presences: {},
        curseur: CFG.curseur || 0,
        attente: new Map(),       // cle -> { message provisoire, element, fil, donnees }
        vus: new Set(),           // ids de messages deja traites (anti-doublon)
        textes: new Map(),        // brouillons de saisie par discussion
        echecs: 0,
        arret: false,
        derniereGarde: Date.now(),
        dernierePresence: 0,
        dernierEcrit: 0,
        modNb: 0
    };

    function cleStockage(k) { return 'messagerie:' + MOI + ':' + k; }
    function lireReglage(k, defaut) { try { var v = localStorage.getItem(cleStockage(k)); return v === null ? defaut : v; } catch (e) { return defaut; } }
    function ecrireReglage(k, v) { try { localStorage.setItem(cleStockage(k), v); } catch (e) { /* stockage indisponible */ } }
    function lireSession(k, defaut) { try { var v = sessionStorage.getItem(cleStockage(k)); return v === null ? defaut : JSON.parse(v); } catch (e) { return defaut; } }
    function ecrireSession(k, v) { try { sessionStorage.setItem(cleStockage(k), JSON.stringify(v)); } catch (e) { /* stockage indisponible */ } }
    /* Durable : bulles, fenetres et brouillons restent tels qu'on les a
       laisses, meme apres la fermeture de l'onglet ou une nouvelle
       connexion, sur ce navigateur. La cle porte le compte : un autre compte
       connecte ici ne les voit pas. On reprend au passage ce qu'un onglet
       ouvert avant cette version gardait en sessionStorage. */
    function lireDurable(k, defaut) {
        try {
            var v = localStorage.getItem(cleStockage(k));
            if (v === null) { v = sessionStorage.getItem(cleStockage(k)); }
            return v === null ? defaut : JSON.parse(v);
        } catch (e) { return defaut; }
    }
    function ecrireDurable(k, v) { try { localStorage.setItem(cleStockage(k), JSON.stringify(v)); } catch (e) { /* stockage indisponible */ } }

    /* Brouillons de saisie : gardes d'une page et d'une connexion a l'autre */
    (function () { var t = lireDurable('textes', {}); Object.keys(t || {}).forEach(function (k) { S.textes.set(isNaN(+k) ? k : +k, t[k]); }); })();
    function garderTextes() {
        var o = {};
        S.textes.forEach(function (v, k) { if (v) { o[k] = v; } });
        ecrireDurable('textes', o);
    }

    /* ------------------------------------------------------------- outils DOM */

    function h(tag, props) {
        var el = document.createElement(tag);
        if (props) {
            Object.keys(props).forEach(function (k) {
                var v = props[k];
                if (v === null || v === undefined || v === false) { return; }
                if (k === 'class') { el.className = v; }
                else if (k === 'text') { el.textContent = v; }
                else if (k === 'style' && typeof v === 'object') { Object.keys(v).forEach(function (p) { if (v[p] !== null && v[p] !== undefined) { if (p.indexOf('--') === 0) { el.style.setProperty(p, v[p]); } else { el.style[p] = v[p]; } } }); }
                else if (k === 'dataset') { Object.assign(el.dataset, v); }
                else if (k.slice(0, 2) === 'on' && typeof v === 'function') { el.addEventListener(k.slice(2), v); }
                else if (v === true) { el.setAttribute(k, ''); }
                else { el.setAttribute(k, v); }
            });
        }
        for (var i = 2; i < arguments.length; i++) { ajouter(el, arguments[i]); }
        return el;
    }
    function ajouter(el, c) {
        if (c === null || c === undefined || c === false) { return; }
        if (Array.isArray(c)) { c.forEach(function (x) { ajouter(el, x); }); return; }
        el.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    }
    function ic(nom) { return h('i', { class: 'bi bi-' + nom, 'aria-hidden': 'true' }); }
    function vider(el) { while (el.firstChild) { el.removeChild(el.firstChild); } }

    var elAnnonce = document.getElementById('msAnnonce');
    if (!elAnnonce) { elAnnonce = h('p', { class: 'sr-only', 'aria-live': 'polite' }); document.body.appendChild(elAnnonce); }
    function annoncer(t) { elAnnonce.textContent = ''; window.setTimeout(function () { elAnnonce.textContent = t; }, 30); }

    function toast(message, genre) {
        /* Toast.show insere du HTML : le texte (noms de fichiers…) est echappe. */
        if (window.Toast && typeof window.Toast[genre || 'info'] === 'function') {
            var d = document.createElement('div');
            d.textContent = message;
            window.Toast[genre || 'info'](d.innerHTML);
            return;
        }
        var t = h('div', { class: 'ms-toast', role: 'status' }, message);
        document.body.appendChild(t);
        window.setTimeout(function () { t.remove(); }, 3600);
    }

    function cle() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }
    function grand() { return window.matchMedia('(min-width: 821px)').matches; }

    /* ------------------------------------------------------------ formats */

    var fmtHM = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });
    var fmtSemaine = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
    var fmtJourMois = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' });
    var fmtComplet = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    var fmtLong = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    function memeJour(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
    function cap(t) { return t.charAt(0).toUpperCase() + t.slice(1); }

    function libelleListe(iso) {
        if (!iso) { return ''; }
        var t = new Date(iso), n = new Date();
        if (memeJour(t, n)) { return fmtHM.format(t); }
        if ((n - t) / 864e5 < 6.5) { return fmtSemaine.format(t); }
        if (t.getFullYear() === n.getFullYear()) { return fmtJourMois.format(t); }
        return fmtComplet.format(t);
    }
    function libelleJour(t) {
        var n = new Date();
        if (memeJour(t, n)) { return 'Aujourd’hui'; }
        var hier = new Date(n); hier.setDate(n.getDate() - 1);
        if (memeJour(t, hier)) { return 'Hier'; }
        var o = { weekday: 'long', day: 'numeric', month: 'long' };
        if (t.getFullYear() !== n.getFullYear()) { o.year = 'numeric'; }
        return cap(new Intl.DateTimeFormat('fr-FR', o).format(t));
    }
    function ilYa(iso) {
        var s = (Date.now() - new Date(iso)) / 1000;
        if (s < 90) { return 'à l’instant'; }
        var m = Math.round(s / 60);
        if (m < 60) { return 'il y a ' + m + ' min'; }
        var hh = Math.round(m / 60);
        if (hh < 24) { return 'il y a ' + hh + ' h'; }
        return 'il y a ' + Math.round(hh / 24) + ' j';
    }
    function taille(o) {
        if (o < 1024) { return o + ' o'; }
        if (o < 1048576) { return (o / 1024).toFixed(o < 10240 ? 1 : 0).replace('.', ',') + ' Ko'; }
        return (o / 1048576).toFixed(1).replace('.', ',') + ' Mo';
    }
    function duree(ms) {
        var s = Math.max(0, Math.round((ms || 0) / 1000));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }
    function sansAccents(t) { return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

    var RE_EMOJI = null;
    try { RE_EMOJI = new RegExp('^(?:\\p{Extended_Pictographic}|\\p{Emoji_Modifier}|\\uFE0F|\\u200D|\\s)+$', 'u'); } catch (e) { RE_EMOJI = null; }
    function emojiSeul(t) {
        t = (t || '').trim();
        if (!RE_EMOJI || !t || t.length > 16 || !RE_EMOJI.test(t) || /^[\d#*\s]+$/.test(t)) { return false; }
        var n = window.Intl && Intl.Segmenter ? Array.from(new Intl.Segmenter('fr', { granularity: 'grapheme' }).segment(t.replace(/\s/g, ''))).length : 1;
        return n <= 3;
    }

    var RE_LIEN = /(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"»])|(\bwww\.[^\s<]+[^\s<.,;:!?)\]'"»])/gi;
    function texteRiche(t) {
        var frag = document.createDocumentFragment();
        var i = 0;
        t.replace(RE_LIEN, function (m, a, b, pos) {
            frag.appendChild(document.createTextNode(t.slice(i, pos)));
            frag.appendChild(h('a', { href: a || ('https://' + b), target: '_blank', rel: 'noopener noreferrer' }, m));
            i = pos + m.length;
            return m;
        });
        frag.appendChild(document.createTextNode(t.slice(i)));
        return frag;
    }

    function iconeFichier(nom, genre) {
        var ext = (nom.split('.').pop() || '').toLowerCase();
        if (genre === 'audio') { return 'file-earmark-music'; }
        if (genre === 'video') { return 'file-earmark-play'; }
        if (ext === 'pdf') { return 'file-earmark-pdf'; }
        if (['doc', 'docx', 'odt', 'rtf'].indexOf(ext) >= 0) { return 'file-earmark-word'; }
        if (['xls', 'xlsx', 'ods', 'csv'].indexOf(ext) >= 0) { return 'file-earmark-spreadsheet'; }
        if (['ppt', 'pptx', 'odp'].indexOf(ext) >= 0) { return 'file-earmark-slides'; }
        if (['zip', 'rar', '7z'].indexOf(ext) >= 0) { return 'file-earmark-zip'; }
        if (['txt', 'epub'].indexOf(ext) >= 0) { return 'file-earmark-text'; }
        return 'file-earmark';
    }

    function urlFichier(f, o) {
        var p = new URLSearchParams({ id: f.id });
        if (o && o.apercu && f.apercu) { p.set('apercu', '1'); }
        if (o && o.telecharger) { p.set('telecharger', '1'); }
        return CFG.fichier + '?' + p.toString();
    }
    function apercuFichier(f) {
        if (!f) { return ''; }
        return f.genre === 'image' ? '📷 Photo' : f.genre === 'video' ? '🎬 Vidéo' : f.genre === 'audio' ? '🎤 Message vocal' : '📎 ' + f.nom;
    }

    /* ---------------------------------------------------------------- API */

    function sessionExpiree(d) {
        if (S.arret) { return; }
        S.arret = true;
        toast((d && d.message) || 'Votre session a expiré. Reconnectez-vous pour continuer.', 'warning');
        window.setTimeout(function () { window.location.href = (d && d.login_url) || (CFG.base + '/login'); }, 2600);
    }

    /*
     * Requetes en XMLHttpRequest, pas en fetch : src/js/session.js enveloppe
     * fetch et renvoie vers la connexion au premier 401. Or un 401 isole peut
     * etre un faux : toutes les 30 minutes, une autre page regenere
     * l'identifiant de session et efface l'ancien ; une interrogation partie a
     * cet instant arrive sans session. On reessaie donc une fois, 1,5 s plus
     * tard, avant de conclure. XHR donne en plus la progression des envois.
     */
    function requete(methode, url, corps, progression) {
        return new Promise(function (resoudre, rejeter) {
            var x = new XMLHttpRequest();
            x.open(methode, url, true);
            x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            x.onload = function () {
                var d;
                try { d = JSON.parse(x.responseText); } catch (e) { d = { ok: false, message: 'Réponse illisible du serveur.' }; }
                if (!d || typeof d !== 'object') { d = { ok: false }; }
                d.statut = x.status;
                resoudre(d);
            };
            x.onerror = function () { rejeter(new Error('reseau')); };
            x.ontimeout = function () { rejeter(new Error('delai')); };
            x.timeout = methode === 'GET' ? 30000 : 120000;
            if (progression && x.upload) { x.upload.onprogress = function (e) { if (e.lengthComputable) { progression(e.loaded / e.total); } }; }
            x.send(corps || null);
        });
    }

    function avecSession(faire) {
        return faire().then(function (d) {
            if (d.statut !== 401) { return d; }
            return new Promise(function (r) { window.setTimeout(r, 1500); }).then(faire).then(function (d2) {
                if (d2.statut === 401) { sessionExpiree(d2); }
                return d2;
            });
        });
    }

    function renouvelerJeton() {
        return requete('GET', CFG.api + '?action=jeton').then(function (d) {
            if (d && d.jeton) { jeton = d.jeton; window.CSRF_TOKEN = d.jeton; }
        });
    }

    function appel(methode, action, donnees, deuxieme) {
        var url = CFG.api;
        var faire;
        if (methode === 'GET') {
            url += '?' + new URLSearchParams(Object.assign({ action: action }, donnees || {})).toString();
            faire = function () { return requete('GET', url); };
        } else {
            faire = function () {
                var fd = new FormData();
                fd.append('action', action);
                fd.append('csrf_token', jeton);
                Object.keys(donnees || {}).forEach(function (k) {
                    var v = donnees[k];
                    if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); }
                    else if (v !== null && v !== undefined) { fd.append(k, v); }
                });
                return requete('POST', url, fd);
            };
        }
        return avecSession(faire).then(function (d) {
            if (d.csrf_expire && !deuxieme) {
                return renouvelerJeton().then(function () { return appel(methode, action, donnees, true); });
            }
            return d;
        });
    }
    function poster(action, donnees) {
        return appel('POST', action, donnees).then(function (d) {
            if (!d.ok && d.statut !== 401) { toast(d.message || 'L’action n’a pas abouti.', 'error'); }
            return d;
        }, function () {
            toast('Connexion perdue : réessayez dans un instant.', 'error');
            return { ok: false };
        });
    }

    /* -------------------------------------------------------- profils, avatars */

    function retenirProfils(liste) {
        if (!liste) { return; }
        (Array.isArray(liste) ? liste : Object.keys(liste).map(function (k) { return liste[k]; })).forEach(function (p) {
            if (p && p.id) { S.profils.set(p.id, p); }
        });
    }
    if (CFG.profil) { retenirProfils([CFG.profil]); }
    function profil(id) {
        return S.profils.get(id) || { id: id, nom: 'Utilisateur', initiales: '?', avatar: null, role: '', actif: true };
    }
    function prenom(id) {
        if (id === MOI) { return 'Vous'; }
        return (profil(id).nom || '').split(' ')[0];
    }
    function enLigne(id) { var p = S.presences[id]; return !!(p && p.en_ligne); }

    function avatar(p, t, point) {
        var el = h('span', { class: 'ms-av', style: { '--t': t ? t + 'px' : null, '--teinte': String(((p && p.id) || 0) * 47 % 360) }, 'aria-hidden': 'true' });
        if (p && p.avatar) {
            var img = h('img', { src: p.avatar, alt: '', loading: 'lazy', referrerpolicy: 'no-referrer' });
            img.addEventListener('error', function () { img.remove(); el.textContent = p.initiales || '?'; });
            el.appendChild(img);
        } else {
            el.textContent = (p && p.initiales) || '?';
        }
        if (point && p && enLigne(p.id)) { el.appendChild(h('span', { class: 'ms-av__point' })); }
        return el;
    }
    function avatarConv(c, t) {
        if (!c) { return avatar(null, t); }
        if (c.type === 'direct') { return avatar(c.autre, t, true); }
        var a = (c.avatars || []).slice(0, 2);
        /* Moins de deux autres membres : l'initiale du groupe, pas le visage d'un seul. */
        if (a.length < 2) {
            return avatar({ id: c.id + 9000, initiales: (c.titre || 'G').slice(0, 1).toUpperCase(), avatar: null }, t);
        }
        var g = h('span', { class: 'ms-av ms-av--groupe', style: { '--t': t + 'px' }, 'aria-hidden': 'true' });
        a.forEach(function (p) { g.appendChild(avatar(p, Math.round(t * 0.68))); });
        return g;
    }

    /* -------------------------------------------------------- lignes de liste */

    function convTriees() {
        return Array.from(S.conv.values()).filter(function (c) { return c.dernier || FILS.some(function (F) { return F.id === c.id; }); })
            .sort(function (a, b) { return (b.trie_le || '').localeCompare(a.trie_le || ''); });
    }
    function convAffichee(id) { return FILS.some(function (F) { return F.id === id && F.visible(); }); }

    function apercuConv(c) {
        var d = c.dernier;
        if (!d) { return ''; }
        if (d.type === 'systeme' || d.retire) { return d.retire ? (d.auteur_id === MOI ? 'Vous avez retiré un message' : 'Message retiré') : d.apercu; }
        if (d.auteur_id === MOI) { return 'Vous : ' + d.apercu; }
        if (c.type === 'groupe') { return prenom(d.auteur_id) + ' : ' + d.apercu; }
        return d.apercu;
    }

    function ligneConv(c, surClic, actif) {
        var nonLu = c.non_lus > 0 && !convAffichee(c.id);
        return h('button', {
            type: 'button', class: 'ms-conv' + (nonLu ? ' ms-conv--non-lu' : ''), 'aria-current': actif ? 'true' : null,
            dataset: { conv: c.id }, onclick: function () { surClic(c.id); }
        },
            avatarConv(c, 52),
            h('span', { style: { minWidth: '0' } },
                h('span', { class: 'ms-conv__titre' }, c.titre),
                h('span', { class: 'ms-conv__apercu' }, h('span', null, apercuConv(c)), c.dernier ? h('time', { datetime: c.dernier.le }, '· ' + libelleListe(c.dernier.le)) : null)
            ),
            h('span', { class: 'ms-conv__meta' },
                c.sourdine ? h('span', { class: 'ms-conv__sourdine', title: 'Notifications coupées' }, ic('bell-slash')) : null,
                nonLu ? h('span', { class: 'ms-conv__nb', 'aria-label': c.non_lus + ' non lu' + (c.non_lus > 1 ? 's' : '') }, c.non_lus > 99 ? '99+' : String(c.non_lus)) : marqueLecture(c)
            )
        );
    }

    /*
     * Mon dernier message est-il lu ? Comme Messenger : une coche dans un
     * rond tant que personne ne l'a lu, puis le visage de qui l'a lu (trois
     * au plus dans un groupe). Rien quand le dernier message n'est pas de moi.
     */
    function marqueLecture(c) {
        var d = c.dernier;
        if (!d || d.auteur_id !== MOI || d.retire || d.type === 'systeme') { return null; }
        var ids = c.vus_ids || [];
        if (!ids.length) {
            return h('span', { class: 'ms-conv__envoye', title: 'Envoyé — pas encore lu', 'aria-label': 'Envoyé, pas encore lu' }, ic('check2-circle'));
        }
        var gens = ids.slice(0, 3).map(function (id) {
            return (c.vus || []).find(function (p) { return p.id === id; }) || profil(id);
        });
        var noms = gens.map(function (p) { return p.nom; }).join(', ') + (ids.length > 3 ? ' et ' + (ids.length - 3) + ' autre' + (ids.length > 4 ? 's' : '') : '');
        return h('span', { class: 'ms-conv__vus', title: 'Vu par ' + noms, 'aria-label': 'Vu par ' + noms },
            gens.map(function (p) { return avatar(p, 16); }),
            ids.length > 3 ? h('span', { class: 'ms-conv__vus-plus' }, '+' + (ids.length - 3)) : null);
    }

    function ligneProfil(p, surClic) {
        return h('button', { type: 'button', class: 'ms-personne', onclick: function () { surClic(p); } },
            avatar(p, 40, true),
            h('span', { style: { minWidth: '0' } }, h('span', { class: 'ms-personne__nom' }, p.nom), h('span', { class: 'ms-personne__role' }, p.role || '')),
            h('span')
        );
    }

    /* Liste avec recherche : discussions filtrees, puis personnes (serveur). */
    function listeAvecRecherche(conteneur, champ, surConv, surPersonne, actif) {
        var etat = { q: '', personnes: [] };
        var minuterie = null;
        function dessiner() {
            var haut = conteneur.scrollTop;
            vider(conteneur);
            var q = sansAccents(etat.q.trim());
            var liste = convTriees();
            if (q) { liste = liste.filter(function (c) { return sansAccents(c.titre).indexOf(q) >= 0; }); }
            if (!S.listeChargee) {
                conteneur.appendChild(h('div', { class: 'ms-squelette', 'aria-hidden': 'true' },
                    [0, 1, 2, 3].map(function () { return h('div', { class: 'ms-squelette__ligne' }, h('span'), h('div', null, h('i'), h('i'))); })));
                return;
            }
            if (!q && inviterNotifications()) { conteneur.appendChild(invitationNotifications()); }
            if (!liste.length && !q) {
                conteneur.appendChild(h('p', { class: 'ms-liste__vide' }, 'Aucune discussion pour l’instant. ', h('br'), 'Commencez-en une avec le bouton ', ic('pencil-square'), '.'));
            }
            liste.forEach(function (c) { conteneur.appendChild(ligneConv(c, surConv, actif && actif() === c.id)); });
            if (q) {
                var dejaLa = new Set(liste.filter(function (c) { return c.type === 'direct' && c.autre; }).map(function (c) { return c.autre.id; }));
                var autres = etat.personnes.filter(function (p) { return !dejaLa.has(p.id); });
                if (autres.length) {
                    conteneur.appendChild(h('p', { class: 'ms-liste__section' }, 'Personnes'));
                    autres.forEach(function (p) { conteneur.appendChild(ligneProfil(p, function (pp) { champ.value = ''; etat.q = ''; etat.personnes = []; surPersonne(pp); })); });
                }
                if (!liste.length && !autres.length) {
                    conteneur.appendChild(h('p', { class: 'ms-liste__vide' }, etat.q.trim().length < 2 ? 'Tapez au moins deux lettres.' : 'Aucun résultat.'));
                }
            }
            conteneur.scrollTop = haut;
        }
        champ.addEventListener('input', function () {
            etat.q = champ.value;
            dessiner();
            window.clearTimeout(minuterie);
            var q = etat.q.trim();
            if (q.length < 2) { etat.personnes = []; return; }
            minuterie = window.setTimeout(function () {
                appel('GET', 'comptes', { q: q }).then(function (d) {
                    if (d.ok && champ.value.trim() === q) { etat.personnes = d.comptes || []; retenirProfils(etat.personnes); dessiner(); }
                }).catch(function () {});
            }, 220);
        });
        champ.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && champ.value) { e.stopPropagation(); champ.value = ''; etat.q = ''; etat.personnes = []; dessiner(); }
        });
        return { dessiner: dessiner };
    }

    /* ======================================================= donnees des fils */

    function donneesFil(id) {
        var f = S.fils.get(id);
        if (!f) { f = { messages: [], parId: new Map(), encore: true, charge: false, chargement: false }; S.fils.set(id, f); }
        return f;
    }
    function insererDansFil(f, m) {
        if (f.parId.has(m.id)) {
            var i = f.messages.findIndex(function (x) { return x.id === m.id; });
            f.messages[i] = m;
        } else {
            var j = f.messages.length;
            while (j > 0 && f.messages[j - 1].id > m.id) { j--; }
            f.messages.splice(j, 0, m);
        }
        f.parId.set(m.id, m);
    }
    function filsDe(id) { return FILS.filter(function (F) { return F.id === id && F.E.flux; }); }

    function chargerMessages(id) {
        var f = donneesFil(id);
        if (f.chargement) { return; }
        f.chargement = true;
        appel('GET', 'messages', { conversation: id }).then(function (d) {
            f.chargement = false;
            if (!d.ok) { if (d.statut !== 401) { toast(d.message || 'Discussion introuvable.', 'error'); } return; }
            retenirProfils(d.profils);
            f.messages = [];
            f.parId = new Map();
            d.messages.forEach(function (m) { S.vus.add(m.id); insererDansFil(f, m); });
            f.encore = d.encore;
            f.charge = true;
            filsDe(id).forEach(function (F) { F.dessinerMessages(); F.allerEnBas(false); F.marquerLu(); });
        }).catch(function () { f.chargement = false; toast('Connexion perdue : la discussion ne s’est pas chargée.', 'error'); });
    }

    function chargerDetails(id) {
        appel('GET', 'details', { conversation: id }).then(function (d) {
            if (!d.ok) { return; }
            S.details.set(id, d.details);
            retenirProfils(d.details.membres);
            filsDe(id).forEach(function (F) { F.majTete(); F.majSaisie(); F.lectures(); });
            if (MODE === 'page' && PAGE.fil.id === id && !PAGE.infos.hidden) { PAGE.dessinerInfos(); }
        }).catch(function () {});
    }

    /* Message recu (reponse d'envoi ou interrogation) : dans chaque fil qui l'affiche. */
    function recevoirMessage(m, depuisServeur, elementProvisoire) {
        var neuf = !S.vus.has(m.id);
        S.vus.add(m.id);
        var f = S.fils.get(m.conversation_id);
        if (f && f.charge) { insererDansFil(f, m); }
        var provisoire = elementProvisoire || null;
        if (!provisoire && m.cle_client && S.attente.has(m.cle_client)) {
            provisoire = S.attente.get(m.cle_client).element;
            S.attente.delete(m.cle_client);
        }
        var place = false;
        filsDe(m.conversation_id).forEach(function (F) {
            var p = provisoire && provisoire.isConnected && F.E.flux.contains(provisoire) ? provisoire : null;
            if (p) { place = true; }
            F.recevoir(m, depuisServeur, p, neuf);
        });
        if (provisoire && provisoire.isConnected && !place) { provisoire.remove(); }
        return neuf;
    }

    /* ========================================================= message (bulle) */

    function signature(m) {
        return JSON.stringify([m.retire, m.texte, m.fichier ? m.fichier.id : 0, m.type, !!m.reponse]);
    }

    /* « Vous avez répondu à Rasoa », « Rasoa vous a répondu », « Rasoa a répondu à Hery » */
    function libelleReponse(auteur, cite) {
        if (auteur === MOI) { return cite === MOI ? 'Vous vous êtes répondu' : 'Vous avez répondu à ' + prenom(cite); }
        if (cite === MOI) { return prenom(auteur) + ' vous a répondu'; }
        if (cite === auteur) { return prenom(auteur) + ' s’est répondu'; }
        return prenom(auteur) + ' a répondu à ' + prenom(cite);
    }

    function construireMessage(m, F) {
        if (m.type === 'systeme') {
            var s = h('div', { class: 'ms-systeme', dataset: { id: m.id } }, m.texte || '');
            s._m = m; s._sig = signature(m);
            return s;
        }
        var moi = m.auteur_id === MOI;
        var el = h('div', { class: 'ms-msg' + (moi ? ' ms-msg--moi' : ''), dataset: { id: m.id } });
        if (m.tmp) { el.dataset.tmp = '1'; el.classList.add('ms-msg--' + (m.etat || 'envoi')); }
        el._m = m;
        el._sig = signature(m);
        var av = h('div', { class: 'ms-msg__av' });
        if (!moi) { av.appendChild(avatar(profil(m.auteur_id), 28)); }
        el.appendChild(av);
        var corps = h('div', { class: 'ms-msg__corps' });
        el.appendChild(corps);

        if (!moi) { corps.appendChild(h('span', { class: 'ms-msg__auteur', hidden: true }, profil(m.auteur_id).nom)); }
        if (m.reponse && !m.retire) {
            var cite = m.reponse;
            corps.appendChild(h('span', { class: 'ms-msg__reponse-a' }, ic('reply'), libelleReponse(m.auteur_id, cite.auteur_id)));
            corps.appendChild(h('button', { type: 'button', class: 'ms-citation', onclick: function () { F.allerAuMessage(cite.id); } },
                cite.retire ? 'Message retiré' : cite.apercu));
        }

        var bulle = construireBulle(m, F);
        corps.appendChild(bulle);
        corps.appendChild(h('span', { class: 'ms-reac' }));
        majReactions(el, m, F);

        if (!m.retire && !m.tmp) {
            el.appendChild(h('div', { class: 'ms-msg__outils' },
                h('button', { type: 'button', class: 'ms-rond', 'aria-label': 'Réagir', title: 'Réagir', onclick: function (e) { choisirReaction(el, e.currentTarget); } }, ic('emoji-smile')),
                h('button', { type: 'button', class: 'ms-rond', 'aria-label': 'Répondre', title: 'Répondre', onclick: function () { F.repondre(el._m); } }, ic('reply')),
                h('button', { type: 'button', class: 'ms-rond', 'aria-label': 'Plus d’actions', title: 'Plus', onclick: function (e) { menuMessage(el, e.currentTarget, F); } }, ic('three-dots-vertical'))
            ));
            /* Appui long sur telephone : reactions et actions */
            var appui = null;
            bulle.addEventListener('touchstart', function () {
                appui = window.setTimeout(function () { appui = null; menuMessage(el, bulle, F, true); }, 480);
            }, { passive: true });
            ['touchend', 'touchmove', 'touchcancel'].forEach(function (t) { bulle.addEventListener(t, function () { if (appui) { window.clearTimeout(appui); appui = null; } }, { passive: true }); });
            bulle.addEventListener('contextmenu', function (e) { if (window.matchMedia('(pointer: coarse)').matches) { e.preventDefault(); } });
        }
        if (m.tmp && m.etat === 'echec') {
            corps.appendChild(h('button', { type: 'button', class: 'ms-statut ms-statut--echec', onclick: function () { reessayer(m.cle_client); } },
                ic('exclamation-circle'), 'Échec de l’envoi. Réessayer'));
        }
        return el;
    }

    function construireBulle(m, F) {
        var moi = m.auteur_id === MOI;
        var petit = F.mode === 'fenetre';
        var titre = m.envoye_le ? fmtLong.format(new Date(m.envoye_le)) : '';
        if (m.retire) {
            return h('div', { class: 'ms-bulle ms-bulle--retire', title: titre }, moi ? 'Vous avez retiré un message' : prenom(m.auteur_id) + ' a retiré un message');
        }
        var f = m.fichier;
        var texte = m.texte || '';
        if (!f) {
            var emoji = emojiSeul(texte);
            var b = h('div', { class: 'ms-bulle' + (emoji ? ' ms-bulle--emoji' : ''), title: titre });
            b.appendChild(emoji ? document.createTextNode(texte.trim()) : texteRiche(texte));
            return b;
        }
        var bulle = h('div', { class: 'ms-bulle' + (f.genre === 'image' || f.genre === 'video' ? ' ms-bulle--media' : ''), title: titre });
        if (f.genre === 'image') {
            var l = f.largeur || 320, ht = f.hauteur || 240;
            var max = petit ? 220 : 300, maxH = petit ? 260 : 360;
            var w = Math.min(max, l), hh = Math.round(w * ht / l);
            if (hh > maxH) { hh = maxH; w = Math.round(hh * l / ht); }
            var cadre = h('button', { type: 'button', class: 'ms-image-cadre', 'aria-label': 'Agrandir la photo', onclick: function () { visionner(m); } },
                h('img', { class: 'ms-image', src: f.local || urlFichier(f, { apercu: true }), alt: f.nom, width: w, height: hh, loading: 'lazy', style: { width: w + 'px', height: hh + 'px' } }));
            if (m.tmp && m.etat !== 'echec') { cadre.appendChild(anneau(m)); }
            bulle.appendChild(cadre);
        } else if (f.genre === 'video') {
            if (m.tmp) {
                var boite = h('div', { style: { position: 'relative', width: (petit ? 220 : 260) + 'px', height: '150px', background: '#000' } });
                if (m.etat !== 'echec') { boite.appendChild(anneau(m)); }
                bulle.appendChild(boite);
            } else {
                bulle.appendChild(h('video', { class: 'ms-video', src: urlFichier(f), controls: true, preload: 'metadata', playsinline: true }));
            }
        } else if (f.genre === 'audio') {
            bulle.appendChild(lecteurVocal(m));
        } else {
            var lien = h('a', { class: 'ms-doc', href: m.tmp ? '#' : urlFichier(f), target: '_blank', rel: 'noopener', download: f.mime === 'application/pdf' ? null : f.nom },
                h('span', { class: 'ms-doc__icone' }, ic(iconeFichier(f.nom, f.genre))),
                h('span', { style: { minWidth: '0' } }, h('span', { class: 'ms-doc__nom' }, f.nom),
                    h('span', { class: 'ms-doc__taille' }, m.tmp ? (m.etat === 'echec' ? 'Échec' : 'Envoi… ' + Math.round((m.progression || 0) * 100) + ' %') : taille(f.taille))));
            if (m.tmp) { lien.addEventListener('click', function (e) { e.preventDefault(); }); }
            bulle.appendChild(lien);
            m._etiquette = lien.querySelector('.ms-doc__taille');
        }
        if (texte.trim()) {
            var leg = h('div', { class: 'ms-legende' });
            leg.appendChild(texteRiche(texte));
            bulle.appendChild(leg);
        }
        return bulle;
    }

    function anneau(m) {
        var r = 20, tour = 2 * Math.PI * r;
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 48 48');
        var fond = document.createElementNS(ns, 'circle');
        var plein = document.createElementNS(ns, 'circle');
        [fond, plein].forEach(function (c) { c.setAttribute('cx', '24'); c.setAttribute('cy', '24'); c.setAttribute('r', String(r)); });
        fond.setAttribute('class', 'fond');
        plein.setAttribute('class', 'plein');
        plein.setAttribute('stroke-dasharray', String(tour));
        plein.setAttribute('stroke-dashoffset', String(tour * (1 - (m.progression || 0))));
        svg.appendChild(fond); svg.appendChild(plein);
        m._anneau = { cercle: plein, tour: tour };
        return h('span', { class: 'ms-progression', 'aria-hidden': 'true' }, svg);
    }
    function peindreProgression(m) {
        if (m._anneau) { m._anneau.cercle.setAttribute('stroke-dashoffset', String(m._anneau.tour * (1 - m.progression))); }
        if (m._etiquette) { m._etiquette.textContent = 'Envoi… ' + Math.round(m.progression * 100) + ' %'; }
    }

    function majReactions(el, m) {
        var corps = el.querySelector('.ms-msg__corps');
        if (!corps) { return; }
        var ancien = corps.querySelector('.ms-reac');
        var neuf = h('span', { class: 'ms-reac' });
        var r = m.reactions || [];
        if (r.length && !m.retire) {
            var parEmoji = {};
            r.forEach(function (x) { (parEmoji[x.emoji] = parEmoji[x.emoji] || []).push(x.user_id); });
            var noms = r.map(function (x) { return (x.user_id === MOI ? 'Vous' : profil(x.user_id).nom) + ' ' + x.emoji; }).join('\n');
            neuf.appendChild(h('button', { type: 'button', class: 'ms-reactions', title: noms, 'aria-label': 'Réactions : ' + noms.replace(/\n/g, ', '),
                onclick: function () { var mienne = r.find(function (x) { return x.user_id === MOI; }); if (mienne) { reagir(m, mienne.emoji); } else { choisirReaction(el, neuf); } } },
                Object.keys(parEmoji).slice(0, 3).join(''), r.length > 1 ? h('b', null, String(r.length)) : null));
        }
        if (ancien) { ancien.replaceWith(neuf); } else { corps.appendChild(neuf); }
    }

    /* ============================================================ creerFil */

    /*
     * Un fil de discussion dans « hote » : en-tete, messages, saisie.
     * mode 'page' (src/messagerie.php) ou 'fenetre' (dock). « actions » relie
     * le fil a l'interface qui l'accueille : retour, infos, infosOuvertes
     * (page) ; reduire, agrandir, fermer (fenetre) ; ouvrirConv, change.
     */
    function creerFil(hote, mode, actions) {
        actions = actions || {};
        var F = { id: null, brouillon: null, E: {}, dom: new Map(), repondreA: null, ecrivent: [], mode: mode, hote: hote, reduit: false };
        var petit = mode === 'fenetre';
        FILS.push(F);

        F.visible = function () {
            if (document.hidden || F.reduit || !F.E.flux) { return false; }
            return mode === 'fenetre' || grand() || PAGE.racine.dataset.vue === 'fil';
        };
        F.cleTexte = function () { return F.id || (F.brouillon ? 'a' + F.brouillon.profil.id : null); };
        F.garderTexte = function () {
            var k = F.cleTexte();
            if (F.E.zone && k !== null) { S.textes.set(k, F.E.zone.value); garderTextes(); }
        };
        F.detruire = function () {
            F.garderTexte();
            vider(hote);
            F.E = {};
            var i = FILS.indexOf(F);
            if (i >= 0) { FILS.splice(i, 1); }
        };

        F.ouvrir = function (id) {
            if (F.id !== id) { F.garderTexte(); }
            F.id = id;
            F.brouillon = null;
            F.repondreA = null;
            F.ecrivent = [];
            var c = S.conv.get(id);
            if (c && c.non_lus && !F.reduit) { c.non_lus = 0; }
            F.construire();
            var f = donneesFil(id);
            if (f.charge) { F.dessinerMessages(); F.allerEnBas(false); F.marquerLu(); }
            else { chargerMessages(id); }
            chargerDetails(id);
            if (actions.change) { actions.change(F); }
            redessinerListes();
            boucle(true);
        };

        F.ecrireA = function (p) {
            var existante = Array.from(S.conv.values()).find(function (c) { return c.type === 'direct' && c.autre && c.autre.id === p.id; });
            if (existante) { (actions.ouvrirConv || F.ouvrir)(existante.id); return; }
            retenirProfils([p]);
            F.garderTexte();
            F.id = null;
            F.brouillon = { profil: p };
            F.repondreA = null;
            F.construire();
            if (actions.change) { actions.change(F); }
        };

        function boutonsFenetre() {
            return h('div', { class: 'ms-fil__outils' },
                h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Ouvrir dans la messagerie', title: 'Ouvrir dans la messagerie', onclick: function (e) { e.stopPropagation(); actions.agrandir(F); } }, ic('arrows-angle-expand')),
                h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': F.reduit ? 'Agrandir la fenêtre' : 'Réduire la fenêtre', title: F.reduit ? 'Agrandir' : 'Réduire', onclick: function (e) { e.stopPropagation(); actions.reduire(F, !F.reduit); } }, ic(F.reduit ? 'chevron-up' : 'dash-lg')),
                h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Fermer la fenêtre', title: 'Fermer', onclick: function (e) { e.stopPropagation(); actions.fermer(F); } }, ic('x-lg')));
        }

        /* « A : » puis la personne */
        F.nouveau = function () {
            F.garderTexte();
            F.id = null;
            F.brouillon = null;
            F.E = {};
            vider(hote);
            var champ = h('input', { type: 'search', placeholder: 'Nom, prénom ou matricule', autocomplete: 'off', 'aria-label': 'Destinataire' });
            var resultats = h('div', { class: 'ms-destinataire__resultats' }, h('p', { class: 'ms-liste__vide' }, 'Cherchez une personne : un collègue, un enseignant ou un étudiant.'));
            var tete = h('header', { class: 'ms-fil__tete' });
            if (mode === 'page') { tete.appendChild(h('button', { type: 'button', class: 'ms-rond ms-rond--nu ms-fil__retour', 'aria-label': 'Retour aux discussions', onclick: actions.retour }, ic('arrow-left'))); }
            tete.appendChild(h('span', { class: 'ms-fil__nom', style: { flex: '1' } }, 'Nouveau message'));
            if (petit) { tete.appendChild(boutonsFenetre()); }
            hote.appendChild(tete);
            hote.appendChild(h('label', { class: 'ms-destinataire' }, h('span', null, 'À :'), champ));
            hote.appendChild(resultats);
            var minuterie = null;
            champ.addEventListener('input', function () {
                window.clearTimeout(minuterie);
                var q = champ.value.trim();
                if (q.length < 2) { return; }
                minuterie = window.setTimeout(function () {
                    appel('GET', 'comptes', { q: q }).then(function (r) {
                        if (!r.ok || champ.value.trim() !== q) { return; }
                        retenirProfils(r.comptes);
                        vider(resultats);
                        if (!r.comptes.length) { resultats.appendChild(h('p', { class: 'ms-liste__vide' }, 'Personne ne correspond.')); }
                        r.comptes.forEach(function (p) { resultats.appendChild(ligneProfil(p, F.ecrireA)); });
                    });
                }, 200);
            });
            if (actions.change) { actions.change(F); }
            window.setTimeout(function () { champ.focus(); }, 30);
        };

        F.construire = function () {
            vider(hote);
            F.E = {};
            F.dom = new Map();
            var E = F.E;
            E.tete = h('header', { class: 'ms-fil__tete' });
            hote.appendChild(E.tete);
            F.majTete();
            E.flux = h('div', { class: 'ms-flux', tabindex: '-1', 'aria-label': 'Messages' });
            E.debut = h('div');
            E.ancre = h('div', { 'aria-hidden': 'true' });
            E.flux.appendChild(E.debut);
            E.flux.appendChild(E.ancre);
            E.flux.addEventListener('scroll', function () {
                if (E.flux.scrollTop < 160) { F.chargerPlusAnciens(); }
                if (F.enBas()) { F.masquerNouveaux(); F.marquerLu(); }
            }, { passive: true });
            hote.appendChild(E.flux);
            E.saisie = h('footer', { class: 'ms-saisie' });
            hote.appendChild(E.saisie);
            F.dessinerSaisie();
            F.majSaisie();
            F.brancherDepot();
            if (!F.id && F.brouillon) { F.majDebut(); }
        };

        F.majTete = function () {
            var E = F.E;
            if (!E.tete) { return; }
            vider(E.tete);
            var c = F.id ? S.conv.get(F.id) : null;
            var p = F.brouillon ? F.brouillon.profil : null;
            if (mode === 'page') {
                E.tete.appendChild(h('button', { type: 'button', class: 'ms-rond ms-rond--nu ms-fil__retour', 'aria-label': 'Retour aux discussions', onclick: actions.retour }, ic('arrow-left')));
            }
            var nom, etat = '', etatClasse = '', av;
            if (c) {
                nom = c.titre;
                av = avatarConv(c, petit ? 34 : 40);
                if (c.type === 'direct' && c.autre) {
                    var pr = S.presences[c.autre.id];
                    if (c.autre.actif === false) { etat = 'Compte désactivé'; }
                    else if (pr && pr.en_ligne) { etat = 'En ligne'; etatClasse = ' ms-fil__etat--en-ligne'; }
                    else if (pr && pr.vu_le) { etat = 'Actif ' + ilYa(pr.vu_le); }
                    else { etat = c.autre.role || ''; }
                } else {
                    etat = c.nb_membres + ' membre' + (c.nb_membres > 1 ? 's' : '');
                }
            } else if (p) {
                nom = p.nom; etat = p.role || ''; av = avatar(p, petit ? 34 : 40, true);
            } else {
                return;
            }
            var nb = c && F.reduit && c.non_lus ? h('span', { class: 'ms-conv__nb ms-fil__nb' }, String(c.non_lus)) : null;
            E.tete.appendChild(h('button', {
                type: 'button', class: 'ms-fil__qui',
                onclick: function () { if (petit) { actions.reduire(F, !F.reduit); } else if (c && actions.infos) { actions.infos(); } },
                'aria-label': petit ? (F.reduit ? 'Agrandir la fenêtre : ' : 'Réduire la fenêtre : ') + nom : (c ? 'Informations sur la discussion' : null)
            }, av, h('span', { style: { minWidth: '0', flex: '1' } }, h('span', { class: 'ms-fil__nom' }, nom, nb), h('span', { class: 'ms-fil__etat' + etatClasse }, etat))));
            if (petit) {
                E.tete.appendChild(boutonsFenetre());
            } else if (c) {
                E.tete.appendChild(h('div', { class: 'ms-fil__outils' },
                    h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Informations sur la discussion', title: 'Informations',
                        'aria-pressed': actions.infosOuvertes && actions.infosOuvertes() ? 'true' : 'false', 'data-infos': '', onclick: actions.infos }, ic('info-circle-fill'))));
            }
        };

        F.majDebut = function () {
            var E = F.E;
            if (!E.debut) { return; }
            vider(E.debut);
            var c = F.id ? S.conv.get(F.id) : null;
            var f = F.id ? S.fils.get(F.id) : null;
            if (f && f.encore) {
                E.debut.appendChild(h('p', { class: 'ms-flux__plus' }, f.chargement ? 'Chargement…' : ''));
                return;
            }
            var p = c ? (c.type === 'direct' ? c.autre : null) : (F.brouillon ? F.brouillon.profil : null);
            var t = petit ? 56 : 76;
            E.debut.appendChild(h('div', { class: 'ms-flux__debut' }, c ? avatarConv(c, t) : avatar(p, t),
                h('strong', null, c ? c.titre : (p ? p.nom : '')),
                h('span', null, c && c.type === 'groupe' ? 'Début du groupe' : ((p && p.role) || '')),
                !c ? h('p', { style: { marginTop: '10px' } }, 'Écrivez votre premier message ci-dessous.') : null));
        };

        F.dessinerMessages = function () {
            var E = F.E;
            if (!E.flux) { return; }
            var f = S.fils.get(F.id);
            Array.from(E.flux.querySelectorAll('[data-id], [data-sep]')).forEach(function (el) { if (!el.dataset.tmp) { el.remove(); } });
            F.dom = new Map();
            F.majDebut();
            if (f) { f.messages.forEach(F.placer); }
            F.structurer();
        };

        /* Place un message a son rang (par id), sans deplacer ceux deja affiches. */
        F.placer = function (m) {
            var ancien = F.dom.get(m.id);
            if (ancien) {
                if (ancien._sig === signature(m)) { majReactions(ancien, m); ancien._m = m; return; }
                var neuf = construireMessage(m, F);
                ancien.replaceWith(neuf);
                F.dom.set(m.id, neuf);
                return;
            }
            var el = construireMessage(m, F);
            var suivant = null;
            var enfants = F.E.flux.children;
            for (var i = 0; i < enfants.length; i++) {
                var x = enfants[i];
                if (x.dataset.tmp || (x.dataset.id && +x.dataset.id > m.id)) { suivant = x; break; }
            }
            if (suivant && suivant.previousElementSibling && suivant.previousElementSibling.dataset.sep) { suivant = suivant.previousElementSibling; }
            F.E.flux.insertBefore(el, suivant || F.E.ancre);
            F.dom.set(m.id, el);
        };

        F.recevoir = function (m, depuisServeur, provisoire, neuf) {
            var enBas = F.enBas();
            if (provisoire) {
                if (F.dom.has(m.id)) { provisoire.remove(); }
                else { var el = construireMessage(m, F); provisoire.replaceWith(el); F.dom.set(m.id, el); }
            } else if (donneesFil(F.id).charge) {
                var etaitLa = F.dom.has(m.id);
                F.placer(m);
                if (!etaitLa && depuisServeur && F.dom.get(m.id)) { F.dom.get(m.id).classList.add('ms-msg--entre'); }
            }
            F.structurer();
            if (neuf && depuisServeur && m.auteur_id !== MOI) {
                if (enBas) { F.allerEnBas(true); } else { F.montrerNouveaux(); }
                if (m.type !== 'systeme' && F.visible()) { annoncer('Nouveau message de ' + profil(m.auteur_id).nom + ' : ' + (m.texte || 'pièce jointe')); }
            } else if (m.auteur_id === MOI && enBas) {
                F.allerEnBas(true);
            }
        };

        /* Separateurs (jour, longue pause) et series de messages d'un meme auteur. */
        var structurePrevue = false;
        F.structurer = function () {
            if (structurePrevue) { return; }
            structurePrevue = true;
            window.requestAnimationFrame(function () {
                structurePrevue = false;
                var flux = F.E.flux;
                if (!flux) { return; }
                var elems = Array.from(flux.children).filter(function (x) { return x.dataset.id; });
                var groupe = (S.conv.get(F.id) || {}).type === 'groupe';
                var prec = null;
                elems.forEach(function (el) {
                    var t = new Date(el._m.envoye_le || Date.now());
                    var tp = prec ? new Date(prec._m.envoye_le || Date.now()) : null;
                    var sep = null;
                    if (!tp || !memeJour(t, tp)) { sep = { classe: 'ms-jour', texte: libelleJour(t) }; }
                    else if (t - tp > 30 * 60000) { sep = { classe: 'ms-heure', texte: fmtHM.format(t) }; }
                    var avant = el.previousElementSibling;
                    var aSep = avant && avant.dataset.sep ? avant : null;
                    if (sep) {
                        if (aSep) { aSep.className = sep.classe; aSep.textContent = sep.texte; }
                        else { flux.insertBefore(h('div', { class: sep.classe, dataset: { sep: '1' } }, sep.texte), el); }
                    } else if (aSep) { aSep.remove(); }
                    el._sep = !!sep;
                    prec = el;
                });
                var lie = function (x, y) {
                    return x && y && x.classList.contains('ms-msg') && y.classList.contains('ms-msg') && !y._sep
                        && x._m.auteur_id === y._m.auteur_id && Math.abs(new Date(y._m.envoye_le || Date.now()) - new Date(x._m.envoye_le || Date.now())) < 5 * 60000;
                };
                elems.forEach(function (el, i) {
                    if (!el.classList.contains('ms-msg')) { return; }
                    var avec = lie(elems[i - 1], el), apres = lie(el, elems[i + 1]);
                    el.classList.toggle('ms-msg--suite-avant', avec);
                    el.classList.toggle('ms-msg--suite-apres', apres);
                    el.classList.toggle('ms-msg--debut', !avec);
                    var av = el.querySelector('.ms-msg__av');
                    if (av) { av.style.visibility = apres ? 'hidden' : ''; }
                    var nom = el.querySelector('.ms-msg__auteur');
                    if (nom) { nom.hidden = !(groupe && !avec && el._m.auteur_id !== MOI); }
                });
                F.lectures();
            });
        };

        /* Accuses de lecture : « Envoye », puis le visage de ceux qui ont lu. */
        F.lectures = function () {
            var flux = F.E.flux;
            if (!flux) { return; }
            flux.querySelectorAll('.ms-statut:not(.ms-statut--echec), .ms-vus').forEach(function (x) { x.remove(); });
            var det = S.details.get(F.id);
            var f = S.fils.get(F.id);
            if (!F.id || !f) { return; }
            var visibles = f.messages.filter(function (m) { return m.type !== 'systeme'; });
            var placer = {};
            if (det) {
                det.membres.forEach(function (mb) {
                    if (mb.id === MOI || !mb.lu_jusqu_a) { return; }
                    var cible = null;
                    for (var i = visibles.length - 1; i >= 0; i--) { if (visibles[i].id <= mb.lu_jusqu_a) { cible = visibles[i]; break; } }
                    /* Sous son propre message, son visage n'apprendrait rien. */
                    if (cible && cible.auteur_id !== mb.id) { (placer[cible.id] = placer[cible.id] || []).push(mb); }
                });
            }
            Object.keys(placer).forEach(function (id) {
                var el = F.dom.get(+id);
                if (!el || !el.classList.contains('ms-msg')) { return; }
                var gens = placer[id];
                var vus = h('div', { class: 'ms-vus', title: 'Vu par ' + gens.map(function (p) { return p.nom; }).join(', ') });
                gens.slice(0, 5).forEach(function (p) { vus.appendChild(avatar(p, 15)); });
                if (gens.length > 5) { vus.appendChild(h('span', { style: { fontSize: '11px', color: 'rgb(var(--c-text-3))' } }, '+' + (gens.length - 5))); }
                el.querySelector('.ms-msg__corps').appendChild(vus);
            });
            var dernier = visibles[visibles.length - 1];
            if (dernier && dernier.auteur_id === MOI && !placer[dernier.id] && !dernier.retire) {
                var el = F.dom.get(dernier.id);
                if (el) { el.querySelector('.ms-msg__corps').appendChild(h('span', { class: 'ms-statut' }, ic('check2-circle'), 'Envoyé')); }
            }
            S.attente.forEach(function (a) {
                if (a.fil === F && a.element && a.element.isConnected && a.message.etat !== 'echec' && !a.element.querySelector('.ms-statut')) {
                    a.element.querySelector('.ms-msg__corps').appendChild(h('span', { class: 'ms-statut' }, ic('circle'), 'Envoi…'));
                }
            });
        };

        F.enBas = function () { var x = F.E.flux; return !x || x.scrollHeight - x.scrollTop - x.clientHeight < 80; };
        F.allerEnBas = function (doux) {
            var x = F.E.flux;
            if (!x) { return; }
            window.requestAnimationFrame(function () { x.scrollTo({ top: x.scrollHeight, behavior: doux ? 'smooth' : 'auto' }); });
            F.masquerNouveaux();
        };
        F.montrerNouveaux = function () {
            if (F.E.nouveaux || !F.E.flux) { return; }
            F.E.nouveaux = h('button', { type: 'button', class: 'ms-nouveaux', onclick: function () { F.allerEnBas(true); } }, ic('arrow-down'), 'Nouveaux messages');
            hote.appendChild(F.E.nouveaux);
        };
        F.masquerNouveaux = function () { if (F.E.nouveaux) { F.E.nouveaux.remove(); F.E.nouveaux = null; } };
        F.allerAuMessage = function (id) {
            var el = F.dom.get(id);
            if (!el) { toast('Ce message est plus ancien : remontez dans la discussion pour le retrouver.'); return; }
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            el.classList.remove('ms-msg--surligne');
            void el.offsetWidth;
            el.classList.add('ms-msg--surligne');
        };

        F.chargerPlusAnciens = function () {
            var id = F.id;
            var f = id ? S.fils.get(id) : null;
            if (!f || !f.charge || !f.encore || f.chargement || !f.messages.length) { return; }
            f.chargement = true;
            appel('GET', 'messages', { conversation: id, avant: f.messages[0].id }).then(function (d) {
                f.chargement = false;
                if (!d.ok) { return; }
                retenirProfils(d.profils);
                f.encore = d.encore;
                d.messages.forEach(function (m) { S.vus.add(m.id); insererDansFil(f, m); });
                filsDe(id).forEach(function (G) {
                    var x = G.E.flux, avant = x.scrollHeight;
                    d.messages.forEach(G.placer);
                    G.majDebut();
                    G.structurer();
                    x.scrollTop += x.scrollHeight - avant;
                });
            }).catch(function () { f.chargement = false; });
        };

        F.dessinerEcrivent = function () {
            var E = F.E;
            if (!E.flux) { return; }
            var qui = F.ecrivent.filter(function (id) { return id !== MOI; });
            if (!qui.length) { if (E.ecrit) { E.ecrit.remove(); E.ecrit = null; } return; }
            var enBas = F.enBas();
            if (!E.ecrit) { E.ecrit = h('div', { class: 'ms-ecrit', 'aria-label': 'En train d’écrire' }); E.flux.appendChild(E.ecrit); }
            vider(E.ecrit);
            E.ecrit.appendChild(avatar(profil(qui[0]), 28));
            E.ecrit.appendChild(h('span', { class: 'ms-ecrit__bulle', title: qui.map(function (id) { return profil(id).nom; }).join(', ') + ' écrit…' }, h('i'), h('i'), h('i')));
            if (enBas) { F.allerEnBas(true); }
        };

        F.marquerLu = function () {
            var id = F.id;
            if (!id || !F.visible() || !F.enBas()) { return; }
            var f = S.fils.get(id);
            var c = S.conv.get(id);
            if (!f || !f.messages.length || !c) { return; }
            var dernier = f.messages[f.messages.length - 1].id;
            if (dernier <= (c.lu_jusqu_a || 0)) {
                if (c.non_lus) { c.non_lus = 0; redessinerListes(); }
                return;
            }
            c.lu_jusqu_a = dernier;
            c.non_lus = 0;
            redessinerListes();
            F.majTete();
            appel('POST', 'lu', { conversation: id, message: dernier }).catch(function () {});
        };

        /* ---------------------------------------------------------- saisie */

        F.majSaisie = function () {
            var c = F.id ? S.conv.get(F.id) : null;
            var blocage = c ? c.blocage : null;
            var motif = null;
            if (c && !c.membre) { motif = { titre: 'Vous ne faites plus partie de ce groupe.', texte: 'Vous ne pouvez plus y écrire.' }; }
            else if (c && c.type === 'direct' && c.autre && c.autre.actif === false) { motif = { titre: 'Ce compte est désactivé.', texte: 'Il ne reçoit plus de messages.' }; }
            else if (blocage === 'moi') { motif = { titre: 'Vous avez bloqué ' + (c.autre ? c.autre.nom : 'cette personne') + '.', texte: 'Elle ne peut plus vous écrire dans cette discussion.', debloquer: c.autre ? c.autre.id : null }; }
            else if (blocage === 'lui') { motif = { titre: 'Vous ne pouvez pas répondre à cette discussion.', texte: '' }; }
            var cleMotif = motif ? motif.titre : '';
            if (cleMotif !== (F.E.motif || '')) { F.E.motif = cleMotif; F.dessinerSaisie(motif); }
        };

        F.dessinerSaisie = function (motif) {
            var E = F.E;
            if (!E.saisie) { return; }
            vider(E.saisie);
            E.zone = null;
            if (motif) {
                E.saisie.appendChild(h('div', { class: 'ms-bloque' }, h('strong', null, motif.titre), motif.texte,
                    motif.debloquer ? h('div', null, h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { bloquer(motif.debloquer, false); } }, 'Débloquer')) : null));
                return;
            }
            E.repondre = h('div');
            E.saisie.appendChild(E.repondre);
            var ligne = h('div', { class: 'ms-saisie__ligne' });
            E.saisie.appendChild(ligne);
            E.choixFichier = h('input', { type: 'file', multiple: true, hidden: true, onchange: function () { envoyerFichiers(F, Array.from(E.choixFichier.files)); E.choixFichier.value = ''; } });
            E.choixMedia = h('input', { type: 'file', multiple: true, hidden: true, accept: 'image/*,video/*', onchange: function () { envoyerFichiers(F, Array.from(E.choixMedia.files)); E.choixMedia.value = ''; } });
            E.zone = h('textarea', { rows: '1', placeholder: 'Message', 'aria-label': 'Message', maxlength: String(S.reglages.texte_max || 4000) });
            E.zone.value = S.textes.get(F.cleTexte()) || '';
            E.envoyer = h('button', { type: 'button', class: 'ms-outil ms-envoyer', 'aria-label': 'Envoyer', title: 'Envoyer (Entrée)', onclick: F.envoyerTexte }, ic('send-fill'));
            E.micro = h('button', { type: 'button', class: 'ms-outil', 'aria-label': 'Enregistrer un message vocal', title: 'Message vocal', onclick: function () { demarrerVocal(F); } }, ic('mic-fill'));
            ligne.appendChild(E.choixFichier);
            ligne.appendChild(E.choixMedia);
            ligne.appendChild(h('button', { type: 'button', class: 'ms-outil', 'aria-label': 'Joindre un document', title: 'Joindre un fichier', onclick: function () { E.choixFichier.click(); } }, ic('paperclip')));
            ligne.appendChild(h('button', { type: 'button', class: 'ms-outil', 'aria-label': 'Envoyer une photo ou une vidéo', title: 'Photo ou vidéo', onclick: function () { E.choixMedia.click(); } }, ic('image')));
            ligne.appendChild(h('div', { class: 'ms-zone' }, E.zone,
                h('button', { type: 'button', class: 'ms-outil', 'aria-label': 'Choisir un émoji', title: 'Émoji', onclick: function (e) { choisirEmoji(e.currentTarget, F); } }, ic('emoji-smile'))));
            ligne.appendChild(E.micro);
            ligne.appendChild(E.envoyer);
            E.zone.addEventListener('input', function () { F.ajusterZone(); F.majBoutonEnvoi(); F.signalerEcrit(); });
            E.zone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !window.matchMedia('(pointer: coarse)').matches) { e.preventDefault(); F.envoyerTexte(); }
                else if (e.key === 'Escape' && F.repondreA) { e.stopPropagation(); F.annulerReponse(); }
            });
            E.zone.addEventListener('blur', F.garderTexte);
            E.zone.addEventListener('focus', function () { F.marquerLu(); });
            E.zone.addEventListener('paste', function (e) {
                var fichiers = Array.from((e.clipboardData && e.clipboardData.files) || []);
                if (fichiers.length) { e.preventDefault(); envoyerFichiers(F, fichiers); }
            });
            F.ajusterZone();
            F.majBoutonEnvoi();
            F.dessinerReponse();
            if (!F.reduit && window.matchMedia('(pointer: fine)').matches && (mode === 'page' || F.focusAuto)) {
                window.setTimeout(function () { if (E.zone && E.zone.isConnected) { E.zone.focus({ preventScroll: true }); } }, 30);
            }
        };

        F.ajusterZone = function () {
            var z = F.E.zone;
            if (!z) { return; }
            z.style.height = 'auto';
            z.style.height = Math.min(petit ? 110 : 160, z.scrollHeight) + 'px';
        };
        F.majBoutonEnvoi = function () {
            var E = F.E;
            if (!E.zone) { return; }
            var plein = E.zone.value.trim() !== '';
            E.envoyer.hidden = !plein;
            E.micro.hidden = plein;
        };
        F.signalerEcrit = function () {
            if (!F.id || !F.E.zone || !F.E.zone.value.trim()) { return; }
            var t = Date.now();
            if (t - S.dernierEcrit < 3000) { return; }
            S.dernierEcrit = t;
            appel('POST', 'ecrit', { conversation: F.id, ecrit: '1' }).catch(function () {});
        };

        F.repondre = function (m) { F.repondreA = m; F.dessinerReponse(); if (F.E.zone) { F.E.zone.focus(); } };
        F.annulerReponse = function () { F.repondreA = null; F.dessinerReponse(); };
        F.dessinerReponse = function () {
            var E = F.E;
            if (!E.repondre) { return; }
            vider(E.repondre);
            var m = F.repondreA;
            if (!m) { return; }
            E.repondre.appendChild(h('div', { class: 'ms-repondre' },
                h('div', null, h('b', null, 'Réponse à ' + (m.auteur_id === MOI ? 'vous-même' : profil(m.auteur_id).nom)), h('span', null, m.texte || apercuFichier(m.fichier))),
                h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Annuler la réponse', onclick: F.annulerReponse }, ic('x-lg'))));
        };

        F.cible = function () {
            if (F.id) { return { conversation: F.id }; }
            if (F.brouillon) { return { destinataire: F.brouillon.profil.id }; }
            return null;
        };

        F.envoyerTexte = function () {
            var E = F.E;
            if (!E.zone) { return; }
            var texte = E.zone.value;
            if (!texte.trim()) { return; }
            var dest = F.cible();
            if (!dest) { return; }
            E.zone.value = '';
            S.textes.delete(F.cleTexte());
            garderTextes();
            F.ajusterZone();
            F.majBoutonEnvoi();
            var donnees = Object.assign({ texte: texte, cle: cle() }, dest);
            var r = F.repondreA;
            if (r) { donnees.reponse_a = r.id; }
            var provisoire = { tmp: true, id: 'tmp-' + donnees.cle, cle_client: donnees.cle, auteur_id: MOI, type: 'texte', texte: texte,
                envoye_le: new Date().toISOString(), etat: 'envoi', reactions: [],
                reponse: r ? { id: r.id, auteur_id: r.auteur_id, apercu: r.texte || apercuFichier(r.fichier), retire: false } : null };
            F.annulerReponse();
            lancerEnvoi(donnees, provisoire, F);
        };

        F.montrerProvisoire = function (p) {
            if (!F.E.flux) { return null; }
            var el = construireMessage(p, F);
            el.classList.add('ms-msg--entre');
            F.E.flux.insertBefore(el, F.E.ancre);
            F.structurer();
            F.allerEnBas(true);
            return el;
        };

        F.brancherDepot = function () {
            var compteur = 0, voile = null;
            function cacher() { if (voile) { voile.remove(); voile = null; } compteur = 0; }
            hote.ondragenter = function (e) {
                if (!F.E.zone || !e.dataTransfer || Array.from(e.dataTransfer.types || []).indexOf('Files') < 0) { return; }
                e.preventDefault(); compteur++;
                if (!voile) { voile = h('div', { class: 'ms-deposer' }, ic('cloud-arrow-up'), 'Déposez vos fichiers pour les envoyer'); hote.appendChild(voile); }
            };
            hote.ondragover = function (e) { if (voile) { e.preventDefault(); } };
            hote.ondragleave = function () { if (--compteur <= 0) { cacher(); } };
            hote.ondrop = function (e) { if (!voile) { return; } e.preventDefault(); cacher(); envoyerFichiers(F, Array.from(e.dataTransfer.files || [])); };
        };

        return F;
    }

    /* ------------------------------------------------------------- envoi */

    function lancerEnvoi(donnees, provisoire, F) {
        var a = S.attente.get(donnees.cle) || { message: provisoire, fil: F };
        a.donnees = donnees;
        a.fil = F;
        a.message.etat = 'envoi';
        if (!a.element || !a.element.isConnected) { a.element = F.montrerProvisoire(a.message); }
        S.attente.set(donnees.cle, a);
        return appel('POST', 'envoyer', donnees).then(function (d) {
            if (!d.ok) { echecEnvoi(donnees.cle, d.message); return; }
            S.attente.delete(donnees.cle);
            if (d.conversation) { S.conv.set(d.conversation.id, d.conversation); }
            var m = d.message;
            /* Premier message d'une nouvelle discussion : le fil devient cette discussion. */
            if (F.brouillon && !F.id && FILS.indexOf(F) >= 0) {
                if (a.element) { a.element.remove(); }
                F.ouvrir(m.conversation_id);
                return;
            }
            recevoirMessage(m, false, a.element);
            redessinerListes();
        }, function () {
            echecEnvoi(donnees.cle, 'Connexion perdue.');
        });
    }

    function echecEnvoi(k, message) {
        var a = S.attente.get(k);
        if (!a) { return; }
        a.message.etat = 'echec';
        if (a.element && a.element.isConnected) {
            var neuf = construireMessage(a.message, a.fil);
            a.element.replaceWith(neuf);
            a.element = neuf;
            a.fil.structurer();
        }
        if (message) { toast(message, 'error'); }
    }

    function reessayer(k) {
        var a = S.attente.get(k);
        if (!a) { return; }
        if (a.relancer) { a.relancer(); return; }
        a.message.etat = 'envoi';
        var neuf = construireMessage(a.message, a.fil);
        a.element.replaceWith(neuf);
        a.element = neuf;
        lancerEnvoi(a.donnees, a.message, a.fil);
    }

    /* ---------------------------------------------------------------- fichiers */

    function extensionAcceptee(nom) {
        var ext = (nom.split('.').pop() || '').toLowerCase();
        return (S.reglages.extensions || []).indexOf(ext) >= 0;
    }

    function envoyerFichiers(F, fichiers) {
        if (!fichiers.length || !F.cible()) { return; }
        var max = S.reglages.taille_max || 104857600;
        fichiers.forEach(function (f) {
            if (!extensionAcceptee(f.name)) { toast('« ' + f.name + ' » : ce type de fichier n’est pas accepté.', 'warning'); return; }
            if (f.size > max) { toast('« ' + f.name + ' » dépasse ' + Math.round(max / 1048576) + ' Mo.', 'warning'); return; }
            if (!f.size) { toast('« ' + f.name + ' » est vide.', 'warning'); return; }
            envoyerFichier(F, f, {});
        });
    }

    function genreLocal(f) {
        var t = f.type || '';
        if (/^image\/(jpeg|png|gif|webp|bmp)$/.test(t)) { return 'image'; }
        if (t.indexOf('video/') === 0) { return 'video'; }
        if (t.indexOf('audio/') === 0) { return 'audio'; }
        return 'fichier';
    }

    function envoyerFichier(F, fichier, options) {
        var dest = F.cible();
        var k = cle();
        var genre = options.genre || genreLocal(fichier);
        var local = genre === 'image' ? URL.createObjectURL(fichier) : null;
        var provisoire = { tmp: true, id: 'tmp-' + k, cle_client: k, auteur_id: MOI, type: genre, texte: null, envoye_le: new Date().toISOString(),
            etat: 'envoi', progression: 0, reactions: [], donnees: options.onde ? { onde: options.onde } : null,
            fichier: { id: 0, nom: fichier.name, genre: genre, taille: fichier.size, local: local, duree_ms: options.duree || null, largeur: null, hauteur: null } };
        var a = { message: provisoire, element: null, donnees: null, fil: F };
        S.attente.set(k, a);
        function afficher() { if (!a.element) { a.element = F.montrerProvisoire(provisoire); } }
        if (local) {
            var img = new Image();
            img.onload = function () { provisoire.fichier.largeur = img.naturalWidth; provisoire.fichier.hauteur = img.naturalHeight; afficher(); };
            img.onerror = afficher;
            img.src = local;
        } else {
            afficher();
        }

        var position = 0, id = 0, jetonEnvoi = '', essais = 0;
        function echec(message) {
            a.relancer = function () {
                a.relancer = null;
                provisoire.etat = 'envoi';
                var neuf = construireMessage(provisoire, a.fil);
                if (a.element) { a.element.replaceWith(neuf); }
                a.element = neuf;
                essais = 0;
                (id ? morceau() : debut());
            };
            echecEnvoi(k, message);
        }
        function postFichier(fd, avance) {
            fd.append('csrf_token', jeton);
            return avecSession(function () { return requete('POST', CFG.fichier, fd, avance); });
        }
        function debut() {
            var fd = new FormData();
            fd.append('action', 'debut');
            fd.append('nom', fichier.name);
            fd.append('taille', String(fichier.size));
            if (options.duree) { fd.append('duree', String(Math.round(options.duree))); }
            postFichier(fd).then(function (d) {
                if (d.csrf_expire && essais++ < 1) { return renouvelerJeton().then(debut); }
                if (!d.ok) { echec(d.message); return; }
                id = d.id; jetonEnvoi = d.jeton; essais = 0;
                morceau();
            }, function () { echec('Connexion perdue pendant l’envoi.'); });
        }
        function morceau() {
            var fd = new FormData();
            fd.append('action', 'morceau');
            fd.append('id', String(id));
            fd.append('jeton', jetonEnvoi);
            fd.append('position', String(position));
            var bloc = fichier.slice(position, position + (CFG.morceau || 1048576));
            fd.append('morceau', bloc, 'morceau');
            var depart = position;
            postFichier(fd, function (r) {
                /* Progression a l'interieur du morceau : l'anneau avance en continu. */
                provisoire.progression = fichier.size ? (depart + r * bloc.size) / fichier.size : 0;
                peindreProgression(provisoire);
            }).then(function (d) {
                if (d.csrf_expire && essais++ < 2) { return renouvelerJeton().then(morceau); }
                if (d.reprendre !== undefined && d.reprendre !== null) { position = d.reprendre; essais = 0; return morceau(); }
                if (!d.ok) { echec(d.message); return; }
                essais = 0;
                position = d.fichier.recu;
                provisoire.progression = fichier.size ? position / fichier.size : 0;
                peindreProgression(provisoire);
                if (d.fichier.pret) {
                    if (local) { URL.revokeObjectURL(local); }
                    var G = a.fil;
                    var donnees = Object.assign({ fichier: d.fichier.id, cle: k }, G.id ? { conversation: G.id } : dest);
                    if (options.onde) { donnees.onde = options.onde; }
                    provisoire.fichier = Object.assign({}, provisoire.fichier, d.fichier, { local: null });
                    lancerEnvoi(donnees, provisoire, G);
                } else {
                    morceau();
                }
            }, function () {
                if (essais++ < 6) { window.setTimeout(morceau, Math.min(15000, 1000 * Math.pow(2, essais))); }
                else { echec('Connexion perdue pendant l’envoi.'); }
            });
        }
        debut();
    }

    /* ---------------------------------------------------------------- vocal */

    var enregistrement = null;

    function demarrerVocal(F) {
        if (enregistrement) { toast('Un message vocal est déjà en cours d’enregistrement.', 'warning'); return; }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            toast('Votre navigateur ne permet pas d’enregistrer un message vocal.', 'warning');
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(function (flux) {
            var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
            var type = types.find(function (t) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t); }) || '';
            var rec;
            try { rec = new MediaRecorder(flux, type ? { mimeType: type, audioBitsPerSecond: 48000 } : undefined); }
            catch (e) { rec = new MediaRecorder(flux); }
            var morceaux = [], niveaux = [];
            var ctx = null, analyseur = null, tampon = null;
            try {
                ctx = new (window.AudioContext || window.webkitAudioContext)();
                analyseur = ctx.createAnalyser();
                analyseur.fftSize = 512;
                ctx.createMediaStreamSource(flux).connect(analyseur);
                tampon = new Uint8Array(analyseur.fftSize);
            } catch (e) { analyseur = null; }
            enregistrement = { rec: rec, flux: flux, ctx: ctx, debut: Date.now(), niveaux: niveaux, annule: false, fil: F };
            rec.ondataavailable = function (e) { if (e.data && e.data.size) { morceaux.push(e.data); } };
            rec.onstop = function () {
                var en = enregistrement;
                enregistrement = null;
                flux.getTracks().forEach(function (t) { t.stop(); });
                if (ctx) { ctx.close().catch(function () {}); }
                window.clearInterval(en.minuterie);
                var encore = FILS.indexOf(F) >= 0;
                if (encore) { F.E.motif = ''; F.dessinerSaisie(); F.majSaisie(); }
                if (en.annule || !morceaux.length || !encore) { return; }
                var mime = rec.mimeType || type || 'audio/webm';
                var ext = mime.indexOf('mp4') >= 0 ? 'm4a' : (mime.indexOf('ogg') >= 0 ? 'ogg' : 'weba');
                var blob = new Blob(morceaux, { type: mime.split(';')[0] });
                var nom = 'Message vocal ' + new Date().toLocaleString('fr-FR').replace(/[/:]/g, '-') + '.' + ext;
                var fichier;
                try { fichier = new File([blob], nom, { type: blob.type }); } catch (err) { fichier = blob; fichier.name = nom; }
                envoyerFichier(F, fichier, { genre: 'audio', duree: Date.now() - en.debut, onde: resumerOnde(niveaux) });
            };
            rec.start(250);
            dessinerEnregistrement(F);
            enregistrement.minuterie = window.setInterval(function () {
                if (!enregistrement) { return; }
                var ms = Date.now() - enregistrement.debut;
                if (analyseur) {
                    analyseur.getByteTimeDomainData(tampon);
                    var somme = 0;
                    for (var i = 0; i < tampon.length; i++) { var v = (tampon[i] - 128) / 128; somme += v * v; }
                    niveaux.push(Math.min(1, Math.sqrt(somme / tampon.length) * 3.2));
                }
                if (F.E.duree) { F.E.duree.textContent = duree(ms); }
                if (F.E.ondeLive) {
                    vider(F.E.ondeLive);
                    niveaux.slice(F.mode === 'fenetre' ? -34 : -60).forEach(function (n) { F.E.ondeLive.appendChild(h('i', { style: { height: Math.max(4, Math.round(n * 30)) + 'px' } })); });
                }
                if (ms > 5 * 60000) { arreterVocal(true); }
            }, 100);
        }).catch(function () {
            toast('Le micro n’est pas accessible : autorisez-le dans votre navigateur.', 'warning');
        });
    }

    function dessinerEnregistrement(F) {
        var E = F.E;
        if (!E.saisie) { return; }
        vider(E.saisie);
        E.zone = null;
        E.duree = h('span', { class: 'ms-enregistrement__duree' }, '0:00');
        E.ondeLive = h('span', { class: 'ms-enregistrement__onde', 'aria-hidden': 'true' });
        E.saisie.appendChild(h('div', { class: 'ms-enregistrement', role: 'group', 'aria-label': 'Enregistrement d’un message vocal' },
            h('button', { type: 'button', class: 'ms-outil', 'aria-label': 'Annuler l’enregistrement', title: 'Annuler', onclick: function () { arreterVocal(false); } }, ic('trash3')),
            h('span', { class: 'ms-enregistrement__point', 'aria-hidden': 'true' }),
            E.duree, E.ondeLive,
            h('button', { type: 'button', class: 'ms-outil', 'aria-label': 'Envoyer le message vocal', title: 'Envoyer', onclick: function () { arreterVocal(true); } }, ic('send-fill'))));
    }

    function arreterVocal(envoyer) {
        if (!enregistrement) { return; }
        enregistrement.annule = !envoyer;
        try { enregistrement.rec.stop(); } catch (e) { /* deja arrete */ }
    }

    /* 48 barres, de 0 a 9, a partir des niveaux mesures toutes les 100 ms. */
    function resumerOnde(niveaux) {
        if (!niveaux.length) { return null; }
        var n = 48, sortie = '';
        for (var i = 0; i < n; i++) {
            var a = Math.floor(i * niveaux.length / n), b = Math.max(a + 1, Math.floor((i + 1) * niveaux.length / n));
            var max = 0;
            for (var j = a; j < b && j < niveaux.length; j++) { max = Math.max(max, niveaux[j]); }
            sortie += String(Math.min(9, Math.round(max * 9)));
        }
        return sortie;
    }

    var lecteurActif = null;

    function lecteurVocal(m) {
        var f = m.fichier;
        var onde = (m.donnees && m.donnees.onde) || '';
        if (!onde) {
            var graine = (f.id || 7) * 9301 + 49297;
            for (var i = 0; i < 48; i++) { graine = (graine * 9301 + 49297) % 233280; onde += String(2 + Math.floor(graine / 233280 * 6)); }
        }
        var barres = h('span', { class: 'ms-vocal__onde', role: 'slider', tabindex: '0', 'aria-label': 'Position de lecture', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': '0' });
        onde.split('').forEach(function (c) { barres.appendChild(h('i', { style: { height: Math.max(5, Math.round(+c / 9 * 28)) + 'px' } })); });
        var temps = h('span', { class: 'ms-vocal__duree' }, duree(f.duree_ms));
        var bouton = h('button', { type: 'button', class: 'ms-vocal__lire', 'aria-label': 'Écouter le message vocal' }, ic('play-fill'));
        var vitesse = h('button', { type: 'button', class: 'ms-vocal__vitesse', 'aria-label': 'Vitesse de lecture', hidden: true }, '1×');
        var boite = h('div', { class: 'ms-vocal' }, bouton, barres, temps, vitesse);
        if (m.tmp) { bouton.disabled = true; return boite; }
        var audio = null;
        function peindre() {
            if (!audio) { return; }
            var d = audio.duration && isFinite(audio.duration) ? audio.duration : (f.duree_ms || 0) / 1000;
            var r = d ? Math.min(1, audio.currentTime / d) : 0;
            var n = Math.round(r * barres.children.length);
            Array.from(barres.children).forEach(function (b, i) { b.classList.toggle('lu', i < n); });
            barres.setAttribute('aria-valuenow', String(Math.round(r * 100)));
            temps.textContent = duree((audio.paused && audio.currentTime === 0 ? d : (d - audio.currentTime)) * 1000);
        }
        function preparer() {
            if (audio) { return audio; }
            audio = new Audio(urlFichier(f));
            audio.preload = 'metadata';
            audio.addEventListener('timeupdate', peindre);
            audio.addEventListener('play', function () { bouton.replaceChildren(ic('pause-fill')); bouton.setAttribute('aria-label', 'Mettre en pause'); vitesse.hidden = false; });
            audio.addEventListener('pause', function () { bouton.replaceChildren(ic('play-fill')); bouton.setAttribute('aria-label', 'Écouter le message vocal'); });
            audio.addEventListener('ended', function () { audio.currentTime = 0; peindre(); vitesse.hidden = true; });
            audio.addEventListener('error', function () { toast('Ce message vocal ne peut pas être lu par ce navigateur.', 'warning'); });
            return audio;
        }
        bouton.addEventListener('click', function () {
            var a = preparer();
            if (a.paused) {
                if (lecteurActif && lecteurActif !== a) { lecteurActif.pause(); }
                lecteurActif = a;
                a.play().catch(function () {});
            } else { a.pause(); }
        });
        vitesse.addEventListener('click', function () {
            var a = preparer();
            var v = a.playbackRate === 1 ? 1.5 : (a.playbackRate === 1.5 ? 2 : 1);
            a.playbackRate = v;
            vitesse.textContent = String(v).replace('.', ',') + '×';
        });
        function chercher(r) {
            var a = preparer();
            var go = function () { if (a.duration && isFinite(a.duration)) { a.currentTime = r * a.duration; peindre(); } };
            if (a.readyState >= 1) { go(); } else { a.addEventListener('loadedmetadata', go, { once: true }); a.load(); }
        }
        barres.addEventListener('click', function (e) {
            var r = barres.getBoundingClientRect();
            chercher(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)));
        });
        barres.addEventListener('keydown', function (e) {
            if (!audio || !audio.duration) { return; }
            if (e.key === 'ArrowRight') { audio.currentTime = Math.min(audio.duration, audio.currentTime + 5); e.preventDefault(); }
            if (e.key === 'ArrowLeft') { audio.currentTime = Math.max(0, audio.currentTime - 5); e.preventDefault(); }
        });
        return boite;
    }

    /* ------------------------------------------------------- emojis et reactions */

    var EMOJIS = [
        ['Visages', '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 😉 😊 😇 🥰 😍 🤩 😘 😋 😛 😜 🤪 🤗 🤭 🤫 🤔 😐 😑 😶 🙄 😏 😬 😌 😔 😴 😷 🤒 🥵 🥶 🤯 🥳 😎 🤓 😕 😟 😮 😲 😳 🥺 😢 😭 😱 😖 😞 😓 😩 😤 😡'],
        ['Gestes', '👍 👎 👏 🙌 🤝 🙏 ✌️ 🤞 👌 🤙 👋 💪 ✍️ 👀 🙇 🤷'],
        ['Cœurs et signes', '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💔 💯 ✨ 🔥 🎉 🎊 ✅ ❌ ⚠️ ❓ ❗ 💡'],
        ['Études', '📚 📖 📝 ✏️ 🎓 🏫 📅 ⏰ 📌 📎 💻 📱 ☕ 🍽️ ⚽ 🎵']
    ];

    var popActif = null;
    function fermerPop() { if (popActif) { popActif.remove(); popActif = null; document.removeEventListener('pointerdown', horsPop, true); } }
    function horsPop(e) { if (popActif && !popActif.contains(e.target)) { fermerPop(); } }
    function ouvrirPop(pop, ancre, focus) {
        fermerPop();
        pop.classList.add('ms-racine');
        document.body.appendChild(pop);
        popActif = pop;
        var r = ancre.getBoundingClientRect();
        var l = pop.offsetWidth, ht = pop.offsetHeight;
        var x = Math.min(window.innerWidth - l - 8, Math.max(8, r.left + r.width / 2 - l / 2));
        var y = r.top - ht - 8;
        if (y < 8) { y = Math.min(window.innerHeight - ht - 8, r.bottom + 8); }
        pop.style.left = x + 'px';
        pop.style.top = y + 'px';
        window.setTimeout(function () { document.addEventListener('pointerdown', horsPop, true); }, 0);
        var premier = pop.querySelector('button, [href]');
        if (premier && focus !== false) { premier.focus({ preventScroll: true }); }
        pop.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); fermerPop(); if (ancre.focus) { ancre.focus(); } } });
    }

    function choisirEmoji(ancre, F) {
        var grille = h('div', { class: 'ms-pop ms-pop--emoji', role: 'dialog', 'aria-label': 'Émojis' });
        EMOJIS.forEach(function (g) {
            grille.appendChild(h('p', null, g[0]));
            var boite = h('div', { class: 'ms-emoji-grille' });
            g[1].split(' ').forEach(function (em) {
                boite.appendChild(h('button', { type: 'button', 'aria-label': em, onclick: function () { insererTexte(F, em); } }, em));
            });
            grille.appendChild(boite);
        });
        ouvrirPop(grille, ancre, false);
    }
    function insererTexte(F, t) {
        var z = F.E.zone;
        if (!z) { return; }
        var a = z.selectionStart || z.value.length, b = z.selectionEnd || z.value.length;
        z.value = z.value.slice(0, a) + t + z.value.slice(b);
        z.selectionStart = z.selectionEnd = a + t.length;
        z.focus();
        F.ajusterZone();
        F.majBoutonEnvoi();
    }

    function choisirReaction(el, ancre) {
        var m = el._m;
        var mienne = (m.reactions || []).find(function (x) { return x.user_id === MOI; });
        var pop = h('div', { class: 'ms-pop ms-pop--reactions', role: 'menu', 'aria-label': 'Réactions' });
        (S.reglages.reactions || []).forEach(function (em) {
            pop.appendChild(h('button', { type: 'button', role: 'menuitem', 'aria-label': em, 'aria-pressed': mienne && mienne.emoji === em ? 'true' : 'false',
                onclick: function () { fermerPop(); reagir(m, em); } }, em));
        });
        ouvrirPop(pop, ancre);
    }
    function reagir(m, emoji) {
        /* Affichage immediat ; l'interrogation suivante confirme. */
        var r = (m.reactions || []).filter(function (x) { return x.user_id !== MOI; });
        var mienne = (m.reactions || []).find(function (x) { return x.user_id === MOI; });
        if (!mienne || mienne.emoji !== emoji) { r.push({ user_id: MOI, emoji: emoji }); }
        m.reactions = r;
        filsDe(m.conversation_id).forEach(function (F) { var el = F.dom.get(m.id); if (el) { majReactions(el, m); } });
        poster('reagir', { message: m.id, emoji: emoji });
    }

    function menuMessage(el, ancre, F, tactile) {
        var m = el._m;
        var moi = m.auteur_id === MOI;
        var menu = h('div', { class: 'ms-pop', role: 'menu' });
        if (tactile) {
            var rangee = h('div', { class: 'ms-pop--reactions', style: { display: 'flex', marginBottom: '4px' } });
            (S.reglages.reactions || []).forEach(function (em) {
                rangee.appendChild(h('button', { type: 'button', 'aria-label': em, onclick: function () { fermerPop(); reagir(m, em); } }, em));
            });
            menu.appendChild(rangee);
        }
        function item(icone, libelle, action, danger) {
            menu.appendChild(h('button', { type: 'button', role: 'menuitem', class: 'ms-menu__item' + (danger ? ' ms-menu__item--danger' : ''),
                onclick: function () { fermerPop(); action(); } }, ic(icone), libelle));
        }
        item('reply', 'Répondre', function () { F.repondre(m); });
        if (m.texte && navigator.clipboard) {
            item('copy', 'Copier le texte', function () { navigator.clipboard.writeText(m.texte).then(function () { toast('Texte copié.'); }); });
        }
        if (m.fichier && !m.tmp) {
            menu.appendChild(h('a', { class: 'ms-menu__item', role: 'menuitem', href: urlFichier(m.fichier, { telecharger: true }), download: m.fichier.nom, onclick: fermerPop }, ic('download'), 'Télécharger'));
        }
        if (moi) {
            item('trash3', 'Retirer pour tout le monde', function () {
                confirmer('Retirer ce message ?', 'Il disparaîtra pour tous les membres de la discussion. Cette action est définitive.', 'Retirer', true)
                    .then(function (oui) { if (oui) { poster('retirer', { message: m.id }); } });
            }, true);
        } else {
            item('flag', 'Signaler', function () { signaler(m); }, true);
        }
        ouvrirPop(menu, ancre);
    }

    /* ============================================================== dialogues */

    function dialogue(titre, contenu, pied) {
        var d = h('dialog', { class: 'ms-dialogue ms-racine', 'aria-label': titre });
        d.appendChild(h('div', { class: 'ms-dialogue__tete' }, h('h2', null, titre),
            h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Fermer', onclick: function () { d.close(); } }, ic('x-lg'))));
        d.appendChild(h('div', { class: 'ms-dialogue__corps' }, contenu));
        if (pied) { d.appendChild(h('div', { class: 'ms-dialogue__pied' }, pied)); }
        d.addEventListener('close', function () { window.setTimeout(function () { d.remove(); }, 200); });
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
        document.body.appendChild(d);
        d.showModal();
        return d;
    }

    function confirmer(titre, texte, libelle, danger) {
        return new Promise(function (resoudre) {
            var reponse = false;
            var d;
            var ok = h('button', { type: 'button', class: 'ms-bouton' + (danger ? ' ms-bouton--danger' : ''), onclick: function () { reponse = true; d.close(); } }, libelle);
            d = dialogue(titre, h('p', { style: { margin: '0', lineHeight: '1.55', color: 'rgb(var(--c-text-2))' } }, texte),
                [h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { d.close(); } }, 'Annuler'), ok]);
            d.addEventListener('close', function () { resoudre(reponse); });
            ok.focus();
        });
    }

    function renommer(id) {
        var c = S.conv.get(id);
        var champ = h('input', { type: 'text', maxlength: '100', value: (c && c.nom) || '', placeholder: 'Nom du groupe' });
        var d;
        var ok = h('button', { type: 'button', class: 'ms-bouton', onclick: function () {
            poster('renommer', { conversation: id, nom: champ.value }).then(function (r) { if (r.ok) { d.close(); } });
        } }, 'Enregistrer');
        d = dialogue('Renommer le groupe', h('label', { class: 'ms-champ' }, h('span', null, 'Nom'), champ),
            [h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { d.close(); } }, 'Annuler'), ok]);
        champ.addEventListener('keydown', function (e) { if (e.key === 'Enter') { ok.click(); } });
        champ.focus();
        champ.select();
    }

    function signaler(m) {
        var motif = h('textarea', { rows: '3', maxlength: '500', placeholder: 'Facultatif : ce qui pose problème' });
        var d;
        var ok = h('button', { type: 'button', class: 'ms-bouton ms-bouton--danger', onclick: function () {
            poster('signaler', { message: m.id, motif: motif.value }).then(function (r) { if (r.ok) { d.close(); toast('Merci : le message a été signalé à l’administration.', 'success'); } });
        } }, 'Signaler');
        d = dialogue('Signaler ce message', [
            h('p', { style: { marginTop: '0', lineHeight: '1.55', color: 'rgb(var(--c-text-2))' } }, 'L’administration de l’application lira ce message et décidera s’il doit être retiré. ', profil(m.auteur_id).nom, ' n’est pas prévenu(e).'),
            h('label', { class: 'ms-champ' }, h('span', null, 'Motif'), motif)
        ], [h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { d.close(); } }, 'Annuler'), ok]);
        motif.focus();
    }

    /* Choix de personnes : nouveau groupe, ou ajout au groupe « id ». */
    function choisirPersonnes(mode, id, surCree) {
        var choisis = new Map();
        var exclus = new Set([MOI]);
        if (mode === 'ajouter') {
            var det = S.details.get(id);
            (det ? det.membres : []).forEach(function (mb) { exclus.add(mb.id); });
        }
        var nom = h('input', { type: 'text', maxlength: '100', placeholder: 'Facultatif' });
        var champ = h('input', { type: 'search', placeholder: 'Nom, prénom ou matricule', autocomplete: 'off' });
        var puces = h('div', { class: 'ms-puces' });
        var resultats = h('div', { role: 'listbox', 'aria-multiselectable': 'true', 'aria-label': 'Personnes' });
        var aide = h('p', { class: 'ms-aide' }, 'Tapez au moins deux lettres.');
        var ok = h('button', { type: 'button', class: 'ms-bouton', disabled: true }, mode === 'ajouter' ? 'Ajouter' : 'Créer le groupe');
        var dernier = [];
        function dessinerPuces() {
            vider(puces);
            choisis.forEach(function (p) {
                puces.appendChild(h('button', { type: 'button', class: 'ms-puce', 'aria-label': 'Retirer ' + p.nom, onclick: function () { choisis.delete(p.id); dessinerPuces(); dessinerResultats(dernier); } },
                    avatar(p, 22), p.nom.split(' ')[0], ic('x')));
            });
            ok.disabled = choisis.size === 0;
        }
        function dessinerResultats(liste) {
            dernier = liste;
            vider(resultats);
            liste.filter(function (p) { return !exclus.has(p.id); }).forEach(function (p) {
                var coche = choisis.has(p.id);
                resultats.appendChild(h('button', { type: 'button', class: 'ms-personne', role: 'option', 'aria-selected': coche ? 'true' : 'false', 'aria-checked': coche ? 'true' : 'false',
                    onclick: function () { if (choisis.has(p.id)) { choisis.delete(p.id); } else { choisis.set(p.id, p); } dessinerPuces(); dessinerResultats(dernier); } },
                    avatar(p, 40), h('span', { style: { minWidth: '0' } }, h('span', { class: 'ms-personne__nom' }, p.nom), h('span', { class: 'ms-personne__role' }, p.role || '')),
                    h('span', { class: 'ms-personne__coche' }, ic('check-lg'))));
            });
        }
        var minuterie = null;
        champ.addEventListener('input', function () {
            window.clearTimeout(minuterie);
            var q = champ.value.trim();
            if (q.length < 2) { aide.hidden = false; dessinerResultats([]); return; }
            aide.hidden = true;
            minuterie = window.setTimeout(function () {
                appel('GET', 'comptes', { q: q }).then(function (r) { if (r.ok && champ.value.trim() === q) { retenirProfils(r.comptes); dessinerResultats(r.comptes); } });
            }, 200);
        });
        var d;
        ok.addEventListener('click', function () {
            ok.disabled = true;
            var ids = Array.from(choisis.keys());
            var demande = mode === 'ajouter'
                ? poster('ajouter', { conversation: id, membres: ids })
                : poster('creer_groupe', { nom: nom.value, membres: ids });
            demande.then(function (r) {
                ok.disabled = false;
                if (!r.ok) { return; }
                d.close();
                if (mode === 'ajouter') { chargerDetails(id); return; }
                if (r.conversation) { S.conv.set(r.conversation.id, r.conversation); if (surCree) { surCree(r.conversation.id); } }
            });
        });
        var contenu = [];
        if (mode !== 'ajouter') { contenu.push(h('label', { class: 'ms-champ' }, h('span', null, 'Nom du groupe'), nom)); }
        contenu.push(h('label', { class: 'ms-champ' }, h('span', null, mode === 'ajouter' ? 'Personnes à ajouter' : 'Membres'), champ), puces, aide, resultats);
        d = dialogue(mode === 'ajouter' ? 'Ajouter des personnes' : 'Nouveau groupe', contenu,
            [h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { d.close(); } }, 'Annuler'), ok]);
        (mode === 'ajouter' ? champ : nom).focus();
    }

    /*
     * Message a plusieurs personnes (superadmin) : la meme annonce a tout le
     * monde, au personnel ou aux etudiants, dans la discussion privee de
     * chacun avec l'expediteur. La cle tiree a l'ouverture du formulaire
     * evite les doublons si l'envoi est repete.
     */
    function diffuser() {
        var cleEnvoi = cle();
        var comptes = {};
        var choix = h('div', { class: 'ms-cibles', role: 'radiogroup', 'aria-label': 'Destinataires' });
        [['tous', 'Tout le monde'], ['personnel', 'Personnel et enseignants'], ['etudiants', 'Étudiants']].forEach(function (c, i) {
            choix.appendChild(h('label', { class: 'ms-cible' },
                h('input', { type: 'radio', name: 'ms-cible', value: c[0], checked: i === 0 }),
                h('span', null, c[1]), h('b', { 'data-nb': c[0] }, '…')));
        });
        var texte = h('textarea', { rows: '7', maxlength: String(S.reglages.texte_max || 4000), placeholder: 'Votre message' });
        var libelle = h('span', null, 'Envoyer');
        var ok = h('button', { type: 'button', class: 'ms-bouton', disabled: true }, ic('megaphone'), libelle);
        var d;
        function cible() { var r = choix.querySelector('input:checked'); return r ? r.value : 'tous'; }
        function majBouton() {
            var n = comptes[cible()];
            libelle.textContent = n === undefined ? 'Envoyer' : 'Envoyer à ' + n + ' personne' + (n > 1 ? 's' : '');
            ok.disabled = !texte.value.trim() || !n;
        }
        choix.addEventListener('change', majBouton);
        texte.addEventListener('input', majBouton);
        appel('GET', 'diffusion').then(function (r) {
            if (!r.ok) { return; }
            comptes = r.comptes || {};
            Object.keys(comptes).forEach(function (k) { var b = choix.querySelector('[data-nb="' + k + '"]'); if (b) { b.textContent = String(comptes[k]); } });
            majBouton();
        });
        ok.addEventListener('click', function () {
            var n = comptes[cible()];
            confirmer('Envoyer ce message à ' + n + ' personne' + (n > 1 ? 's' : '') + ' ?',
                'Chacun le recevra dans sa discussion privée avec vous ; ceux qui sont connectés verront une bulle et entendront le son. Le message ne pourra pas être retiré d’un seul coup.', 'Envoyer')
                .then(function (oui) {
                    if (!oui) { return; }
                    ok.disabled = true;
                    libelle.textContent = 'Envoi en cours…';
                    poster('diffuser', { cible: cible(), texte: texte.value, cle: cleEnvoi }).then(function (r) {
                        if (!r.ok) { majBouton(); return; }
                        d.close();
                        toast('Message envoyé à ' + r.envoyes + ' personne' + (r.envoyes > 1 ? 's' : '')
                            + (r.ignores ? ' (' + r.ignores + ' bloqué' + (r.ignores > 1 ? 's' : '') + ', non envoyé' + (r.ignores > 1 ? 's' : '') + ')' : '') + '.', 'success');
                        chargerListe();
                        boucle(true);
                    });
                });
        });
        d = dialogue('Message à plusieurs personnes', [
            h('p', { class: 'ms-aide', style: { margin: '0 0 12px' } }, 'Chacun le reçoit dans sa discussion privée avec vous : les réponses n’arrivent qu’à vous.'),
            h('fieldset', { class: 'ms-champ' }, h('legend', null, 'Destinataires'), choix),
            h('label', { class: 'ms-champ' }, h('span', null, 'Message'), texte)
        ], [h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { d.close(); } }, 'Annuler'), ok]);
        texte.focus();
    }

    /* Visionneuse : photo ou video en grand. */
    function visionner(m) {
        var f = m.fichier;
        if (!f || m.tmp) { return; }
        var d = h('dialog', { class: 'ms-visionneuse ms-racine', 'aria-label': f.nom });
        var media = f.genre === 'video'
            ? h('video', { src: urlFichier(f), controls: true, autoplay: true, playsinline: true })
            : h('img', { src: urlFichier(f), alt: f.nom });
        d.appendChild(media);
        d.appendChild(h('div', { class: 'ms-visionneuse__nom' }, (m.auteur_id === MOI ? 'Vous' : profil(m.auteur_id).nom) + ' · ' + (m.envoye_le ? fmtLong.format(new Date(m.envoye_le)) : '')));
        d.appendChild(h('div', { class: 'ms-visionneuse__outils' },
            h('a', { class: 'ms-rond', href: urlFichier(f, { telecharger: true }), download: f.nom, 'aria-label': 'Télécharger', title: 'Télécharger' }, ic('download')),
            h('button', { type: 'button', class: 'ms-rond', 'aria-label': 'Fermer', title: 'Fermer (Échap)', onclick: function () { d.close(); } }, ic('x-lg'))));
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
        d.addEventListener('close', function () { if (media.pause) { media.pause(); } d.remove(); });
        document.body.appendChild(d);
        d.showModal();
    }

    function bloquer(qui, oui) {
        var faire = function () {
            poster('bloquer', { compte: qui, valeur: oui ? '1' : '0' }).then(function (d) {
                if (!d.ok) { return; }
                toast(oui ? 'Personne bloquée.' : 'Personne débloquée.');
                S.conv.forEach(function (c) {
                    if (c.type === 'direct' && c.autre && c.autre.id === qui) { c.blocage = oui ? 'moi' : (c.blocage === 'moi' ? null : c.blocage); }
                });
                FILS.forEach(function (F) { F.majSaisie(); });
                if (MODE === 'page' && !PAGE.infos.hidden) { PAGE.dessinerInfos(); }
                boucle(true);
            });
        };
        if (!oui) { faire(); return; }
        confirmer('Bloquer ' + profil(qui).nom + ' ?', 'Cette personne ne pourra plus vous écrire dans votre discussion à deux, et vous non plus. Les groupes ne changent pas. Elle n’est pas prévenue.', 'Bloquer', true)
            .then(function (ok) { if (ok) { faire(); } });
    }

    function basculerSourdine(id) {
        var c = S.conv.get(id);
        if (!c) { return; }
        var v = !c.sourdine;
        poster('sourdine', { conversation: id, valeur: v ? '1' : '0' }).then(function (d) {
            if (!d.ok) { return; }
            c.sourdine = v;
            redessinerListes();
            if (MODE === 'page' && !PAGE.infos.hidden) { PAGE.dessinerInfos(); }
        });
    }

    /* ============================================================ notifications */

    /*
     * Son d'arrivee, joue par le navigateur (pas de fichier a telecharger).
     * Les navigateurs n'autorisent le son qu'apres un premier geste sur la
     * page : le contexte audio est donc ouvert au premier clic ou a la
     * premiere touche, puis reutilise.
     */
    var audioCtx = null;
    function deverrouillerSon() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') { audioCtx.resume(); }
        } catch (e) { audioCtx = null; }
    }
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (t) { document.addEventListener(t, deverrouillerSon, { capture: true, passive: true }); });

    /* Deux notes montantes ; plus discretes si la discussion est deja sous les yeux. */
    function bip(doux) {
        try {
            if (!audioCtx || audioCtx.state !== 'running') { deverrouillerSon(); }
            if (!audioCtx) { return; }
            var t = audioCtx.currentTime + .01;
            var fort = doux ? 0.035 : 0.11;
            [[587.33, 0], [880, .1]].forEach(function (n) {
                var o = audioCtx.createOscillator(), g = audioCtx.createGain();
                o.type = 'sine';
                o.frequency.value = n[0];
                g.gain.setValueAtTime(0.0001, t + n[1]);
                g.gain.exponentialRampToValueAtTime(fort, t + n[1] + .015);
                g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + .22);
                o.connect(g); g.connect(audioCtx.destination);
                o.start(t + n[1]); o.stop(t + n[1] + .24);
            });
        } catch (e) { /* son indisponible */ }
    }

    /*
     * Titre de l'onglet, comme Facebook : quand un message arrive alors que
     * l'onglet n'est pas regarde, il alterne « Rasoa vous a envoye un
     * message » et le titre de la page, jusqu'au retour sur l'onglet.
     */
    var onglet = { minuterie: null, texte: '', alterne: false };
    function titreCompte() {
        var n = nombreNonLus();
        return (n ? '(' + n + ') ' : '') + titreBase;
    }
    function alerterOnglet(texte) {
        onglet.texte = texte;
        document.title = texte;
        onglet.alterne = true;
        if (onglet.minuterie) { return; }
        onglet.minuterie = window.setInterval(function () {
            onglet.alterne = !onglet.alterne;
            document.title = onglet.alterne ? onglet.texte : titreCompte();
        }, 1500);
    }
    function calmerOnglet() {
        if (!onglet.minuterie) { return; }
        window.clearInterval(onglet.minuterie);
        onglet.minuterie = null;
        document.title = titreCompte();
    }
    window.addEventListener('focus', calmerOnglet);
    document.addEventListener('visibilitychange', function () { if (!document.hidden && document.hasFocus()) { calmerOnglet(); } });

    function notificationsActives() {
        return 'Notification' in window && Notification.permission === 'granted' && lireReglage('notifications', '0') === '1';
    }
    function majBoutonsNotifications() {
        document.querySelectorAll('[data-ms-notifications]').forEach(function (b) {
            if (!('Notification' in window)) { b.hidden = true; return; }
            var actif = notificationsActives();
            b.setAttribute('aria-pressed', actif ? 'true' : 'false');
            b.title = actif ? 'Notifications activées (cliquer pour les couper)' : 'Activer les notifications du navigateur';
            b.setAttribute('aria-label', b.title);
            b.replaceChildren(ic(actif ? 'bell-fill' : 'bell'));
        });
    }
    function basculerNotifications() {
        if (!('Notification' in window)) { return; }
        if (notificationsActives()) {
            ecrireReglage('notifications', '0');
            majBoutonsNotifications();
            desabonnerPush();
            toast('Notifications coupées.');
            return;
        }
        if (Notification.permission === 'denied') { toast('Les notifications sont bloquées dans les réglages du navigateur pour ce site.', 'warning'); return; }
        Notification.requestPermission().then(function (p) {
            redessinerListes();   // l'invitation en tete de liste disparait
            if (p !== 'granted') { majBoutonsNotifications(); return; }
            ecrireReglage('notifications', '1');
            majBoutonsNotifications();
            /* Le premier abonnement aupres du service de push peut tarder :
               au-dela de 10 s, un message neutre ; l'abonnement continue. */
            var attente = new Promise(function (r) { window.setTimeout(function () { r(null); }, 10000); });
            Promise.race([abonnerPush(true), attente]).then(function (push) {
                var iPhoneNavigateur = window.PWA && window.PWA.iOS && !window.PWA.enApp;
                toast(push === true
                    ? 'Notifications activées : vous serez prévenu des nouveaux messages, même application fermée.'
                    : push === null
                        ? 'Notifications activées : vous serez prévenu des nouveaux messages.'
                        : 'Notifications activées : vous serez prévenu des nouveaux messages tant qu’une page est ouverte.'
                          + (iPhoneNavigateur ? ' Installez l’application pour les recevoir aussi quand elle est fermée.' : ''), 'success');
            });
        });
    }

    /*
     * Invitation en tete de la liste des discussions : la cloche est discrete,
     * et c'est elle qui fait d'un telephone un vrai moyen d'etre joint. Tant
     * que le navigateur n'a jamais pose la question ; « Plus tard » la range
     * pour une semaine.
     */
    function inviterNotifications() {
        if (!('Notification' in window) || Notification.permission !== 'default') { return false; }
        if (lireReglage('notifications', '0') === '1') { return false; }
        var plusTard = +lireReglage('invitation-notifications', '0');
        return !plusTard || Date.now() - plusTard > 7 * 86400000;
    }
    function invitationNotifications() {
        var ferme = pushPossible() ? 'même application fermée' : 'tant qu’une page est ouverte';
        return h('div', { class: 'ms-invitation', role: 'note' },
            h('span', { class: 'ms-invitation__icone' }, ic('bell')),
            h('div', { class: 'ms-invitation__texte' },
                h('strong', null, 'Être prévenu des nouveaux messages'),
                h('span', null, 'Une notification à chaque message, ' + ferme + '.')),
            h('div', { class: 'ms-invitation__actions' },
                h('button', { type: 'button', class: 'ms-bouton', onclick: basculerNotifications }, 'Activer'),
                h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () {
                    ecrireReglage('invitation-notifications', String(Date.now()));
                    redessinerListes();
                } }, 'Plus tard')));
    }

    /*
     * Notifications « push » : prevenu meme application fermee. Le
     * navigateur s'abonne aupres de son service (Google, Apple, Mozilla) avec
     * la cle de l'application, et donne l'adresse obtenue au serveur
     * (data/push.php), qui y envoie chaque message ; /sw.js l'affiche.
     * Sur iPhone, seulement dans l'application installee (iOS 16.4 et plus).
     */
    var PUSH = { actif: false, reg: null };
    function pushPossible() {
        return !!(S.reglages.push && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
    }
    function clePush() {
        var b = S.reglages.push.replace(/-/g, '+').replace(/_/g, '/');
        var s = atob(b + '==='.slice((b.length + 3) % 4)), u = new Uint8Array(s.length);
        for (var i = 0; i < s.length; i++) { u[i] = s.charCodeAt(i); }
        return u;
    }
    function memeCle(tampon, cle) {
        if (!tampon) { return true; }
        var a = new Uint8Array(tampon);
        if (a.length !== cle.length) { return false; }
        for (var i = 0; i < a.length; i++) { if (a[i] !== cle[i]) { return false; } }
        return true;
    }
    function enregistrementPush() {
        if (PUSH.reg) { return Promise.resolve(PUSH.reg); }
        return navigator.serviceWorker.register(CFG.base + '/sw.js', { scope: CFG.base + '/' })
            .then(function () { return navigator.serviceWorker.ready; })
            .then(function (r) { PUSH.reg = r; return r; });
    }
    function nomAppareil() {
        var u = navigator.userAgent;
        var sys = /iPhone|iPad/.test(u) ? 'iPhone/iPad' : /Android/.test(u) ? 'Android' : /Windows/.test(u) ? 'Windows' : /Mac OS/.test(u) ? 'Mac' : /Linux/.test(u) ? 'Linux' : 'Appareil';
        var nav = /Edg\//.test(u) ? 'Edge' : /OPR\//.test(u) ? 'Opera' : /Firefox\//.test(u) ? 'Firefox' : /Chrome\//.test(u) ? 'Chrome' : /Safari\//.test(u) ? 'Safari' : 'Navigateur';
        return nav + ' · ' + sys + (window.PWA && window.PWA.enApp ? ' · application' : '');
    }
    /* force : renvoyer l'abonnement au serveur meme s'il l'a deja recu dans
       cette session (activation par la cloche). */
    function abonnerPush(force) {
        if (!pushPossible()) { return Promise.resolve(false); }
        var cle = clePush();
        return enregistrementPush().then(function (reg) {
            return reg.pushManager.getSubscription().then(function (ab) {
                /* Cle de l'application changee : l'ancien abonnement ne marche plus. */
                if (ab && !memeCle(ab.options && ab.options.applicationServerKey, cle)) {
                    return ab.unsubscribe().then(function () { return null; });
                }
                return ab;
            }).then(function (ab) {
                return ab || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: cle });
            });
        }).then(function (ab) {
            var j = ab.toJSON();
            /* Une fois par session du navigateur : le serveur rend l'abonnement
               a ce compte (appareil partage) et sait qu'il sert encore. */
            if (!force && lireSession('push', '') === j.endpoint) { PUSH.actif = true; return true; }
            return poster('push_abonner', { adresse: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth, appareil: nomAppareil() })
                .then(function (d) {
                    PUSH.actif = !!d.ok;
                    if (d.ok) { ecrireSession('push', j.endpoint); }
                    return PUSH.actif;
                });
        }).catch(function () { PUSH.actif = false; return false; });
    }
    /* Couper : le serveur oublie l'adresse, le navigateur se desabonne. */
    function desabonnerPush() {
        PUSH.actif = false;
        ecrireSession('push', '');
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) { return Promise.resolve(); }
        return navigator.serviceWorker.getRegistration(CFG.base + '/').then(function (reg) {
            return reg ? reg.pushManager.getSubscription() : null;
        }).then(function (ab) {
            if (!ab) { return; }
            return appel('POST', 'push_desabonner', { adresse: ab.endpoint }).catch(function () {}).then(function () { return ab.unsubscribe(); });
        }).catch(function () {});
    }
    /* Au demarrage : notifications voulues -> abonnement a jour ; sinon, ce
       navigateur ne doit plus recevoir celles d'un autre compte. */
    function verifierPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) { return; }
        if (notificationsActives()) { abonnerPush(false); return; }
        navigator.serviceWorker.getRegistration(CFG.base + '/').then(function (reg) {
            return reg ? reg.pushManager.getSubscription() : null;
        }).then(function (ab) { if (ab) { desabonnerPush(); } }).catch(function () {});
    }

    function notifier(m) {
        var c = S.conv.get(m.conversation_id);
        if (c && c.sourdine) { return; }
        var affichee = convAffichee(m.conversation_id);
        var regardee = !document.hidden && document.hasFocus();
        bip(affichee && regardee);
        if (MODE === 'dock' && !affichee) { DOCK.arrivee(m); }
        var p = profil(m.auteur_id);
        if (!regardee) {
            alerterOnglet(c && c.type === 'groupe'
                ? prenom(m.auteur_id) + ' a écrit dans « ' + c.titre + ' »'
                : p.nom + ' vous a envoyé un message');
        }
        /* Avec le push, /sw.js affiche deja la notification : pas de doublon. */
        if (document.hidden && notificationsActives() && !PUSH.actif) {
            var corps = m.texte || apercuFichier(m.fichier);
            try {
                var n = new Notification(c && c.type === 'groupe' ? c.titre : p.nom, {
                    body: (c && c.type === 'groupe' ? prenom(m.auteur_id) + ' : ' : '') + corps,
                    icon: p.avatar || (CFG.base + '/file/logo-coldbloud.png'), tag: 'messagerie-' + m.conversation_id
                });
                n.onclick = function () { window.focus(); ouvrirConversation(m.conversation_id); n.close(); };
            } catch (e) { /* notifications indisponibles */ }
        }
    }

    /* Titre de l'onglet et pastilles (barre du haut, menu, accueil etudiant, dock). */
    function nombreNonLus() {
        if (!S.listeChargee) { return S.nonLus; }
        var n = 0;
        S.conv.forEach(function (c) { if (c.non_lus > 0 && !c.sourdine) { n++; } });
        return n;
    }
    function majTitre() {
        var n = nombreNonLus();
        if (!onglet.minuterie) { document.title = titreCompte(); }
        document.querySelectorAll('[data-msg-pastille]').forEach(function (el) {
            el.hidden = !n;
            el.textContent = n > 99 ? '99+' : (n ? String(n) : '');
        });
        document.querySelectorAll('[data-msg-lien]').forEach(function (a) {
            a.setAttribute('aria-label', 'Messagerie' + (n ? ' : ' + n + ' discussion' + (n > 1 ? 's' : '') + ' non lue' + (n > 1 ? 's' : '') : ''));
        });
        /* Pastille sur l'icone de l'application installee (posee par /sw.js a l'arrivee d'un push). */
        if (navigator.setAppBadge) {
            try { (n ? navigator.setAppBadge(n) : navigator.clearAppBadge()).catch(function () {}); } catch (e) { /* non gere */ }
        }
    }

    var listes = [];   // listes de discussions a redessiner (page, panneau du dock)
    function redessinerListes() { listes.forEach(function (l) { l.dessiner(); }); majTitre(); }

    /* ============================================================ interrogation */

    var minuterie = null, enCours = false, relancer = false;

    function delai() {
        if (S.echecs) { return Math.min(30000, 2500 * Math.pow(2, S.echecs)); }
        if (document.hidden) { return MODE === 'page' ? 15000 : 30000; }
        if (MODE === 'page') { return 2500; }
        if (FILS.some(function (F) { return F.id && !F.reduit; })) { return 3000; }
        if (DOCK && DOCK.panneauOuvert()) { return 5000; }
        return FILS.length ? 8000 : 15000;   // bulles seules : la conversation est en cours
    }

    function boucle(immediat) {
        window.clearTimeout(minuterie);
        if (S.arret) { return; }
        if (immediat && enCours) { relancer = true; return; }
        if (!immediat) { minuterie = window.setTimeout(interroger, delai()); return; }
        interroger();
    }

    function interroger() {
        if (S.arret || enCours) { return; }
        enCours = true;
        var params = { curseur: S.curseur };
        var ouvertes = FILS.filter(function (F) { return F.id; }).map(function (F) { return F.id; });
        if (ouvertes.length) { params.ouvertes = ouvertes.join(','); }
        var t = Date.now();
        if (t - S.derniereGarde > 5 * 60000) { params.garder = 1; S.derniereGarde = t; }
        if (S.listeChargee && t - S.dernierePresence > 60000) { params.presences = 1; S.dernierePresence = t; }
        /* Page sous les yeux : le serveur n'envoie pas de push (data/push.php). */
        if (document.visibilityState === 'visible') { params.vue = 1; }
        appel('GET', 'etat', params).then(function (d) {
            if (!d.ok) { if (d.statut !== 401) { S.echecs++; } return; }
            S.echecs = 0;
            appliquerEtat(d);
        }, function () { S.echecs++; }).then(function () {
            enCours = false;
            if (relancer) { relancer = false; boucle(true); } else { boucle(false); }
        });
    }

    function appliquerEtat(d) {
        if (d.recharger) {
            S.fils = new Map();
            S.curseur = d.curseur || S.curseur;
            chargerListe().then(function () { FILS.forEach(function (F) { if (F.id) { F.ouvrir(F.id); } }); });
            return;
        }
        S.curseur = Math.max(S.curseur, d.curseur || 0);
        retenirProfils(d.profils);
        if (d.presences) { S.presences = d.presences; }
        if (typeof d.non_lus === 'number') { S.nonLus = d.non_lus; }
        (d.conversations || []).forEach(function (c) {
            var avant = S.conv.get(c.id);
            if (convAffichee(c.id)) { c.non_lus = 0; }
            S.conv.set(c.id, c);
            retenirProfils(c.vus || []);
            FILS.forEach(function (F) {
                if (F.id !== c.id) { return; }
                if (avant && (avant.titre !== c.titre || avant.membre !== c.membre || avant.nb_membres !== c.nb_membres)) { chargerDetails(c.id); }
                F.majTete();
                F.majSaisie();
            });
            if (MODE === 'page' && PAGE.fil.id === c.id && avant && (avant.blocage !== c.blocage || avant.sourdine !== c.sourdine) && !PAGE.infos.hidden) { PAGE.dessinerInfos(); }
        });
        (d.messages || []).forEach(function (m) {
            var neuf = recevoirMessage(m, true);
            if (neuf && m.auteur_id !== MOI && m.type !== 'systeme' && !m.retire) { notifier(m); }
        });
        (d.lectures || []).forEach(function (l) {
            /* La liste : mon dernier message vient d'etre lu par l.user_id. */
            var cl = S.conv.get(l.conversation_id);
            if (cl && cl.dernier && cl.dernier.auteur_id === MOI && l.lu_jusqu_a >= cl.dernier.id) {
                cl.vus_ids = cl.vus_ids || [];
                if (cl.vus_ids.indexOf(l.user_id) < 0) { cl.vus_ids.push(l.user_id); }
            }
            var det = S.details.get(l.conversation_id);
            if (!det) { return; }
            det.membres.forEach(function (mb) { if (mb.id === l.user_id) { mb.lu_jusqu_a = Math.max(mb.lu_jusqu_a || 0, l.lu_jusqu_a); } });
            filsDe(l.conversation_id).forEach(function (F) { F.lectures(); });
        });
        var ecrivent = d.ecrivent || {};
        FILS.forEach(function (F) {
            var ids = (F.id && ecrivent[F.id]) || [];
            if (ids.join(',') !== F.ecrivent.join(',')) { F.ecrivent = ids; F.dessinerEcrivent(); }
        });
        if (d.presences) { FILS.forEach(function (F) { F.majTete(); }); }
        redessinerListes();
        if (d.messages && d.messages.length) { FILS.forEach(function (F) { F.marquerLu(); }); }
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { boucle(true); FILS.forEach(function (F) { F.marquerLu(); }); }
    });
    window.addEventListener('online', function () { S.echecs = 0; boucle(true); });

    /* Liste des discussions et presences : au demarrage de la page, a
       l'ouverture du panneau ou d'une fenetre du dock. */
    var listePromesse = null;
    function chargerListe() {
        listePromesse = appel('GET', 'demarrer').then(function (d) {
            if (!d.ok) { listePromesse = null; return; }
            S.reglages = d.reglages || S.reglages;
            S.presences = d.presences || {};
            S.dernierePresence = Date.now();
            retenirProfils([d.moi]);
            S.conv = new Map();
            (d.conversations || []).forEach(function (c) { S.conv.set(c.id, c); if (c.autre) { retenirProfils([c.autre]); } retenirProfils(c.avatars); retenirProfils(c.vus || []); });
            if (!S.curseur) { S.curseur = d.curseur || 0; }
            S.listeChargee = true;
            redessinerListes();
            majBoutonsNotifications();
        }, function () {
            listePromesse = null;
            toast('La messagerie ne répond pas. Nouvel essai dans un instant…', 'error');
        });
        return listePromesse;
    }
    function listePrete() { return S.listeChargee ? Promise.resolve() : (listePromesse || chargerListe()); }

    function ouvrirConversation(id) {
        if (MODE === 'page') { PAGE.ouvrir(id); } else { DOCK.ouvrirFenetre(id, { focus: true }); }
    }

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && popActif) { fermerPop(); } });

    /* ================================================================== PAGE */

    var PAGE = null;

    function demarrerPage() {
        var racine = document.getElementById('messagerie');
        var elListe = document.getElementById('msListe');
        var elFil = document.getElementById('msFil');
        var elInfos = document.getElementById('msInfos');
        var elRecherche = document.getElementById('msRecherche');
        var accueilInitial = elFil.firstElementChild ? elFil.firstElementChild.cloneNode(true) : null;
        PAGE = { racine: racine, infos: elInfos };
        var infosVoulues = lireReglage('infos', '1') === '1';

        function ouvrir(id) {
            racine.dataset.vue = 'fil';
            fil.ouvrir(id);
        }
        function allerA(hash) {
            try {
                if (!grand() && location.hash !== hash) { history.pushState({ ms: hash }, '', hash); }
                else { history.replaceState({ ms: hash }, '', hash); }
            } catch (e) { /* adresse inchangee */ }
        }
        function revenirListe() {
            racine.dataset.vue = 'liste';
            afficherInfos(false);
            elRecherche.focus({ preventScroll: true });
        }

        var fil = creerFil(elFil, 'page', {
            retour: revenirListe,
            infos: function () { basculerInfos(); },
            infosOuvertes: function () { return !elInfos.hidden; },
            ouvrirConv: ouvrir,
            change: function (F) {
                racine.dataset.vue = 'fil';
                if (F.id) { allerA('#c' + F.id); afficherInfos(window.matchMedia('(min-width: 1181px)').matches && infosVoulues); }
                else if (F.brouillon) { allerA('#a' + F.brouillon.profil.id); afficherInfos(false); }
                else { afficherInfos(false); }
            }
        });
        PAGE.fil = fil;
        PAGE.ouvrir = ouvrir;

        var liste = listeAvecRecherche(elListe, elRecherche, ouvrir, function (p) { racine.dataset.vue = 'fil'; fil.ecrireA(p); }, function () { return fil.id; });
        listes.push(liste);

        window.addEventListener('popstate', function () {
            var m = /^#c(\d+)$/.exec(location.hash);
            if (m && S.conv.has(+m[1])) { if (fil.id !== +m[1]) { fil.ouvrir(+m[1]); } racine.dataset.vue = 'fil'; }
            else { racine.dataset.vue = 'liste'; afficherInfos(false); }
        });

        function afficherInfos(oui) {
            elInfos.hidden = !oui || !fil.id;
            var b = fil.E.tete && fil.E.tete.querySelector('[data-infos]');
            if (b) { b.setAttribute('aria-pressed', !elInfos.hidden ? 'true' : 'false'); }
            if (!elInfos.hidden) { dessinerInfos(); }
        }
        function basculerInfos() {
            var oui = elInfos.hidden;
            afficherInfos(oui);
            if (window.matchMedia('(min-width: 1181px)').matches) { infosVoulues = oui; ecrireReglage('infos', oui ? '1' : '0'); }
        }

        function dessinerInfos() {
            var id = fil.id;
            var c = S.conv.get(id);
            var det = S.details.get(id);
            vider(elInfos);
            if (!c) { return; }
            elInfos.appendChild(h('button', { type: 'button', class: 'ms-rond ms-rond--nu ms-infos__fermer', 'aria-label': 'Fermer les informations', onclick: function () { afficherInfos(false); } }, ic('x-lg')));
            var groupe = c.type === 'groupe';
            var admin = groupe && det && det.role === 'admin' && c.membre;
            elInfos.appendChild(h('div', { class: 'ms-infos__tete' }, avatarConv(c, 80),
                h('h2', { class: 'ms-infos__nom' }, c.titre),
                h('p', { class: 'ms-infos__role' }, groupe ? c.nb_membres + ' membre' + (c.nb_membres > 1 ? 's' : '') : (c.autre ? c.autre.role : ''))));

            var actions = h('div', { class: 'ms-infos__actions' });
            function action(icone, libelle, fn, presse) {
                actions.appendChild(h('button', { type: 'button', class: 'ms-infos__action', onclick: fn, 'aria-pressed': presse === undefined ? null : (presse ? 'true' : 'false') },
                    h('span', { class: 'ms-rond' + (presse ? ' ms-rond--accent' : '') }, ic(icone)), libelle));
            }
            if (c.membre) { action(c.sourdine ? 'bell-slash-fill' : 'bell-fill', c.sourdine ? 'Réactiver' : 'Sourdine', function () { basculerSourdine(id); }, c.sourdine); }
            if (admin) { action('person-plus-fill', 'Ajouter', function () { choisirPersonnes('ajouter', id); }); }
            if (admin) { action('pencil-fill', 'Renommer', function () { renommer(id); }); }
            elInfos.appendChild(actions);

            if (groupe && det) {
                var corps = h('div', { class: 'ms-infos__corps' });
                det.membres.forEach(function (mb) {
                    var ligne = h('div', { class: 'ms-membre' }, avatar(mb, 36, true),
                        h('span', { style: { minWidth: '0' } }, h('span', { class: 'ms-membre__nom' }, mb.id === MOI ? mb.nom + ' (vous)' : mb.nom),
                            h('span', { class: 'ms-membre__role' }, (mb.admin ? 'Administrateur · ' : '') + (mb.role || ''))));
                    if (mb.id !== MOI) {
                        ligne.appendChild(h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Actions pour ' + mb.nom, onclick: function (e) { menuMembre(mb, e.currentTarget, admin); } }, ic('three-dots')));
                    } else { ligne.appendChild(h('span')); }
                    corps.appendChild(ligne);
                });
                if (admin) {
                    corps.appendChild(h('button', { type: 'button', class: 'ms-menu__item', onclick: function () { choisirPersonnes('ajouter', id); } }, ic('person-plus'), 'Ajouter des personnes'));
                }
                elInfos.appendChild(h('details', { open: true }, h('summary', null, 'Membres (' + det.membres.length + ')'), corps));
            }

            var medias = h('div', { class: 'ms-infos__corps' }, h('p', { class: 'ms-infos__vide' }, 'Chargement…'));
            var docs = h('div', { class: 'ms-infos__corps' }, h('p', { class: 'ms-infos__vide' }, 'Chargement…'));
            var dMedias = h('details', null, h('summary', null, 'Photos et vidéos'), medias);
            var dDocs = h('details', null, h('summary', null, 'Fichiers et vocaux'), docs);
            dMedias.addEventListener('toggle', function () { if (dMedias.open) { chargerMedias(id, 'medias', medias); } });
            dDocs.addEventListener('toggle', function () { if (dDocs.open) { chargerMedias(id, 'fichiers', docs); } });
            elInfos.appendChild(dMedias);
            elInfos.appendChild(dDocs);

            var confid = h('div', { class: 'ms-infos__corps' });
            if (c.type === 'direct' && c.autre) {
                var bloque = c.blocage === 'moi';
                confid.appendChild(h('button', { type: 'button', class: 'ms-menu__item' + (bloque ? '' : ' ms-menu__item--danger'),
                    onclick: function () { bloquer(c.autre.id, !bloque); } }, ic(bloque ? 'person-check' : 'slash-circle'), bloque ? 'Débloquer' : 'Bloquer'));
            }
            if (groupe && c.membre) {
                confid.appendChild(h('button', { type: 'button', class: 'ms-menu__item ms-menu__item--danger', onclick: function () { quitter(id); } }, ic('box-arrow-right'), 'Quitter le groupe'));
            }
            confid.appendChild(h('button', { type: 'button', class: 'ms-menu__item ms-menu__item--danger', onclick: function () { effacer(id); } }, ic('trash3'), 'Supprimer la discussion'));
            elInfos.appendChild(h('details', null, h('summary', null, 'Confidentialité'), confid));
        }
        PAGE.dessinerInfos = dessinerInfos;

        function chargerMedias(id, genre, boite) {
            appel('GET', 'medias', { conversation: id, genre: genre }).then(function (d) {
                vider(boite);
                if (!d.ok || !d.messages.length) { boite.appendChild(h('p', { class: 'ms-infos__vide' }, genre === 'medias' ? 'Aucune photo ni vidéo.' : 'Aucun fichier.')); return; }
                if (genre === 'medias') {
                    var grille = h('div', { class: 'ms-medias' });
                    d.messages.forEach(function (m) {
                        var f = m.fichier;
                        if (!f) { return; }
                        grille.appendChild(h('button', { type: 'button', 'aria-label': f.nom, onclick: function () { visionner(m); } },
                            f.genre === 'image' ? h('img', { src: urlFichier(f, { apercu: true }), alt: '', loading: 'lazy' }) : h('video', { src: urlFichier(f) + '#t=0.1', preload: 'metadata', muted: true }),
                            f.genre === 'video' ? h('span', { class: 'bi bi-play-circle-fill', 'aria-hidden': 'true' }) : null));
                    });
                    boite.appendChild(grille);
                } else {
                    d.messages.forEach(function (m) {
                        var f = m.fichier;
                        if (!f) { return; }
                        boite.appendChild(h('a', { class: 'ms-menu__item', href: urlFichier(f, { telecharger: true }), download: f.nom },
                            ic(iconeFichier(f.nom, f.genre)), h('span', { style: { minWidth: '0', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, f.nom),
                            h('small', { style: { marginLeft: 'auto', color: 'rgb(var(--c-text-3))' } }, taille(f.taille))));
                    });
                }
            }).catch(function () {});
        }

        function menuMembre(mb, ancre, admin) {
            var menu = h('div', { class: 'ms-pop', role: 'menu' });
            function item(icone, libelle, fn, danger) {
                menu.appendChild(h('button', { type: 'button', role: 'menuitem', class: 'ms-menu__item' + (danger ? ' ms-menu__item--danger' : ''), onclick: function () { fermerPop(); fn(); } }, ic(icone), libelle));
            }
            item('chat-dots', 'Envoyer un message', function () { fil.ecrireA(mb); });
            if (admin) {
                item(mb.admin ? 'shield-minus' : 'shield-plus', mb.admin ? 'Retirer le rôle d’administrateur' : 'Nommer administrateur', function () {
                    poster('role', { conversation: fil.id, membre: mb.id, role: mb.admin ? 'membre' : 'admin' }).then(function (d) { if (d.ok) { chargerDetails(fil.id); } });
                });
                item('person-dash', 'Retirer du groupe', function () {
                    confirmer('Retirer ' + mb.nom + ' ?', 'Cette personne ne verra plus les nouveaux messages du groupe.', 'Retirer', true).then(function (oui) {
                        if (oui) { poster('exclure', { conversation: fil.id, membre: mb.id }).then(function (d) { if (d.ok) { chargerDetails(fil.id); } }); }
                    });
                }, true);
            }
            ouvrirPop(menu, ancre);
        }

        function quitter(id) {
            confirmer('Quitter le groupe ?', 'Vous ne recevrez plus ses messages. Un administrateur pourra vous y ajouter de nouveau.', 'Quitter', true).then(function (oui) {
                if (oui) { poster('quitter', { conversation: id }).then(function (d) { if (d.ok) { chargerDetails(id); } }); }
            });
        }

        function effacer(id) {
            confirmer('Supprimer la discussion ?', 'Ses messages disparaîtront de votre messagerie seulement ; les autres membres les gardent. Elle reviendra si quelqu’un écrit.', 'Supprimer', true).then(function (oui) {
                if (!oui) { return; }
                poster('effacer', { conversation: id }).then(function (d) {
                    if (!d.ok) { return; }
                    S.conv.delete(id);
                    S.fils.delete(id);
                    fil.garderTexte();
                    fil.id = null;
                    fil.E = {};
                    afficherInfos(false);
                    vider(elFil);
                    if (accueilInitial) { elFil.appendChild(accueilInitial.cloneNode(true)); }
                    racine.dataset.vue = 'liste';
                    try { history.replaceState(null, '', location.pathname + location.search); } catch (e) { /* rien */ }
                    redessinerListes();
                });
            });
        }

        /* Administration (superadmin) : message a plusieurs personnes, messages signales. */
        function menuAdmin(ancre) {
            var menu = h('div', { class: 'ms-pop', role: 'menu' });
            if (S.reglages.diffusion) {
                menu.appendChild(h('button', { type: 'button', role: 'menuitem', class: 'ms-menu__item', onclick: function () { fermerPop(); diffuser(); } },
                    ic('megaphone'), 'Message à plusieurs personnes'));
            }
            if (S.reglages.moderateur) {
                menu.appendChild(h('button', { type: 'button', role: 'menuitem', class: 'ms-menu__item', onclick: function () { fermerPop(); moderer(); } },
                    ic('flag'), 'Messages signalés' + (S.modNb ? ' (' + S.modNb + ')' : '')));
            }
            ouvrirPop(menu, ancre);
        }

        /* Moderation (superadmin) : les messages signales. */
        function moderer() {
            var contenu = h('div', null, h('p', { class: 'ms-infos__vide' }, 'Chargement…'));
            var d = dialogue('Messages signalés', contenu);
            d.style.width = 'min(640px, calc(100vw - 24px))';
            function charger() {
                appel('GET', 'signalements').then(function (r) {
                    vider(contenu);
                    if (!r.ok) { contenu.appendChild(h('p', null, r.message || 'Indisponible.')); return; }
                    S.modNb = r.signalements.length;
                    majModeration();
                    if (!r.signalements.length) { contenu.appendChild(h('p', { class: 'ms-infos__vide' }, 'Aucun message signalé.')); return; }
                    r.signalements.forEach(function (s) {
                        var texte = s.texte || (s.fichier ? '📎 ' + s.fichier.nom : '(sans texte)');
                        contenu.appendChild(h('article', { class: 'ms-signalement' },
                            h('div', { class: 'ms-signalement__tete' }, h('span', null, s.lieu + ' · ' + libelleListe(s.envoye_le)), h('span', null, 'Signalé ' + ilYa(s.le))),
                            h('p', { style: { margin: '0 0 6px', fontWeight: '600' } }, (s.auteur ? s.auteur.nom : '?'), h('span', { style: { fontWeight: '400', color: 'rgb(var(--c-text-3))' } }, s.auteur ? ' · ' + s.auteur.role : '')),
                            h('blockquote', null, texte),
                            h('p', { style: { margin: '0 0 10px', fontSize: '13px', color: 'rgb(var(--c-text-2))' } }, 'Par ' + (s.par ? s.par.nom : '?') + (s.motif ? ' : « ' + s.motif + ' »' : ' (sans motif)')),
                            h('div', { class: 'ms-signalement__actions' },
                                h('button', { type: 'button', class: 'ms-bouton ms-bouton--doux', onclick: function () { traiter(s.id, 'rejete'); } }, 'Rejeter'),
                                s.retire ? null : h('button', { type: 'button', class: 'ms-bouton ms-bouton--danger', onclick: function () { traiter(s.id, 'retire'); } }, 'Retirer le message'))));
                    });
                });
            }
            function traiter(id, decision) {
                poster('traiter', { signalement: id, decision: decision }).then(function (r) { if (r.ok) { toast(decision === 'retire' ? 'Message retiré.' : 'Signalement rejeté.'); charger(); } });
            }
            charger();
        }
        function majModeration() {
            var b = racine.querySelector('[data-moderation-nb]');
            if (!b) { return; }
            b.hidden = !S.modNb;
            b.textContent = S.modNb > 99 ? '99+' : String(S.modNb);
        }
        function compterSignalements() {
            if (!S.reglages.moderateur) { return; }
            appel('GET', 'signalements').then(function (r) { if (r.ok) { S.modNb = r.signalements.length; majModeration(); } }).catch(function () {});
        }

        racine.addEventListener('click', function (e) {
            var b = e.target.closest('[data-action]');
            if (!b || !racine.contains(b)) { return; }
            var a = b.dataset.action;
            if (a === 'nouveau') { afficherInfos(false); racine.dataset.vue = 'fil'; fil.nouveau(); }
            else if (a === 'groupe') { choisirPersonnes('groupe', null, ouvrir); }
            else if (a === 'notifications') { basculerNotifications(); }
            else if (a === 'admin') { menuAdmin(b); }
        });
        var cloche = racine.querySelector('[data-action="notifications"]');
        if (cloche) { cloche.setAttribute('data-ms-notifications', ''); }

        listes[0].dessiner();
        chargerListe().then(function () {
            racine.removeAttribute('aria-busy');
            racine.querySelector('[data-action="groupe"]').hidden = !S.reglages.peut_creer_groupe;
            racine.querySelector('[data-action="admin"]').hidden = !S.reglages.moderateur && !S.reglages.diffusion;
            var mc = /^#c(\d+)$/.exec(location.hash), ma = /^#a(\d+)$/.exec(location.hash);
            if (mc && S.conv.has(+mc[1])) { ouvrir(+mc[1]); }
            else if (ma) {
                /* Lien « ecrire a » depuis une autre page ou une fenetre : /messagerie#a45 */
                appel('GET', 'profil', { id: +ma[1] }).then(function (r) {
                    if (r.ok) { racine.dataset.vue = 'fil'; fil.ecrireA(r.profil); } else { toast(r.message || 'Compte introuvable.', 'warning'); }
                });
            }
            compterSignalements();
            window.setInterval(compterSignalements, 120000);
            boucle(false);
        });
    }

    /* ================================================================== DOCK */

    /*
     * Comme Facebook : en bas a droite, au-dessus du pied de page (la date et
     * l'heure de l'application restent visibles), un bouton rond « Messagerie »
     * avec le nombre de non lus. Il ouvre la liste des discussions au-dessus de
     * lui. Une discussion s'ouvre dans une fenetre a gauche ; reduite, elle
     * devient une bulle ronde au-dessus du bouton, avec ses non lus. Un message
     * recu fait apparaitre (ou sautiller) la bulle de sa discussion.
     * Sur telephone, le bouton mene a la page /messagerie.
     */
    var DOCK = null;

    function demarrerDock() {
        /* Pages de l'espace etudiant sans les jetons ni les icones de l'application */
        if (!getComputedStyle(document.documentElement).getPropertyValue('--c-canvas').trim()) {
            document.head.appendChild(h('link', { rel: 'stylesheet', href: CFG.base + '/src/css/tokens.css' }));
        }
        if (!document.querySelector('link[href*="bootstrap-icons"]')) {
            document.head.appendChild(h('link', { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' }));
        }

        var etat = lireDurable('dock', null) || { fenetres: [] };
        var fenetres = [];   // { F, el, tete } — la plus recemment ouverte d'abord
        var dock = h('div', { class: 'ms-dock ms-racine', role: 'region', 'aria-label': 'Messagerie' });
        /* Au-dessus du pied de page ; plus haut si la page a ses propres
           pastilles en bas a droite (couleurs de l'accueil etudiant). */
        if (document.querySelector('.color-controls')) { dock.style.setProperty('--ms-bas', '84px'); }
        else if (!document.querySelector('.footer-bleu-nuit')) { dock.style.setProperty('--ms-bas', '20px'); }

        var zoneFenetres = h('div', { class: 'ms-dock__fenetres' });
        var zoneTetes = h('div', { class: 'ms-dock__tetes' });
        var lanceur = h('button', { type: 'button', class: 'ms-lanceur', 'aria-haspopup': 'dialog', 'aria-expanded': 'false', 'data-msg-lien': '', title: 'Messagerie' },
            h('i', { class: 'bi bi-chat-dots-fill ms-lanceur__ouvrir', 'aria-hidden': 'true' }),
            h('i', { class: 'bi bi-x-lg ms-lanceur__fermer', 'aria-hidden': 'true' }),
            h('span', { class: 'ms-lanceur__nb', 'data-msg-pastille': '', hidden: true }));
        var recherche = h('input', { type: 'search', placeholder: 'Rechercher', autocomplete: 'off', spellcheck: 'false', 'aria-label': 'Rechercher une discussion ou une personne' });
        var corpsListe = h('div', { class: 'ms-liste__corps' });
        var panneau = h('section', { class: 'ms-dock__panneau', role: 'dialog', 'aria-label': 'Discussions', hidden: true },
            h('header', { class: 'ms-dock__tete' }, h('h2', null, 'Discussions'),
                h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'data-ms-notifications': '', onclick: basculerNotifications }, ic('bell')),
                S.reglages.peut_creer_groupe ? h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Nouveau groupe', title: 'Nouveau groupe',
                    onclick: function () { listePrete().then(function () { choisirPersonnes('groupe', null, function (id) { ouvrirFenetre(id, { focus: true }); }); }); } }, ic('people')) : null,
                h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Nouveau message', title: 'Nouveau message', onclick: function () { ouvrirFenetre(null, { nouveau: true, focus: true }); } }, ic('pencil-square')),
                S.reglages.diffusion ? h('button', { type: 'button', class: 'ms-rond ms-rond--nu', 'aria-label': 'Message à plusieurs personnes', title: 'Message à plusieurs personnes',
                    onclick: function () { fermerPanneau(); diffuser(); } }, ic('megaphone')) : null,
                h('a', { class: 'ms-rond ms-rond--nu', href: CFG.page, 'aria-label': 'Ouvrir la messagerie en grand', title: 'Ouvrir la messagerie en grand' }, ic('arrows-angle-expand'))),
            h('label', { class: 'ms-recherche' }, ic('search'), recherche),
            corpsListe);
        dock.appendChild(zoneFenetres);
        dock.appendChild(panneau);
        dock.appendChild(h('div', { class: 'ms-dock__colonne' }, zoneTetes, lanceur));
        document.body.appendChild(dock);

        var liste = listeAvecRecherche(corpsListe, recherche, function (id) { ouvrirFenetre(id, { focus: true }); },
            function (p) { ouvrirFenetre(null, { profil: p, focus: true }); }, null);
        listes.push(liste);
        listes.push({ dessiner: majTetes });

        function fenetresPossibles() { return window.matchMedia('(min-width: 768px)').matches; }
        function finePointe() { return window.matchMedia('(pointer: fine)').matches; }

        /* Animation de sortie, puis « puis » ; immediat si l'animation est coupee. */
        function sortir(el, classe, puis) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || el.hidden) { puis(); return; }
            var fini = false;
            var finir = function () { if (fini) { return; } fini = true; el.classList.remove(classe); puis(); };
            el.classList.add(classe);
            el.addEventListener('animationend', finir, { once: true });
            window.setTimeout(finir, 320);
        }
        function rejouer(el) { el.style.animation = 'none'; void el.offsetWidth; el.style.animation = ''; }

        /* ------------------------------------------------------------ panneau */

        function panneauOuvert() { return !panneau.hidden; }
        function ouvrirPanneau() {
            if (!fenetresPossibles()) { window.location.href = CFG.page; return; }
            panneau.hidden = false;
            rejouer(panneau);
            lanceur.setAttribute('aria-expanded', 'true');
            lanceur.classList.add('ms-lanceur--ouvert');
            liste.dessiner();
            listePrete().then(function () { liste.dessiner(); });
            if (finePointe()) { window.setTimeout(function () { recherche.focus({ preventScroll: true }); }, 60); }
            document.addEventListener('pointerdown', horsPanneau, true);
            boucle(true);
        }
        function fermerPanneau() {
            if (panneau.hidden) { return; }
            lanceur.setAttribute('aria-expanded', 'false');
            lanceur.classList.remove('ms-lanceur--ouvert');
            document.removeEventListener('pointerdown', horsPanneau, true);
            sortir(panneau, 'ms-sort', function () { panneau.hidden = true; });
        }
        function horsPanneau(e) {
            var t = e.target;
            if (panneau.contains(t) || lanceur.contains(t) || (popActif && popActif.contains(t)) || (t.closest && t.closest('dialog'))) { return; }
            fermerPanneau();
        }
        lanceur.addEventListener('click', function () { if (panneauOuvert()) { fermerPanneau(); } else { ouvrirPanneau(); } });
        panneau.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); fermerPanneau(); lanceur.focus(); } });

        /* ----------------------------------------------------------- fenetres */

        function capacite() { return Math.max(1, Math.min(3, Math.floor((window.innerWidth - 92 - 16) / (336 + 12)))); }
        function ouvertes() { return fenetres.filter(function (w) { return !w.F.reduit; }); }
        function sauver() {
            ecrireDurable('dock', {
                fenetres: fenetres.map(function (w) { return { id: w.F.id, reduite: w.F.reduit, a: !w.F.id && w.F.brouillon ? w.F.brouillon.profil.id : null }; })
                    .filter(function (x) { return x.id || x.a; })
            });
        }

        function ouvrirFenetre(id, o) {
            o = o || {};
            if (!fenetresPossibles()) { window.location.href = CFG.page + (id ? '#c' + id : (o.profil ? '#a' + o.profil.id : '')); return; }
            if (!o.reduite) { fermerPanneau(); }
            var existante = id ? fenetres.find(function (w) { return w.F.id === id; }) : null;
            if (existante) {
                if (!o.reduite) { developper(existante, o.focus); }
                return;
            }
            listePrete().then(function () {
                if (id && !S.conv.has(id)) { return; }
                if (id && fenetres.some(function (w) { return w.F.id === id; })) { return; }
                var el = h('section', { class: 'ms-fenetre' + (o.restauration ? ' ms-sans-anim' : ''), 'aria-label': 'Discussion', hidden: !!o.reduite });
                var w = { el: el, F: null, tete: null };
                w.F = creerFil(el, 'fenetre', {
                    reduire: function (F, oui) { if (oui) { reduire(w); } else { developper(w, true); } },
                    fermer: function () { fermer(w); },
                    agrandir: function (F) { F.garderTexte(); window.location.href = CFG.page + (F.id ? '#c' + F.id : (F.brouillon ? '#a' + F.brouillon.profil.id : '')); },
                    ouvrirConv: function (cid) {
                        var autre = fenetres.find(function (x) { return x.F.id === cid && x !== w; });
                        if (autre) { fermer(w); developper(autre, true); } else { w.F.ouvrir(cid); }
                    },
                    change: function () { sauver(); majTete(w); }
                });
                w.F.reduit = !!o.reduite;
                w.F.focusAuto = !!o.focus;
                fenetres.unshift(w);
                zoneFenetres.insertBefore(el, zoneFenetres.firstChild);
                if (id) { w.F.ouvrir(id); }
                else if (o.profil) { w.F.ecrireA(o.profil); }
                else { w.F.nouveau(); }
                if (o.reduite) { montrerTete(w, o.arrivee, o.restauration); }
                limiter(w);
                if (o.restauration) { window.setTimeout(function () { el.classList.remove('ms-sans-anim'); }, 60); }
                sauver();
            });
        }

        /* Trop de fenetres pour l'ecran : les plus anciennes deviennent des bulles ;
           au-dela de six bulles, les plus anciennes se ferment. */
        function limiter(garder) {
            var ouv = ouvertes();
            for (var i = ouv.length - 1; i >= 0 && ouv.length > capacite(); i--) {
                if (ouv[i] !== garder) { reduire(ouv[i]); ouv = ouvertes(); }
            }
            var bulles = fenetres.filter(function (w) { return w.F.reduit; });
            while (bulles.length > 6) { fermer(bulles[bulles.length - 1]); bulles = fenetres.filter(function (w) { return w.F.reduit; }); }
        }

        function reduire(w) {
            if (w.F.reduit) { return; }
            w.F.reduit = true;
            w.F.garderTexte();
            sortir(w.el, 'ms-sort--bulle', function () { if (w.F.reduit) { w.el.hidden = true; } });
            montrerTete(w);
            sauver();
            redessinerListes();
        }

        function developper(w, focus) {
            var etaitReduite = w.F.reduit;
            w.F.reduit = false;
            cacherTete(w);
            if (etaitReduite || w.el.hidden) {
                w.el.classList.remove('ms-sort--bulle', 'ms-sort');
                w.el.hidden = false;
                rejouer(w.el);
                /* La fenetre rouverte se place a cote des bulles. */
                if (zoneFenetres.firstChild !== w.el) { zoneFenetres.insertBefore(w.el, zoneFenetres.firstChild); }
                fenetres.splice(fenetres.indexOf(w), 1);
                fenetres.unshift(w);
            }
            limiter(w);
            w.F.majTete();
            w.F.allerEnBas(false);
            window.setTimeout(function () {
                w.F.marquerLu();
                if (focus !== false && w.F.E.zone && finePointe()) { w.F.E.zone.focus({ preventScroll: true }); }
            }, 80);
            sauver();
            redessinerListes();
            boucle(true);
        }

        function fermer(w) {
            var i = fenetres.indexOf(w);
            if (i < 0) { return; }
            fenetres.splice(i, 1);
            w.F.reduit = true;
            if (enregistrement && enregistrement.fil === w.F) { arreterVocal(false); }
            cacherTete(w);
            sortir(w.el, 'ms-sort', function () { w.F.detruire(); w.el.remove(); });
            sauver();
            redessinerListes();
        }

        /* -------------------------------------------------------------- bulles */

        function montrerTete(w, secouer, sansAnim) {
            if (!w.tete) {
                var bouton = h('button', { type: 'button', class: 'ms-tete__bouton', onclick: function () { developper(w, true); } });
                var croix = h('button', { type: 'button', class: 'ms-tete__fermer', 'aria-label': 'Fermer la discussion', title: 'Fermer',
                    onclick: function (e) { e.stopPropagation(); fermer(w); } }, ic('x'));
                w.tete = h('div', { class: 'ms-tete' + (sansAnim ? ' ms-sans-anim' : '') }, bouton, croix);
                w.teteSig = '';
                zoneTetes.appendChild(w.tete);
            }
            majTete(w);
            if (secouer) {
                w.tete.classList.remove('ms-tete--secoue');
                void w.tete.offsetWidth;
                w.tete.classList.add('ms-tete--secoue');
            }
        }
        function cacherTete(w) {
            if (!w.tete) { return; }
            var t = w.tete;
            w.tete = null;
            sortir(t, 'ms-sort', function () { t.remove(); });
        }
        function majTete(w) {
            if (!w.tete) { return; }
            var c = w.F.id ? S.conv.get(w.F.id) : null;
            var p = w.F.brouillon ? w.F.brouillon.profil : null;
            var nom = c ? c.titre : (p ? p.nom : 'Nouveau message');
            var n = c ? (c.non_lus || 0) : 0;
            var apercu = c ? apercuConv(c) : '';
            var sig = nom + '|' + n + '|' + (c && c.autre ? c.autre.avatar : '') + '|' + (c ? (c.avatars || []).length : 0) + '|' + (c && c.autre && enLigne(c.autre.id) ? 1 : 0)
                + '|' + apercu + '|' + (c && c.dernier ? c.dernier.le : '');
            if (sig === w.teteSig) { return; }
            w.teteSig = sig;
            var b = w.tete.firstChild;
            vider(b);
            b.appendChild(c ? avatarConv(c, 48) : avatar(p || { id: 0, initiales: '+' }, 48, !!p));
            if (n) { b.appendChild(h('span', { class: 'ms-tete__nb' }, n > 99 ? '99+' : String(n))); }
            /* Au survol, comme Messenger : qui, et ce qu'il a ecrit en dernier
               (deux lignes au plus), avec l'heure. */
            if (w.carte) { w.carte.remove(); }
            w.carte = h('div', { class: 'ms-tete__carte' + (n ? ' ms-tete__carte--non-lu' : ''), 'aria-hidden': 'true' },
                h('span', { class: 'ms-tete__carte-nom' }, nom),
                apercu ? h('span', { class: 'ms-tete__carte-texte' }, apercu) : null,
                c && c.dernier ? h('time', { class: 'ms-tete__carte-heure', datetime: c.dernier.le }, libelleListe(c.dernier.le)) : null);
            w.tete.appendChild(w.carte);
            b.setAttribute('aria-label', 'Ouvrir la discussion : ' + nom + (n ? ' (' + n + ' non lu' + (n > 1 ? 's' : '') + ')' : '') + (apercu ? '. Dernier message : ' + apercu : ''));
        }
        function majTetes() { fenetres.forEach(function (w) { if (w.tete) { majTete(w); } }); }

        /* Message recu d'une discussion qui n'est pas affichee : sa bulle
           apparait, ou sautille si elle est deja la. */
        function arrivee(m) {
            if (!fenetresPossibles()) { return; }
            var w = fenetres.find(function (x) { return x.F.id === m.conversation_id; });
            if (w) { if (w.F.reduit) { montrerTete(w, true); } return; }
            ouvrirFenetre(m.conversation_id, { reduite: true, arrivee: true });
        }

        window.addEventListener('resize', function () { limiter(null); });
        window.addEventListener('pagehide', function () { FILS.forEach(function (F) { F.garderTexte(); }); sauver(); });

        DOCK = { panneauOuvert: panneauOuvert, ouvrirFenetre: ouvrirFenetre, arrivee: arrivee };

        majBoutonsNotifications();
        majTitre();
        /* Fenetres et bulles de la page precedente, sans les faire rejouer leur entree */
        if (fenetresPossibles() && etat.fenetres && etat.fenetres.length) {
            listePrete().then(function () {
                etat.fenetres.slice().reverse().forEach(function (x) {
                    if (x.id && S.conv.has(x.id)) { ouvrirFenetre(x.id, { reduite: x.reduite, restauration: true }); }
                    else if (x.a) {
                        appel('GET', 'profil', { id: x.a }).then(function (r) { if (r.ok) { ouvrirFenetre(null, { profil: r.profil, reduite: x.reduite, restauration: true }); } });
                    }
                });
            });
        }
        boucle(false);
    }

    /* ================================================================ demarrage */

    if (MODE === 'page') { demarrerPage(); } else { demarrerDock(); }
    /* Notifications push : une fois la cle de l'application connue (dans la
       page pour le dock, a la premiere liste pour la page Messagerie). */
    (S.reglages.push !== undefined ? Promise.resolve() : (listePromesse || Promise.resolve())).then(verifierPush);

    /* Notification touchee : /sw.js -> src/js/pwa.js -> ici. */
    window.Messagerie = {
        ouvrir: function (id) { listePrete().then(function () { ouvrirConversation(+id); }); }
    };
})();
