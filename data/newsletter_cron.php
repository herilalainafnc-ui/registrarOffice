<?php

require_once __DIR__ . '/backdb.php';
require_once __DIR__ . '/newsletter.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$limit = isset($argv[1]) ? (int)$argv[1] : 5;

try {
    $processed = newsletterProcessDueCampaigns($dtb, $limit);
    echo json_encode([
        'success' => true,
        'processed' => $processed,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
