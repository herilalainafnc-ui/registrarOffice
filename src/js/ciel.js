/*
 * Pose les etoiles des ciels (src/css/ciel.css). Trois couches, de la plus
 * fine et nombreuse a la plus grosse et rare, comme la reference ; la
 * densite suit la largeur de l ecran pour qu un grand ecran ne paraisse
 * pas vide. Environ une etoile sur douze prend l or du sceau.
 */
(function () {
	'use strict';

	function etoiles(nombre, largeur) {
		var liste = [];
		for (var i = 0; i < nombre; i++) {
			var x = Math.round(Math.random() * largeur);
			var y = Math.round(Math.random() * 2000);
			var couleur = Math.random() < 0.08
				? 'rgba(206, 180, 128, .9)'
				: 'rgba(255, 255, 255, ' + (0.45 + Math.random() * 0.55).toFixed(2) + ')';
			liste.push(x + 'px ' + y + 'px ' + couleur);
		}
		return liste.join(', ');
	}

	function peupler(ciel) {
		if (ciel.getAttribute('data-ciel-pret')) { return; }
		ciel.setAttribute('data-ciel-pret', '1');
		var largeur = Math.max(ciel.clientWidth || 0, 600);
		var densite = parseFloat(ciel.getAttribute('data-ciel')) || 1;
		/* 700 / 200 / 100 etoiles pour 2 000 px de large dans la reference. */
		[[700, 1], [200, 2], [100, 3]].forEach(function (c) {
			var couche = document.createElement('div');
			couche.className = 'ciel__couche ciel__couche--' + c[1];
			couche.style.setProperty('--etoiles', etoiles(Math.round(c[0] * densite * largeur / 2000), largeur));
			ciel.appendChild(couche);
		});
	}

	function tout() { document.querySelectorAll('[data-ciel]').forEach(peupler); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', tout); } else { tout(); }
})();
