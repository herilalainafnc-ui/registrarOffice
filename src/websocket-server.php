<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require __DIR__ . '/vendor/autoload.php';

//require dirname(__DIR__) . '/vendor/autoload.php';

class WebSocketServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Stocker la nouvelle connexion
        $this->clients->attach($conn);
        echo "Nouvelle connexion! ({$conn->resourceId})\n";
    }

    // public function onMessage(ConnectionInterface $from, $msg) {
    //     $numRecv = count($this->clients) - 1;
    //     echo sprintf('Connexion %d envoie un message "%s" à %d autres connexions' . "\n", $from->resourceId, $msg, $numRecv);

    //     // Envoyer le message à tous les autres clients
    //     foreach ($this->clients as $client) {
    //         if ($from !== $client) {
    //             // Le client ne doit pas recevoir son propre message
    //             $client->send($msg);
    //         }
    //     }
    // }
    public function onMessage(ConnectionInterface $from, $msg) {
        // Affichez le message reçu pour le débogage
        echo "Message reçu : $msg\n";

        // Remplacer ce code par la logique qui génère votre nouveau contenu HTML
        $newContent = "<div>Nouveau contenu à afficher : " . htmlspecialchars($msg) . "</div>";
        
        // Envoyer le nouveau contenu à tous les clients
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($newContent);
            }
        }
    }


    public function onClose(ConnectionInterface $conn) {
        // La connexion a été fermée
        $this->clients->detach($conn);
        echo "Connexion {$conn->resourceId} fermée\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Erreur : {$e->getMessage()}\n";
        $conn->close();
    }
}

// Créer et démarrer le serveur WebSocket
$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new WebSocketServer()
        )
    ),
    8080
);

$server->run();
