<?php
/* =============================================================================
   Fuseau horaire de l'application : Madagascar
   (Indian/Antananarivo, UTC+3 toute l'annee, sans heure d'ete)

   Le serveur de production tourne en UTC : systeme, PHP (date.timezone non
   renseigne) et MySQL (verifie le 28/09/2026). date(), strtotime() et NOW()
   y retardaient de trois heures, tandis que quelques scripts fixaient eux-
   memes le fuseau : l'application melangeait les deux heures.

   Ce fichier aligne PHP (date_default_timezone_set) et, par
   fuseauConnexion(), chaque connexion MySQL (SET time_zone) : NOW(),
   CURDATE() et les colonnes TIMESTAMP suivent alors l'heure de Madagascar.
   Il est charge par index.php et par data/backdb.php ; le .user.ini de la
   racine fixe aussi date.timezone pour les scripts qui ne passent par
   aucun des deux (PHP-FPM le lit, pas Apache avec mod_php).

   Les colonnes TIMESTAMP, stockees en temps universel, s'affichent
   desormais a l'heure locale. Les colonnes DATETIME deja remplies ne sont
   pas reecrites : les lignes enregistrees en UTC avant ce changement le
   restent.
   ========================================================================== */

if (!defined('APP_FUSEAU')) {
    define('APP_FUSEAU', 'Indian/Antananarivo');
    date_default_timezone_set(APP_FUSEAU);
}

if (!function_exists('fuseauConnexion')) {
/** Aligne une connexion MySQL sur l'heure de Madagascar. */
function fuseauConnexion(PDO $pdo) : void
{
    /* Decalage chiffre (« +03:00 ») : un fuseau nomme exige des tables que
       MySQL n'a pas forcement chargees. */
    $decalage = (new DateTimeImmutable('now', new DateTimeZone(APP_FUSEAU)))->format('P');
    try {
        $pdo->exec("SET time_zone = '" . $decalage . "'");
    } catch (Throwable $e) {
        // Une heure decalee vaut mieux qu'une page en erreur.
        error_log('Fuseau MySQL non applique : ' . $e->getMessage());
    }
}
}
