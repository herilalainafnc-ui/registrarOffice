#!/bin/bash
# Studio UAZ : installe (ou met a jour) le service de rendu des visuels sur le serveur.
# Source : depot du registrar, outils/studio-rendu/, envoye dans ~/studio-rendu/.
#
#   1. verifie que le code du Studio est deja livre (/var/www/registrar/src/studio) et que le port 3310 est libre ;
#   2. construit l'image Docker (Playwright + Chromium, ~2 Go la premiere fois) ;
#   3. remplace le conteneur uaz-studio-rendu : 127.0.0.1:3310 seulement, src/studio en lecture seule,
#      1,5 Go de memoire et 2 processeurs au plus, aucun droit superflu, redemarrage automatique ;
#   4. verifie : /sante, puis un vrai rendu (essai.json) qui doit donner un PNG de 1080 x 1350.
#
# A lancer avec sudo :  sudo bash ~/studio-rendu/installer.sh
# Il continue tout seul si la connexion SSH tombe. Journal : ~/deploy-backups/.

SRC=/home/rabenamana/studio-rendu
STUDIO=/var/www/registrar/src/studio
NOM=uaz-studio-rendu
IMAGE=uaz-studio-rendu:1.0.0
PORT=3310

if [ -z "$UAZ_DETACHE" ]; then
	[ "$(id -u)" = 0 ] || { echo "ARRET : lancer avec sudo"; exit 1; }
	for f in Dockerfile package.json package-lock.json moteur.js serveur.js; do
		[ -f "$SRC/$f" ] || { echo "ARRET : $SRC/$f manquant"; exit 1; }
	done
	TS=$(TZ=Indian/Antananarivo date +%Y%m%d-%H%M%S)
	B=/home/rabenamana/deploy-backups/$TS-studio-rendu
	mkdir -p "$B"
	UAZ_DETACHE=1 UAZ_B="$B" setsid nohup bash "$0" > "$B/sortie.txt" 2>&1 < /dev/null &
	PID=$!
	echo "journal : $B/sortie.txt (le travail continue meme si la connexion tombe)"
	sleep 1
	tail --pid=$PID -n +1 -f "$B/sortie.txt"
	exit 0
fi

B=$UAZ_B
etape() { echo; echo "=== $(TZ=Indian/Antananarivo date +%H:%M:%S) $*"; }

etape "1. verifications"
[ -f "$STUDIO/rendu.html" ] && [ -f "$STUDIO/studio-rendu.js" ] && [ -f "$STUDIO/polices/archivo-100-400-latin.woff2" ] \
	|| { echo "ARRET : $STUDIO incomplet : livrer le code du Studio avant le service (sinon Docker creerait un dossier vide a root)"; exit 1; }
command -v docker > /dev/null || { echo "ARRET : docker absent"; exit 1; }
if ss -ltn "( sport = :$PORT )" | grep -q ":$PORT" && ! docker ps --format '{{.Names}}' | grep -qx "$NOM"; then
	echo "ARRET : le port $PORT est deja pris par autre chose"; ss -ltnp "( sport = :$PORT )"; exit 1
fi
echo "  code du Studio present ; port $PORT libre (ou deja a nous)"
docker ps -a --filter "name=^${NOM}$" --format '  conteneur actuel : {{.Names}} {{.Image}} {{.Status}}' > "$B/avant.txt"
cat "$B/avant.txt"

etape "2. image $IMAGE (la premiere fois : telechargement de l'image Playwright, quelques minutes)"
docker build --pull -t "$IMAGE" "$SRC" > "$B/build.txt" 2>&1
CODE=$?
tail -5 "$B/build.txt"
[ $CODE = 0 ] || { echo "ARRET : construction de l'image (code $CODE), voir $B/build.txt"; exit 1; }

etape "3. conteneur $NOM"
docker rm -f "$NOM" > /dev/null 2>&1 && echo "  ancien conteneur retire"
docker run -d --name "$NOM" --restart unless-stopped --init \
	-p 127.0.0.1:$PORT:$PORT \
	-v "$STUDIO":/studio:ro \
	--memory 1536m --cpus 2 --pids-limit 512 \
	--cap-drop ALL --security-opt no-new-privileges \
	--log-opt max-size=10m --log-opt max-file=3 \
	"$IMAGE" > /dev/null || { echo "ARRET : le conteneur ne demarre pas"; docker logs "$NOM" 2>&1 | tail -20; exit 1; }
echo "  demarre : $(docker ps --filter "name=^${NOM}$" --format '{{.Status}} {{.Ports}}')"

etape "4. verifications"
OK=0
for i in $(seq 1 30); do
	if curl -sf "http://127.0.0.1:$PORT/sante" > "$B/sante.json"; then OK=1; break; fi
	sleep 2
done
[ $OK = 1 ] || { echo "ARRET : /sante ne repond pas"; docker logs "$NOM" 2>&1 | tail -20; exit 1; }
echo "  /sante : $(cat "$B/sante.json")"
# Le rendu d'essai : le preset EVT livre, des donnees fictives, sans image.
php -r '$p = json_decode(file_get_contents($argv[1]), true); echo json_encode(["preset" => $p, "format" => "4:5", "sortie" => "png", "echelle" => 1, "images" => (object) [],
	"donnees" => ["organisateur" => "Service de test", "type" => "Essai du service", "titre" => "Visuel de test du Studio UAZ", "motCleOr" => "Studio UAZ",
	"date" => date("Y-m-d"), "heure" => "15", "lieu" => "Salle de test", "acces" => "Essai"]], JSON_UNESCAPED_UNICODE);' "$STUDIO/presets/evt.json" > "$B/essai.json"
CODE=$(curl -s -o "$B/essai.png" -w '%{http_code}' -H 'Content-Type: application/json' --data-binary @"$B/essai.json" "http://127.0.0.1:$PORT/rendre")
SIG=$(head -c 8 "$B/essai.png" | od -An -tx1 | tr -d ' \n')
DIM=$(php -r '$i = @getimagesize($argv[1]); echo $i ? $i[0] . " x " . $i[1] : "illisible";' "$B/essai.png")
echo "  rendu d'essai : http $CODE, $(wc -c < "$B/essai.png") octets, $DIM"
[ "$CODE" = 200 ] && [ "$SIG" = 89504e470d0a1a0a ] && [ "$DIM" = "1080 x 1350" ] || { echo "ARRET : le rendu d'essai a echoue"; head -c 400 "$B/essai.png"; echo; docker logs "$NOM" 2>&1 | tail -20; exit 1; }
echo "  depuis l'exterieur du serveur, le port n'est pas publie : $(ss -ltn "( sport = :$PORT )" | awk 'NR>1 {print $4}' | tr '\n' ' ')"

{
	echo "Studio UAZ, service de rendu ($(TZ=Indian/Antananarivo date '+%d/%m/%Y %H:%M')) : conteneur $NOM ($IMAGE), 127.0.0.1:$PORT, $STUDIO monte en lecture seule, redemarrage automatique."
	echo "Avant : $(cat "$B/avant.txt")"
	echo "Journal du service : docker logs $NOM"
	echo "Retour arriere : docker rm -f $NOM (l'export du Studio repond alors « le service de rendu ne répond pas ») ; l'image : docker rmi $IMAGE"
} > "$B/RESUME.txt"
chown -R rabenamana:rabenamana "$B"
etape "termine"
