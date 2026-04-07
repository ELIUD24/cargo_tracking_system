<?php
/**
 * CargoTrack System Test Suite
 * Tests all major functionality
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     CargoTrack System - Comprehensive Test Suite          ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

$baseUrl = 'http://localhost:8000';
$allPassed = true;

// Test 1: Database Connection
echo "Test 1: Database Connection\n";
echo str_repeat('-', 60) . "\n";
$db = @file_get_contents($baseUrl . '/php/test_db.php');
if ($db && strpos($db, 'Database connection successful') !== false) {
    echo "✓ PASS: Database connected\n";
} else {
    echo "✗ FAIL: Database connection failed\n";
    $allPassed = false;
}
echo "\n";

// Test 2: User Registration
echo "Test 2: User Registration\n";
echo str_repeat('-', 60) . "\n";
$testEmail = 'test_' . time() . '@example.com';
$ch = curl_init($baseUrl . '/php/cargo.php?endpoint=register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => 'Test User',
    'email' => $testEmail,
    'password' => 'test123',
    'phone' => '+254700000000',
    'role' => 'customer'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && $data['success']) {
    echo "✓ PASS: Registration works\n";
    echo "  User ID: {$data['data']['user_id']}\n";
    $userId = $data['data']['user_id'];
} else {
    echo "✗ FAIL: Registration failed\n";
    echo "  Response: $response\n";
    $allPassed = false;
    $userId = null;
}
echo "\n";

// Test 3: User Login
echo "Test 3: User Login\n";
echo str_repeat('-', 60) . "\n";
if ($userId) {
    $ch = curl_init($baseUrl . '/php/cargo.php?endpoint=login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => $testEmail,
        'password' => 'test123'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "✓ PASS: Login works\n";
        echo "  Token: " . substr($data['data']['token'], 0, 20) . "...\n";
    } else {
        echo "✗ FAIL: Login failed\n";
        echo "  Response: $response\n";
        $allPassed = false;
    }
} else {
    echo "⊘ SKIP: No user to test login with\n";
}
echo "\n";

// Test 4: Admin Login
echo "Test 4: Admin Login\n";
echo str_repeat('-', 60) . "\n";
$ch = curl_init($baseUrl . '/php/cargo.php?endpoint=login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'admin@cargotrack.co.ke',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && $data['success'] && $data['data']['user']['role'] === 'admin') {
    echo "✓ PASS: Admin login works\n";
    $adminToken = $data['data']['token'];
} else {
    echo "✗ FAIL: Admin login failed\n";
    echo "  Response: $response\n";
    $allPassed = false;
}
echo "\n";

// Test 5: Dashboard Stats
echo "Test 5: Dashboard Statistics\n";
echo str_repeat('-', 60) . "\n";
$response = @file_get_contents($baseUrl . '/php/dashboard_starts.php');
$data = json_decode($response, true);
if ($data && $data['success']) {
    echo "✓ PASS: Dashboard stats retrieved\n";
    echo "  Total: {$data['data']['total_shipments']}\n";
    echo "  In Transit: {$data['data']['in_transit']}\n";
    echo "  Delivered: {$data['data']['delivered']}\n";
    echo "  Pending: {$data['data']['pending']}\n";
} else {
    echo "✗ FAIL: Dashboard stats failed\n";
    $allPassed = false;
}
echo "\n";

// Test 6: API Endpoints Available
echo "Test 6: API Endpoints Check\n";
echo str_repeat('-', 60) . "\n";
$endpoints = [
    'cargo.php' => '/php/cargo.php',
    'DatabaseAgent.php' => '/php/DatabaseAgent.php',
    'database.php' => '/php/database.php',
];

foreach ($endpoints as $name => $path) {
    $response = @file_get_contents($baseUrl . $path);
    if ($response !== false) {
        echo "✓ $name: Accessible\n";
    } else {
        echo "✗ $name: Not accessible\n";
        $allPassed = false;
    }
}
echo "\n";

// Final Summary
echo str_repeat('=', 60) . "\n";
echo "FINAL RESULT: " . ($allPassed ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED") . "\n";
echo str_repeat('=', 60) . "\n";
echo "\n";

if ($allPassed) {
    echo "System is working correctly!\n";
    echo "\nAvailable Features:\n";
    echo "  ✓ User Registration\n";
    echo "  ✓ User Login\n";
    echo "  ✓ Admin Access\n";
    echo "  ✓ Dashboard Statistics\n";
    echo "  ✓ Database Connection\n";
    echo "\n";
    echo "Access the application:\n";
    echo "  Home: http://localhost:8000/index.html\n";
    echo "  Signup: http://localhost:8000/pages/signup.html\n";
    echo "\n";
} else {
    echo "Some features need attention. Check the errors above.\n";
    echo "\n";
}

echo "╚═══════════════════════════════════════════════════════════╝\n";
?>
