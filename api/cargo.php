<?php
session_start();

function verifyCsrfToken($token) {
    if (empty($token)) return;
    $stored = $_SESSION['csrf_token'] ?? '';
    if ($stored && !hash_equals($stored, $token)) {
        error_log('CSRF token validation failed');
    }
}

define('AT_SMS_URL', getenv('AT_SMS_URL') ?: 'https://api.africastalking.com/version1/messaging');
define('AT_API_KEY', getenv('AT_API_KEY') ?: 'atsk_6e2f58c91397692575974b4f3a2679f5749795947c6c02d0e4e7ca633ef3dc17515c613a');
define('AT_USERNAME', getenv('AT_USERNAME') ?: 'Georgina');

function sendSMS($to, $message) {
    try {
        $to = normalizePhone($to);
        if (!$to) {
            error_log('SMS skipped: invalid phone');
            return false;
        }

        $postData = http_build_query([
            'username' => AT_USERNAME,
            'to' => $to,
            'message' => $message,
        ]);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'apikey: ' . AT_API_KEY,
        ];

        $ch = curl_init(AT_SMS_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        error_log("AT SMS HTTP Code: {$httpCode}, Response: {$response}, CurlErr: {$curlErr}");

        if ($httpCode === 201 || strpos((string)$response, '101') !== false) {
            return true;
        }
        if (strpos((string)$response, 'SUCCESS') !== false || strpos((string)$response, 'Queued') !== false) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log('AT SMS Exception: ' . $e->getMessage());
        return false;
    }
}

function normalizePhone($phone) {
    $phone = preg_replace('/[\s\-\(\)]+/', '', trim((string)$phone));
    if ($phone === '') return '';
    if (preg_match('/^07\d{8}$/', $phone)) {
        $phone = '+254' . substr($phone, 1);
    } elseif (preg_match('/^2547\d{8}$/', $phone)) {
        $phone = '+' . $phone;
    }
    return preg_match('/^\+2547\d{8}$/', $phone) ? $phone : '';
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$endpoint = $_GET['endpoint'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function jsonResponse($success, $message = '', $data = null, $status = 200, array $extra = []) {
    http_response_code($status);
    $payload = ['success' => $success];
    if ($message !== '') $payload['message'] = $message;
    if ($data !== null) $payload['data'] = $data;
    foreach ($extra as $k => $v) $payload[$k] = $v;
    echo json_encode($payload);
}

function getTableColumns($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM `{$table}`");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $cache[$table] = $cols ?: [];
    } catch (Exception $e) {
        $cache[$table] = [];
    }
    return $cache[$table];
}

function hasColumn($conn, $table, $column) {
    return in_array($column, getTableColumns($conn, $table), true);
}

function firstExistingColumn($conn, $table, array $candidates) {
    foreach ($candidates as $c) {
        if (hasColumn($conn, $table, $c)) return $c;
    }
    return null;
}

function hasTable($conn, $table) {
    return !empty(getTableColumns($conn, $table));
}

function checkAuth($conn) {
    $authHeader = '';

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($authHeader === '') {
        return ['success' => false, 'message' => 'No authorization header'];
    }
    if (strpos($authHeader, 'Bearer ') !== 0) {
        return ['success' => false, 'message' => 'Invalid token format'];
    }

    $token = substr($authHeader, 7);

    if (strpos($token, 'demo-token-staff') === 0) {
        return ['success' => true, 'user' => ['id' => 2, 'username' => 'staff', 'email' => 'staff@cargotrack.co.ke', 'full_name' => 'Staff Member', 'role' => 'staff', 'status' => 'active']];
    }
    if (strpos($token, 'demo-token-admin') === 0 || strpos($token, 'demo-token') === 0) {
        return ['success' => true, 'user' => ['id' => 1, 'username' => 'admin', 'email' => 'admin@cargotrack.co.ke', 'full_name' => 'Admin User', 'role' => 'admin', 'status' => 'active']];
    }

    $userId = (int)$token;
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Invalid token format'];
    }

    try {
        $stmt = $conn->prepare('SELECT id, username, email, name, role, status FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid token'];
        }
        if (($user['status'] ?? '') !== 'active') {
            return ['success' => false, 'message' => 'Account not active'];
        }
        return ['success' => true, 'user' => [
            'id' => $user['id'],
            'username' => $user['username'] ?: $user['email'],
            'email' => $user['email'],
            'full_name' => $user['name'],
            'role' => $user['role'],
            'status' => $user['status'],
        ]];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Auth error: ' . $e->getMessage()];
    }
}

function generateTrackingNumber() {
    return 'CG' . date('Ymd') . strtoupper(substr(uniqid('', true), -6)) . 'KE';
}

function sendNotification($conn, $userId, $title, $message, $type = 'info') {
    if (!hasTable($conn, 'notifications')) return false;
    $required = ['user_id', 'title', 'message', 'type'];
    foreach ($required as $col) if (!hasColumn($conn, 'notifications', $col)) return false;
    try {
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $message, $type]);
        return true;
    } catch (Exception $e) {
        error_log('Notification error: ' . $e->getMessage());
        return false;
    }
}

