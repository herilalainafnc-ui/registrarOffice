<?php
/* =============================================================================
   Plan du bureau — le parcours des etudiants dans le bureau, etape par etape.

   Montre sur l'ecran public de la file d'attente (/queue/display, bouton
   « Plan du bureau », ou /queue/display#plan) et modifie par le superadmin
   (/queue/plan). Le dessin et le calcul du parcours se font
   dans le navigateur (src/js/plan-bureau.js) : ce fichier lit, valide et
   enregistre le plan.

   Le plan, en metres (origine en haut a gauche du batiment) :
     batiment  { l, p }                     largeur (x) et profondeur (y)
     entree    { cote, pos, l }             cote du batiment, milieu de la porte, largeur
     pieces    [{ id, code, nom, type, x, y, l, p, portes: [{ cote, pos, l }] }]
               type « salle » (murs et portes) ou « espace » (ouvert : hall,
               couloir) ; pos d'une porte : son milieu, depuis le debut du cote
     etapes    [{ nom, detail, piece }]     dans l'ordre du parcours ; le numero
               est le rang

   Table : t_queue_plan (data/migration_plan_bureau.sql). Sans elle, le plan
   par defaut, redessine d'apres le croquis du bureau.
   ========================================================================== */

const PLAN_COTES = ['haut', 'bas', 'gauche', 'droite'];
const PLAN_MAX_PIECES = 80;
const PLAN_MAX_ETAPES = 20;
const PLAN_MAX_PORTES = 8;

function planTablePresente(PDO $dtb): bool
{
    static $presente = null;
    if ($presente === null) {
        try {
            $dtb->query('SELECT 1 FROM t_queue_plan LIMIT 0');
            $presente = true;
        } catch (Throwable $e) {
            $presente = false;
        }
    }
    return $presente;
}

/** Le plan de depart, d'apres le croquis du bureau (dimensions approximatives). */
function planDefaut(): array
{
    $salle = static fn (string $id, string $code, float $x, float $y, float $l, float $p, array $portes = []) =>
        ['id' => $id, 'code' => $code, 'nom' => '', 'type' => 'salle', 'x' => $x, 'y' => $y, 'l' => $l, 'p' => $p, 'portes' => $portes];
    return [
        'v' => 1,
        'batiment' => ['l' => 28.3, 'p' => 16.8],
        'entree' => ['cote' => 'bas', 'pos' => 13.7, 'l' => 2.8],
        'pieces' => [
            $salle('a', 'A', 0, 0, 4.9, 3.5),
            $salle('b', 'B', 4.9, 0, 5.4, 3.5),
            $salle('wc', 'WC', 10.3, 0, 2.1, 3.5),
            $salle('c', 'C', 12.4, 0, 3.3, 3.5),
            $salle('d', 'D', 15.7, 0, 3.2, 3.5),
            $salle('e', 'E', 18.9, 0, 9.4, 3.5),
            $salle('s4', 'L', 0, 3.5, 7.4, 4.0, [['cote' => 'bas', 'pos' => 3.7, 'l' => 1.2]]),
            $salle('f', 'F', 7.4, 3.5, 4.3, 4.0),
            ['id' => 'hall', 'code' => '', 'nom' => '', 'type' => 'espace', 'x' => 11.7, 'y' => 3.5, 'l' => 8.2, 'p' => 4.4, 'portes' => []],
            $salle('s3', 'K', 19.9, 3.5, 8.4, 4.4, [['cote' => 'bas', 'pos' => 4.2, 'l' => 1.2]]),
            $salle('g', 'G', 0, 10.1, 5.9, 6.7),
            $salle('s2', 'J', 5.9, 10.1, 5.1, 6.7, [['cote' => 'haut', 'pos' => 2.5, 'l' => 1.2]]),
            $salle('h', 'H', 16.9, 10.5, 6.9, 6.3),
            $salle('i', 'I', 23.8, 10.5, 4.5, 6.3),
        ],
        'etapes' => [
            ['nom' => '', 'detail' => '', 'piece' => 'hall'],
            ['nom' => '', 'detail' => '', 'piece' => 's2'],
            ['nom' => '', 'detail' => '', 'piece' => 's3'],
            ['nom' => '', 'detail' => '', 'piece' => 's4'],
        ],
    ];
}

