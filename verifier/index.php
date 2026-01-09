<?php
/**
 * Page publique de vérification d'authenticité des documents
 * URL: /verifier/?code=UAZ-2026-XXXXXX
 */

require_once(__DIR__ . '/../data/connectdb.php');
require_once(__DIR__ . '/../src/services/DocumentVerification.php');

$docCode = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_code'])) {
    $docCode = strtoupper(trim($_POST['doc_code']));
}

$verification = new DocumentVerification($dtb);
$result = null;
$searched = false;

if (!empty($docCode)) {
    $result = $verification->verifyDocument($docCode);
    $searched = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de Document - UAZ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%); min-height: 100vh; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .status-valid { background: linear-gradient(135deg, #10b981, #059669); }
        .status-invalid { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .status-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .status-notfound { background: linear-gradient(135deg, #6b7280, #4b5563); }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="bg-white rounded-lg p-3 inline-block mb-3">
                <img src="../file/UAZ Official Black Logo.jpg" alt="UAZ Logo" class="h-16 mx-auto">
            </div>
            <h1 class="text-2xl font-bold text-white">Université Adventiste Zurcher</h1>
            <p class="text-blue-200">Système de Vérification d'Authenticité</p>
        </div>

        <!-- Formulaire -->
        <div class="glass-card rounded-xl p-6 shadow-2xl mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-search text-blue-600 mr-2"></i> Vérifier un document
            </h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code de vérification</label>
                    <input type="text" name="doc_code" value="<?= htmlspecialchars($docCode ?? '') ?>"
                           placeholder="Ex: UAZ-2026-ABC123"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-center font-mono text-lg uppercase" required>
                    <p class="mt-1 text-xs text-gray-500">Entrez le code imprimé sur votre document ou scannez le QR code</p>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                    <i class="fas fa-shield-alt mr-2"></i> Vérifier l'authenticité
                </button>
            </form>
        </div>

        <?php if ($searched && $result): ?>
        <!-- Résultat -->
        <div class="glass-card rounded-xl overflow-hidden shadow-2xl">
            <div class="<?php 
                if ($result['status'] === 'valide') echo 'status-valid';
                elseif ($result['status'] === 'non_trouve') echo 'status-notfound';
                elseif (in_array($result['status'], ['expire', 'suspendu'])) echo 'status-warning';
                else echo 'status-invalid';
            ?> px-6 py-4 text-white text-center">
                <span class="text-4xl block mb-2"><?= $result['icon'] ?></span>
                <h3 class="text-xl font-bold"><?= strtoupper($result['message']) ?></h3>
            </div>

            <div class="p-6">
                <?php if ($result['valid'] && isset($result['data'])): $doc = $result['data']; ?>
                <div class="space-y-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="font-semibold text-green-800 mb-3"><i class="fas fa-user-graduate mr-2"></i>Titulaire</h4>
                        <table class="w-full text-sm">
                            <tr><td class="text-gray-600 py-1">Nom complet</td><td class="font-semibold"><?= htmlspecialchars($doc['student_name']) ?></td></tr>
                            <tr><td class="text-gray-600 py-1">Matricule</td><td class="font-mono font-semibold"><?= htmlspecialchars($doc['student_id']) ?></td></tr>
                        </table>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-3"><i class="fas fa-file-alt mr-2"></i>Document</h4>
                        <table class="w-full text-sm">
                            <tr><td class="text-gray-600 py-1">Type</td><td class="font-semibold capitalize"><?= htmlspecialchars($doc['doc_type']) ?></td></tr>
                            <tr><td class="text-gray-600 py-1">Code</td><td class="font-mono font-semibold text-blue-700"><?= htmlspecialchars($doc['doc_code']) ?></td></tr>
                            <tr><td class="text-gray-600 py-1">Date d'émission</td><td class="font-semibold"><?= date('d/m/Y à H:i', strtotime($doc['date_emission'])) ?></td></tr>
                            <?php if ($doc['level']): ?><tr><td class="text-gray-600 py-1">Niveau</td><td class="font-semibold"><?= htmlspecialchars($doc['level']) ?></td></tr><?php endif; ?>
                        </table>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center text-xs text-gray-500">
                        <i class="fas fa-eye mr-1"></i> Vérifié <?= $doc['nb_verifications'] ?> fois
                    </div>
                </div>

                <?php elseif ($result['status'] === 'annule'): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="font-semibold text-red-800 mb-2"><i class="fas fa-ban mr-2"></i>Document annulé</h4>
                    <p class="text-red-700 text-sm">Ce document a été annulé et n'est plus valide.
                    <?php if (!empty($result['motif'])): ?><br><strong>Motif :</strong> <?= htmlspecialchars($result['motif']) ?><?php endif; ?></p>
                </div>

                <?php elseif ($result['status'] === 'non_trouve'): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-question-circle mr-2"></i>Introuvable</h4>
                    <p class="text-gray-600 text-sm">Le code <strong class="font-mono"><?= htmlspecialchars($docCode) ?></strong> ne correspond à aucun document.</p>
                    <ul class="list-disc list-inside mt-2 text-sm text-gray-500">
                        <li>Code mal saisi</li>
                        <li>Document possiblement contrefait</li>
                        <li>Document non émis par l'UAZ</li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800 text-sm"><?= htmlspecialchars($result['message']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-6 text-blue-200 text-sm">
            <p><i class="fas fa-shield-alt mr-1"></i> Système sécurisé de vérification</p>
            <p class="mt-1 text-xs text-blue-400">BP 325, Antsirabe 110, MADAGASCAR | Tél : 034 46 000 08</p>
        </div>
    </div>
    <script>document.querySelector('input[name="doc_code"]')?.addEventListener('input', function() { this.value = this.value.toUpperCase(); });</script>
</body>
</html>
