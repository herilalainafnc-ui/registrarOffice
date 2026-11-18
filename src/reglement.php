<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Bulletin académique</title>
	<style>
	/* ===== Bulletin académique — lecture et recherche ===== */
	.reg-wrap{ display:grid; grid-template-columns:minmax(0,1fr); gap:16px; }
	@media (min-width:1024px){ .reg-wrap{ grid-template-columns:320px minmax(0,1fr); align-items:start; } }

	/* --- Colonne gauche : recherche, résultats, sommaire --- */
	.reg-cote{
		display:flex; flex-direction:column; gap:10px; min-height:0;
	}
	@media (min-width:1024px){
		.reg-cote{ position:sticky; top:0; max-height:calc(100vh - 150px); }
	}
	.reg-panneau{
		background:rgb(var(--c-surface)); border:1px solid rgb(var(--c-line)); border-radius:10px;
	}

	/* Recherche */
	.reg-recherche{ padding:10px; }
	.reg-champ{ position:relative; }
	.reg-champ > i{ position:absolute; left:11px; top:50%; transform:translateY(-50%);
		color:rgb(var(--c-text-3)); font-size:14px; pointer-events:none; }
	.reg-champ input{
		width:100%; height:40px; padding:0 34px 0 34px !important; border-radius:8px;
		border:1px solid rgb(var(--c-line-strong)); background:rgb(var(--c-inset)); color:rgb(var(--c-text));
		font-size:14px;
	}
	.reg-champ input::-webkit-search-cancel-button{ display:none; }
	.reg-champ input:focus{ outline:none; border-color:rgb(var(--c-accent)); box-shadow:var(--ring); }
	.reg-effacer{
		position:absolute; right:6px; top:50%; transform:translateY(-50%);
		width:28px; height:28px; border:0; border-radius:6px; background:none;
		color:rgb(var(--c-text-3)); display:none; place-items:center; cursor:pointer;
	}
	.reg-effacer:hover{ background:rgb(var(--c-raised)); color:rgb(var(--c-text)); }
	.reg-recherche.a-une-requete .reg-effacer{ display:grid; }

	.reg-nav{ display:none; align-items:center; gap:6px; margin-top:8px; }
	.reg-recherche.a-une-requete .reg-nav{ display:flex; }
	.reg-compteur{ flex:1; font-size:12.5px; color:rgb(var(--c-text-2)); font-variant-numeric:tabular-nums; }
	.reg-compteur b{ color:rgb(var(--c-text)); }
	.reg-fleche{
		width:32px; height:32px; border-radius:7px; border:1px solid rgb(var(--c-line-strong));
		background:rgb(var(--c-surface-2)); color:rgb(var(--c-text)); display:grid; place-items:center; cursor:pointer;
	}
	.reg-fleche:hover:not(:disabled){ border-color:rgb(var(--c-accent)); color:rgb(var(--c-accent-text)); }
	.reg-fleche:disabled{ opacity:.4; cursor:default; }
	.reg-aide{ margin:8px 2px 0; font-size:11.5px; color:rgb(var(--c-text-3)); }
	.reg-recherche.a-une-requete .reg-aide{ display:none; }

	/* Résultats : toutes les occurrences, groupées par section */
	.reg-resultats{ display:none; overflow-y:auto; min-height:0; flex:1; padding:6px; }
	.reg-cote.en-recherche .reg-resultats{ display:block; }
	.reg-cote.en-recherche .reg-toc{ display:none; }
	.reg-groupe + .reg-groupe{ margin-top:6px; }
	.reg-groupe-titre{
		display:flex; align-items:baseline; gap:6px; padding:8px 8px 4px;
		font-size:11.5px; font-weight:600; letter-spacing:.02em; color:rgb(var(--c-text-2));
	}
	.reg-groupe-titre span{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
	.reg-groupe-titre em{ font-style:normal; font-weight:500; color:rgb(var(--c-text-3)); }
	.reg-resultat{
		display:block; width:100%; text-align:left; border:0; background:none; cursor:pointer;
		padding:7px 8px; border-radius:7px; font-size:12.5px; line-height:1.45; color:rgb(var(--c-text-2));
	}
	.reg-resultat:hover{ background:rgb(var(--c-surface-2)); color:rgb(var(--c-text)); }
	.reg-resultat.actuel{ background:rgb(var(--c-accent) / .16); color:rgb(var(--c-text)); box-shadow:inset 3px 0 0 rgb(var(--c-gold)); }
	.reg-resultat b{ color:rgb(var(--c-gold-text)); font-weight:700; }
	.reg-vide{ padding:18px 12px; text-align:center; font-size:13px; color:rgb(var(--c-text-3)); }
	.reg-vide strong{ display:block; color:rgb(var(--c-text)); margin-bottom:4px; }
	.reg-plus{ padding:8px; font-size:12px; color:rgb(var(--c-text-3)); text-align:center; }

	/* Sommaire replié par chapitre, section courante surlignée */
	.reg-toc{ padding:10px 8px; overflow-y:auto; min-height:0; flex:1; }
	.reg-toc h2{ font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.08em;
		color:rgb(var(--c-text-3)); margin:0 4px 6px; }
	.reg-toc ul{ list-style:none; margin:0; padding:0; }
	.reg-toc li{ position:relative; }
	.reg-toc li a{ display:block; padding:5px 8px; border-radius:6px; color:rgb(var(--c-text-2));
		text-decoration:none; font-size:12.5px; line-height:1.35; }
	.reg-toc li a:hover{ background:rgb(var(--c-surface-2)); color:rgb(var(--c-text)); }
	.reg-toc li.n2 > a{ font-weight:600; color:rgb(var(--c-text)); padding-right:32px; margin-top:2px; }
	.reg-toc li.n3 > a{ padding-left:18px; }
	.reg-toc li.n4 > a{ padding-left:30px; color:rgb(var(--c-text-3)); font-size:12px; }
	.reg-toc li.replie{ display:none; }
	.reg-toc li.actif > a{ background:rgb(var(--c-accent) / .14); color:rgb(var(--c-text)); box-shadow:inset 3px 0 0 rgb(var(--c-gold)); }
	.reg-deplier{
		position:absolute; right:2px; top:2px; width:26px; height:26px; border:0; border-radius:6px;
		background:none; color:rgb(var(--c-text-3)); display:grid; place-items:center; cursor:pointer;
	}
	.reg-deplier:hover{ background:rgb(var(--c-raised)); color:rgb(var(--c-text)); }
	.reg-deplier i{ transition:transform .2s ease; font-size:11px; }
	.reg-deplier[aria-expanded="true"] i{ transform:rotate(90deg); }

	/* Mobile : la colonne passe au-dessus du texte, repliable. */
	@media (max-width:1023px){
		.reg-cote .reg-toc, .reg-cote .reg-resultats{ max-height:45vh; }
		.reg-cote:not(.sommaire-ouvert):not(.en-recherche) .reg-toc{ display:none; }
		.reg-bascule-sommaire{ display:flex !important; }
	}
	.reg-bascule-sommaire{
		display:none; align-items:center; justify-content:center; gap:8px; width:100%; height:40px;
		border:1px solid rgb(var(--c-line)); border-radius:10px; background:rgb(var(--c-surface));
		color:rgb(var(--c-text)); font-size:13px; font-weight:500; cursor:pointer;
	}
	.reg-cote.en-recherche .reg-bascule-sommaire{ display:none !important; }

	/* Mobile : pendant une recherche, la navigation reste en bas de l ecran,
	   comme la barre « Rechercher » d un navigateur : la page defile jusqu au
	   passage, les fleches restent sous le pouce. */
	.reg-fermer{ display:none; }
	@media (max-width:1023px){
		.reg-recherche.a-une-requete .reg-nav{
			position:fixed; left:12px; right:12px; bottom:calc(12px + env(safe-area-inset-bottom, 0px)); z-index:1000;
			margin:0; padding:8px 8px 8px 14px; border-radius:12px;
			background:rgb(var(--c-surface)); border:1px solid rgb(var(--c-line-strong)); box-shadow:var(--shadow-2);
		}
		.reg-fleche, .reg-fermer{ width:44px; height:44px; }
		.reg-recherche.a-une-requete .reg-fermer{ display:grid; }
		.reg-doc{ padding-bottom:84px; }
	}

	/* --- Contenu --- */
	.reg-doc{
		background:rgb(var(--c-surface)); border:1px solid rgb(var(--c-line)); border-radius:10px;
		padding:24px 28px; color:rgb(var(--c-text)); line-height:1.7; font-size:14.5px;
		max-height:calc(100vh - 150px); overflow-y:auto; scroll-behavior:smooth;
	}
	@media (max-width:1023px){ .reg-doc{ max-height:none; padding:18px; } }
	.reg-doc article{ max-width:78ch; }
	.reg-doc h2{ font-size:20px; color:rgb(var(--c-accent-text)); margin:32px 0 10px;
		padding-bottom:6px; border-bottom:1px solid rgb(var(--c-line)); scroll-margin-top:12px; }
	.reg-doc h3{ font-size:16px; color:rgb(var(--c-text)); margin:22px 0 8px; scroll-margin-top:12px; }
	.reg-doc h4{ font-size:14px; color:rgb(var(--c-text-2)); margin:16px 0 6px; scroll-margin-top:12px; }
	.reg-doc h2:first-child{ margin-top:0; }
	.reg-doc p{ margin:0 0 10px; }
	.reg-doc ul{ margin:0 0 12px; padding-left:22px; }
	.reg-doc li{ margin-bottom:4px; }
	.reg-doc blockquote{ margin:10px 0; padding:8px 14px; border-left:3px solid rgb(var(--c-accent-strong));
		background:rgb(var(--c-accent) / .06); color:rgb(var(--c-text-2)); font-style:italic; }
	.reg-doc strong{ color:rgb(var(--c-text)); }

	.reg-doc .tbl-wrap{ overflow-x:auto; margin:12px 0; }
	.reg-doc table{ border-collapse:collapse; width:100%; font-size:13px; }
	.reg-doc th, .reg-doc td{ border:1px solid rgb(var(--c-line)); padding:6px 9px; text-align:left; vertical-align:top; }
	.reg-doc th{ background:rgb(var(--c-surface-2)); color:rgb(var(--c-text)); font-weight:600; }
	.reg-doc tr:nth-child(even) td{ background:rgb(var(--c-text) / .02); }

	/* Ancre « §n » : discrète, visible au survol du titre */
	.reg-doc .ancre{ color:rgb(var(--c-text-3)); font-size:11px; text-decoration:none;
		opacity:0; transition:opacity .15s; font-weight:400; }
	.reg-doc h2:hover .ancre, .reg-doc h3:hover .ancre, .reg-doc h4:hover .ancre{ opacity:1; }
	.reg-doc .ancre:hover{ color:rgb(var(--c-accent-text)); }

	/* Cible d'un lien profond : mise en évidence temporaire */
	.reg-doc :target{ animation:cible 2.5s ease-out; }
	@keyframes cible{ 0%,60%{ background:rgb(var(--c-accent) / .22); } 100%{ background:transparent; } }

	/* Occurrences : toutes surlignées, la courante en or plein */
	.reg-doc mark.reg-hit{ background:rgb(var(--c-gold) / .32); color:inherit; border-radius:2px; padding:0 1px; }
	.reg-doc mark.reg-hit.actuel{ background:rgb(var(--c-gold)); color:#0d1023; box-shadow:0 0 0 2px rgb(var(--c-gold)); }

	@media (prefers-reduced-motion: reduce){
		.reg-doc{ scroll-behavior:auto; }
		.reg-doc :target{ animation:none; }
	}
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

				<div class="back flex-1 overflow-y-auto p-4">

					<div class="flex flex-wrap items-center gap-3 mb-4">
						<div class="min-w-0">
							<h1 class="text-xl font-semibold text-slate-100 m-0">Bulletin académique — Licence</h1>
							<p class="text-slate-400 text-xs m-0">Règlement en vigueur · Université Adventiste Zurcher</p>
						</div>
						<a href="<?=$app_base?>/reglement/telecharger"
							class="ml-auto inline-flex items-center gap-2 rounded-lg border <?=$br_three_color?> <?=$bg_one_color?> px-3 py-2 text-sm text-slate-200 hover:border-cyan-500 transition-colors whitespace-nowrap"
							title="Télécharger le document Word d'origine (1,2 Mo)">
							<i class="bi-file-earmark-word" aria-hidden="true"></i>
							<span>Word d'origine</span>
						</a>
					</div>

					<div class="reg-wrap">

						<aside class="reg-cote" id="reg-cote" aria-label="Recherche et sommaire">

							<div class="reg-panneau reg-recherche" id="reg-recherche-bloc" role="search">
								<div class="reg-champ">
									<i class="bi bi-search" aria-hidden="true"></i>
									<input type="search" id="reg-recherche" placeholder="Rechercher dans le règlement…"
										autocomplete="off" spellcheck="false"
										aria-label="Rechercher dans le règlement" aria-controls="reg-resultats">
									<button type="button" class="reg-effacer" id="reg-effacer" aria-label="Effacer la recherche" title="Effacer (Échap)">
										<i class="bi bi-x-lg" aria-hidden="true"></i>
									</button>
								</div>
								<div class="reg-nav">
									<span class="reg-compteur" id="reg-compteur" aria-live="polite"></span>
									<button type="button" class="reg-fleche" id="reg-precedent" aria-label="Occurrence précédente" title="Précédente (Maj + Entrée)">
										<i class="bi bi-chevron-up" aria-hidden="true"></i>
									</button>
									<button type="button" class="reg-fleche" id="reg-suivant" aria-label="Occurrence suivante" title="Suivante (Entrée)">
										<i class="bi bi-chevron-down" aria-hidden="true"></i>
									</button>
									<button type="button" class="reg-fleche reg-fermer" id="reg-fermer" aria-label="Fermer la recherche">
										<i class="bi bi-x-lg" aria-hidden="true"></i>
									</button>
								</div>
								<p class="reg-aide">Accents facultatifs · trouve les mots qui commencent ainsi.</p>
							</div>

							<button type="button" class="reg-bascule-sommaire" id="reg-bascule-sommaire" aria-expanded="false" aria-controls="reg-toc-hote">
								<i class="bi bi-list-nested" aria-hidden="true"></i><span>Afficher le sommaire</span>
							</button>

							<div class="reg-panneau reg-resultats" id="reg-resultats" role="region" aria-label="Résultats de la recherche"></div>

							<div class="reg-panneau reg-toc-hote" id="reg-toc-hote"></div>

						</aside>

						<div class="reg-doc" id="reg-doc">
							<?php
								/* Contenu généré depuis le .docx par le script de conversion.
								   Les identifiants « p{n} » correspondent aux § cités dans le code
								   (ex. §383 = règle des 180 crédits), ce qui permet d'y lier
								   directement depuis les autres pages de l'application. */
								readfile(__DIR__ . '/assets/bulletin-academique.html');
							?>
						</div>

					</div>

				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

<script>
(function () {
	'use strict';

	var doc      = document.getElementById('reg-doc');
	var article  = document.getElementById('contenu') || doc;
	var cote     = document.getElementById('reg-cote');
	var hoteToc  = document.getElementById('reg-toc-hote');
	var bloc     = document.getElementById('reg-recherche-bloc');
	var champ    = document.getElementById('reg-recherche');
	var effacer  = document.getElementById('reg-effacer');
	var compteur = document.getElementById('reg-compteur');
	var panneau  = document.getElementById('reg-resultats');
	var btnPrec  = document.getElementById('reg-precedent');
	var btnSuiv  = document.getElementById('reg-suivant');
	var bascule  = document.getElementById('reg-bascule-sommaire');

	/* ------------------------------------------------------------ sommaire */

	/* Le sommaire est genere dans le contenu : on le deplace dans sa colonne. */
	var som = doc.querySelector('#sommaire');
	if (som) {
		som.classList.add('reg-toc');
		hoteToc.classList.remove('reg-panneau');
		hoteToc.appendChild(som);
		som.classList.add('reg-panneau');
	}

	/* Replie par chapitre : un chapitre (n2) et les sections qui le suivent.
	   Le chapitre de la section courante est toujours deplie. */
	var chapitres = [];
	if (som) {
		var courant = null;
		som.querySelectorAll('li').forEach(function (li) {
			if (li.classList.contains('n2')) {
				courant = { li: li, enfants: [] };
				chapitres.push(courant);
			} else if (courant) {
				courant.enfants.push(li);
			}
		});
		chapitres.forEach(function (ch) {
			if (!ch.enfants.length) { return; }
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'reg-deplier';
			b.setAttribute('aria-expanded', 'false');
			b.setAttribute('aria-label', 'Déplier « ' + ch.li.textContent.trim() + ' »');
			b.innerHTML = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';
			b.addEventListener('click', function () { deplier(ch, b.getAttribute('aria-expanded') !== 'true'); });
			ch.bouton = b;
			ch.li.appendChild(b);
			deplier(ch, false);
		});
	}
	function deplier(ch, ouvert) {
		ch.enfants.forEach(function (li) { li.classList.toggle('replie', !ouvert); });
		if (ch.bouton) { ch.bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false'); }
	}

	/* Un lien du sommaire ne fait defiler que la zone de lecture. */
	if (som) {
		som.addEventListener('click', function (e) {
			var a = e.target.closest('a[href^="#"]');
			if (!a) { return; }
			var cible = doc.querySelector(a.getAttribute('href'));
			if (!cible) { return; }
			e.preventDefault();
			history.replaceState(null, '', a.getAttribute('href'));
			montrer(cible, 'start');
			marquerActif(cible.id);
		});
	}

	/* Cliquer un chapitre le deplie aussi. */
	chapitres.forEach(function (ch) {
		var a = ch.li.querySelector('a');
		if (a) { a.addEventListener('click', function () { deplier(ch, true); }); }
	});

	/* Section en cours de lecture : surlignee dans le sommaire. */
	var liensToc = {};
	if (som) {
		som.querySelectorAll('a[href^="#"]').forEach(function (a) { liensToc[a.getAttribute('href').slice(1)] = a; });
	}
	var titres = Array.prototype.slice.call(article.querySelectorAll('h2[id], h3[id], h4[id]'));
	var actif = null;
	function marquerActif(id) {
		var a = liensToc[id];
		if (!a || a === actif) { return; }
		if (actif) { actif.parentNode.classList.remove('actif'); }
		actif = a;
		a.parentNode.classList.add('actif');
		chapitres.forEach(function (ch) {
			if (ch.li === a.parentNode || ch.enfants.indexOf(a.parentNode) !== -1) { deplier(ch, true); }
		});
		/* Le sommaire suit, sans faire defiler la page entiere. */
		var boite = som.getBoundingClientRect(), r = a.getBoundingClientRect();
		if (r.top < boite.top || r.bottom > boite.bottom) {
			som.scrollTop += (r.top - boite.top) - boite.height / 3;
		}
	}
	var attenteDefil = 0;
	function surDefilement() {
		if (attenteDefil) { return; }
		attenteDefil = requestAnimationFrame(function () {
			attenteDefil = 0;
			var haut = doc.getBoundingClientRect().top + 24;
			var dernier = null;
			for (var i = 0; i < titres.length; i++) {
				if (titres[i].getBoundingClientRect().top <= haut) { dernier = titres[i]; } else { break; }
			}
			if (dernier) { marquerActif(dernier.id); }
		});
	}
	doc.addEventListener('scroll', surDefilement, { passive: true });
	window.addEventListener('scroll', surDefilement, { passive: true });

	/* Mobile : le sommaire est replie derriere un bouton. */
	bascule.addEventListener('click', function () {
		var ouvert = !cote.classList.contains('sommaire-ouvert');
		cote.classList.toggle('sommaire-ouvert', ouvert);
		bascule.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
		bascule.querySelector('span').textContent = ouvert ? 'Masquer le sommaire' : 'Afficher le sommaire';
	});

	/* ------------------------------------------------------------ recherche */

	/* Chaque caractere est ramene a une forme sans accent et en minuscule, UN
	   pour UN : les positions trouvees dans le texte normalise sont donc celles
	   du texte d origine. */
	function normCar(c) {
		if (c === ' ' || c === ' ') { return ' '; }
		if (c === '’' || c === '‘') { return "'"; }
		var n = c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
		return n.length === 1 ? n : c.toLowerCase().charAt(0) || c;
	}
	function normaliser(s) {
		var r = '';
		for (var i = 0; i < s.length; i++) { r += normCar(s.charAt(i)); }
		return r;
	}

	/* L index : chaque noeud texte du document et sa forme normalisee,
	   construit une fois. Les liens « §n » des titres n en font pas partie. */
	var index = [];
	(function () {
		var w = document.createTreeWalker(article, NodeFilter.SHOW_TEXT, {
			acceptNode: function (n) {
				if (!n.nodeValue.trim()) { return NodeFilter.FILTER_REJECT; }
				var p = n.parentNode;
				if (p.closest && p.closest('a.ancre, script, style, #sommaire')) { return NodeFilter.FILTER_REJECT; }
				return NodeFilter.FILTER_ACCEPT;
			}
		});
		while (w.nextNode()) { index.push({ n: w.currentNode, s: normaliser(w.currentNode.nodeValue) }); }
	})();

	var marques = [];
	var position = -1;
	var derniereRequete = '';

	/* Retire les surlignages en rendant a chaque noeud son texte d origine :
	   l index reste valable, sans relire le document. */
	function nettoyer() {
		var parents = [];
		marques.forEach(function (m) {
			var p = m.parentNode;
			if (!p) { return; }
			m.replaceWith(m.firstChild);
			if (parents.indexOf(p) === -1) { parents.push(p); }
		});
		parents.forEach(function (p) { p.normalize(); });
		marques = [];
		position = -1;
	}

	function titreDe(h) {
		var t = '';
		h.childNodes.forEach(function (c) { if (!(c.nodeType === 1 && c.classList.contains('ancre'))) { t += c.textContent; } });
		return t.replace(/\s+/g, ' ').trim();
	}

	function echapper(s) {
		return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}

	function chercher(brut) {
		var q = normaliser(brut.replace(/\s+/g, ' ').trim());
		nettoyer();
		derniereRequete = q;
		bloc.classList.toggle('a-une-requete', brut.trim() !== '');
		majAdresse(brut.trim());

		if (q.length < 2) {
			cote.classList.remove('en-recherche');
			panneau.innerHTML = '';
			compteur.textContent = '';
			return;
		}
		cote.classList.add('en-recherche');

		/* 1. Reperer toutes les occurrences, dans l ordre du document. */
		var trouvees = [];
		index.forEach(function (e) {
			var pos = [], i = e.s.indexOf(q);
			while (i !== -1) {
				/* Debut de mot seulement : « credit » trouve credit, credits,
				   crediter, mais pas accreditation. */
				if (i === 0 || !/[a-z0-9]/.test(e.s.charAt(i - 1))) { pos.push(i); i = e.s.indexOf(q, i + q.length); }
				else { i = e.s.indexOf(q, i + 1); }
			}
			if (pos.length) { trouvees.push({ e: e, pos: pos }); }
		});

		/* 2. Les surligner. De la derniere a la premiere dans chaque noeud,
		      pour que les positions restent justes. */
		trouvees.forEach(function (t) {
			var n = t.e.n, locales = [];
			for (var k = t.pos.length - 1; k >= 0; k--) {
				var i = t.pos[k];
				if (i + q.length < n.nodeValue.length) { n.splitText(i + q.length); }
				var cible = i > 0 ? n.splitText(i) : n;
				var m = document.createElement('mark');
				m.className = 'reg-hit';
				cible.parentNode.insertBefore(m, cible);
				m.appendChild(cible);
				locales.unshift(m);
			}
			marques = marques.concat(locales);
		});

		/* 3. La liste des resultats, groupes par section. */
		var groupes = [], g = null, h = -1;
		marques.forEach(function (m, idx) {
			while (h + 1 < titres.length && (titres[h + 1].compareDocumentPosition(m) & Node.DOCUMENT_POSITION_FOLLOWING)) { h++; }
			var titre = h >= 0 ? titres[h] : null;
			if (!g || g.titre !== titre) { g = { titre: titre, items: [] }; groupes.push(g); }
			var avant = (m.previousSibling && m.previousSibling.nodeType === 3) ? m.previousSibling.nodeValue : '';
			var apres = (m.nextSibling && m.nextSibling.nodeType === 3) ? m.nextSibling.nodeValue : '';
			avant = avant.length > 48 ? '…' + avant.slice(-48).replace(/^\S*\s/, '') : avant;
			apres = apres.length > 64 ? apres.slice(0, 64).replace(/\s\S*$/, '') + '…' : apres;
			g.items.push({ i: idx, html: echapper(avant) + '<b>' + echapper(m.textContent) + '</b>' + echapper(apres) });
		});

		if (!marques.length) {
			compteur.innerHTML = 'Aucun résultat';
			panneau.innerHTML = '<div class="reg-vide"><strong>Aucun passage ne contient « ' + echapper(brut.trim()) + ' ».</strong>Vérifiez l’orthographe ou essayez un mot plus court.</div>';
			majFleches();
			return;
		}

		var PLAFOND = 400, rendus = 0, html = '';
		groupes.forEach(function (gr) {
			if (rendus >= PLAFOND) { return; }
			var nom = gr.titre ? titreDe(gr.titre) : 'Début du document';
			html += '<div class="reg-groupe"><div class="reg-groupe-titre"><span title="' + echapper(nom) + '">' + echapper(nom) + '</span><em>' + gr.items.length + '</em></div>';
			gr.items.forEach(function (it) {
				if (rendus >= PLAFOND) { return; }
				html += '<button type="button" class="reg-resultat" data-i="' + it.i + '">' + it.html + '</button>';
				rendus++;
			});
			html += '</div>';
		});
		if (marques.length > PLAFOND) {
			html += '<div class="reg-plus">' + PLAFOND + ' premiers passages listés sur ' + marques.length + ' — les flèches les parcourent tous.</div>';
		}
		panneau.innerHTML = html;
		panneau.scrollTop = 0;
		aller(0);
	}

	/* Amene un element au milieu de la zone de lecture, sans faire defiler
	   le reste de la page (l en-tete resterait coupe). Sur mobile, la zone
	   n a pas de hauteur propre : c est la page qui defile. */
	function montrer(el, bloc) {
		var doux = !matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (doc.scrollHeight > doc.clientHeight + 1) {
			var d = el.getBoundingClientRect().top - doc.getBoundingClientRect().top;
			var cible = doc.scrollTop + d - (bloc === 'start' ? 12 : doc.clientHeight / 2 - el.offsetHeight / 2);
			doc.scrollTo({ top: Math.max(0, cible), behavior: doux ? 'smooth' : 'auto' });
		} else {
			el.scrollIntoView({ block: bloc || 'center', behavior: doux ? 'smooth' : 'auto' });
		}
	}

	/* Aller a une occurrence : surlignage or plein, texte et liste suivent. */
	function aller(i) {
		if (!marques.length) { return; }
		i = (i + marques.length) % marques.length;
		if (position >= 0 && marques[position]) { marques[position].classList.remove('actuel'); }
		var anc = panneau.querySelector('.reg-resultat.actuel');
		if (anc) { anc.classList.remove('actuel'); }
		position = i;
		var m = marques[i];
		m.classList.add('actuel');
		montrer(m, 'center');
		var item = panneau.querySelector('.reg-resultat[data-i="' + i + '"]');
		if (item) {
			item.classList.add('actuel');
			var b = panneau.getBoundingClientRect(), r = item.getBoundingClientRect();
			if (r.top < b.top || r.bottom > b.bottom) { panneau.scrollTop += (r.top - b.top) - b.height / 3; }
		}
		compteur.innerHTML = '<b>' + (i + 1) + '</b> / ' + marques.length + ' passage' + (marques.length > 1 ? 's' : '');
		majFleches();
	}
	function majFleches() {
		btnPrec.disabled = btnSuiv.disabled = marques.length < 2;
	}

	panneau.addEventListener('click', function (e) {
		var b = e.target.closest('.reg-resultat');
		if (b) { aller(+b.getAttribute('data-i')); }
	});
	btnSuiv.addEventListener('click', function () { aller(position + 1); });
	btnPrec.addEventListener('click', function () { aller(position - 1); });

	var minuteur = 0;
	champ.addEventListener('input', function () {
		clearTimeout(minuteur);
		var v = champ.value;
		minuteur = setTimeout(function () { chercher(v); }, 220);
	});
	champ.addEventListener('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			/* Entree juste apres la frappe : on lance la recherche sans attendre. */
			if (normaliser(champ.value.replace(/\s+/g, ' ').trim()) !== derniereRequete) {
				clearTimeout(minuteur); chercher(champ.value); return;
			}
			aller(position + (e.shiftKey ? -1 : 1));
		} else if (e.key === 'Escape') {
			champ.value = ''; chercher('');
		}
	});
	effacer.addEventListener('click', function () { champ.value = ''; chercher(''); champ.focus(); });
	document.getElementById('reg-fermer').addEventListener('click', function () {
		champ.value = ''; chercher('');
		bloc.scrollIntoView({ block: 'start' });
	});

	/* La recherche s inscrit dans l adresse : un lien la partage. */
	function majAdresse(q) {
		try {
			var u = new URL(location.href);
			if (q) { u.searchParams.set('q', q); } else { u.searchParams.delete('q'); }
			history.replaceState(null, '', u.pathname + u.search + u.hash);
		} catch (e) {}
	}

	/* Lien profond « …/reglement#p383 » : le contenu defile dans sa zone. */
	function versAncre() {
		if (!location.hash) { return; }
		var cible = doc.querySelector(location.hash);
		if (cible) { montrer(cible, 'start'); marquerActif(cible.id); }
	}
	window.addEventListener('hashchange', versAncre);

	setTimeout(function () {
		var q = new URLSearchParams(location.search).get('q');
		if (q) { champ.value = q; chercher(q); } else { versAncre(); surDefilement(); }
	}, 60);

})();
</script>
</body>
</html>