/** Le plan en service et qui l'a enregistre. */
function planLire(PDO $dtb): array
{
    $ligne = null;
    if (planTablePresente($dtb)) {
        $ligne = $dtb->query(
            "SELECT p.plan, p.modifie_le, (p.plan_precedent IS NOT NULL AND p.plan_precedent <> '') AS precedent,
                    TRIM(CONCAT(COALESCE(u.prenom, ''), ' ', COALESCE(u.nom, ''))) AS auteur
               FROM t_queue_plan p LEFT JOIN compt_utilisateur u ON u.id = p.modifie_par
              WHERE p.id = 1"
        )->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $plan = $ligne ? json_decode((string) $ligne['plan'], true) : null;
    return [
        'plan' => is_array($plan) ? $plan : planDefaut(),
        'defaut' => !is_array($plan),
        'modifie_le' => $ligne['modifie_le'] ?? null,
        'auteur' => $ligne['auteur'] ?? '',
        'precedent' => !empty($ligne['precedent']),
    ];
}

/**
 * Le plan recu, controle et remis en forme. Refuse (InvalidArgumentException)
 * ce qui ne peut pas se dessiner ; arrondit les mesures au centimetre.
 */
function planValider($plan): array
{
    if (!is_array($plan)) {
        throw new InvalidArgumentException('Plan illisible.');
    }
    $nombre = static function ($v, float $min, float $max, string $quoi): float {
        if (!is_numeric($v)) {
            throw new InvalidArgumentException("$quoi : une mesure en mètres est attendue.");
        }
        $v = round((float) $v, 2);
        if ($v < $min || $v > $max) {
            throw new InvalidArgumentException("$quoi : entre " . str_replace('.', ',', (string) $min) . ' et ' . str_replace('.', ',', (string) $max) . ' m.');
        }
        return $v;
    };
    $texte = static function ($v, int $max): string {
        $v = trim(preg_replace('~[\x00-\x1F\x7F]+~u', ' ', strip_tags((string) $v)));
        return mb_substr(preg_replace('~\s+~u', ' ', $v), 0, $max);
    };
    $cote = static function ($v, string $quoi): string {
        if (!in_array($v, PLAN_COTES, true)) {
            throw new InvalidArgumentException("$quoi : côté inconnu.");
        }
        return $v;
    };

    $b = $plan['batiment'] ?? null;
    if (!is_array($b)) {
        throw new InvalidArgumentException('Les dimensions du bâtiment manquent.');
    }
    $L = $nombre($b['l'] ?? null, 3, 200, 'Largeur du bâtiment');
    $P = $nombre($b['p'] ?? null, 3, 200, 'Profondeur du bâtiment');

    $e = $plan['entree'] ?? null;
    if (!is_array($e)) {
        throw new InvalidArgumentException("L'entrée manque.");
    }
    $eCote = $cote($e['cote'] ?? null, 'Entrée');
    $eLong = in_array($eCote, ['haut', 'bas'], true) ? $L : $P;
    $eL = $nombre($e['l'] ?? null, 0.6, 12, "Largeur de l'entrée");
    $ePos = $nombre($e['pos'] ?? null, $eL / 2, $eLong - $eL / 2, "Position de l'entrée");

    $pieces = [];
    $ids = [];
    foreach (array_values((array) ($plan['pieces'] ?? [])) as $i => $p) {
        if (count($pieces) >= PLAN_MAX_PIECES) {
            throw new InvalidArgumentException('Pas plus de ' . PLAN_MAX_PIECES . ' pièces.');
        }
        if (!is_array($p)) {
            throw new InvalidArgumentException('Pièce ' . ($i + 1) . ' illisible.');
        }
        $id = (string) ($p['id'] ?? '');
        if (!preg_match('~^[a-z0-9_-]{1,24}$~', $id) || isset($ids[$id])) {
            throw new InvalidArgumentException('Pièce ' . ($i + 1) . ' : identifiant invalide ou en double.');
        }
        $ids[$id] = true;
        $code = $texte($p['code'] ?? '', 6);
        $nom = $texte($p['nom'] ?? '', 60);
        $quoi = $nom !== '' ? "« $nom »" : ($code !== '' ? "La pièce $code" : 'La pièce ' . ($i + 1));
        $type = ($p['type'] ?? '') === 'espace' ? 'espace' : 'salle';
        $x = $nombre($p['x'] ?? null, 0, $L, "$quoi, position");
        $y = $nombre($p['y'] ?? null, 0, $P, "$quoi, position");
        $l = $nombre($p['l'] ?? null, 0.5, $L, "$quoi, largeur");
        $pp = $nombre($p['p'] ?? null, 0.5, $P, "$quoi, profondeur");
        if ($x + $l > $L + 0.01 || $y + $pp > $P + 0.01) {
            throw new InvalidArgumentException("$quoi dépasse du bâtiment.");
        }
        $portes = [];
        if ($type === 'salle') {
            foreach (array_values((array) ($p['portes'] ?? [])) as $j => $porte) {
                if (count($portes) >= PLAN_MAX_PORTES) {
                    throw new InvalidArgumentException("$quoi : pas plus de " . PLAN_MAX_PORTES . ' portes.');
                }
                $pc = $cote($porte['cote'] ?? null, "$quoi, porte " . ($j + 1));
                $long = in_array($pc, ['haut', 'bas'], true) ? $l : $pp;
                $pl = $nombre($porte['l'] ?? null, 0.5, min(6, $long), "$quoi, largeur de la porte " . ($j + 1));
                $portes[] = ['cote' => $pc, 'pos' => $nombre($porte['pos'] ?? null, $pl / 2, $long - $pl / 2, "$quoi, position de la porte " . ($j + 1)), 'l' => $pl];
            }
        }
        $pieces[] = ['id' => $id, 'code' => $code, 'nom' => $nom, 'type' => $type, 'x' => $x, 'y' => $y, 'l' => $l, 'p' => $pp, 'portes' => $portes];
    }

    $etapes = [];
    foreach (array_values((array) ($plan['etapes'] ?? [])) as $i => $et) {
        if (count($etapes) >= PLAN_MAX_ETAPES) {
            throw new InvalidArgumentException('Pas plus de ' . PLAN_MAX_ETAPES . ' étapes.');
        }
        $piece = (string) ($et['piece'] ?? '');
        if (!isset($ids[$piece])) {
            throw new InvalidArgumentException('Étape ' . ($i + 1) . ' : choisissez la pièce où elle se passe.');
        }
        $etapes[] = ['nom' => $texte($et['nom'] ?? '', 80), 'detail' => $texte($et['detail'] ?? '', 160), 'piece' => $piece];
    }

    return ['v' => 1, 'batiment' => ['l' => $L, 'p' => $P], 'entree' => ['cote' => $eCote, 'pos' => $ePos, 'l' => $eL],
            'pieces' => $pieces, 'etapes' => $etapes];
}

/** Enregistre le plan ; la version en service devient la version precedente. */
function planEnregistrer(PDO $dtb, $plan, ?int $auteur): array
{
    if (!planTablePresente($dtb)) {
        throw new RuntimeException("La table du plan n'existe pas encore : appliquez data/migration_plan_bureau.sql.");
    }
    $json = json_encode(planValider($plan), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    $dtb->beginTransaction();
    try {
        $actuel = $dtb->query('SELECT plan FROM t_queue_plan WHERE id = 1 FOR UPDATE')->fetchColumn();
        if ($actuel === false) {
            $dtb->prepare('INSERT INTO t_queue_plan (id, plan, modifie_par, modifie_le) VALUES (1, :plan, :auteur, NOW())')
                ->execute(['plan' => $json, 'auteur' => $auteur]);
        } elseif ($actuel !== $json) {
            $dtb->prepare('UPDATE t_queue_plan SET plan = :plan, plan_precedent = :precedent, modifie_par = :auteur, modifie_le = NOW() WHERE id = 1')
                ->execute(['plan' => $json, 'precedent' => $actuel, 'auteur' => $auteur]);
        }
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    return planLire($dtb);
}

/** Remet en service la version precedente ; celle qu'elle remplace devient la precedente. */
function planRestaurer(PDO $dtb, ?int $auteur): array
{
    if (!planTablePresente($dtb)) {
        throw new RuntimeException("La table du plan n'existe pas encore : appliquez data/migration_plan_bureau.sql.");
    }
    $dtb->beginTransaction();
    try {
        $l = $dtb->query('SELECT plan, plan_precedent FROM t_queue_plan WHERE id = 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if (!$l || (string) $l['plan_precedent'] === '') {
            throw new InvalidArgumentException("Il n'y a pas de version précédente.");
        }
        $dtb->prepare('UPDATE t_queue_plan SET plan = :plan, plan_precedent = :precedent, modifie_par = :auteur, modifie_le = NOW() WHERE id = 1')
            ->execute(['plan' => $l['plan_precedent'], 'precedent' => $l['plan'], 'auteur' => $auteur]);
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    return planLire($dtb);
}
