<?php
/**
 * Page d'administration - Carte des localisations de connexion
 * Affiche sur une carte OpenStreetMap les positions GPS des connexions utilisateurs
 */
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

// Accès réservé aux administrateurs
requireAuth();
if (!isAdmin() && !isRegistrar()) {
    header('Location: ' . $app_base . '/dashboard');
    exit;
}

// Récupérer les filtres
$filterUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filterDateFrom = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : null;
$filterDateTo = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : null;
$filterLimit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;

// Récupérer les localisations
$locations = Middleware::getLoginLocations($filterUserId, $filterLimit, $filterDateFrom, $filterDateTo);

// Récupérer la liste des utilisateurs pour le filtre
$users = [];
try {
    $stmt = $dtb->query("SELECT id, pseudo, nom, prenom, level, privilege FROM compt_utilisateur WHERE etat = 1 ORDER BY nom, prenom");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// Préparer les données JSON pour la carte
$mapData = [];
foreach ($locations as $loc) {
    if ($loc['latitude'] !== null && $loc['longitude'] !== null) {
        $mapData[] = [
            'lat' => floatval($loc['latitude']),
            'lng' => floatval($loc['longitude']),
            'accuracy' => floatval($loc['accuracy'] ?? 0),
            'user' => e($loc['nom'] ?? '') . ' ' . e($loc['prenom'] ?? ''),
            'pseudo' => e($loc['pseudo'] ?? ''),
            'ip' => e($loc['ip_address'] ?? ''),
            'method' => e($loc['login_method'] ?? 'password'),
            'date' => $loc['created_at'] ?? '',
            'level' => $loc['level'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Localisations des connexions</title>
    <!-- Leaflet CSS pour la carte -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            animation: slideInFromRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        @keyframes slideInFromRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        #map {
            width: 100%;
            height: 500px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            z-index: 1;
        }
        .stat-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 12px;
            padding: 16px 20px;
            text-align: center;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0ea5e9;
        }
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 4px;
        }
        .filter-panel {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .location-table {
            width: 100%;
            border-collapse: collapse;
        }
        .location-table th, .location-table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            font-size: 0.85rem;
        }
        .location-table th {
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        .location-table tr:hover {
            background: rgba(14, 165, 233, 0.05);
        }
        .badge-method {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .badge-password { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
        .badge-google { background: rgba(234, 67, 53, 0.2); color: #f87171; }
        .badge-denied { background: rgba(251, 146, 60, 0.2); color: #fb923c; }
        .badge-ok { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .leaflet-popup-content { color: #1e293b; }
        .popup-title { font-weight: 700; font-size: 14px; margin-bottom: 6px; }
        .popup-info { font-size: 12px; line-height: 1.6; }
        .popup-info span { color: #64748b; }
    </style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
    <div class="h-screen w-full <?=$bg_three_color?>">

        <?php require('../init/topbar.php'); ?>

        <div class="w-full flex flex-col lg:flex-row">

            <?php require('../init/menubar.php'); ?>

            <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
                <div class="back p-4 flex-1 overflow-y-auto space-y-5">
                    <!-- Titre -->
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
                            <i class="bi bi-geo-alt-fill text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">Localisations des connexions</h2>
                            <p class="text-xs text-slate-400">Suivi GPS des connexions utilisateurs</p>
                        </div>
                    </div>

                    <!-- Statistiques rapides -->
                    <?php
                    $totalConnections = count($locations);
                    $withGps = count(array_filter($locations, fn($l) => $l['latitude'] !== null));
                    $denied = count(array_filter($locations, fn($l) => $l['gps_denied'] == 1));
                    $uniqueUsers = count(array_unique(array_column($locations, 'user_id')));
                    ?>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="stat-card">
                            <div class="stat-value"><?= $totalConnections ?></div>
                            <div class="stat-label">Connexions totales</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $withGps ?></div>
                            <div class="stat-label">Avec GPS</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $denied ?></div>
                            <div class="stat-label">GPS refusé</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $uniqueUsers ?></div>
                            <div class="stat-label">Utilisateurs uniques</div>
                        </div>
                    </div>

                    <!-- Filtres -->
                    <div class="filter-panel">
                        <form method="GET" class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[180px]">
                                <label class="block text-xs text-slate-400 mb-1">Utilisateur</label>
                                <select name="user_id" class="w-full bg-slate-800 text-slate-200 border border-slate-600 rounded-lg px-3 py-2 text-sm">
                                    <option value="">-- Tous les utilisateurs --</option>
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $filterUserId == $u['id'] ? 'selected' : '' ?>>
                                        <?= e($u['nom']) ?> <?= e($u['prenom']) ?> (<?= e($u['pseudo']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="min-w-[150px]">
                                <label class="block text-xs text-slate-400 mb-1">Date début</label>
                                <input type="date" name="date_from" value="<?= e($filterDateFrom ?? '') ?>" class="w-full bg-slate-800 text-slate-200 border border-slate-600 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div class="min-w-[150px]">
                                <label class="block text-xs text-slate-400 mb-1">Date fin</label>
                                <input type="date" name="date_to" value="<?= e($filterDateTo ?? '') ?>" class="w-full bg-slate-800 text-slate-200 border border-slate-600 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div class="min-w-[100px]">
                                <label class="block text-xs text-slate-400 mb-1">Limite</label>
                                <select name="limit" class="w-full bg-slate-800 text-slate-200 border border-slate-600 rounded-lg px-3 py-2 text-sm">
                                    <option value="50" <?= $filterLimit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $filterLimit == 100 ? 'selected' : '' ?>>100</option>
                                    <option value="200" <?= $filterLimit == 200 ? 'selected' : '' ?>>200</option>
                                    <option value="500" <?= $filterLimit == 500 ? 'selected' : '' ?>>500</option>
                                </select>
                            </div>
                            <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition">
                                <i class="bi bi-funnel"></i> Filtrer
                            </button>
                            <a href="./login-locations" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                        </form>
                    </div>

                    <!-- Carte -->
                    <div class="rounded-xl overflow-hidden">
                        <div id="map"></div>
                    </div>

                    <!-- Tableau des connexions récentes -->
                    <div class="filter-panel">
                        <h3 class="text-white font-semibold mb-3"><i class="bi bi-clock-history"></i> Historique des connexions</h3>
                        <div class="overflow-x-auto">
                            <table class="location-table">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Méthode</th>
                                        <th>GPS</th>
                                        <th>Coordonnées</th>
                                        <th>IP</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($locations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-slate-500 py-8">Aucune connexion enregistrée</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($locations as $loc): ?>
                                    <tr class="text-slate-300">
                                        <td>
                                            <div class="font-medium"><?= e($loc['nom'] ?? '') ?> <?= e($loc['prenom'] ?? '') ?></div>
                                            <div class="text-xs text-slate-500"><?= e($loc['pseudo'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge-method <?= $loc['login_method'] === 'google' ? 'badge-google' : 'badge-password' ?>">
                                                <?= $loc['login_method'] === 'google' ? 'Google' : 'Mot de passe' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($loc['gps_denied'] == 1): ?>
                                                <span class="badge-method badge-denied"><i class="bi bi-x-circle"></i> Refusé</span>
                                            <?php elseif ($loc['latitude'] !== null): ?>
                                                <span class="badge-method badge-ok"><i class="bi bi-check-circle"></i> OK</span>
                                            <?php else: ?>
                                                <span class="text-slate-500">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-xs font-mono">
                                            <?php if ($loc['latitude'] !== null && $loc['longitude'] !== null): ?>
                                                <a href="https://www.google.com/maps?q=<?= $loc['latitude'] ?>,<?= $loc['longitude'] ?>" target="_blank" class="text-cyan-400 hover:text-cyan-300 underline">
                                                    <?= number_format(floatval($loc['latitude']), 5) ?>, <?= number_format(floatval($loc['longitude']), 5) ?>
                                                </a>
                                                <?php if ($loc['accuracy']): ?>
                                                <div class="text-slate-500">±<?= round($loc['accuracy']) ?>m</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-slate-500">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-xs font-mono text-slate-400"><?= e($loc['ip_address'] ?? '') ?></td>
                                        <td class="text-xs text-slate-400">
                                            <?= date('d/m/Y H:i', strtotime($loc['created_at'])) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

				<?php require('../init/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Données de localisation depuis PHP
        const locations = <?= json_encode($mapData, JSON_UNESCAPED_UNICODE) ?>;

        // Initialiser la carte (centré sur Madagascar par défaut)
        const map = L.map('map').setView([-18.9149, 47.5316], 6);

        // Tile layer OpenStreetMap (gratuit, pas de clé API)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        // Couleurs par méthode de connexion
        const methodColors = {
            'password': '#8b5cf6',
            'google': '#ef4444'
        };

        // Ajouter les marqueurs
        const markers = [];
        locations.forEach(loc => {
            const color = methodColors[loc.method] || '#0ea5e9';
            
            // Créer un marqueur circulaire coloré
            const marker = L.circleMarker([loc.lat, loc.lng], {
                radius: 8,
                fillColor: color,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);

            // Popup avec les détails
            const dateFormatted = new Date(loc.date).toLocaleString('fr-FR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });

            marker.bindPopup(`
                <div class="popup-title">${loc.user}</div>
                <div class="popup-info">
                    <span>Pseudo:</span> ${loc.pseudo}<br>
                    <span>Méthode:</span> ${loc.method === 'google' ? '🔴 Google' : '🟣 Mot de passe'}<br>
                    <span>IP:</span> ${loc.ip}<br>
                    <span>Précision:</span> ±${Math.round(loc.accuracy)}m<br>
                    <span>Date:</span> ${dateFormatted}<br>
                    <a href="https://www.google.com/maps?q=${loc.lat},${loc.lng}" target="_blank" style="color:#0ea5e9;">
                        📍 Voir sur Google Maps
                    </a>
                </div>
            `);

            markers.push(marker);
        });

        // Ajuster la vue pour montrer tous les marqueurs
        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }

        // Légende
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function() {
            const div = L.DomUtil.create('div', 'leaflet-control');
            div.style.cssText = 'background:white;padding:10px 14px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.2);font-size:12px;';
            div.innerHTML = `
                <div style="font-weight:700;margin-bottom:6px;">Méthode de connexion</div>
                <div><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#8b5cf6;margin-right:6px;vertical-align:middle;"></span> Mot de passe</div>
                <div><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ef4444;margin-right:6px;vertical-align:middle;"></span> Google</div>
            `;
            return div;
        };
        legend.addTo(map);
    </script>

</body>
</html>