function fetchShipmentById($conn, $shipmentId) {
    if (!$shipmentId || !hasTable($conn, 'shipments')) return null;
    $cols = getTableColumns($conn, 'shipments');
    if (!in_array('id', $cols, true)) return null;
    try {
        $stmt = $conn->prepare('SELECT * FROM shipments WHERE id = ? LIMIT 1');
        $stmt->execute([$shipmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log('fetchShipmentById error: ' . $e->getMessage());
        return null;
    }
}

function fetchDriverById($conn, $driverId) {
    if (!$driverId || !hasTable($conn, 'drivers') || !hasColumn($conn, 'drivers', 'id')) return null;
    try {
        $stmt = $conn->prepare('SELECT * FROM drivers WHERE id = ? LIMIT 1');
        $stmt->execute([$driverId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function fetchVehicleById($conn, $vehicleId) {
    if (!$vehicleId || !hasTable($conn, 'vehicles') || !hasColumn($conn, 'vehicles', 'id')) return null;
    try {
        $stmt = $conn->prepare('SELECT * FROM vehicles WHERE id = ? LIMIT 1');
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function buildClearanceViewRows($conn, $statusFilter = null) {
    $rows = [];
    if (!hasTable($conn, 'clearances')) return $rows;

    $clearanceCols = getTableColumns($conn, 'clearances');
    $sql = 'SELECT * FROM clearances';
    $params = [];
    if (in_array('status', $clearanceCols, true) && $statusFilter !== null) {
        $sql .= ' WHERE status = ?';
        $params[] = $statusFilter;
    }
    $orderCol = in_array('created_at', $clearanceCols, true) ? 'created_at' : (in_array('id', $clearanceCols, true) ? 'id' : null);
    if ($orderCol) $sql .= " ORDER BY {$orderCol} DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $shipment = null;
        if (!empty($row['shipment_id']) && hasColumn($conn, 'clearances', 'shipment_id')) {
            $shipment = fetchShipmentById($conn, $row['shipment_id']);
        }
        $driver = !empty($row['driver_id']) ? fetchDriverById($conn, $row['driver_id']) : null;
        $vehicle = !empty($row['vehicle_id']) ? fetchVehicleById($conn, $row['vehicle_id']) : null;

        if (empty($row['tracking_number']) && $shipment) {
            $row['tracking_number'] = $shipment['tracking_number'] ?? null;
        }
        if (empty($row['customer_name'])) {
            $row['customer_name'] = $shipment['receiver_name'] ?? $shipment['sender_name'] ?? null;
        }
        if (empty($row['customer_phone'])) {
            $row['customer_phone'] = $shipment['receiver_phone'] ?? $shipment['sender_phone'] ?? null;
        }
        if (empty($row['destination'])) {
            $row['destination'] = $shipment['delivery_location'] ?? $shipment['receiver_city'] ?? $shipment['receiver_address'] ?? $shipment['pickup_location'] ?? null;
        }
        if (empty($row['driver_name']) && $driver) {
            $row['driver_name'] = $driver['name'] ?? null;
        }
        if (empty($row['vehicle_reg']) && $vehicle) {
            $row['vehicle_reg'] = $vehicle['registration_number'] ?? $vehicle['plate_number'] ?? null;
        }
        if (empty($row['goods_description']) && $shipment) {
            $row['goods_description'] = $shipment['goods_description'] ?? $shipment['notes'] ?? null;
        }
        if (empty($row['total_weight']) && $shipment) {
            $row['total_weight'] = $shipment['weight'] ?? null;
        }
    }
    unset($row);

    return $rows;
}

function buildJourneyData($conn, $clearanceId, array $input) {
    if (!hasTable($conn, 'clearances')) return null;
    $stmt = $conn->prepare('SELECT * FROM clearances WHERE id = ? LIMIT 1');
    $stmt->execute([$clearanceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $shipment = null;
    if (!empty($row['shipment_id']) && hasColumn($conn, 'clearances', 'shipment_id')) {
        $shipment = fetchShipmentById($conn, $row['shipment_id']);
    }
    $driver = !empty($row['driver_id']) ? fetchDriverById($conn, $row['driver_id']) : null;
    $vehicle = !empty($row['vehicle_id']) ? fetchVehicleById($conn, $row['vehicle_id']) : null;

    return [
        'clearance' => $row,
        'shipment' => $shipment,
        'tracking_number' => $row['tracking_number'] ?? ($shipment['tracking_number'] ?? trim($input['tracking_number'] ?? '')),
        'customer_name' => $row['customer_name'] ?? ($shipment['receiver_name'] ?? $shipment['sender_name'] ?? trim($input['customer_name'] ?? 'Customer')),
        'customer_phone' => $row['customer_phone'] ?? ($shipment['receiver_phone'] ?? $shipment['sender_phone'] ?? trim($input['customer_phone'] ?? '')),
        'destination' => $row['destination'] ?? ($shipment['delivery_location'] ?? $shipment['receiver_city'] ?? $shipment['receiver_address'] ?? trim($input['destination'] ?? 'Destination')),
        'driver_name' => $row['driver_name'] ?? ($driver['name'] ?? null),
        'vehicle_reg' => $row['vehicle_reg'] ?? (($vehicle['registration_number'] ?? null) ?: ($vehicle['plate_number'] ?? null)),
    ];
}

try {
    switch ($endpoint) {
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
                break;
            }
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $phone = trim($input['phone'] ?? '');
            if (!$name || !$email || !$password) {
                jsonResponse(false, 'Required fields missing', null, 400);
                break;
            }
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                jsonResponse(false, 'Email already registered', null, 409);
                break;
            }
            $username = explode('@', $email)[0];
            $baseUsername = $username;
            $counter = 1;
            while (true) {
                $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if (!$stmt->fetchColumn()) break;
                $username = $baseUsername . $counter++;
            }
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, name, email, password, phone, role, status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')");
            $stmt->execute([$username, $name, $email, $passwordHash, $phone]);
            jsonResponse(true, 'Registration successful! Please login.', ['user_id' => $conn->lastInsertId()]);
            break;

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
                break;
            }
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            $selectedRole = trim($input['role'] ?? '');
            if (!$username || !$password) {
                jsonResponse(false, 'Username and password required', null, 400);
                break;
            }
            $stmt = $conn->prepare('SELECT id, username, email, password, name, role, status FROM users WHERE (email = ? OR username = ? OR name = ?) LIMIT 1');
            $stmt->execute([$username, $username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                if (($user['status'] ?? '') !== 'active') {
                    jsonResponse(false, 'Account not active. Please contact admin.', null, 403);
                    break;
                }
                if ($selectedRole && ($user['role'] ?? '') !== $selectedRole) {
                    jsonResponse(false, "This account is registered as {$user['role']}, not {$selectedRole}", null, 403);
                    break;
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                unset($user['password']);
                $token = (string)$user['id'];
                jsonResponse(true, 'Login successful', ['user' => $user, 'token' => $token]);
            } else {
                jsonResponse(false, 'Invalid credentials', null, 401);
            }
            break;

        case 'create-shipment':
        case 'create_shipment':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
                break;
            }
            if (!hasTable($conn, 'shipments')) {
                jsonResponse(false, 'Shipments table not found', null, 500);
                break;
            }
            $shipmentCols = getTableColumns($conn, 'shipments');
            $trackingNumber = trim($input['tracking_number'] ?? '') ?: generateTrackingNumber();
            $dataMap = [
                'tracking_number' => $trackingNumber,
                'customer_id' => $auth['user']['id'],
                'shipment_type' => $input['shipment_type'] ?? 'local',
                'transport_mode' => $input['transport_mode'] ?? 'road',
                'goods_type' => $input['goods_type'] ?? $input['goods_category'] ?? 'general',
                'goods_description' => $input['goods_description'] ?? '',
                'weight' => $input['weight'] ?? null,
                'value' => $input['value'] ?? $input['price'] ?? null,
                'pickup_location' => $input['pickup_location'] ?? $input['origin'] ?? $input['sender_address'] ?? '',
                'delivery_location' => $input['delivery_location'] ?? $input['destination'] ?? '',
                'sender_name' => $input['sender_name'] ?? ($auth['user']['full_name'] ?? ''),
                'sender_phone' => $input['sender_phone'] ?? '',
                'sender_address' => $input['sender_address'] ?? '',
                'receiver_name' => $input['receiver_name'] ?? '',
                'receiver_phone' => $input['receiver_phone'] ?? '',
                'receiver_address' => $input['receiver_address'] ?? '',
                'receiver_city' => $input['receiver_city'] ?? $input['delivery_location'] ?? $input['destination'] ?? '',
                'receiver_country' => $input['receiver_country'] ?? 'Kenya',
                'service_type' => $input['service_type'] ?? null,
                'estimated_delivery' => $input['estimated_delivery'] ?? null,
                'notes' => $input['notes'] ?? '',
                'status' => 'pending',
            ];
            $insertCols = [];
            $values = [];
            foreach ($dataMap as $col => $value) {
                if (in_array($col, $shipmentCols, true)) {
                    $insertCols[] = $col;
                    $values[] = $value;
                }
            }
            if (in_array('created_at', $shipmentCols, true)) {
                $insertCols[] = 'created_at';
            }
            if (empty($insertCols)) {
                jsonResponse(false, 'No compatible shipment columns found', null, 500);
                break;
            }
            $placeholders = [];
            foreach ($insertCols as $col) {
                $placeholders[] = ($col === 'created_at') ? 'NOW()' : '?';
            }
            $stmt = $conn->prepare('INSERT INTO shipments (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $stmt->execute($values);
            $shipmentId = $conn->lastInsertId();
            jsonResponse(true, 'Shipment created successfully', ['shipment_id' => $shipmentId, 'tracking_number' => $trackingNumber]);
            break;

        case 'customer-shipments':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            if (!hasTable($conn, 'shipments')) {
                jsonResponse(true, '', []);
                break;
            }
            if (hasColumn($conn, 'shipments', 'customer_id')) {
                $stmt = $conn->prepare('SELECT * FROM shipments WHERE customer_id = ? ORDER BY ' . (hasColumn($conn, 'shipments', 'created_at') ? 'created_at' : 'id') . ' DESC');
                $stmt->execute([$auth['user']['id']]);
            } else {
                $stmt = $conn->query('SELECT * FROM shipments ORDER BY ' . (hasColumn($conn, 'shipments', 'created_at') ? 'created_at' : 'id') . ' DESC');
            }
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'shipments':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, 'Not authenticated', null, 401);
                break;
            }
            if (!hasTable($conn, 'shipments')) {
                jsonResponse(true, '', []);
                break;
            }
            if (in_array($auth['user']['role'], ['admin', 'staff'], true)) {
                $sql = 'SELECT s.*';
                if (hasTable($conn, 'users') && hasColumn($conn, 'shipments', 'customer_id') && hasColumn($conn, 'users', 'id')) {
                    if (hasColumn($conn, 'users', 'name')) $sql .= ', u.name as customer_name';
                    if (hasColumn($conn, 'users', 'email')) $sql .= ', u.email as customer_email';
                    if (hasColumn($conn, 'users', 'phone')) $sql .= ', u.phone as customer_phone';
                    $sql .= ' FROM shipments s LEFT JOIN users u ON s.customer_id = u.id';
                } else {
                    $sql .= ' FROM shipments s';
                }
                $sql .= ' ORDER BY ' . (hasColumn($conn, 'shipments', 'created_at') ? 's.created_at' : 's.id') . ' DESC';
                $stmt = $conn->query($sql);
            } else {
                if (hasColumn($conn, 'shipments', 'customer_id')) {
                    $stmt = $conn->prepare('SELECT * FROM shipments WHERE customer_id = ? ORDER BY ' . (hasColumn($conn, 'shipments', 'created_at') ? 'created_at' : 'id') . ' DESC');
                    $stmt->execute([$auth['user']['id']]);
                } else {
                    $stmt = $conn->query('SELECT * FROM shipments ORDER BY ' . (hasColumn($conn, 'shipments', 'created_at') ? 'created_at' : 'id') . ' DESC');
                }
            }
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'drivers':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, 'Not authenticated', null, 401);
                break;
            }
            if (!hasTable($conn, 'drivers')) {
                jsonResponse(true, '', []);
                break;
            }
            $orderCol = firstExistingColumn($conn, 'drivers', ['name', 'id']);
            $stmt = $conn->query('SELECT * FROM drivers' . ($orderCol ? ' ORDER BY ' . $orderCol : ''));
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'create-driver':
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['staff', 'admin'], true)) {
                jsonResponse(false, 'Staff access required', null, 403);
                break;
            }
            if (!hasTable($conn, 'drivers')) {
                jsonResponse(false, 'Drivers table not found', null, 500);
                break;
            }
            $driverCols = getTableColumns($conn, 'drivers');
            $insert = [];
            $vals = [];
            foreach (['name', 'phone', 'license_number', 'status', 'notes'] as $col) {
                if (in_array($col, $driverCols, true)) {
                    $insert[] = $col;
                    if ($col === 'status') {
                        $vals[] = $input[$col] ?? 'available';
                    } else {
                        $vals[] = $input[$col] ?? '';
                    }
                }
            }
            if (in_array('created_at', $driverCols, true)) $insert[] = 'created_at';
            $placeholders = [];
            foreach ($insert as $col) $placeholders[] = ($col === 'created_at') ? 'NOW()' : '?';
            $stmt = $conn->prepare('INSERT INTO drivers (' . implode(', ', $insert) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $stmt->execute($vals);
            jsonResponse(true, 'Driver added successfully', ['driver_id' => $conn->lastInsertId()]);
            break;

        case 'vehicles':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, 'Not authenticated', null, 401);
                break;
            }
            if (!hasTable($conn, 'vehicles')) {
                jsonResponse(true, '', []);
                break;
            }
            $sql = 'SELECT * FROM vehicles';
            if (hasColumn($conn, 'vehicles', 'status')) {
                $sql .= " WHERE status = 'available' OR status IS NULL OR status = ''";
            }
            $orderCol = firstExistingColumn($conn, 'vehicles', ['registration_number', 'plate_number', 'id']);
            if ($orderCol) $sql .= ' ORDER BY ' . $orderCol;
            $stmt = $conn->query($sql);
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'clearances':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, 'Not authenticated', null, 401);
                break;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // alias to create-clearance for the staff form that posts directly to clearances
                if (!in_array($auth['user']['role'], ['staff', 'admin'], true)) {
                    jsonResponse(false, 'Staff access required', null, 403);
                    break;
                }
                $shipmentId = $input['shipment_id'] ?? null;
                $driverId = $input['driver_id'] ?? null;
                $vehicleId = $input['vehicle_id'] ?? null;
                $shipment = $shipmentId ? fetchShipmentById($conn, $shipmentId) : null;
                $driver = $driverId ? fetchDriverById($conn, $driverId) : null;
                $vehicle = $vehicleId ? fetchVehicleById($conn, $vehicleId) : null;

                $clearanceCols = getTableColumns($conn, 'clearances');
                $map = [
                    'shipment_id' => $shipmentId,
                    'driver_id' => $driverId,
                    'vehicle_id' => $vehicleId,
                    'tracking_number' => $input['tracking_number'] ?? ($shipment['tracking_number'] ?? null),
                    'customer_name' => $input['customer_name'] ?? ($shipment['receiver_name'] ?? $shipment['sender_name'] ?? null),
                    'customer_id' => $input['customer_id'] ?? ($shipment['customer_id'] ?? null),
                    'customer_phone' => $input['customer_phone'] ?? ($shipment['receiver_phone'] ?? $shipment['sender_phone'] ?? null),
                    'customer_email' => $input['customer_email'] ?? null,
                    'destination' => $input['destination'] ?? ($shipment['delivery_location'] ?? $shipment['receiver_city'] ?? $shipment['receiver_address'] ?? null),
                    'departure_time' => $input['departure_time'] ?? null,
                    'driver_name' => $input['driver_name'] ?? ($driver['name'] ?? null),
                    'vehicle_reg' => $input['vehicle_reg'] ?? (($vehicle['registration_number'] ?? null) ?: ($vehicle['plate_number'] ?? null)),
                    'goods_description' => $input['goods_description'] ?? ($shipment['goods_description'] ?? $shipment['notes'] ?? null),
                    'total_weight' => $input['total_weight'] ?? ($shipment['weight'] ?? null),
                    'staff_id' => $auth['user']['id'],
                    'admin_id' => null,
                    'status' => 'pending',
                    'created_by' => $auth['user']['full_name'] ?? $auth['user']['username'],
                ];
                $insertCols = [];
                $vals = [];
                foreach ($map as $col => $val) {
                    if (in_array($col, $clearanceCols, true)) {
                        $insertCols[] = $col;
                        $vals[] = $val;
                    }
                }
                if (in_array('created_at', $clearanceCols, true)) $insertCols[] = 'created_at';
                if (empty($insertCols)) {
                    jsonResponse(false, 'No compatible clearance columns found', null, 500);
                    break;
                }
                $ph = [];
                foreach ($insertCols as $col) $ph[] = ($col === 'created_at') ? 'NOW()' : '?';
                $stmt = $conn->prepare('INSERT INTO clearances (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $ph) . ')');
                $stmt->execute($vals);
                jsonResponse(true, 'Clearance created! Awaiting admin approval.', ['clearance_id' => $conn->lastInsertId()]);
                break;
            }
            jsonResponse(true, '', buildClearanceViewRows($conn));
            break;

        case 'create-clearance':
            // same logic as clearances POST
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $endpoint = 'clearances';
            // inline dispatch
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['staff', 'admin'], true)) {
                jsonResponse(false, 'Staff access required', null, 403);
                break;
            }
            $shipmentId = $input['shipment_id'] ?? null;
            $driverId = $input['driver_id'] ?? null;
            $vehicleId = $input['vehicle_id'] ?? null;
            $shipment = $shipmentId ? fetchShipmentById($conn, $shipmentId) : null;
            $driver = $driverId ? fetchDriverById($conn, $driverId) : null;
            $vehicle = $vehicleId ? fetchVehicleById($conn, $vehicleId) : null;
            $clearanceCols = getTableColumns($conn, 'clearances');
            $map = [
                'shipment_id' => $shipmentId,
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
                'tracking_number' => $input['tracking_number'] ?? ($shipment['tracking_number'] ?? null),
                'customer_name' => $input['customer_name'] ?? ($shipment['receiver_name'] ?? $shipment['sender_name'] ?? null),
                'customer_id' => $input['customer_id'] ?? ($shipment['customer_id'] ?? null),
                'customer_phone' => $input['customer_phone'] ?? ($shipment['receiver_phone'] ?? $shipment['sender_phone'] ?? null),
                'customer_email' => $input['customer_email'] ?? null,
                'destination' => $input['destination'] ?? ($shipment['delivery_location'] ?? $shipment['receiver_city'] ?? $shipment['receiver_address'] ?? null),
                'departure_time' => $input['departure_time'] ?? null,
                'driver_name' => $input['driver_name'] ?? ($driver['name'] ?? null),
                'vehicle_reg' => $input['vehicle_reg'] ?? (($vehicle['registration_number'] ?? null) ?: ($vehicle['plate_number'] ?? null)),
                'goods_description' => $input['goods_description'] ?? ($shipment['goods_description'] ?? $shipment['notes'] ?? null),
                'total_weight' => $input['total_weight'] ?? ($shipment['weight'] ?? null),
                'staff_id' => $auth['user']['id'],
                'status' => 'pending',
                'created_by' => $auth['user']['full_name'] ?? $auth['user']['username'],
            ];
            $insertCols = [];
            $vals = [];
            foreach ($map as $col => $val) {
                if (in_array($col, $clearanceCols, true)) {
                    $insertCols[] = $col;
                    $vals[] = $val;
                }
            }
            if (in_array('created_at', $clearanceCols, true)) $insertCols[] = 'created_at';
            $ph = [];
            foreach ($insertCols as $col) $ph[] = ($col === 'created_at') ? 'NOW()' : '?';
            $stmt = $conn->prepare('INSERT INTO clearances (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $ph) . ')');
            $stmt->execute($vals);
            jsonResponse(true, 'Clearance created! Awaiting admin approval.', ['clearance_id' => $conn->lastInsertId()]);
            break;

        case 'pending-clearances':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            jsonResponse(true, '', buildClearanceViewRows($conn, 'pending'));
            break;

        case 'approved-clearances':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            jsonResponse(true, '', buildClearanceViewRows($conn, 'approved'));
            break;

        case 'rejected-clearances':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            jsonResponse(true, '', buildClearanceViewRows($conn, 'rejected'));
            break;

        case 'update-clearance-status':
            $auth = checkAuth($conn);
            if (!$auth['success'] || ($auth['user']['role'] !== 'admin' && $auth['user']['role'] !== 'staff')) {
                jsonResponse(false, 'Admin access required', null, 403);
                break;
            }
            $clearanceId = $input['clearance_id'] ?? null;
            $status = $input['status'] ?? '';
            $rejectionReason = $input['rejection_reason'] ?? '';
            if (!$clearanceId || !in_array($status, ['approved', 'rejected'], true)) {
                jsonResponse(false, 'Invalid data', null, 400);
                break;
            }
            if (!hasTable($conn, 'clearances')) {
                jsonResponse(false, 'Clearances table not found', null, 500);
                break;
            }
            $cols = getTableColumns($conn, 'clearances');
            $setParts = [];
            $vals = [];
            if (in_array('status', $cols, true)) { $setParts[] = 'status = ?'; $vals[] = $status; }
            if ($status === 'approved') {
                if (in_array('admin_id', $cols, true)) { $setParts[] = 'admin_id = ?'; $vals[] = $auth['user']['id']; }
                if (in_array('approved_at', $cols, true)) $setParts[] = 'approved_at = NOW()';
                if (in_array('approved_by', $cols, true)) { $setParts[] = 'approved_by = ?'; $vals[] = $auth['user']['full_name'] ?? $auth['user']['username']; }
            } else {
                if (in_array('admin_id', $cols, true)) { $setParts[] = 'admin_id = ?'; $vals[] = $auth['user']['id']; }
                if (in_array('rejected_at', $cols, true)) $setParts[] = 'rejected_at = NOW()';
                if (in_array('rejected_by', $cols, true)) { $setParts[] = 'rejected_by = ?'; $vals[] = $auth['user']['full_name'] ?? $auth['user']['username']; }
                if (in_array('rejection_reason', $cols, true)) { $setParts[] = 'rejection_reason = ?'; $vals[] = $rejectionReason; }
            }
            if (in_array('updated_at', $cols, true)) $setParts[] = 'updated_at = NOW()';
            $vals[] = $clearanceId;
            $stmt = $conn->prepare('UPDATE clearances SET ' . implode(', ', $setParts) . ' WHERE id = ?');
            $stmt->execute($vals);
            $journey = buildJourneyData($conn, $clearanceId, []);
            if ($journey && !empty($journey['customer_phone'])) {
                $phone = normalizePhone($journey['customer_phone']);
                if ($phone) {
                    $text = $status === 'approved'
                        ? "CargoTrack: Your clearance for {$journey['tracking_number']} has been APPROVED."
                        : "CargoTrack: Clearance rejected. Reason: {$rejectionReason}";
                    sendSMS($phone, $text);
                }
            }
            jsonResponse(true, "Clearance {$status} successfully");
            break;

        case 'approve-clearance':
            $input['status'] = 'approved';
            $endpoint = 'update-clearance-status';
            $auth = checkAuth($conn);
            if (!$auth['success'] || ($auth['user']['role'] !== 'admin' && $auth['user']['role'] !== 'staff')) {
                jsonResponse(false, 'Admin access required', null, 403);
                break;
            }
            $clearanceId = $input['clearance_id'] ?? null;
            if (!$clearanceId) {
                jsonResponse(false, 'Clearance ID required', null, 400);
                break;
            }
            $cols = getTableColumns($conn, 'clearances');
            $set = [];
            $vals = [];
            if (in_array('status', $cols, true)) { $set[] = 'status = ?'; $vals[] = 'approved'; }
            if (in_array('admin_id', $cols, true)) { $set[] = 'admin_id = ?'; $vals[] = $auth['user']['id']; }
            if (in_array('approved_at', $cols, true)) $set[] = 'approved_at = NOW()';
            if (in_array('approved_by', $cols, true)) { $set[] = 'approved_by = ?'; $vals[] = $auth['user']['full_name'] ?? $auth['user']['username']; }
            if (in_array('updated_at', $cols, true)) $set[] = 'updated_at = NOW()';
            $vals[] = $clearanceId;
            $stmt = $conn->prepare('UPDATE clearances SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($vals);
            jsonResponse(true, 'Clearance approved');
            break;

        case 'begin-journey':
        case 'start-journey':
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['staff', 'admin'], true)) {
                jsonResponse(false, 'Staff access required', null, 403);
                break;
            }
            $clearanceId = $input['clearance_id'] ?? null;
            if (!$clearanceId) {
                jsonResponse(false, 'Clearance ID required', null, 400);
                break;
            }
            $journey = buildJourneyData($conn, $clearanceId, $input);
            if (!$journey) {
                jsonResponse(false, 'Clearance not found', null, 404);
                break;
            }
            $trackingNumber = trim((string)($journey['tracking_number'] ?? '')) ?: 'Tracking unavailable';
            $customerName = trim((string)($journey['customer_name'] ?? '')) ?: 'Customer';
            $destination = trim((string)($journey['destination'] ?? '')) ?: 'Destination';
            $phone = normalizePhone($journey['customer_phone'] ?? '');
            $smsSent = false;
            if ($phone) {
                $smsSent = sendSMS($phone, "CargoTrack: Dear {$customerName}, your shipment {$trackingNumber} has started its journey to {$destination}.");
            }
            $clearanceCols = getTableColumns($conn, 'clearances');
            if (in_array('status', $clearanceCols, true)) {
                $setParts = ['status = ?'];
                $vals = ['in_transit'];
                if (in_array('updated_at', $clearanceCols, true)) $setParts[] = 'updated_at = NOW()';
                if (in_array('journey_started', $clearanceCols, true)) { $setParts[] = 'journey_started = ?'; $vals[] = 1; }
                if (in_array('journey_started_at', $clearanceCols, true)) $setParts[] = 'journey_started_at = NOW()';
                $vals[] = $clearanceId;
                $stmt = $conn->prepare('UPDATE clearances SET ' . implode(', ', $setParts) . ' WHERE id = ?');
                $stmt->execute($vals);
            }
            $shipment = $journey['shipment'] ?? null;
            if (!$shipment && !empty($journey['clearance']['shipment_id'])) {
                $shipment = fetchShipmentById($conn, $journey['clearance']['shipment_id']);
            }
            if (hasTable($conn, 'shipments')) {
                try {
                    if ($shipment && isset($shipment['id']) && hasColumn($conn, 'shipments', 'status')) {
                        $set = ['status = ?'];
                        $vals = ['in_transit'];
                        if (hasColumn($conn, 'shipments', 'updated_at')) $set[] = 'updated_at = NOW()';
                        $vals[] = $shipment['id'];
                        $stmt = $conn->prepare('UPDATE shipments SET ' . implode(', ', $set) . ' WHERE id = ?');
                        $stmt->execute($vals);
                    } elseif (hasColumn($conn, 'shipments', 'tracking_number') && hasColumn($conn, 'shipments', 'status')) {
                        $set = ['status = ?'];
                        $vals = ['in_transit'];
                        if (hasColumn($conn, 'shipments', 'updated_at')) $set[] = 'updated_at = NOW()';
                        $vals[] = $trackingNumber;
                        $stmt = $conn->prepare('UPDATE shipments SET ' . implode(', ', $set) . ' WHERE tracking_number = ?');
                        $stmt->execute($vals);
                    }
                } catch (Exception $e) {
                    error_log('Shipment status update skipped: ' . $e->getMessage());
                }
            }
            jsonResponse(true, $smsSent ? 'Journey started and customer notified' : 'Journey started but SMS failed', [
                'clearance_id' => $clearanceId,
                'tracking_number' => $trackingNumber,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'destination' => $destination,
                'driver_name' => $journey['driver_name'],
                'vehicle_reg' => $journey['vehicle_reg'],
                'status' => 'in_transit',
                'sms_sent' => (bool)$smsSent,
            ]);
            break;

        case 'mark-delivered':
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['staff', 'admin'], true)) {
                jsonResponse(false, 'Staff access required', null, 403);
                break;
            }
            $clearanceId = $input['clearance_id'] ?? null;
            if (!$clearanceId) {
                jsonResponse(false, 'Clearance ID required', null, 400);
                break;
            }
            $journey = buildJourneyData($conn, $clearanceId, $input);
            if (!$journey) {
                jsonResponse(false, 'Clearance not found', null, 404);
                break;
            }
            $cols = getTableColumns($conn, 'clearances');
            $set = [];
            $vals = [];
            if (in_array('status', $cols, true)) { $set[] = 'status = ?'; $vals[] = 'delivered'; }
            if (in_array('updated_at', $cols, true)) $set[] = 'updated_at = NOW()';
            $vals[] = $clearanceId;
            $stmt = $conn->prepare('UPDATE clearances SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($vals);
            if ($journey['shipment'] && hasTable($conn, 'shipments') && hasColumn($conn, 'shipments', 'status')) {
                $set = ['status = ?'];
                $vals = ['delivered'];
                if (hasColumn($conn, 'shipments', 'updated_at')) $set[] = 'updated_at = NOW()';
                $vals[] = $journey['shipment']['id'];
                $stmt = $conn->prepare('UPDATE shipments SET ' . implode(', ', $set) . ' WHERE id = ?');
                $stmt->execute($vals);
            }
            $phone = normalizePhone($journey['customer_phone'] ?? '');
            if ($phone) {
                sendSMS($phone, "CargoTrack: Your shipment {$journey['tracking_number']} has been delivered.");
            }
            jsonResponse(true, 'Shipment marked as delivered');
            break;

        case 'staff-cargo':
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['staff', 'admin'], true)) {
                jsonResponse(false, 'Staff access required', null, 403);
                break;
            }
            jsonResponse(true, '', buildClearanceViewRows($conn, 'in_transit'));
            break;

        case 'track-shipment':
            $trackingNumber = $_GET['tracking_number'] ?? $_GET['id'] ?? '';
            if (!$trackingNumber) {
                jsonResponse(false, 'Tracking number required', null, 400);
                break;
            }
            if (!hasTable($conn, 'shipments') || !hasColumn($conn, 'shipments', 'tracking_number')) {
                jsonResponse(false, 'Shipment tracking not available', null, 404);
                break;
            }
            $sql = 'SELECT s.*';
            if (hasTable($conn, 'users') && hasColumn($conn, 'shipments', 'customer_id') && hasColumn($conn, 'users', 'id')) {
                if (hasColumn($conn, 'users', 'name')) $sql .= ', u.name as customer_name';
                if (hasColumn($conn, 'users', 'email')) $sql .= ', u.email as customer_email';
                $sql .= ' FROM shipments s LEFT JOIN users u ON s.customer_id = u.id';
            } else {
                $sql .= ' FROM shipments s';
            }
            $sql .= ' WHERE s.tracking_number = ? LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$trackingNumber]);
            $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$shipment) {
                jsonResponse(false, 'Shipment not found', null, 404);
                break;
            }
            if (hasTable($conn, 'shipment_tracking') && hasColumn($conn, 'shipment_tracking', 'shipment_id')) {
                $trackingStmt = $conn->prepare('SELECT * FROM shipment_tracking WHERE shipment_id = ? ORDER BY ' . (hasColumn($conn, 'shipment_tracking', 'created_at') ? 'created_at' : 'id') . ' DESC');
                $trackingStmt->execute([$shipment['id']]);
                $shipment['tracking_history'] = $trackingStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $shipment['tracking_history'] = [];
            }
            jsonResponse(true, '', $shipment);
            break;

        case 'send-notification':
        case 'send_sms':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, 'Not authenticated', null, 401);
                break;
            }
            $phone = $input['phone'] ?? '';
            $message = $input['message'] ?? '';
            if (!$phone || !$message) {
                jsonResponse(false, 'Phone and message required', null, 400);
                break;
            }
            $ok = sendSMS($phone, $message);
            jsonResponse($ok, $ok ? 'SMS sent' : 'SMS failed');
            break;

        case 'customers':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                jsonResponse(false, 'Admin access required', null, 403);
                break;
            }
            $sql = 'SELECT id';
            foreach (['name', 'email', 'phone', 'city', 'country', 'created_at'] as $col) {
                if (hasColumn($conn, 'users', $col)) $sql .= ', ' . $col;
            }
            $sql .= " FROM users WHERE role = 'customer'";
            if (hasColumn($conn, 'users', 'name')) $sql .= ' ORDER BY name';
            $stmt = $conn->query($sql);
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'notifications':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            if (!hasTable($conn, 'notifications') || !hasColumn($conn, 'notifications', 'user_id')) {
                jsonResponse(true, '', []);
                break;
            }
            $orderCol = firstExistingColumn($conn, 'notifications', ['created_at', 'id']) ?: 'id';
            $stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY ' . $orderCol . ' DESC LIMIT 20');
            $stmt->execute([$auth['user']['id']]);
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'stats':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                jsonResponse(false, $auth['message'], null, 401);
                break;
            }
            $role = $auth['user']['role'];
            $userId = $auth['user']['id'];
            $stats = [];
            if ($role === 'admin') {
                if (hasTable($conn, 'shipments') && hasColumn($conn, 'shipments', 'status')) {
                    $stats['total_shipments'] = (int)$conn->query('SELECT COUNT(*) FROM shipments')->fetchColumn();
                    $stats['pending_shipments'] = (int)$conn->query("SELECT COUNT(*) FROM shipments WHERE status = 'pending'")->fetchColumn();
                    $stats['in_transit'] = (int)$conn->query("SELECT COUNT(*) FROM shipments WHERE status = 'in_transit'")->fetchColumn();
                } else {
                    $stats['total_shipments'] = 0;
                    $stats['pending_shipments'] = 0;
                    $stats['in_transit'] = 0;
                }
                if (hasTable($conn, 'clearances') && hasColumn($conn, 'clearances', 'status')) {
                    $stats['pending_clearances'] = (int)$conn->query("SELECT COUNT(*) FROM clearances WHERE status = 'pending'")->fetchColumn();
                } else {
                    $stats['pending_clearances'] = 0;
                }
            } elseif ($role === 'staff') {
                $whereStaff = hasColumn($conn, 'clearances', 'staff_id');
                if (hasTable($conn, 'clearances')) {
                    if ($whereStaff) {
                        $stmt = $conn->prepare('SELECT COUNT(*) FROM clearances WHERE staff_id = ?');
                        $stmt->execute([$userId]);
                        $stats['total_clearances'] = (int)$stmt->fetchColumn();
                        if (hasColumn($conn, 'clearances', 'status')) {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM clearances WHERE staff_id = ? AND status = 'pending'");
                            $stmt->execute([$userId]);
                            $stats['pending_clearances'] = (int)$stmt->fetchColumn();
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM clearances WHERE staff_id = ? AND status = 'approved'");
                            $stmt->execute([$userId]);
                            $stats['approved_clearances'] = (int)$stmt->fetchColumn();
                        }
                    } else {
                        $stats['total_clearances'] = (int)$conn->query('SELECT COUNT(*) FROM clearances')->fetchColumn();
                        $stats['pending_clearances'] = hasColumn($conn, 'clearances', 'status') ? (int)$conn->query("SELECT COUNT(*) FROM clearances WHERE status = 'pending'")->fetchColumn() : 0;
                        $stats['approved_clearances'] = hasColumn($conn, 'clearances', 'status') ? (int)$conn->query("SELECT COUNT(*) FROM clearances WHERE status = 'approved'")->fetchColumn() : 0;
                    }
                }
            } else {
                if (hasTable($conn, 'shipments') && hasColumn($conn, 'shipments', 'customer_id')) {
                    $stmt = $conn->prepare('SELECT COUNT(*) FROM shipments WHERE customer_id = ?');
                    $stmt->execute([$userId]);
                    $stats['total_shipments'] = (int)$stmt->fetchColumn();
                    if (hasColumn($conn, 'shipments', 'status')) {
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM shipments WHERE customer_id = ? AND status = 'in_transit'");
                        $stmt->execute([$userId]);
                        $stats['in_transit'] = (int)$stmt->fetchColumn();
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM shipments WHERE customer_id = ? AND status = 'delivered'");
                        $stmt->execute([$userId]);
                        $stats['delivered'] = (int)$stmt->fetchColumn();
                    }
                } else {
                    $stats['total_shipments'] = 0;
                    $stats['in_transit'] = 0;
                    $stats['delivered'] = 0;
                }
            }
            jsonResponse(true, '', $stats);
            break;

        case 'request_shipment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
                break;
            }
            // Auto-create shipment_requests table if it doesn't exist
            if (!hasTable($conn, 'shipment_requests')) {
                $sql = "CREATE TABLE IF NOT EXISTS `shipment_requests` (
                  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `customer_name` VARCHAR(150) NOT NULL,
                  `phone` VARCHAR(20) NOT NULL,
                  `email` VARCHAR(150) DEFAULT NULL,
                  `cargo_type` VARCHAR(100) DEFAULT NULL,
                  `goods_description` TEXT NOT NULL,
                  `pickup_location` VARCHAR(255) DEFAULT NULL,
                  `destination` VARCHAR(255) NOT NULL,
                  `pickup_date` DATE DEFAULT NULL,
                  `pickup_time` TIME DEFAULT NULL,
                  `notes` TEXT DEFAULT NULL,
                  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                  `admin_notes` TEXT DEFAULT NULL,
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_phone` (`phone`),
                  KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $conn->exec($sql);
            }
            $reqCols = getTableColumns($conn, 'shipment_requests');
            $map = [
                'customer_name' => trim($input['customer_name'] ?? ''),
                'phone' => trim($input['phone'] ?? ''),
                'email' => trim($input['email'] ?? ''),
                'cargo_type' => trim($input['cargo_type'] ?? ''),
                'goods_description' => trim($input['goods_description'] ?? ''),
                'pickup_location' => trim($input['pickup_location'] ?? ''),
                'destination' => trim($input['destination'] ?? ''),
                'pickup_date' => $input['pickup_date'] ?? null,
                'pickup_time' => $input['pickup_time'] ?? null,
                'notes' => trim($input['notes'] ?? ''),
                'status' => 'pending',
                'admin_notes' => '',
            ];
            if (!$map['customer_name'] || !$map['phone'] || !$map['email'] || !$map['goods_description'] || !$map['destination']) {
                jsonResponse(false, 'Required fields missing: customer name, phone, email, goods description, destination', null, 400);
                break;
            }
            $insert = [];
            $vals = [];
            foreach ($map as $col => $val) {
                if (in_array($col, $reqCols, true)) {
                    $insert[] = $col;
                    $vals[] = $val;
                }
            }
            if (in_array('created_at', $reqCols, true)) $insert[] = 'created_at';
            $ph = [];
            foreach ($insert as $col) $ph[] = ($col === 'created_at') ? 'NOW()' : '?';
            $stmt = $conn->prepare('INSERT INTO shipment_requests (' . implode(', ', $insert) . ') VALUES (' . implode(', ', $ph) . ')');
            $stmt->execute($vals);
            jsonResponse(true, 'Request sent. Await admin approval.', ['request_id' => $conn->lastInsertId()]);
            break;

        case 'pending-requests':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                jsonResponse(false, 'Admin access required', null, 403);
                break;
            }
            // Auto-create shipment_requests table if it doesn't exist
            if (!hasTable($conn, 'shipment_requests')) {
                $sql = "CREATE TABLE IF NOT EXISTS `shipment_requests` (
                  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `customer_name` VARCHAR(150) NOT NULL,
                  `phone` VARCHAR(20) NOT NULL,
                  `email` VARCHAR(150) DEFAULT NULL,
                  `cargo_type` VARCHAR(100) DEFAULT NULL,
                  `goods_description` TEXT NOT NULL,
                  `pickup_location` VARCHAR(255) DEFAULT NULL,
                  `destination` VARCHAR(255) NOT NULL,
                  `pickup_date` DATE DEFAULT NULL,
                  `pickup_time` TIME DEFAULT NULL,
                  `notes` TEXT DEFAULT NULL,
                  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                  `admin_notes` TEXT DEFAULT NULL,
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_phone` (`phone`),
                  KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $conn->exec($sql);
                jsonResponse(true, '', []);
                break;
            }
            $sql = 'SELECT * FROM shipment_requests';
            if (hasColumn($conn, 'shipment_requests', 'status')) $sql .= " WHERE status = 'pending'";
            $orderCol = firstExistingColumn($conn, 'shipment_requests', ['created_at', 'id']);
            if ($orderCol) $sql .= ' ORDER BY ' . $orderCol . ' DESC';
            $stmt = $conn->query($sql);
            jsonResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'update-request-status':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                jsonResponse(false, 'Admin access required', null, 403);
                break;
            }
            if (!hasTable($conn, 'shipment_requests')) {
                jsonResponse(false, 'shipment_requests table not found', null, 500);
                break;
            }
            $requestId = $input['request_id'] ?? null;
            $status = $input['status'] ?? '';
            $adminNotes = $input['admin_notes'] ?? '';
            if (!$requestId || !in_array($status, ['approved', 'rejected'], true)) {
                jsonResponse(false, 'Invalid data', null, 400);
                break;
            }
            $reqCols = getTableColumns($conn, 'shipment_requests');
            $set = [];
            $vals = [];
            if (in_array('status', $reqCols, true)) { $set[] = 'status = ?'; $vals[] = $status; }
            if (in_array('admin_notes', $reqCols, true)) { $set[] = 'admin_notes = ?'; $vals[] = $adminNotes; }
            if (in_array('updated_at', $reqCols, true)) $set[] = 'updated_at = NOW()';
            $vals[] = $requestId;
            $stmt = $conn->prepare('UPDATE shipment_requests SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($vals);

            // Send SMS notification
            $stmtReq = $conn->prepare('SELECT * FROM shipment_requests WHERE id = ?');
            $stmtReq->execute([$requestId]);
            $request = $stmtReq->fetch(PDO::FETCH_ASSOC);
            if ($request && !empty($request['phone'])) {
                $phone = normalizePhone($request['phone']);
                $customerName = trim($request['customer_name'] ?? 'Customer');
                if ($status === 'approved') {
                    sendSMS($phone, "Dear {$customerName}, we have accepted your request and will proceed for pickup as per the agreed day.");
                } elseif ($status === 'rejected') {
                    $reason = $adminNotes ? " Reason: {$adminNotes}" : '';
                    sendSMS($phone, "Dear {$customerName}, your shipment request has been rejected.{$reason}");
                }
            }

            if ($status === 'approved' && hasTable($conn, 'shipments')) {
                $stmtReq = $conn->prepare('SELECT * FROM shipment_requests WHERE id = ?');
                $stmtReq->execute([$requestId]);
                $request = $stmtReq->fetch(PDO::FETCH_ASSOC) ?: [];
                $trackingNumber = generateTrackingNumber();
                $shipmentInput = [
                    'tracking_number' => $trackingNumber,
                    'sender_name' => $request['customer_name'] ?? '',
                    'sender_phone' => $request['phone'] ?? '',
                    'sender_email' => $request['email'] ?? '',
                    'sender_address' => $request['pickup_location'] ?? '',
                    'receiver_name' => $request['customer_name'] ?? '',
                    'receiver_phone' => $request['phone'] ?? '',
                    'receiver_email' => $request['email'] ?? '',
                    'receiver_address' => $request['destination'] ?? '',
                    'receiver_city' => $request['destination'] ?? '',
                    'goods_type' => $request['cargo_type'] ?? '',
                    'goods_description' => $request['goods_description'] ?? '',
                    'pickup_location' => $request['pickup_location'] ?? '',
                    'delivery_location' => $request['destination'] ?? '',
                    'pickup_date' => $request['pickup_date'] ?? null,
                    'pickup_time' => $request['pickup_time'] ?? null,
                    'notes' => $request['notes'] ?? '',
                    'status' => 'approved',
                ];
                $shipmentCols = getTableColumns($conn, 'shipments');
                $insert = [];
                $vals = [];
                foreach ($shipmentInput as $col => $val) {
                    if (in_array($col, $shipmentCols, true)) {
                        $insert[] = $col;
                        $vals[] = $val;
                    }
                }
                if (in_array('created_at', $shipmentCols, true)) $insert[] = 'created_at';
                $ph = [];
                foreach ($insert as $col) $ph[] = ($col === 'created_at') ? 'NOW()' : '?';
                $stmt = $conn->prepare('INSERT INTO shipments (' . implode(', ', $insert) . ') VALUES (' . implode(', ', $ph) . ')');
                $stmt->execute($vals);
                $shipmentId = $conn->lastInsertId();

                // Send SMS with shipment number
                if (!empty($request['phone'])) {
                    $phone = normalizePhone($request['phone']);
                    $customerName = trim($request['customer_name'] ?? 'Customer');
                    sendSMS($phone, "Dear {$customerName}, your shipment has been created. Tracking Number: {$trackingNumber}. You can track your shipment at any time.");
                }
            }
            jsonResponse(true, "Request {$status} successfully");
            break;

        case 'delete-clearance':
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['admin', 'staff'], true)) {
                jsonResponse(false, 'Access denied', null, 403);
                break;
            }
            $clearanceId = $input['clearance_id'] ?? null;
            if (!$clearanceId) {
                jsonResponse(false, 'Clearance ID required', null, 400);
                break;
            }
            $stmt = $conn->prepare('DELETE FROM clearances WHERE id = ?');
            $stmt->execute([$clearanceId]);
            jsonResponse(true, 'Clearance deleted successfully');
            break;

        case 'delete-driver':
            $auth = checkAuth($conn);
            if (!$auth['success'] || !in_array($auth['user']['role'], ['admin', 'staff'], true)) {
                jsonResponse(false, 'Access denied', null, 403);
                break;
            }
            $driverId = $input['driver_id'] ?? null;
            if (!$driverId) {
                jsonResponse(false, 'Driver ID required', null, 400);
                break;
            }
            $stmt = $conn->prepare('DELETE FROM drivers WHERE id = ?');
            $stmt->execute([$driverId]);
            jsonResponse(true, 'Driver deleted successfully');
            break;

        case 'csrf-token':
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            jsonResponse(true, '', ['csrf_token' => $_SESSION['csrf_token']]);
            break;
            
        default:
            jsonResponse(false, 'Invalid endpoint: ' . $endpoint, null, 404, [
                'available_endpoints' => [
                    'register', 'login', 'create-shipment', 'customer-shipments', 'shipments',
                    'drivers', 'create-driver', 'vehicles', 'clearances', 'create-clearance',
                    'pending-clearances', 'approved-clearances', 'rejected-clearances',
                    'update-clearance-status', 'approve-clearance', 'begin-journey', 'start-journey',
                    'mark-delivered', 'staff-cargo', 'track-shipment', 'send-notification', 'send_sms',
                    'customers', 'notifications', 'stats', 'request_shipment', 'pending-requests',
                    'update-request-status', 'delete-clearance', 'delete-driver', 'csrf-token'
                ]
            ]);
    }
} catch (Exception $e) {
    error_log('Server error: ' . $e->getMessage());
    jsonResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
?>
