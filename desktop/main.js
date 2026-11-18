/**
 * Processus principal Electron — version desktop d'Infinit Registrar.
 *
 * Electron ne sait pas executer du PHP : il lance donc le serveur interne de
 * PHP (php -S) sur 127.0.0.1, attend qu'il reponde, puis affiche l'application
 * dans une fenetre Chromium. Le code PHP existant est reutilise tel quel ; le
 * routage d'Apache est reproduit par router.php a la racine du projet.
 *
 * Cycle de vie : le serveur PHP est un processus enfant. Il est arrete des que
 * l'application se ferme, pour ne pas laisser un php.exe orphelin.
 */

const { app, BrowserWindow, shell } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const net = require('net');
const http = require('http');
const fs = require('fs');

const RACINE = path.join(__dirname, '..'); // racine du projet (ou vit router.php)
const HOTE = '127.0.0.1';

let serveurPhp = null;
let fenetre = null;

/* --------------------------------------------------------------------------
   Evenements de cycle de vie Squirrel (installeur Windows).

   A l'installation, la mise a jour et la desinstallation, Squirrel relance
   l'executable avec un argument --squirrel-*. L'application doit alors creer
   ou supprimer ses raccourcis (via Update.exe, place un niveau au-dessus),
   puis quitter aussitot — sans demarrer PHP ni ouvrir de fenetre. Sans cette
   gestion, aucun raccourci n'est cree au menu Demarrer.

   Gere sans dependance externe (equivalent de electron-squirrel-startup).
   Renvoie true si un evenement a ete traite : le demarrage normal est alors
   ignore.
   -------------------------------------------------------------------------- */
function gererEvenementsSquirrel() {
    if (process.platform !== 'win32') { return false; }
    const evenement = process.argv[1];
    if (!evenement || evenement.indexOf('--squirrel-') !== 0) { return false; }

    const dossierApp = path.resolve(process.execPath, '..');
    const racineInstall = path.resolve(dossierApp, '..');
    const updateExe = path.resolve(racineInstall, 'Update.exe');
    const nomExe = path.basename(process.execPath);

    const lancerUpdate = (args) => {
        try {
            spawn(updateExe, args, { detached: true }).on('close', () => app.quit());
        } catch (e) { app.quit(); }
    };

    switch (evenement) {
        case '--squirrel-install':
        case '--squirrel-updated':
            lancerUpdate(['--createShortcut', nomExe]);
            return true;
        case '--squirrel-uninstall':
            lancerUpdate(['--removeShortcut', nomExe]);
            return true;
        case '--squirrel-obsolete':
            app.quit();
            return true;
        default:
            return false;
    }
}

/* --------------------------------------------------------------------------
   Localisation du binaire PHP.

   Ordre de recherche :
     1. variable d'environnement REGISTRAR_PHP (override explicite) ;
     2. PHP embarque dans l'app (php/php.exe a la racine) — pour la version
        distribuee, autonome, sans dependance a une installation locale ;
     3. emplacements systeme connus (Laragon / installation standard) ;
     4. « php » dans le PATH, en dernier recours.
   -------------------------------------------------------------------------- */
function trouverPhp() {
    const candidats = [
        process.env.REGISTRAR_PHP,
        path.join(RACINE, 'php', 'php.exe'),
        'C:\\Program Files\\PHP\\current\\php.exe',
        'C:\\laragon\\bin\\php\\php.exe',
    ].filter(Boolean);

    for (const c of candidats) {
        try {
            if (fs.existsSync(c)) { return c; }
        } catch (e) { /* ignore */ }
    }
    return 'php'; // suppose present dans le PATH
}

/* Trouve un port TCP libre sur la boucle locale. */
function portLibre() {
    return new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.unref();
        srv.on('error', reject);
        srv.listen(0, HOTE, () => {
            const port = srv.address().port;
            srv.close(() => resolve(port));
        });
    });
}

