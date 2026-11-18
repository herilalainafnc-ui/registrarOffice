<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php'); ?>
	<title>Candidatures</title>
	<style>
		.cand-badge { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; white-space:nowrap; }
		.cand-nouveau  { background:rgb(var(--c-accent) / .15);  color:rgb(var(--c-accent-text)); border:1px solid rgb(var(--c-accent) / .35); }
		.cand-en_cours { background:rgba(245,158,11,.15); color:#fbbf24; border:1px solid rgba(245,158,11,.35); }
		.cand-accepte  { background:rgba(34,197,94,.15);  color:#4ade80; border:1px solid rgba(34,197,94,.35); }
		.cand-refuse   { background:rgba(248,113,113,.15);color:#f87171; border:1px solid rgba(248,113,113,.35); }
		.cand-converti { background:rgba(139,92,246,.15); color:#a78bfa; border:1px solid rgba(139,92,246,.35); }

		.cand-table { width:100%; border-collapse:collapse; font-size:13px; }
		.cand-table th { text-align:left; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:rgb(var(--c-text-3)); border-bottom:1px solid rgb(var(--c-line-strong) / .2); position:sticky; top:0; background:rgb(var(--c-canvas)); z-index:1; }
		.cand-table td { padding:10px 12px; border-bottom:1px solid rgb(var(--c-line-strong) / .08); vertical-align:top; }
		.cand-table tr:hover td { background:rgb(var(--c-text-3) / .05); }
		.cand-vide { padding:48px 16px; text-align:center; color:rgb(var(--c-text-3)); }

		/* Rangee compacte
		   ------------------------------------------------------------------
		   Un candidat rediges une lettre de motivation de vingt lignes : sa
		   rangee faisait cinq cents pixels de haut et la liste devenait
		   illisible des la deuxieme candidature. La colonne du message a donc
		   disparu, et chaque cellule tient en DEUX lignes au plus.

		   Le plafond est pose ici et non laisse a la bonne volonte du contenu :
		   un nom compose, une adresse email longue ou un intitule de parcours
		   verbeux relanceraient sinon le meme probleme, plus discretement. */
		.cand-table td { max-width:260px; overflow:hidden; text-overflow:ellipsis; }
		.cand-table td > div,
		.cand-table td { overflow-wrap:anywhere; }

		.cand-ligne { cursor:pointer; }
		.cand-ligne:hover td { background:rgb(var(--c-accent) / .07); }
		.cand-ligne:focus-visible { outline:2px solid rgb(var(--c-accent)); outline-offset:-2px; }

		/* Pastille de message : elle dit qu'il y en a un, sans en montrer un mot. */
		.cand-puce-msg { margin-left:6px; color:rgb(var(--c-accent-text)); font-size:12px; opacity:.85; }

		/* Fiche complete
		   ------------------------------------------------------------------
		   Ouverte au clic sur la rangee. C'est le seul endroit ou le message
		   entier, la filiation et le suivi sont lisibles — la liste ne montre
		   que ce qui sert a reperer un dossier. */
		.cand-fiche {
			position:fixed; inset:0; z-index:3000;
			display:flex; align-items:center; justify-content:center; padding:20px;
			background:rgb(var(--c-canvas) / .72);
			-webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px);
		}
		.cand-fiche[hidden] { display:none; }

		.cand-fiche__carte {
			width:100%; max-width:720px; max-height:90vh;
			display:flex; flex-direction:column;
			background:rgb(var(--c-canvas)); border:1px solid rgb(var(--c-line-strong) / .2);
			border-radius:16px; box-shadow:0 30px 70px -20px rgba(0,0,0,.6);
			animation:cand-entree .18s ease-out;
		}
		@keyframes cand-entree { from { opacity:0; transform:translateY(10px) scale(.98); } to { opacity:1; transform:none; } }
		@media (prefers-reduced-motion:reduce) { .cand-fiche__carte { animation:none; } }

		.cand-fiche__tete {
			display:flex; align-items:flex-start; justify-content:space-between; gap:16px;
			padding:20px 24px 16px; border-bottom:1px solid rgb(var(--c-line-strong) / .16);
		}
		.cand-fiche__tete h2 { margin:0; font-size:18px; font-weight:700; color:rgb(var(--c-text)); }
		.cand-fiche__tete p { margin:5px 0 0; font-size:12.5px; color:rgb(var(--c-text-2)); }

		.cand-fiche__fermer {
			flex-shrink:0; width:34px; height:34px; display:grid; place-items:center;
			border:0; border-radius:9px; background:transparent; color:rgb(var(--c-text-2));
			font-size:20px; line-height:1; cursor:pointer;
		}
		.cand-fiche__fermer:hover { background:rgb(var(--c-text-3) / .14); color:rgb(var(--c-text)); }
		.cand-fiche__fermer:focus-visible { outline:2px solid rgb(var(--c-accent)); outline-offset:1px; }

		/* Le corps defile, pas la fenetre : la tete et le pied restent en vue
		   quelle que soit la longueur du message. */
		.cand-fiche__corps { padding:18px 24px 22px; overflow-y:auto; }

		.cand-groupe { margin-bottom:20px; }
		.cand-groupe:last-child { margin-bottom:0; }
		.cand-groupe h3 {
			margin:0 0 10px; font-size:11px; font-weight:700; text-transform:uppercase;
			letter-spacing:.06em; color:rgb(var(--c-accent-text));
		}
		.cand-champs { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:11px 20px; }
		.cand-champ__cle { display:block; font-size:11.5px; color:rgb(var(--c-text-2)); }
		.cand-champ__val { display:block; margin-top:2px; font-size:13.5px; color:rgb(var(--c-text)); overflow-wrap:anywhere; }
		.cand-champ__val--vide { color:rgb(var(--c-text-3)); font-style:italic; }

		/* Le message garde ses retours a la ligne : une lettre de motivation
		   sans ses paragraphes se lit mal. */
		.cand-message {
			padding:13px 15px; border-radius:10px;
			background:rgb(var(--c-canvas) / .6); border:1px solid rgb(var(--c-line-strong) / .14);
			font-size:13.5px; line-height:1.65; color:rgb(var(--c-text-2));
			white-space:pre-wrap; overflow-wrap:anywhere;
		}

		@media (max-width:640px) {
			.cand-fiche { padding:0; }
			.cand-fiche__carte { max-width:none; max-height:100vh; border-radius:0; }
			.cand-champs { grid-template-columns:1fr; }
		}

		/* Actions d'instruction
		   ------------------------------------------------------------------
		   Chaque bouton portait sa propre teinte pleine des le repos : trois a
		   quatre couleurs saturees par ligne, multipliees par le nombre de
		   candidatures, donnaient un tableau illisible ou plus rien ne
		   ressortait.
		   Principe retenu : UNE seule action remplie par ligne — la conversion
		   en etudiant, qui est la suite logique d'un dossier accepte. Les
		   autres restent neutres et ne revelent leur couleur qu'au survol et
		   au focus. L'information reste portee par le libelle, jamais par la
		   couleur seule : rien n'est perdu sur ecran tactile, ou le survol
		   n'existe pas. */
		.cand-actions { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }

		.cand-btn { display:inline-flex; align-items:center; gap:5px;
		            border:1px solid rgb(var(--c-line-strong) / .26); border-radius:7px;
		            padding:5px 11px; font-size:11px; font-weight:600;
		            font-family:inherit; background:transparent; color:rgb(var(--c-text-2));
		            cursor:pointer; white-space:nowrap; text-decoration:none;
		            transition:background .15s ease, border-color .15s ease, color .15s ease; }
		.cand-btn:hover { background:rgb(var(--c-text-3) / .12); border-color:rgb(var(--c-line-strong) / .45); color:rgb(var(--c-text)); }
		/* Anneau de focus explicite : le clavier doit voir ce que la souris survole. */
		.cand-btn:focus-visible { outline:2px solid rgb(var(--c-accent)); outline-offset:2px; }

		/* Intentions, revelees seulement a l'interaction. */
		.cand-btn--encours:hover { background:rgba(245,158,11,.13); border-color:rgba(245,158,11,.5); color:#fbbf24; }
		.cand-btn--accept:hover  { background:rgba(34,197,94,.13);  border-color:rgba(34,197,94,.5);  color:#4ade80; }
		.cand-btn--refus:hover   { background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.45);color:#f87171; }
		/* Le refus est ecarte des autres : une action irreversible ne doit pas
		   se trouver sous le doigt de celui qui visait « Accepter ». */
		.cand-btn--refus { margin-left:6px; }

		/* Conversion en etudiant : seule action remplie de la ligne. Le violet
		   d'origine n'appartenait a aucune palette du produit ; on reprend le
		   bleu deja employe comme accent dans l'application. */
		.cand-btn--creer   { background:rgb(var(--c-accent-strong)); color:#fff; border-color:rgb(var(--c-accent)); }
		.cand-btn--creer:hover { background:rgb(var(--c-accent-strong)); border-color:rgb(var(--c-accent-strong)); color:#fff; }

		/* Archiver : ranger n'est ni une bonne ni une mauvaise nouvelle. Le
		   bouton garde donc le gris neutre du repos, sans teinte semantique —
		   lui en donner une laisserait croire a une decision. */
		.cand-btn--archive { margin-left:6px; }
		.cand-btn--desarchive:hover { background:rgb(var(--c-accent) / .12); border-color:rgb(var(--c-accent) / .45); color:rgb(var(--c-accent-text)); }

		/* Le filtre des archives est ecarte des filtres de statut : ce n'est
		   pas un etat de plus dans le meme axe, c'est un autre rayon. */
		.cand-filtre--archive { margin-left:14px; }
		.cand-actions form { display:inline; margin:0; }
		.cand-traite { font-size:11px; color:rgb(var(--c-text-3)); white-space:nowrap; }

		/* Filtres */
		.cand-filtres { display:flex; gap:6px; flex-wrap:wrap; }
		.cand-filtre { padding:5px 12px; border-radius:9999px; font-size:12px; text-decoration:none;
		               border:1px solid rgb(var(--c-line-strong) / .25); color:rgb(var(--c-text-2)); background:transparent; }
		.cand-filtre:hover { background:rgb(var(--c-text-3) / .1); color:#fff; }
		.cand-filtre.actif { background:rgb(var(--c-accent-strong)); border-color:rgb(var(--c-accent)); color:#fff; font-weight:600; }

		/* --- Toasts -------------------------------------------------------
		   Empiles en haut a droite, au-dessus du contenu. Ils remplacent le
		   bandeau qui s'inserait dans la page et en decalait le contenu. */
		#cand-toasts {
			position:fixed; top:16px; right:16px; z-index:1200;
			display:flex; flex-direction:column; gap:8px;
			max-width:min(420px, calc(100vw - 32px));
		}
		.cand-toast {
			display:flex; align-items:flex-start; gap:10px;
			padding:12px 14px; border-radius:10px; font-size:13px; line-height:1.5;
			background:rgb(var(--c-canvas)); border:1px solid rgb(var(--c-line-strong) / .25);
			box-shadow:0 12px 32px -10px rgba(0,0,0,.7);
			animation:cand-toast-entree .18s ease-out;
		}
		.cand-toast--ok  { border-color:rgba(34,197,94,.45);  color:#86efac; }
		.cand-toast--err { border-color:rgba(248,113,113,.45); color:#fca5a5; }
		.cand-toast__texte { flex:1 1 auto; }
		.cand-toast__fermer {
			flex:0 0 auto; border:0; background:transparent; cursor:pointer;
			color:inherit; opacity:.6; font-size:15px; line-height:1; padding:0 2px;
		}
		.cand-toast__fermer:hover { opacity:1; }
		.cand-toast.cand-toast--sortie { animation:cand-toast-sortie .18s ease-in forwards; }
		@keyframes cand-toast-entree { from { opacity:0; transform:translateX(12px); } }
		@keyframes cand-toast-sortie { to   { opacity:0; transform:translateX(12px); } }
		@media (prefers-reduced-motion: reduce) {
			.cand-toast, .cand-toast.cand-toast--sortie { animation:none; }
		}

		/* --- Modale de confirmation ---------------------------------------
		   Remplace confirm(), dont l'apparence echappe a l'application et qui
		   bloque le fil d'execution du navigateur. */
		#cand-confirm { position:fixed; inset:0; z-index:1300; display:none;
		                align-items:center; justify-content:center; padding:20px;
		                background:rgb(var(--c-canvas) / .6); }
		#cand-confirm.cand-ouvert { display:flex; }
		.cand-confirm__carte {
			width:100%; max-width:420px; background:rgb(var(--c-canvas)); color:rgb(var(--c-text));
			border:1px solid rgb(var(--c-line-strong) / .2); border-radius:14px; padding:22px 24px;
			box-shadow:0 24px 60px -12px rgba(0,0,0,.8);
		}
		.cand-confirm__titre { margin:0 0 8px; font-size:16px; font-weight:650; }
		.cand-confirm__texte { margin:0 0 20px; font-size:13.5px; color:rgb(var(--c-text-3)); line-height:1.6; }
		.cand-confirm__actions { display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
		.cand-confirm__btn {
			border:1px solid transparent; border-radius:8px; padding:8px 16px;
			font-family:inherit; font-size:13px; font-weight:600; cursor:pointer;
		}
		.cand-confirm__btn--annuler { background:rgb(var(--c-surface)); color:rgb(var(--c-text-2)); border-color:rgb(var(--c-line) / .9); }
		.cand-confirm__btn--annuler:hover { background:rgb(var(--c-raised)); }
		.cand-confirm__btn--valider { background:#dc2626; color:#fff; }
		.cand-confirm__btn--valider:hover { background:#b91c1c; }
		.cand-confirm__btn:focus-visible { outline:3px solid rgb(var(--c-line-strong) / .6); outline-offset:2px; }

		/* Bascule de filtre sans rechargement : on attenue la zone le temps
		   de la reponse, sans deplacer le contenu (pas de saut visuel). */
		#cand-zone { transition:opacity .12s ease; }
		#cand-zone.cand-charge { opacity:.45; pointer-events:none; }
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php'); ?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php'); ?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-4 text-slate-100 overflow-auto">

						<?php
							// Message issu d'une redirection (parcours sans JavaScript).
							// Il est confie au script, qui l'affiche en toast comme les
							// autres ; sans script, il reste lisible dans le repli ci-dessous.
							$messageServeur = null;
							if (!empty($_GET['ok']))     { $messageServeur = ['ok' => true,  'texte' => (string) $_GET['ok']]; }
							elseif (!empty($_GET['erreur'])) { $messageServeur = ['ok' => false, 'texte' => (string) $_GET['erreur']]; }
						?>
						<?php if ($messageServeur): ?>
						<noscript>
							<div style="padding:10px 14px;border-radius:9px;margin-bottom:14px;font-size:13px;
							            background:rgb(var(--c-text-3) / .12);border:1px solid rgb(var(--c-line-strong) / .35);">
								<?= htmlspecialchars($messageServeur['texte'], ENT_QUOTES, 'UTF-8') ?>
							</div>
						</noscript>
						<?php endif; ?>

						<!-- Zone remplacee lors d'un changement de filtre : elle contient
						     l'en-tete (dont les compteurs), les filtres et le tableau. -->
						<div id="cand-zone">
<?php require(__DIR__ . '/admissions.candidats.partie.php'); ?>
						</div>

						<?php /* Coquille de la fiche, HORS de #cand-zone : cette zone est
						         remplacee a chaque bascule de filtre, et une fenetre ouverte
						         disparaitrait sous les doigts de qui la lit. Seules les
						         DONNEES vivent dans le fragment, et se rafraichissent avec
						         les lignes. */ ?>
						<div class="cand-fiche" id="cand-fiche" hidden
						     role="dialog" aria-modal="true" aria-labelledby="cand-fiche-titre">
							<div class="cand-fiche__carte">
								<div class="cand-fiche__tete">
									<div>
										<h2 id="cand-fiche-titre"></h2>
										<p id="cand-fiche-sous"></p>
									</div>
									<button type="button" class="cand-fiche__fermer" id="cand-fiche-fermer"
									        aria-label="Fermer la fiche">&times;</button>
								</div>
								<div class="cand-fiche__corps" id="cand-fiche-corps"></div>
							</div>
						</div>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>

<!-- Pile des toasts et modale de confirmation, hors du contenu remplace. -->
<div id="cand-toasts" aria-live="polite" aria-atomic="false"></div>

<div id="cand-confirm" role="dialog" aria-modal="true" aria-labelledby="cand-confirm-titre">
	<div class="cand-confirm__carte">
		<h2 class="cand-confirm__titre" id="cand-confirm-titre">Confirmer l'action</h2>
		<p class="cand-confirm__texte" id="cand-confirm-texte"></p>
		<div class="cand-confirm__actions">
			<button type="button" class="cand-confirm__btn cand-confirm__btn--annuler" id="cand-confirm-non">Annuler</button>
			<button type="button" class="cand-confirm__btn cand-confirm__btn--valider" id="cand-confirm-oui">Confirmer</button>
		</div>
	</div>
</div>

<script>
/**
 * Bascule des filtres sans rechargement.
 *
 * Les filtres restent de vrais liens : sans JavaScript, ou si la requete
 * echoue, la navigation classique fonctionne toujours. Le script se contente
 * d'intercepter le clic, de recuperer la meme page en fragment et de
 * remplacer la zone du tableau.
 *
 * L'URL est mise a jour (history.pushState) pour que l'adresse reste
 * partageable et que les boutons Precedent / Suivant du navigateur
 * fonctionnent.
 */
(function () {
	var zone = document.getElementById('cand-zone');
	if (!zone || !window.fetch || !window.history || !history.pushState) { return; }

	var requeteEnCours = null;

	function urlFragment(url) {
		// On demande la meme page, mais reduite a la zone echangeable.
		return url + (url.indexOf('?') === -1 ? '?' : '&') + 'fragment=1';
	}

	function charger(url, empiler) {
		// Une bascule rapide entre filtres ne doit pas laisser une reponse
		// tardive ecraser la plus recente.
		if (requeteEnCours) { requeteEnCours.abort(); }
		var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
		requeteEnCours = ctrl;

		zone.classList.add('cand-charge');

		fetch(urlFragment(url), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: ctrl ? ctrl.signal : undefined
		})
		.then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.text();
		})
		.then(function (html) {
			zone.innerHTML = html;
			zone.classList.remove('cand-charge');
			requeteEnCours = null;
			if (empiler) { history.pushState({ candidatures: true }, '', url); }
		})
		.catch(function (e) {
			if (e && e.name === 'AbortError') { return; }
			// En cas d'echec, on retombe sur une navigation normale plutot
			// que de laisser l'utilisateur devant une zone figee.
			window.location.href = url;
		});
	}

	// Delegation : les filtres sont remplaces a chaque bascule, donc on
	// ecoute sur la zone plutot que sur chaque lien.
	zone.addEventListener('click', function (e) {
		var lien = e.target.closest ? e.target.closest('a.cand-filtre') : null;
		if (!lien) { return; }
		// On laisse passer les ouvertures dans un nouvel onglet.
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
		e.preventDefault();
		charger(lien.getAttribute('href'), true);
	});

	window.addEventListener('popstate', function () {
		charger(window.location.pathname + window.location.search, false);
	});

	/* ------------------------------------------------------------------
	   Actions d'instruction sans rechargement.

	   Le serveur applique le changement et renvoie, dans la meme reponse,
	   la liste deja rafraichie : un seul aller-retour, et les compteurs
	   comme les boutons proposes restent coherents.

	   Les formulaires restent de vrais formulaires : si la requete echoue
	   pour une raison quelconque, on laisse le navigateur les envoyer
	   normalement plutot que de laisser l'action sans effet.
	   ------------------------------------------------------------------ */
	var pileToasts = document.getElementById('cand-toasts');

	/* Affiche un toast. Il disparait seul, sauf en cas d'erreur : un message
	   d'echec doit pouvoir etre lu sans course contre la montre. */
	function afficherMessage(ok, texte) {
		if (!pileToasts || !texte) { return; }

		var toast = document.createElement('div');
		toast.className = 'cand-toast ' + (ok ? 'cand-toast--ok' : 'cand-toast--err');
		toast.setAttribute('role', ok ? 'status' : 'alert');

		var corps = document.createElement('span');
		corps.className = 'cand-toast__texte';
		// textContent, jamais innerHTML : le message peut contenir des donnees
		// venues d'un formulaire public.
		corps.textContent = (ok ? '✓ ' : '✗ ') + texte;

		var fermer = document.createElement('button');
		fermer.type = 'button';
		fermer.className = 'cand-toast__fermer';
		fermer.setAttribute('aria-label', 'Fermer');
		fermer.innerHTML = '&times;';

		toast.appendChild(corps);
		toast.appendChild(fermer);
		pileToasts.appendChild(toast);

		var minuteur = null;
		function retirer() {
			if (minuteur) { clearTimeout(minuteur); }
			toast.classList.add('cand-toast--sortie');
			setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 200);
		}
		fermer.addEventListener('click', retirer);
		if (ok) { minuteur = setTimeout(retirer, 5000); }
	}

	/* Confirmation en modale, a la place de confirm() : celui-ci fige le
	   navigateur et son apparence echappe a l'application.
	   Renvoie une promesse resolue a true si l'utilisateur confirme. */
	var boiteConfirm = document.getElementById('cand-confirm');
	function demanderConfirmation(question) {
		return new Promise(function (resoudre) {
			if (!boiteConfirm) { resoudre(true); return; }
			var oui = document.getElementById('cand-confirm-oui');
			var non = document.getElementById('cand-confirm-non');
			var elementActifAvant = document.activeElement;
			document.getElementById('cand-confirm-texte').textContent = question;
			boiteConfirm.classList.add('cand-ouvert');
			non.focus();

			function terminer(reponse) {
				boiteConfirm.classList.remove('cand-ouvert');
				oui.removeEventListener('click', surOui);
				non.removeEventListener('click', surNon);
				document.removeEventListener('keydown', surTouche);
				boiteConfirm.removeEventListener('click', surFond);
				if (elementActifAvant && elementActifAvant.focus) { elementActifAvant.focus(); }
				resoudre(reponse);
			}
			function surOui() { terminer(true); }
			function surNon() { terminer(false); }
			function surFond(e) { if (e.target === boiteConfirm) { terminer(false); } }
			function surTouche(e) {
				if (e.key === 'Escape') { terminer(false); return; }
				if (e.key === 'Tab') { // le clavier reste dans la boite
					e.preventDefault();
					(document.activeElement === non ? oui : non).focus();
				}
			}
			oui.addEventListener('click', surOui);
			non.addEventListener('click', surNon);
			boiteConfirm.addEventListener('click', surFond);
			document.addEventListener('keydown', surTouche);
		});
	}

	zone.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || form.tagName !== 'FORM') { return; }
		e.preventDefault();

		// Certaines actions demandent une confirmation. Le texte est porte par
		// le bouton (data-confirmer), ce qui evite au script de connaitre le
		// detail des actions.
		var declencheur = (e.submitter) || form.querySelector('[data-confirmer]');
		var question = declencheur ? declencheur.getAttribute('data-confirmer') : null;

		(question ? demanderConfirmation(question) : Promise.resolve(true))
			.then(function (confirme) {
				if (!confirme) { return; }
				envoyerAction(form);
			});
	});

	function envoyerAction(form) {
		zone.classList.add('cand-charge');

		fetch(form.getAttribute('action'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: new FormData(form)
		})
		.then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.json();
		})
		.then(function (data) {
			zone.classList.remove('cand-charge');
			afficherMessage(data.ok, data.message || '');
			// Le serveur renvoie la liste a jour : on remplace la zone.
			if (data.ok && typeof data.html === 'string') {
				zone.innerHTML = data.html;
			}
		})
		.catch(function () {
			// Repli : envoi classique du formulaire, avec rechargement.
			zone.classList.remove('cand-charge');
			form.submit();
		});
	}

	// Message venu d'une redirection : affiche en toast, puis retire de l'URL
	// pour qu'un rafraichissement ne le rejoue pas.
	(function messageDeLaRedirection() {
<?php if ($messageServeur): ?>
		afficherMessage(<?= $messageServeur['ok'] ? 'true' : 'false' ?>, <?= json_encode($messageServeur['texte'], JSON_UNESCAPED_UNICODE) ?>);
		if (window.history && history.replaceState) {
			var propre = window.location.pathname + (window.location.search
				.replace(/[?&](ok|erreur)=[^&]*/g, '').replace(/^&/, '?') || '');
			history.replaceState(null, '', propre);
		}
<?php endif; ?>
	})();
})();
</script>

<script>
/* =============================================================================
   Fiche complète d'une candidature

   La liste ne montre plus que ce qui sert à repérer un dossier : deux lignes
   par candidat. Tout le reste — le message entier, la filiation, le tuteur,
   l'adresse, le suivi — s'ouvre ici, au clic sur la rangée.

   Les données viennent du bloc JSON du fragment, relu à chaque ouverture :
   une bascule de filtre remplace la zone, donc la fiche doit lire l'état
   courant et non une copie prise au chargement.
   ========================================================================== */
(function () {
	var zone = document.getElementById('cand-zone');
	var boite = document.getElementById('cand-fiche');
	if (!zone || !boite) { return; }

	var titre = document.getElementById('cand-fiche-titre');
	var sous = document.getElementById('cand-fiche-sous');
	var corps = document.getElementById('cand-fiche-corps');
	var fermerBtn = document.getElementById('cand-fiche-fermer');

	var elementActifAvant = null;
	var debordement = '';

	function fiches() {
		var bloc = document.getElementById('cand-fiches');
		if (!bloc) { return {}; }
		try {
			var liste = JSON.parse(bloc.textContent) || [];
			var parId = {};
			liste.forEach(function (c) { parId[String(c.id)] = c; });
			return parId;
		} catch (e) { return {}; }
	}

	/* --- Mise en forme ---------------------------------------------------- */

	function texte(v) {
		if (v === null || v === undefined) { return ''; }
		return String(v).trim();
	}

	function dateFr(v) {
		var s = texte(v);
		if (s === '' || s.indexOf('0000') === 0) { return ''; }
		var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(s);
		if (!m) { return s; }
		return m[3] + '/' + m[2] + '/' + m[1] + (m[4] ? ' à ' + m[4] + ':' + m[5] : '');
	}

	/** Un champ. textContent partout : ces valeurs viennent du public. */
	function champ(cle, valeur) {
		var bloc = document.createElement('div');
		bloc.className = 'cand-champ';

		var k = document.createElement('span');
		k.className = 'cand-champ__cle';
		k.textContent = cle;
		bloc.appendChild(k);

		var v = document.createElement('span');
		v.className = 'cand-champ__val';
		var t = texte(valeur);
		if (t === '') {
			/* « Non renseigné » plutôt qu'un blanc : sur un formulaire à vingt
			   champs facultatifs, un vide ne dit pas si le candidat a omis la
			   réponse ou si la question ne lui a pas été posée. */
			v.textContent = 'Non renseigné';
			v.classList.add('cand-champ__val--vide');
		} else {
			v.textContent = t;
		}
		bloc.appendChild(v);
		return bloc;
	}

	function groupe(nom, champs) {
		// Un groupe dont tous les champs sont vides n'apporte rien : on l'omet.
		var remplis = champs.filter(function (p) { return texte(p[1]) !== ''; });
		if (!remplis.length) { return null; }

		var s = document.createElement('section');
		s.className = 'cand-groupe';
		var h = document.createElement('h3');
		h.textContent = nom;
		s.appendChild(h);

		var grille = document.createElement('div');
		grille.className = 'cand-champs';
		// On affiche TOUS les champs du groupe dès qu'un seul est rempli : les
		// trous font partie de l'information quand on instruit un dossier.
		champs.forEach(function (p) { grille.appendChild(champ(p[0], p[1])); });
		s.appendChild(grille);
		return s;
	}

	/* --- Ouverture -------------------------------------------------------- */

	function ouvrir(id) {
		var c = fiches()[String(id)];
		if (!c) { return; }

		var naissance = dateFr(c.date_naissance) || texte(c.annee_naissance);

		titre.textContent = texte(c.nom_complet) || 'Candidature';
		var bouts = [
			'Reçue le ' + (dateFr(c.date_creation) || '—'),
			texte(c.mention_libelle) || texte(c.mention),
			texte(c.diplome_vise)
		].filter(function (x) { return x !== ''; });
		sous.textContent = bouts.join(' · ');

		corps.textContent = '';

		[
			groupe('Identité', [
				['Nom', c.nom], ['Prénom', c.prenom],
				['Genre', c.genre === 'F' ? 'Féminin' : (c.genre === 'M' ? 'Masculin' : '')],
				['Naissance', naissance], ['Lieu de naissance', c.lieu_naissance],
				['État civil', c.etat_civil],
				['Nationalité', c.nationalite], ['Pays d’origine', c.pays_origine]
			]),
			groupe('Coordonnées', [
				['Téléphone', c.telephone], ['Email', c.email],
				['Ville', c.ville], ['Adresse', c.adresse]
			]),
			groupe('Famille', [
				['Père', c.nom_pere], ['Mère', c.nom_mere],
				['Contact des parents', c.contact_parent],
				['Tuteur', c.tuteur], ['Contact du tuteur', c.contact_tuteur]
			]),
			groupe('Vœu d’études', [
				['Mention', texte(c.mention_libelle) || texte(c.mention)],
				['Parcours', c.parcours],
				['Diplôme visé', c.diplome_vise],
				['Année du BACC', c.annee_bacc]
			]),
			groupe('Suivi', [
				['Statut', c.statut], ['Traitée par', c.traite_par],
				['Date de traitement', dateFr(c.date_traitement)],
				['Matricule attribué', c.student_id],
				['Archivée le', dateFr(c.archive_le)], ['Archivée par', c.archive_par],
				['Notifiée par email', dateFr(c.notifie_le)],
				['Notifiée par SMS', dateFr(c.notifie_sms_le)],
				['Adresse IP du dépôt', c.ip]
			])
		].forEach(function (s) { if (s) { corps.appendChild(s); } });

		// Le message en dernier : c'est le plus long, et on le lit après avoir
		// situé le candidat.
		var msg = texte(c.message);
		if (msg !== '') {
			var s = document.createElement('section');
			s.className = 'cand-groupe';
			var h = document.createElement('h3');
			h.textContent = 'Message du candidat';
			s.appendChild(h);
			var p = document.createElement('div');
			p.className = 'cand-message';
			p.textContent = msg;   // jamais innerHTML : texte saisi par un visiteur
			s.appendChild(p);
			corps.appendChild(s);
		}

		var note = texte(c.note_interne);
		if (note !== '') {
			var sn = document.createElement('section');
			sn.className = 'cand-groupe';
			var hn = document.createElement('h3');
			hn.textContent = 'Note interne';
			sn.appendChild(hn);
			var pn = document.createElement('div');
			pn.className = 'cand-message';
			pn.textContent = note;
			sn.appendChild(pn);
			corps.appendChild(sn);
		}

		elementActifAvant = document.activeElement;
		boite.hidden = false;
		debordement = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		corps.scrollTop = 0;
		document.addEventListener('keydown', surTouche);
		fermerBtn.focus();
	}

	function fermer() {
		if (boite.hidden) { return; }
		boite.hidden = true;
		document.body.style.overflow = debordement;
		document.removeEventListener('keydown', surTouche);
		if (elementActifAvant && elementActifAvant.focus) { elementActifAvant.focus(); }
	}

	function surTouche(e) {
		if (e.key === 'Escape' || e.keyCode === 27) { fermer(); return; }
		// Tant que la fiche est ouverte, la tabulation y reste enfermée.
		if (e.key === 'Tab') {
			var cibles = boite.querySelectorAll('button');
			if (!cibles.length) { return; }
			var premier = cibles[0], dernier = cibles[cibles.length - 1];
			if (e.shiftKey && document.activeElement === premier) { e.preventDefault(); dernier.focus(); }
			else if (!e.shiftKey && document.activeElement === dernier) { e.preventDefault(); premier.focus(); }
		}
	}

	/* --- Déclencheurs ------------------------------------------------------
	   Délégation sur la zone : les rangées sont remplacées à chaque bascule de
	   filtre, des écouteurs posés sur chacune disparaîtraient avec elles. */
	zone.addEventListener('click', function (e) {
		var ligne = e.target.closest ? e.target.closest('.cand-ligne') : null;
		if (!ligne) { return; }

		/* Un clic sur un bouton, un lien ou un formulaire d'instruction ne doit
		   PAS ouvrir la fiche : ces gestes ont leur propre effet, et la fenêtre
		   viendrait s'interposer entre l'agent et le résultat de son action. */
		if (e.target.closest('button, a, form, input, select, label')) { return; }

		ouvrir(ligne.dataset.fiche);
	});

	zone.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') { return; }
		var ligne = e.target.closest ? e.target.closest('.cand-ligne') : null;
		if (!ligne || e.target !== ligne) { return; }
		e.preventDefault();
		ouvrir(ligne.dataset.fiche);
	});

	fermerBtn.addEventListener('click', fermer);
	// Clic sur le fond, mais pas sur la carte.
	boite.addEventListener('click', function (e) { if (e.target === boite) { fermer(); } });
})();
</script>
</body>
</html>
