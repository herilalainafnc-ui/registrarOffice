<?php 
/**
 * Data API Endpoint
 * Returns current date and time information in JSON format
 * Timezone: Indian/Antananarivo (UTC+3)
 */

// Prevent direct access and ensure clean output
if (!defined('API_ACCESS')) {
    // Allow API access for this file
    define('API_ACCESS', true);
}

// Security headers
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS headers (adjust as needed for your application)
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST');
// header('Access-Control-Allow-Headers: Content-Type');

try {
    // Set timezone
    date_default_timezone_set('Indian/Antananarivo');
    
    // Create DateTime object
    $now = new DateTime('now', new DateTimeZone('Indian/Antananarivo'));
    
    // Prepare response data
    $data = [
        'success' => true,
        'timestamp' => time(),
        'datetime' => [
            'formatted' => $now->format('d/m/Y H:i:s'),
            'date' => $now->format('d/m/Y'),
            'time' => $now->format('H:i:s'),
            'iso8601' => $now->format(DateTime::ATOM),
            'rfc2822' => $now->format(DateTime::RFC2822),
        ],
        'components' => [
            'day' => $now->format('d'),
            'month' => $now->format('m'),
            'year' => $now->format('Y'),
            'hour' => $now->format('H'),
            'minute' => $now->format('i'),
            'second' => $now->format('s'),
            'day_name' => $now->format('l'),
            'month_name' => $now->format('F'),
        ],
        'timezone' => [
            'name' => 'Indian/Antananarivo',
            'offset' => $now->format('P'),
            'abbreviation' => $now->format('T'),
        ],
        'message' => "Aujourd'hui, le " . $now->format('d/m/Y') . ' à ' . $now->format('H:i:s'),
    ];
    
    // Return JSON response
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Error handling
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Unable to retrieve date/time information',
        'debug' => $e->getMessage() // Remove in production
    ], JSON_PRETTY_PRINT);
}

exit;
?>