/* Lance php -S avec la configuration adaptee a la source de PHP. */
function lancerServeurPhp(port) {
    const php = trouverPhp();
    const phpEmbarque = path.join(RACINE, 'php', 'php.exe');
    const iniEmbarque = path.join(RACINE, 'php', 'php.ini');
    const utiliseEmbarque = (php === phpEmbarque) && fs.existsSync(iniEmbarque);

    const args = [];
    if (utiliseEmbarque) {
        // Le PHP embarque a son propre php.ini (extensions + reglages,
        // extension_dir relatif). On le charge explicitement et on ne force
        // aucune extension a la main : c'est le mode de distribution.
        args.push('-c', iniEmbarque);
    } else {
        // PHP systeme (poste de developpement, sans bundle) : son php.ini
        // n'active pas forcement les extensions requises, on les force donc.
        const extensions = [
            'pdo_mysql', 'mysqli', 'mbstring', 'openssl',
            'curl', 'gd', 'fileinfo', 'exif', 'zip',
        ];
        for (const ext of extensions) { args.push('-d', 'extension=' + ext); }
        args.push('-d', 'display_errors=0');
    }
    args.push('-S', HOTE + ':' + port, '-t', RACINE, path.join(RACINE, 'router.php'));

    console.log('[php]', php, args.join(' '));
    const proc = spawn(php, args, { cwd: RACINE, windowsHide: true });

    proc.stdout.on('data', (d) => console.log('[php]', d.toString().trim()));
    proc.stderr.on('data', (d) => console.log('[php:err]', d.toString().trim()));
    proc.on('exit', (code) => console.log('[php] arrete, code', code));
    return proc;
}

/* Attend que le serveur reponde (jusqu'a ~15 s). */
function attendreServeur(port, delaiMax = 15000) {
    const debut = Date.now();
    const url = 'http://' + HOTE + ':' + port + '/';
    return new Promise((resolve, reject) => {
        const essayer = () => {
            const req = http.get(url, (res) => {
                res.destroy();
                resolve();
            });
            req.on('error', () => {
                if (Date.now() - debut > delaiMax) {
                    reject(new Error('Le serveur PHP n\'a pas repondu a temps.'));
                } else {
                    setTimeout(essayer, 200);
                }
            });
        };
        essayer();
    });
}

async function creerFenetre() {
    const port = await portLibre();
    serveurPhp = lancerServeurPhp(port);
    await attendreServeur(port);

    fenetre = new BrowserWindow({
        width: 1280,
        height: 820,
        minWidth: 960,
        minHeight: 600,
        show: false,
        title: 'Infinit Registrar',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    // Les liens externes (http/https vers l'exterieur) s'ouvrent dans le
    // navigateur du systeme, pas dans la fenetre de l'application.
    fenetre.webContents.setWindowOpenHandler(({ url }) => {
        if (url.startsWith('http://' + HOTE) || url.startsWith('http://localhost')) {
            return { action: 'allow' };
        }
        shell.openExternal(url);
        return { action: 'deny' };
    });

    fenetre.once('ready-to-show', () => fenetre.show());
    fenetre.loadURL('http://' + HOTE + ':' + port + '/');
}

// Un evenement Squirrel (install/maj/desinstall) court-circuite le demarrage :
// on cree/supprime les raccourcis puis on quitte, sans lancer l'application.
if (!gererEvenementsSquirrel()) {

    app.whenReady().then(creerFenetre).catch((err) => {
        console.error('Demarrage impossible :', err);
        app.quit();
    });

    app.on('window-all-closed', () => {
        if (process.platform !== 'darwin') { app.quit(); }
    });

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) { creerFenetre(); }
    });

}

/* Arret propre du serveur PHP avec l'application. */
function arreterServeurPhp() {
    if (!serveurPhp || serveurPhp.killed) { return; }
    try {
        if (process.platform === 'win32') {
            // sur Windows, kill() ne suffit pas toujours a fermer php.exe
            spawn('taskkill', ['/pid', String(serveurPhp.pid), '/f', '/t'], { windowsHide: true });
        } else {
            serveurPhp.kill('SIGTERM');
        }
    } catch (e) { /* ignore */ }
    serveurPhp = null;
}

app.on('will-quit', arreterServeurPhp);
process.on('exit', arreterServeurPhp);
