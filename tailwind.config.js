/*
 * Tailwind du back-office, precompile.
 *
 * Les pages du back-office chargeaient Tailwind depuis son CDN : 511 Ko de
 * JavaScript qui generaient les styles DANS le navigateur, a chaque page.
 * Cette configuration produit a la place une feuille statique, mise en cache :
 *
 *     npm run build:css
 *
 * a relancer apres toute modification de classes dans les pages. La feuille
 * produite, src/css/tailwind.build.css, est versionnee : le serveur n a pas
 * besoin de Node pour la servir.
 *
 * Les couleurs slate, gray, cyan et blue pointent sur les jetons de
 * src/css/tokens.css (le sceau de l universite) : c est ce qui permet au
 * meme balisage de suivre les themes clair et sombre.
 */

const echelle = (prefixe) => Object.fromEntries(
	[50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]
		.map((n) => [n, `rgb(var(--${prefixe}-${n}) / <alpha-value>)`])
);

module.exports = {
	content: [
		'./src/**/*.{php,html,js}',
		'./app/**/*.{php,js}',
		'./app/.*/**/*.{php,js}',
		'./init/**/*.php',
		'./init/.*/**/*.php',
		'./core/**/*.php',
		'./data/**/*.php',
		// Le guichet d inscription et ses fragments : absents de la liste, leurs
		// classes (lg:w-7/12, text-[10px]...) manquaient a la feuille produite.
		'./inscription/**/*.{php,js}',
		'./routes.php',
		// Bibliotheques tierces : ni classes Tailwind, ni raison de les lire.
		'!./src/dompdf/**',
		'!./src/genPDF/dompdf/**',
		'!./src/vendor/**',
	],

	/* Classes composees en PHP, invisibles a la lecture des fichiers :
	   « hover:<?=$bg_five_color?> » ne donne « hover:bg-slate-700 » qu a
	   l execution (voir les couleurs de session dans init/head.php). */
	safelist: [
		'hover:bg-slate-600',
		'hover:bg-slate-700',
		'hover:bg-slate-800',
		'hover:bg-slate-900',
		'hover:text-slate-100',
	],

	theme: {
		extend: {
			colors: {
				slate: echelle('n'),
				gray: echelle('n'),
				cyan: echelle('a'),
				blue: echelle('a'),
			},
			fontFamily: {
				sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'],
				mono: ['JetBrains Mono', 'ui-monospace', 'Consolas', 'monospace'],
			},
		},
	},

	plugins: [
		require('@tailwindcss/forms'),
		require('@tailwindcss/typography'),
		require('@tailwindcss/aspect-ratio'),
	],
};
