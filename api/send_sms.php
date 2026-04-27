<?php
// Allow CORS for local development
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input from JavaScript
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? 'sandbox';
    $to = $input['to'] ?? '';
    $message = $input['message'] ?? '';
    
    // ⚠️ IMPORTANT: Keep your API Key on the server, not in JavaScript
    $apiKey = 'atsk_ae9e5b40a7388f9ff494af9c36081af784b9240a14c9791aa10e9d67fad24ce3504a196f'; 

    $url = 'https://api.africastalking.com/version1/messaging';
    
    $postData = [
        'username' => $username,
        'to' => $to,
        'message' => $message
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    // Execute request
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Return response to JavaScript
    if ($httpCode === 201 || $httpCode === 200) {
        echo json_encode(['success' => true, 'response' => $result]);
    } else {
        http_response_code($httpCode);
        echo json_encode(['success' => false, 'error' => $result]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>