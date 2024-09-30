<?php
require 'vendor/autoload.php';

use Google\Client;

function getClient() {
    $client = new Client();
    $client->setApplicationName('Infinit Registrar');
    $client->setScopes(Google\Service\Sheets::SPREADSHEETS);
    $client->setAuthConfig('/json/credentials.json'); // Chemin vers le fichier JSON téléchargé
    $client->setAccessType('offline');

    // Vérifier si le fichier token existe
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // Si l'accès n'est pas valide, en demander un nouveau
    if ($client->isAccessTokenExpired()) {
        // Récupérer un nouveau jeton d'accès
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Demander un nouveau jeton d'accès
            $authUrl = $client->createAuthUrl();
            printf("Ouvrez ce lien dans votre navigateur : %s\n", $authUrl);
            print 'Entrez le code : ';
            $handle = fopen("php://stdin", "r");
            $code = fgets($handle);
            $accessToken = $client->fetchAccessTokenWithAuthCode(trim($code));
            $client->setAccessToken($accessToken);
            // Sauvegarder le jeton d'accès
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
    }

    return $client;
}
?>