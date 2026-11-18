/**
 * Preload Electron — pont sûr entre la page et le processus principal.
 *
 * Volontairement minimal pour l'instant : l'application PHP fonctionne telle
 * quelle dans la fenetre. On expose seulement un indicateur permettant au code
 * de la page de savoir qu'il tourne dans l'enveloppe desktop (utile plus tard,
 * par exemple pour activer l'impression locale de badges ou de PDF).
 */

const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('registrarDesktop', {
    estDesktop: true,
    version: process.versions.electron,
});
