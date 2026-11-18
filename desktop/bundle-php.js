/**
 * Assemble le PHP portable de la version desktop dans php/ (a la racine).
 *
 * Les binaires PHP (~96 Mo) ne sont PAS suivis par git : ils sont recopies
 * depuis le PHP installe sur la machine de build par ce script. Seul le
 * fichier de configuration desktop/php.ini l'est ; il devient php/php.ini.
 *
 *     npm run bundle:php
 *
 * Recherche du PHP source, dans l'ordre :
 *   1. variable d'environnement REGISTRAR_PHP (chemin vers php.exe) ;
 *   2. emplacements Windows connus (PHP officiel, Laragon) ;
 *   3. « php » dans le PATH.
 * Le dossier d'installation copie est celui qui CONTIENT ce php.exe (liens
 * symboliques resolus), avec son php8.dll, ses DLL de support et son ext/.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const RACINE = path.join(__dirname, '..');
const DEST = path.join(RACINE, 'php');
const INI_SOURCE = path.join(__dirname, 'php.ini');

function trouverPhpExe() {
    const candidats = [
        process.env.REGISTRAR_PHP,
        'C:\\Program Files\\PHP\\current\\php.exe',
        'C:\\laragon\\bin\\php\\php.exe',
    ].filter(Boolean);

    for (const c of candidats) {
        if (fs.existsSync(c)) { return c; }
    }
    // Dernier recours : resoudre « php » via le PATH.
    try {
        const cmd = process.platform === 'win32' ? 'where php' : 'command -v php';
        const sortie = execSync(cmd, { encoding: 'utf8' }).split(/\r?\n/)[0].trim();
        if (sortie && fs.existsSync(sortie)) { return sortie; }
    } catch (e) { /* introuvable */ }
    return null;
}

function main() {
    const phpExe = trouverPhpExe();
    if (!phpExe) {
        console.error('PHP introuvable. Definissez REGISTRAR_PHP vers un php.exe, '
            + 'ou installez PHP puis reessayez.');
        process.exit(1);
    }

    // Resoudre les liens (ex. C:\Program Files\PHP\current -> ...\8.x\nts\x64)
    // pour copier les vrais fichiers.
    const phpReel = fs.realpathSync(phpExe);
    const source = path.dirname(phpReel);
    console.log('PHP source :', source);

    // Verification minimale : le dossier doit contenir le coeur et les extensions.
    const dllCoeur = fs.readdirSync(source).find((f) => /^php\d*\.dll$/i.test(f));
    if (!dllCoeur || !fs.existsSync(path.join(source, 'ext'))) {
        console.error('Le dossier PHP source semble incomplet (php*.dll ou ext/ manquant) :', source);
        process.exit(1);
    }

    console.log('Copie vers', DEST, '...');
    fs.rmSync(DEST, { recursive: true, force: true });
    fs.cpSync(source, DEST, { recursive: true });

    // Remplacer le php.ini copie par notre configuration desktop.
    fs.copyFileSync(INI_SOURCE, path.join(DEST, 'php.ini'));
    console.log('php.ini desktop applique.');

    // Verifier que le bundle demarre et charge les extensions cles.
    try {
        const out = execSync(
            '"' + path.join(DEST, 'php.exe') + '" -c "' + path.join(DEST, 'php.ini') + '" -m',
            { encoding: 'utf8' }
        ).toLowerCase();
        const requises = ['pdo_mysql', 'mbstring', 'curl', 'gd', 'zip', 'openssl'];
        const manquantes = requises.filter((e) => !out.split(/\r?\n/).includes(e));
        if (manquantes.length) {
            console.error('Extensions manquantes dans le bundle :', manquantes.join(', '));
            process.exit(1);
        }
    } catch (e) {
        console.error('Le PHP embarque ne demarre pas :', e.message);
        process.exit(1);
    }

    console.log('OK — PHP portable pret dans php/.');
}

main();
