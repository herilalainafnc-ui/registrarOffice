<?php
/**
 * ApplicationController - Application installable (PWA)
 * Routes: /manifest.webmanifest, /espace
 *
 * Le manifeste dit au telephone comment installer le site comme une
 * application : nom, icones, couleurs, page d'ouverture. Il passe par PHP
 * parce que nginx ne connait pas le type .webmanifest (il le servirait en
 * application/octet-stream) et pour suivre APP_BASE.
 *
 * /espace est la page d'ouverture de l'application installee : elle renvoie
 * chacun vers son espace (etudiant, enseignant, personnel), ou vers la
 * connexion. Le reste (service worker, page hors connexion) : /sw.js,
 * src/pwa/, init/pwa.php.
 */
class ApplicationController extends BaseController {

    public function manifeste() {
        $base = defined('APP_BASE') ? APP_BASE : '';
        $icones = [];
        foreach ([192, 512] as $n) {
            $icones[] = ['src' => "$base/src/pwa/icone-$n.png", 'sizes' => "{$n}x$n", 'type' => 'image/png', 'purpose' => 'any'];
        }
        foreach ([192, 512] as $n) {
            $icones[] = ['src' => "$base/src/pwa/icone-masquable-$n.png", 'sizes' => "{$n}x$n", 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        $manifeste = [
            'id'               => "$base/espace",
            'name'             => 'Infinit Registrar',
            'short_name'       => 'Registrar',
            'description'      => "Notes, assiduité, messagerie et dossiers de l'Université Adventiste Zurcher.",
            'lang'             => 'fr',
            'dir'              => 'ltr',
            'start_url'        => "$base/espace",
            'scope'            => "$base/",
            'display'          => 'standalone',
            'background_color' => '#12162c',
            'theme_color'      => '#222e65', // la barre du haut (--c-topbar), la meme dans les deux themes
            'categories'       => ['education', 'productivity'],
            'icons'            => $icones,
            /* Un seul raccourci : le manifeste est lu sans la session, il
               doit donc valoir pour tous les roles. */
            'shortcuts'        => [[
                'name'       => 'Messagerie',
                'short_name' => 'Messages',
                'url'        => "$base/messagerie",
                'icons'      => [['src' => "$base/src/pwa/icone-192.png", 'sizes' => '192x192', 'type' => 'image/png']],
            ]],
        ];

        header('Content-Type: application/manifest+json; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($manifeste, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Page d'ouverture de l'application installee.
     *
     * Meme destination par role que la connexion (src/index.php,
     * src/api/google-auth.php). Le cookie « Se souvenir de moi » est
     * honore : getCurrentUser reconnecte silencieusement.
     */
    public function ouvrir() {
        require_once ROOT_DIR . '/data/backdb.php';
        require_once ROOT_DIR . '/data/middleware.php';
        initMiddleware($dtb);

        header('Cache-Control: no-store');
        $user = Middleware::getCurrentUser();
        if (!$user) {
            $this->redirectTo('/login');
        }

        $niveau = (int) ($user['level'] ?? 4);
        $type = $user['user_type'] ?? 'staff';
        $privilege = $user['privilege'] ?? '';
        if ($niveau === 8 || $type === 'student' || $privilege === 'student') {
            $this->redirectTo('/student/home');
        }
        if ($niveau === 7 || $type === 'teacher' || $privilege === 'teacher') {
            $this->redirectTo('/teacher/dashboard');
        }
        $this->redirectTo('/tableau-de-bord');
    }
}
