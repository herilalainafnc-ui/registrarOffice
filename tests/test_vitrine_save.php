<?php
/* =============================================================================
   Enregistrement des reglages de la vitrine

   Verifie la fonction partagee par le point d'entree AJAX et par le repli sans
   JavaScript. Le test ECRIT en base : il releve la valeur d'origine et la
   remet en place a la fin, y compris si une assertion echoue.

   Lancement :  php tests/test_vitrine_save.php
   ========================================================================== */

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/vitrine.save.php';

$ok = 0; $ko = 0;
function verifie(string $quoi, $attendu, $obtenu) : void {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  ok   $quoi\n"; return; }
    $ko++;
    echo "  ECHEC $quoi\n       attendu: " . var_export($attendu, true)
       . "\n       obtenu : " . var_export($obtenu, true) . "\n";
}

$cle = 'contact.bp';
$origine = $dtb->query("SELECT valeur FROM vitrine_config WHERE cle = " . $dtb->quote($cle))
               ->fetchColumn();
if ($origine === false) { fwrite(STDERR, "La cle $cle est absente. Lancez data/vitrine.seed.php.\n"); exit(1); }
echo "Valeur d'origine de $cle : " . var_export($origine, true) . "\n\n";

$champ = str_replace('.', '__', $cle);

try {
    /* --- 1. Une valeur est bien ecrite ---------------------------------- */
    $r = vitrineEnregistrer($dtb, [$champ => 'BP TEST 999'], [], 'Test automatise');
    verifie("type de retour", 'ok', $r['type']);
    verifie("valeur en base",
        'BP TEST 999',
        $dtb->query("SELECT valeur FROM vitrine_config WHERE cle = " . $dtb->quote($cle))->fetchColumn());
    verifie("une trace est renvoyee pour le champ", true, isset($r['traces'][$champ]));
    verifie("aucune image", [], $r['images']);

    /* --- 2. L'agent est inscrit dans la piste d'audit ------------------- */
    verifie("modifie_par enregistre",
        'Test automatise',
        $dtb->query("SELECT modifie_par FROM vitrine_config WHERE cle = " . $dtb->quote($cle))->fetchColumn());

    /* --- 3. Un champ vide devient NULL, pas une chaine vide ------------- */
    vitrineEnregistrer($dtb, [$champ => '   '], [], 'Test automatise');
    verifie("chaine vide stockee en NULL",
        null,
        $dtb->query("SELECT valeur FROM vitrine_config WHERE cle = " . $dtb->quote($cle))->fetchColumn());

    /* --- 4. Un champ absent du POST n'est pas touche -------------------- */
    vitrineEnregistrer($dtb, [$champ => 'GARDE'], [], 'Test automatise');
    $r = vitrineEnregistrer($dtb, ['cle__inexistante' => 'x'], [], 'Test automatise');
    verifie("cle inconnue ignoree", 0, count($r['traces']));
    verifie("valeur precedente intacte",
        'GARDE',
        $dtb->query("SELECT valeur FROM vitrine_config WHERE cle = " . $dtb->quote($cle))->fetchColumn());

    /* --- 5. Le prefixe https est ajoute sur les champs de type url ------ */
    $cleUrl = $dtb->query("SELECT cle FROM vitrine_config WHERE type = 'url' LIMIT 1")->fetchColumn();
    if ($cleUrl !== false) {
        $urlOrigine = $dtb->query("SELECT valeur FROM vitrine_config WHERE cle = " . $dtb->quote($cleUrl))->fetchColumn();
        vitrineEnregistrer($dtb, [str_replace('.', '__', $cleUrl) => 'exemple.org/page'], [], 'Test automatise');
        verifie("url sans schema prefixee",
            'https://exemple.org/page',
            $dtb->query("SELECT valeur FROM vitrine_config WHERE cle = " . $dtb->quote($cleUrl))->fetchColumn());
        $dtb->prepare('UPDATE vitrine_config SET valeur = ? WHERE cle = ?')->execute([$urlOrigine, $cleUrl]);
        echo "  (restaure $cleUrl)\n";
    } else {
        echo "  -- aucun champ de type url en base, cas non teste\n";
    }

} finally {
    $dtb->prepare('UPDATE vitrine_config SET valeur = ? WHERE cle = ?')->execute([$origine, $cle]);
    echo "\nValeur de $cle restauree : " . var_export($origine, true) . "\n";
}

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko === 0 ? 0 : 1);
