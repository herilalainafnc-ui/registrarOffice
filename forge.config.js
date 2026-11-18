/**
 * Configuration electron-forge — empaquetage de la version desktop.
 *
 * `npm run package` assemble l'application dans out/ (sans installeur).
 * `npm run make` produit les artefacts distribuables (installeur + zip).
 * Les makers necessitent d'avoir ete installes :
 *
 *     npm install --save-dev @electron-forge/maker-squirrel @electron-forge/maker-zip
 *
 * A FAIRE plus tard (hebergement Hostinger) :
 *   - externaliser DB_HOST pour pointer vers le MySQL central ;
 *   - servir les photos/uploads depuis le serveur (elles sont exclues du
 *     paquet ci-dessous : ce sont des donnees, pas du code).
 */

module.exports = {
    packagerConfig: {
        name: 'Infinit Registrar',
        executableName: 'infinit-registrar',
        // Le code PHP doit rester lisible sur le disque pour php -S : pas d'asar.
        asar: false,
        // Icone de l'application (Windows ajoute l'extension .ico).
        icon: 'desktop/app-icon',
        ignore: [
            // Sources de version / outillage / sorties de build
            /^\/\.git/,
            /^\/\.claude/,
            /^\/out/,
            /^\/logs/,
            /^\/desktop\/dist/,

            // DONNEES (vivent sur le serveur, pas dans l'app) :
            // photos etudiants (1,2 Go), enseignants, utilisateurs, uploads.
            /^\/app\/photosetudiants/,
            /^\/app\/photosenseignants/,
            /^\/app\/photosuser/,
            /^\/app\/uploads/,
            /^\/data\/\.BACKUP/,

            // Masters de design et archives lourdes, inutiles a l'execution.
            /\.psd$/,
            /\.rar$/,
            /\.heic(\.bak)?$/,
            /\.sql$/,
            /\.map$/,
        ],
    },
    rebuildConfig: {},
    makers: [
        {
            // Installeur Windows (Setup.exe).
            name: '@electron-forge/maker-squirrel',
            config: {
                name: 'infinit_registrar',
                setupExe: 'InfinitRegistrar-Setup.exe',
                setupIcon: 'desktop/app-icon.ico',
            },
        },
        {
            // Version portable (dossier zippe), toujours fiable en secours.
            name: '@electron-forge/maker-zip',
            platforms: ['win32'],
        },
    ],
    plugins: [],
};
