/* =============================================================================
   Réglage des mentions — interactions de /settings/mentions

   Trois gestes : créer, modifier, supprimer.

   La page manipule des clés dont dépendent plus de quatre mille lignes
   réparties sur six tables, sans contrainte de clé étrangère pour les
   protéger. Chaque action destructrice ou propagée annonce donc son effet
   chiffré avant confirmation — le nombre vient du serveur, rendu dans le DOM,
   pas d'une estimation côté client.
   ========================================================================== */

(function () {
	'use strict';

	var POINT = (window.APP_BASE || '') + '/app/mentions.save.php';

	/** Envoie une action et renvoie la charge utile. */
	function envoyer(champs) {
		var corps = new FormData();
		Object.keys(champs).forEach(function (k) { corps.append(k, champs[k]); });
		corps.append('csrf_token', window.CSRF_TOKEN || '');

		return fetch(POINT, {
			method: 'POST',
			body: corps,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () {
				return { ok: false, message: 'Réponse illisible du serveur (' + r.status + ').' };
			});
		});
	}

	/** Message d'échec, en distinguant le jeton expiré du reste. */
	function signalerEchec(charge) {
		var message = (charge && charge.message) || "L'opération a échoué.";
		if (charge && charge.csrf_expire) {
			message = 'Jeton de sécurité expiré. Rechargez la page et réessayez.';
		}
		if (window.Toast) { window.Toast.error(message); } else if (window.console) { console.error(message); }
	}

	function succes(message) {
		if (window.Toast) { window.Toast.success(message); }
	}

	/** Confirmation en modale (init/toast.php). Sans elle, on ne fait rien. */
	function demander(options, texte) {
		if (window.Toast && typeof window.Toast.confirm === 'function') {
			return window.Toast.confirm(texte, options || {});
		}
		return Promise.resolve(false);
	}

	/**
	 * La modale répond de façon asynchrone. Au premier clic on demande et on
	 * s'arrête ; si c'est confirmé, le clic est rejoué sur le même bouton et ce
	 * second passage trouve la marque et poursuit.
	 * @return {boolean} true si le geste est déjà confirmé.
	 */
	function confirme(el, options, texte) {
		if (el.dataset.confirme === '1') { delete el.dataset.confirme; return true; }
		demander(options, texte).then(function (ok) {
			if (ok) { el.dataset.confirme = '1'; el.click(); }
		});
		return false;
	}

	/* ---- Mouvement ---------------------------------------------------------- */

	/* Les trois replis de cette page — formulaire d'une mention, liste de ses
	   parcours, formulaire d'un parcours — basculaient par l'attribut `hidden`,
	   donc d'un seul coup : la carte changeait de hauteur sans transition, et
	   sur une grille ses voisines semblaient sauter.

	   `hidden` est conservé, parce qu'il retire le bloc replié du parcours au
	   clavier, ce qu'une opacité ne fait pas. Le mouvement est joué autour de
	   lui : on retire l'attribut, on mesure le contenu, on anime `max-height`,
	   puis on efface toute trace — hors animation le bloc n'a ni overflow ni
	   hauteur plafonnée, sans quoi une liste déroulante ouverte vers le bas se
	   retrouverait coupée.

	   Voir la section MOUVEMENT de settings.mentions.css pour les durées. */

	/* Choix système respecté : sans mouvement, les bascules redeviennent
	   instantanées. Les animer en une fraction de milliseconde laisserait des
	   styles en ligne à nettoyer pour rien. */
	var MOUVEMENT = !(window.matchMedia
		&& window.matchMedia('(prefers-reduced-motion: reduce)').matches);

	var DUREE = 220;
	var DUREE_COURTE = 160;

	/**
	 * Le bloc est-il ouvert ? On lit l'intention, pas l'état du DOM : pendant
	 * une fermeture `hidden` n'est posé qu'à la fin, et un clic entre-temps
	 * doit rouvrir plutôt que refermer une seconde fois.
	 */
	function estOuvert(bloc) {
		if (!bloc) { return false; }
		if (bloc.dataset.mnEtat) { return bloc.dataset.mnEtat === 'ouvert'; }
		return !bloc.hidden;
	}

	/** Abandonne l'animation en cours sans jouer son nettoyage. */
	function arreter(bloc) {
		if (bloc.mnMinuteur) { clearTimeout(bloc.mnMinuteur); bloc.mnMinuteur = null; }
		if (bloc.mnEcoute) {
			bloc.removeEventListener('transitionend', bloc.mnEcoute);
			bloc.mnEcoute = null;
		}
	}

	/** Exécute `faire` à la fin de la transition de hauteur, ou sur le filet. */
	function aLaFin(bloc, duree, faire) {
		var achever = function (e) {
			if (e && (e.target !== bloc || e.propertyName !== 'max-height')) { return; }
			arreter(bloc);
			faire();
		};
		bloc.mnEcoute = achever;
		bloc.addEventListener('transitionend', achever);
		/* Filet : un onglet caché ne joue aucune transition, et le bloc
		   resterait figé à mi-hauteur, coupé par son overflow. */
		bloc.mnMinuteur = setTimeout(function () { achever(null); }, duree + 90);
	}

	/** Rend le bloc à son état de repos : plus d'overflow ni de plafond. */
	function libererHauteur(bloc) {
		bloc.classList.remove('mn-anim', 'mn-anim--ferme');
		bloc.style.maxHeight = '';
		bloc.style.opacity = '';
	}

	/**
	 * Fige le bloc à la hauteur atteinte, transition comprise : retirer la
	 * classe supprime la transition, donc la valeur posée s'applique d'un coup
	 * et sert de nouveau point de départ. C'est ce qui rend une bascule
	 * annulable en pleine course.
	 */
	function figer(bloc) {
		arreter(bloc);
		var h = bloc.getBoundingClientRect().height;
		bloc.classList.remove('mn-anim', 'mn-anim--ferme');
		bloc.style.maxHeight = h + 'px';
		void bloc.offsetHeight;
		return h;
	}

	function deplier(bloc) {
		if (!bloc) { return; }

		// Mesuré avant de démasquer : masqué, le bloc n'a pas de hauteur.
		var depart = bloc.hidden ? 0 : figer(bloc);
		bloc.hidden = false;
		bloc.dataset.mnEtat = 'ouvert';
		if (!MOUVEMENT) { libererHauteur(bloc); return; }

		bloc.classList.remove('mn-anim', 'mn-anim--ferme');
		bloc.style.maxHeight = depart + 'px';
		bloc.style.opacity = depart > 0 ? '1' : '0';
		void bloc.offsetHeight;

		bloc.classList.add('mn-anim');
		bloc.style.maxHeight = bloc.scrollHeight + 'px';
		bloc.style.opacity = '1';

		aLaFin(bloc, DUREE, function () { libererHauteur(bloc); });
	}

	function replier(bloc) {
		if (!bloc) { return; }

		bloc.dataset.mnEtat = 'ferme';
		if (!MOUVEMENT || bloc.hidden) {
			bloc.hidden = true;
			libererHauteur(bloc);
			return;
		}

		figer(bloc);
		bloc.classList.add('mn-anim', 'mn-anim--ferme');
		void bloc.offsetHeight;
		bloc.style.maxHeight = '0px';
		bloc.style.opacity = '0';

		aLaFin(bloc, DUREE_COURTE, function () {
			bloc.hidden = true;
			libererHauteur(bloc);
		});
	}

	/**
	 * Amène l'élément dans la vue s'il en dépasse, jamais autrement : `nearest`
	 * ne bouge rien quand l'élément est déjà visible, donc la page ne se
	 * déplace pas sous les yeux de qui lisait ailleurs. L'attente laisse le
	 * repli finir — viser avant, c'est viser l'ancienne hauteur.
	 */
	function montrer(el) {
		if (!el || !el.scrollIntoView) { return; }
		setTimeout(function () {
			try {
				el.scrollIntoView({ behavior: MOUVEMENT ? 'smooth' : 'auto', block: 'nearest' });
			} catch (err) {
				el.scrollIntoView(false);
			}
		}, MOUVEMENT ? DUREE + 20 : 0);
	}

	/** Retire un élément en le laissant s'effacer sur place, pour qu'on voie
	 *  lequel part. */
	function retirer(el) {
		if (!MOUVEMENT) { el.remove(); return; }
		el.classList.add('mn-sort');
		setTimeout(function () { el.remove(); }, DUREE_COURTE + 40);
	}

	/**
	 * Bouton en attente : son icône devient un indicateur qui tourne, et son
	 * libellé d'origine est mis de côté pour être rendu tel quel.
	 * `texte` vient toujours d'une chaîne écrite ici, jamais d'une saisie.
	 */
	function occuper(bouton, texte) {
		if (bouton.dataset.occupe === '1') { return; }
		bouton.dataset.mnLibelle = bouton.innerHTML;
		bouton.dataset.occupe = '1';
		bouton.disabled = true;
		bouton.innerHTML = '<i class="bi bi-arrow-repeat mn-spin"></i>'
			+ (texte ? ' ' + texte : '');
	}

	function liberer(bouton) {
		bouton.disabled = false;
		if (bouton.dataset.occupe !== '1') { return; }
		bouton.dataset.occupe = '';
		bouton.innerHTML = bouton.dataset.mnLibelle || bouton.innerHTML;
	}

	/** Fondu discret sur un bloc que la relecture vient de remplacer. */
	function marquerRevenu(el) {
		if (!MOUVEMENT) { return; }
		el.classList.add('mn-revient');
		setTimeout(function () { el.classList.remove('mn-revient'); }, 240);
	}

	/**
	 * Entrée décalée des cartes, au premier affichage seulement.
	 *
	 * La classe est retirée à la fin : laissée en place, l'animation « both »
	 * figerait `transform: none` — les animations l'emportant sur les
	 * déclarations ordinaires — et le survol ne soulèverait plus rien.
	 * Le rang est plafonné, sinon la dernière carte se ferait attendre.
	 */
	function animerEntree(grille) {
		if (!grille || !MOUVEMENT) { return; }

		var cartes = grille.querySelectorAll('.mn-carte');
		var dernier = 0;
		Array.prototype.forEach.call(cartes, function (c, i) {
			var rang = Math.min(i, 8);
			if (rang > dernier) { dernier = rang; }
			c.style.setProperty('--mn-i', rang);
		});

		grille.classList.add('mn-grille--entree');
		setTimeout(function () {
			grille.classList.remove('mn-grille--entree');
			Array.prototype.forEach.call(cartes, function (c) {
				c.style.removeProperty('--mn-i');
			});
		}, dernier * 35 + 380);
	}

	/**
	 * Signale la carte ou la ligne qui vient de changer et l'amène dans la
	 * vue. Après une relecture la page est reconstruite entière : sans repère,
	 * l'œil ne retrouve pas ce qu'il venait d'enregistrer.
	 */
	function signalerChangement(page, marque) {
		if (!marque) { return; }

		var cible = null;
		if (marque.code) {
			cible = page.querySelector('.mn-pc[data-code="' + marque.code + '"]');
		}
		if (!cible && marque.sigle) {
			cible = page.querySelector('.mn-carte[data-sigle="' + marque.sigle + '"]');
		}
		if (!cible) { return; }

		montrer(cible);
		if (!MOUVEMENT) { return; }
		cible.classList.add('mn-surligne');
		setTimeout(function () { cible.classList.remove('mn-surligne'); }, 1200);
	}
	/* ---- Rafraîchissement sans rechargement -------------------------------- */

	/* Après une écriture, la page est relue et ses blocs remplacés sur place :
	   compteurs d'usage, pastilles, anomalies et en-tête viennent du serveur,
	   seule source fiable de ce qui se rattache à chaque mention. La page ne se
	   recharge plus et garde sa position.

	   Deux choses sont préservées :
	     - le formulaire « Nouvelle mention », qui n'est pas remplacé (des
	       écouteurs y sont posés directement) ;
	     - les parcours dépliés, qui le restent.
	   Les autres écouteurs sont délégués au document et survivent au
	   remplacement. En cas d'échec de la relecture, rechargement classique. */
	function rafraichirPage(marque) {
		var page = document.querySelector('.mn-page');
		if (!page) { location.reload(); return Promise.resolve(); }

		var deplies = Array.prototype.filter.call(page.querySelectorAll('.mn-carte'), function (c) {
			var corps = c.querySelector('.mn-parcours__corps');
			return corps && !corps.hidden;
		}).map(function (c) { return c.dataset.sigle; });

		return fetch(window.location.href, { credentials: 'same-origin' })
			.then(function (r) {
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				return r.text();
			})
			.then(function (html) {
				var neuve = new DOMParser().parseFromString(html, 'text/html').querySelector('.mn-page');
				if (!neuve) { throw new Error('page illisible'); }

				var ajout = null;
				Array.prototype.forEach.call(page.children, function (el) {
					if (el.classList.contains('mn-ajout')) { ajout = el; }
				});
				Array.prototype.slice.call(page.children).forEach(function (el) {
					if (el !== ajout) { el.remove(); }
				});

				var avantAjout = true;
				Array.prototype.slice.call(neuve.children).forEach(function (el) {
					if (el.classList.contains('mn-ajout')) { avantAjout = false; return; }
					var copie = document.importNode(el, true);
					if (avantAjout && ajout) { page.insertBefore(copie, ajout); } else { page.appendChild(copie); }
					marquerRevenu(copie);
				});

				deplies.forEach(function (sigle) {
					var c = page.querySelector('.mn-carte[data-sigle="' + sigle + '"]');
					if (!c) { return; }
					var corps = c.querySelector('.mn-parcours__corps');
					var bascule = c.querySelector('.js-mn-parcours-bascule');
					if (corps) { corps.hidden = false; corps.dataset.mnEtat = 'ouvert'; }
					if (bascule) { bascule.setAttribute('aria-expanded', 'true'); }
				});

				signalerChangement(page, marque);
			})
			.catch(function () { location.reload(); });
	}

	/** La carte qui contient cet élément. */
	function carte(el) { return el.closest('.mn-carte'); }

	/* ---- Modifier ---------------------------------------------------------- */

	document.addEventListener('click', function (e) {
		if (!e.target.closest) { return; }

		var ouvrir = e.target.closest('.js-mn-modifier');
		if (ouvrir) {
			var c = carte(ouvrir);
			var form = c.querySelector('.mn-form');
			var ouvert = estOuvert(form);
			// hidden plutôt qu'un display en style : retire aussi le formulaire
			// du parcours au clavier quand il est replié. deplier et replier
			// animent la hauteur autour de cet attribut sans le remplacer.
			if (ouvert) { replier(form); } else { deplier(form); }
			c.dataset.ouvert = ouvert ? '0' : '1';
			if (!ouvert) {
				var premier = form.querySelector('.js-mn-champ');
				/* preventScroll : sans lui le navigateur saute au champ pendant
				   que le bloc grandit encore, et la carte part de travers. */
				if (premier) { premier.focus({ preventScroll: true }); }
				montrer(c);
			}
			return;
		}

		var annuler = e.target.closest('.js-mn-annuler');
		if (annuler) {
			var c2 = carte(annuler);
			// Les champs reviennent à la valeur rendue par le serveur.
			Array.prototype.forEach.call(c2.querySelectorAll('.js-mn-champ'), function (i) {
				i.value = i.defaultValue;
			});
			replier(c2.querySelector('.mn-form'));
			c2.dataset.ouvert = '0';
			return;
		}
	});

	/* ---- Enregistrer ------------------------------------------------------- */

	document.addEventListener('click', function (e) {
		var bouton = e.target.closest ? e.target.closest('.js-mn-enregistrer') : null;
		if (!bouton) { return; }

		var c = carte(bouton);
		var sigle = c.dataset.sigle;

		var champs = { action: 'enregistrer', sigle: sigle };
		var libelleAvant = c.dataset.libelle;
		var libelleApres = libelleAvant;

		Array.prototype.forEach.call(c.querySelectorAll('.js-mn-champ'), function (i) {
			champs[i.dataset.col] = i.value.trim();
			if (i.dataset.col === 'filiere_description') { libelleApres = i.value.trim(); }
		});

		if (libelleApres === '') {
			signalerEchec({ message: 'Le libellé en français est obligatoire.' });
			return;
		}

		/* Renommer le libellé touche aussi les fiches étudiantes : plusieurs
		   requêtes comparent « filiere_description = etude_envisage », donc les
		   laisser derrière ferait échouer la recherche du barème. Le nombre
		   vient du serveur, rendu dans data-etudiants. */
		if (libelleApres !== libelleAvant) {
			var n = parseInt(c.dataset.etudiants, 10) || 0;
			var texte = 'Renommer « ' + libelleAvant + ' » en « ' + libelleApres + ' » ?';
			if (n > 0) {
				texte += '\n\n' + n + ' fiche' + (n > 1 ? 's' : '') + ' étudiante'
					+ (n > 1 ? 's' : '') + ' sera' + (n > 1 ? 'nt' : '')
					+ ' mise' + (n > 1 ? 's' : '') + ' à jour avec le nouveau libellé.'
					+ '\n\nSans cela, la recherche du barème échouerait pour '
					+ (n > 1 ? 'ces fiches' : 'cette fiche') + '.';
			}
			if (!confirme(bouton, { title: 'Renommer la mention', confirmText: 'Renommer' }, texte)) { return; }
		}

		occuper(bouton, 'Enregistrement…');

		envoyer(champs).then(function (charge) {
			liberer(bouton);

			if (!charge || !charge.ok) {
				signalerEchec(charge);
				return;
			}

			succes(charge.message);

			/* Le libellé a changé : les compteurs d'usage et les anomalies
			   relevées plus bas ne sont plus à jour. On relit la page plutôt
			   que de recalculer ces liens dans le DOM — le serveur est la
			   seule source fiable de ce qui s'y rattache. */
			if (libelleApres !== libelleAvant) {
				rafraichirPage({ sigle: sigle });
				return;
			}

			// Seuls des champs secondaires ont bougé : mise à jour sur place.
			Array.prototype.forEach.call(c.querySelectorAll('.js-mn-champ'), function (i) {
				i.defaultValue = i.value;
				if (i.dataset.col === 'domaine_name') {
					var zone = c.querySelector('.js-mn-domaine');
					if (zone) {
						if (i.value.trim() === '') {
							zone.innerHTML = '<em>domaine non renseigné</em>';
						} else {
							zone.textContent = i.value.trim();
						}
					}
				}
			});
			replier(c.querySelector('.mn-form'));
			c.dataset.ouvert = '0';
		}).catch(function () {
			liberer(bouton);
			signalerEchec(null);
		});
	});

	/* ---- Fermer / réouvrir une mention ------------------------------------- */

	/* La fermeture est réversible et ne touche aucune donnée : c'est la seule
	   action possible sur une mention qui porte des inscriptions, la
	   suppression restant fermée tant qu'une donnée s'y rattache. La
	   confirmation dit précisément ce qui change et ce qui ne change pas —
	   sans quoi « fermer » se confondrait avec « supprimer ». */

	document.addEventListener('click', function (e) {
		var bouton = e.target.closest ? e.target.closest('.js-mn-ouverture') : null;
		if (!bouton) { return; }

		var c = carte(bouton);
		var ouverte = c.dataset.ouverte === '1';
		var nom = c.dataset.libelle;

		var texte = ouverte
			? 'Fermer « ' + nom + ' » aux inscriptions ?\n\n'
				+ 'Elle ne sera plus proposée pour :\n'
				+ '  • la création d\'un étudiant\n'
				+ '  • le rattachement d\'un cours\n'
				+ '  • la pré-inscription en ligne\n\n'
				+ 'Rien n\'est supprimé : fiches, relevés, statistiques, filtres '
				+ 'd\'impression et barème continuent de l\'afficher. '
				+ 'La réouverture se fait au même endroit.'
			: 'Réouvrir « ' + nom + ' » aux inscriptions ?\n\n'
				+ 'Elle redeviendra proposée pour un nouvel étudiant, un nouveau cours '
				+ 'et la pré-inscription en ligne.';

		if (!confirme(bouton, { title: ouverte ? 'Fermer la mention' : 'Réouvrir la mention', confirmText: ouverte ? 'Fermer aux inscriptions' : 'Réouvrir', danger: ouverte }, texte)) { return; }

		occuper(bouton, '');

		envoyer({ action: 'ouverture', sigle: c.dataset.sigle, ouverte: ouverte ? '0' : '1' })
			.then(function (charge) {
				liberer(bouton);
				if (!charge || !charge.ok) {
					signalerEchec(charge);
					return;
				}
				succes(charge.message);
				/* L'état change la pastille, la bordure, le libellé du bouton et
				   le décompte de l'en-tête. On relit la page plutôt que de tenir
				   ces quatre endroits à jour à la main. */
				rafraichirPage({ sigle: c.dataset.sigle });
			}).catch(function () {
				liberer(bouton);
				signalerEchec(null);
			});
	});

	/* ---- Supprimer --------------------------------------------------------- */

	document.addEventListener('click', function (e) {
		var bouton = e.target.closest ? e.target.closest('.js-mn-supprimer') : null;
		if (!bouton || bouton.disabled) { return; }

		var c = carte(bouton);
		var nom = c.dataset.libelle;

		if (!confirme(bouton, { title: 'Supprimer la mention', confirmText: 'Supprimer', danger: true }, 
			'Supprimer la mention « ' + nom + ' » ?\n\n'
			+ 'Aucune donnée ne s\'y rattache aujourd\'hui, la suppression est donc sans risque. '
			+ 'Elle est définitive.'
		)) { return; }

		occuper(bouton, '');

		envoyer({ action: 'supprimer', sigle: c.dataset.sigle }).then(function (charge) {
			if (!charge || !charge.ok) {
				liberer(bouton);
				signalerEchec(charge);
				return;
			}
			succes(charge.message);
			retirer(c);
		}).catch(function () {
			liberer(bouton);
			signalerEchec(null);
		});
	});

	/* ---- Parcours d'une mention -------------------------------------------- */

	/* Les parcours se réfèrent de deux façons : t_2023_cours porte le CODE
	   (324 cours), tandis que tbl_2024_etudiant.etude_option et
	   t_2024_inscription_session.parcours_std portent le LIBELLÉ (1 714 et
	   1 362 lignes). Le code est donc verrouillé dès qu'un cours s'y rattache,
	   et renommer un libellé doit propager — sinon les jointures
	   « WHERE description = etude_option » ne trouvent plus rien. */

	/** La ligne de parcours qui contient cet élément. */
	function ligneParcours(el) { return el.closest('.mn-pc'); }

	/** Le code du parcours à créer s'écrit en majuscules, comme les existants. */
	document.addEventListener('input', function (e) {
		var champ = e.target;
		if (!champ.classList || !champ.classList.contains('js-mn-pc-new')) { return; }
		if (champ.dataset.col !== 'shortcode') { return; }

		var pos = champ.selectionStart;
		champ.value = champ.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
		champ.setSelectionRange(pos, pos);
	});

	document.addEventListener('click', function (e) {
		if (!e.target.closest) { return; }

		/* Replier / déplier la liste des parcours d'une mention. */
		var bascule = e.target.closest('.js-mn-parcours-bascule');
		if (bascule) {
			var corps = carte(bascule).querySelector('.mn-parcours__corps');
			var ouvert = estOuvert(corps);
			if (ouvert) { replier(corps); } else { deplier(corps); }
			bascule.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
			/* Une liste dépliée dépasse souvent le bas de l'écran : la carte
			   est ramenée dans la vue, et seulement si elle en sort. */
			if (!ouvert) { montrer(carte(bascule)); }
			return;
		}

		/* Ouvrir le formulaire d'un parcours. */
		var modifier = e.target.closest('.js-mn-pc-modifier');
		if (modifier) {
			var ligne = ligneParcours(modifier);
			var form = ligne.querySelector('.mn-pc__form');
			if (estOuvert(form)) { replier(form); return; }

			deplier(form);
			var premier = form.querySelector('.js-mn-pc-champ');
			if (premier) { premier.focus({ preventScroll: true }); }
			montrer(ligne);
			return;
		}

		var annulerPc = e.target.closest('.js-mn-pc-annuler');
		if (annulerPc) {
			var l = ligneParcours(annulerPc);
			Array.prototype.forEach.call(l.querySelectorAll('.js-mn-pc-champ'), function (i) {
				i.value = i.defaultValue;
			});
			replier(l.querySelector('.mn-pc__form'));
			return;
		}

		/* Enregistrer un parcours. */
		var enregistrerPc = e.target.closest('.js-mn-pc-enregistrer');
		if (enregistrerPc) {
			var l2 = ligneParcours(enregistrerPc);
			var champs = { action: 'parcours_enregistrer', shortcode: l2.dataset.code };
			var avant = l2.dataset.libelle;
			var apres = avant;

			Array.prototype.forEach.call(l2.querySelectorAll('.js-mn-pc-champ'), function (i) {
				champs[i.dataset.col] = i.value.trim();
				if (i.dataset.col === 'description') { apres = i.value.trim(); }
			});

			if (apres === '') {
				signalerEchec({ message: 'Le libellé en français est obligatoire.' });
				return;
			}

			if (apres !== avant) {
				var nE = parseInt(l2.dataset.etudiants, 10) || 0;
				var nI = parseInt(l2.dataset.inscriptions, 10) || 0;
				var texte = 'Renommer « ' + avant + ' » en « ' + apres + ' » ?';
				if (nE + nI > 0) {
					texte += '\n\n' + nE + ' fiche(s) étudiante(s) et ' + nI
						+ ' inscription(s) seront mises à jour avec le nouveau libellé.'
						+ '\n\nSans cela, le relevé de notes et la liste des cours offerts '
						+ 'ne retrouveraient plus le parcours de ces étudiants.';
				}
				if (!confirme(enregistrerPc, { title: 'Renommer le parcours', confirmText: 'Renommer' }, texte)) { return; }
			}

			occuper(enregistrerPc, '');

			envoyer(champs).then(function (charge) {
				liberer(enregistrerPc);

				if (!charge || !charge.ok) {
					signalerEchec(charge);
					return;
				}
				succes(charge.message);
				/* Un renommage change les compteurs et peut faire disparaître une
				   anomalie signalée plus bas : on relit la page plutôt que de
				   recalculer ces liens dans le DOM. */
				/* Le code d'un parcours ne se modifie pas ici (voir
				   mentionParcoursChamps) : il reste un repère valable après
				   la relecture. */
				rafraichirPage({ code: l2.dataset.code });
			}).catch(function () {
				liberer(enregistrerPc);
				signalerEchec(null);
			});
			return;
		}

		/* Supprimer un parcours. */
		var supprimerPc = e.target.closest('.js-mn-pc-supprimer');
		if (supprimerPc && !supprimerPc.disabled) {
			var l3 = ligneParcours(supprimerPc);
			if (!confirme(supprimerPc, { title: 'Supprimer le parcours', confirmText: 'Supprimer', danger: true }, 
				'Supprimer le parcours « ' + l3.dataset.libelle + ' » ?\n\n'
				+ 'Aucun cours, étudiant ni inscription ne s\'y rattache aujourd\'hui. '
				+ 'La suppression est définitive.'
			)) { return; }

			occuper(supprimerPc, '');
			envoyer({ action: 'parcours_supprimer', shortcode: l3.dataset.code })
				.then(function (charge) {
					if (!charge || !charge.ok) {
						liberer(supprimerPc);
						signalerEchec(charge);
						return;
					}
					succes(charge.message);
					retirer(l3);
				}).catch(function () {
					liberer(supprimerPc);
					signalerEchec(null);
				});
			return;
		}

		/* Créer un parcours dans cette mention. */
		var creerPc = e.target.closest('.js-mn-pc-creer');
		if (creerPc) {
			var c = carte(creerPc);
			var bloc = creerPc.closest('.mn-pc-ajout');
			var champsNew = { action: 'parcours_creer', sigle: c.dataset.sigle };

			Array.prototype.forEach.call(bloc.querySelectorAll('.js-mn-pc-new'), function (i) {
				champsNew[i.dataset.col] = i.value.trim();
			});

			if (!champsNew.shortcode || !champsNew.description) {
				signalerEchec({ message: 'Le code et le libellé en français sont obligatoires.' });
				return;
			}

			if (!confirme(creerPc, { title: 'Ajouter un parcours', confirmText: 'Ajouter' }, 
				'Ajouter le parcours « ' + champsNew.description + ' » ('
				+ champsNew.shortcode + ') à ' + c.dataset.libelle + ' ?\n\n'
				+ 'Le code servira de clé aux cours : il ne pourra plus être modifié '
				+ 'dès le premier cours rattaché.'
			)) { return; }

			occuper(creerPc, 'Ajout…');

			envoyer(champsNew).then(function (charge) {
				liberer(creerPc);

				if (!charge || !charge.ok) {
					signalerEchec(charge);
					return;
				}
				succes(charge.message);
				// La nouvelle ligne doit venir du serveur, avec ses compteurs.
				rafraichirPage({ code: champsNew.shortcode, sigle: c.dataset.sigle });
			}).catch(function () {
				liberer(creerPc);
				signalerEchec(null);
			});
		}
	});

	/* ---- Créer ------------------------------------------------------------- */

	// Le sigle s'écrit en majuscules : le champ le fait visuellement par CSS,
	// on aligne la valeur pour que ce qui part au serveur soit ce qui est lu.
	var champSigle = document.getElementById('mnNewSigle');
	if (champSigle) {
		champSigle.addEventListener('input', function () {
			var pos = this.selectionStart;
			this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '');
			this.setSelectionRange(pos, pos);
		});
	}

	var boutonCreer = document.getElementById('mnCreer');
	if (boutonCreer) {
		boutonCreer.addEventListener('click', function () {
			var lire = function (id) {
				var el = document.getElementById(id);
				return el ? el.value.trim() : '';
			};

			var sigle = lire('mnNewSigle');
			var fr = lire('mnNewFr');

			if (sigle === '' || fr === '') {
				signalerEchec({ message: 'Le sigle et le libellé en français sont obligatoires.' });
				return;
			}

			if (!confirme(boutonCreer, { title: 'Créer la mention', confirmText: 'Créer' }, 
				'Créer la mention « ' + fr + ' » avec le sigle ' + sigle + ' ?\n\n'
				+ 'Le sigle servira de clé dans les inscriptions, les cours et le barème : '
				+ 'il ne pourra plus être modifié dès la première donnée rattachée.'
			)) { return; }

			occuper(boutonCreer, 'Création…');

			envoyer({
				action: 'creer',
				sigle: sigle,
				filiere_description: fr,
				filiere_description_ang: lire('mnNewEn'),
				domaine_signe: lire('mnNewDs'),
				domaine_name: lire('mnNewDn')
			}).then(function (charge) {
				liberer(boutonCreer);

				if (!charge || !charge.ok) {
					signalerEchec(charge);
					return;
				}
				succes(charge.message);
				/* La nouvelle carte doit venir du serveur, avec ses compteurs
				   d'usage et sa pastille de visibilité publique. On relit la
				   page plutôt que de fabriquer une carte approximative. */
				['mnNewSigle', 'mnNewFr', 'mnNewEn', 'mnNewDs', 'mnNewDn'].forEach(function (id) {
					var champ = document.getElementById(id);
					if (champ) { champ.value = ''; }
				});
				rafraichirPage({ sigle: sigle });
			}).catch(function () {
				liberer(boutonCreer);
				signalerEchec(null);
			});
		});
	}
	/* Premier affichage : les cartes entrent en cascade. C'est le seul
	   mouvement d'ensemble que la page se permet — après une écriture elle
	   revient en fondu, sans rejouer un chargement. */
	animerEntree(document.querySelector('.mn-grille'));
})();
