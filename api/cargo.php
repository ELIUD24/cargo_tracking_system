<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "../config/database.php";

$database = new Database();
$conn = $database->getConnection();

$endpoint = $_GET['endpoint'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? [];

// Auth check helper
function checkAuth($conn) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (strpos($authHeader, 'Bearer demo-token') === 0) {
        return [
            'success' => true,
            'user' => [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@cargotrack.co.ke',
                'full_name' => 'Admin User',
                'role' => 'admin',
                'status' => 'active'
            ]
        ];
    }
    
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        return ['success' => false, 'message' => 'Not authenticated'];
    }
    
    $token = substr($authHeader, 7);
    $userId = filter_var($token, FILTER_VALIDATE_INT) ?: 1;
    
    try {
        $stmt = $conn->prepare("SELECT id, email, name, role, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid token'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account not active'];
        }
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['email'],
                'email' => $user['email'],
                'full_name' => $user['name'],
                'role' => $user['role'],
                'status' => $user['status']
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Auth error: ' . $e->getMessage()];
    }
}

// Generate tracking number
function generateTrackingNumber() {
    return 'CG' . date('Ymd') . strtoupper(substr(uniqid(), -6)) . 'KE';
}

// Send notification
function sendNotification($conn, $userId, $title, $message, $type = 'info') {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

try {
    switch ($endpoint) {
        // ========== USER REGISTRATION ==========
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                break;
            }
            
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $phone = trim($input['phone'] ?? '');
            
            if (!$name || !$email || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Required fields missing']);
                break;
            }
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Email already registered']);
                break;
            }
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'customer', 'active')");
            $stmt->execute([$name, $email, $passwordHash, $phone]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful! Please login.',
                'data' => ['user_id' => $conn->lastInsertId()]
            ]);
            break;
        
        // ========== USER LOGIN ==========
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                break;
            }
            
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            
            if (!$username || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username and password required']);
                break;
            }
            
            $stmt = $conn->prepare("SELECT id, email, password, name, role, status FROM users WHERE (email = ? OR name = ?) LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Account not active. Please contact admin.']);
                    break;
                }
                
                unset($user['password']);
                $token = bin2hex(random_bytes(32));
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'user' => $user,
                        'token' => $token
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;
        
        // ========== CREATE SHIPMENT (CUSTOMER) ==========
        case 'create-shipment':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                break;
            }
            
            $trackingNumber = generateTrackingNumber();
            $customerId = $auth['user']['id'];
            
            $stmt = $conn->prepare("
                INSERT INTO shipments (
                    tracking_number, customer_id, shipment_type, transport_mode, goods_type,
                    goods_description, weight, value, pickup_location, delivery_location,
                    pickup_deadline, delivery_deadline, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $trackingNumber,
                $customerId,
                $input['shipment_type'],
                $input['transport_mode'],
                $input['goods_type'],
                $input['goods_description'] ?? '',
                $input['weight'] ?? null,
                $input['value'] ?? null,
                $input['pickup_location'] ?? '',
                $input['delivery_location'] ?? '',
                $input['pickup_deadline'] ?? null,
                $input['delivery_deadline'] ?? null
            ]);
            
            // Send notification to admin
            $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll();
            
            foreach ($admins as $admin) {
                sendNotification(
                    $conn,
                    $admin['id'],
                    'New Shipment Request',
                    "Customer {$auth['user']['full_name']} has requested a new shipment (Tracking: {$trackingNumber})",
                    'info'
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Shipment request submitted! Awaiting admin approval.',
                'data' => [
                    'shipment_id' => $conn->lastInsertId(),
                    'tracking_number' => $trackingNumber
                ]
            ]);
            break;
        
        // ========== GET CUSTOMER SHIPMENTS ==========
        case 'customer-shipments':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            $customerId = $auth['user']['id'];
            
            $stmt = $conn->prepare("
                SELECT s.*, 
                       (SELECT location FROM shipment_tracking WHERE shipment_id = s.id ORDER BY timestamp DESC LIMIT 1) as last_location,
                       (SELECT latitude FROM shipment_tracking WHERE shipment_id = s.id ORDER BY timestamp DESC LIMIT 1) as latitude,
                       (SELECT longitude FROM shipment_tracking WHERE shipment_id = s.id ORDER BY timestamp DESC LIMIT 1) as longitude
                FROM shipments s
                WHERE s.customer_id = ?
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$customerId]);
            $shipments = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $shipments]);
            break;
        
        // ========== GET PENDING SHIPMENTS (ADMIN) ==========
        case 'pending-shipments':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $stmt = $conn->prepare("
                SELECT s.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
                FROM shipments s
                JOIN users u ON s.customer_id = u.id
                WHERE s.status = 'pending'
                ORDER BY s.created_at DESC
            ");
            $stmt->execute();
            $shipments = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $shipments]);
            break;
        
        // ========== APPROVE/REJECT SHIPMENT (ADMIN) ==========
        case 'update-shipment-status':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $shipmentId = $input['shipment_id'] ?? null;
            $status = $input['status'] ?? '';
            $notes = $input['admin_notes'] ?? '';
            $rejectionReason = $input['rejection_reason'] ?? '';
            
            if (!$shipmentId || !in_array($status, ['approved', 'rejected'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                break;
            }
            
            $stmt = $conn->prepare("
                UPDATE shipments 
                SET status = ?, admin_notes = ?, rejection_reason = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $rejectionReason, $shipmentId]);
            
            // Get shipment details for notification
            $shipmentStmt = $conn->prepare("SELECT s.*, u.email as customer_email FROM shipments s JOIN users u ON s.customer_id = u.id WHERE s.id = ?");
            $shipmentStmt->execute([$shipmentId]);
            $shipment = $shipmentStmt->fetch();
            
            if ($shipment) {
                // Send notification to customer
                if ($status === 'approved') {
                    sendNotification(
                        $conn,
                        $shipment['customer_id'],
                        'Shipment Approved!',
                        "Your shipment ({$shipment['tracking_number']}) has been approved. You can now track your shipment.",
                        'success'
                    );
                } else {
                    sendNotification(
                        $conn,
                        $shipment['customer_id'],
                        'Shipment Rejected',
                        "Your shipment request was rejected. Reason: {$rejectionReason}",
                        'error'
                    );
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Shipment {$status} successfully"]);
            break;
        
        // ========== CREATE CLEARANCE (STAFF) ==========
        case 'create-clearance':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'staff') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Staff access required']);
                break;
            }
            
            $shipmentId = $input['shipment_id'] ?? null;
            $driverId = $input['driver_id'] ?? null;
            $vehicleId = $input['vehicle_id'] ?? null;
            
            if (!$shipmentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
                break;
            }
            
            // Get shipment tracking number
            $shipmentStmt = $conn->prepare("SELECT tracking_number FROM shipments WHERE id = ?");
            $shipmentStmt->execute([$shipmentId]);
            $shipment = $shipmentStmt->fetch();
            
            if (!$shipment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Shipment not found']);
                break;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO clearances (shipment_id, staff_id, tracking_number, driver_id, vehicle_id, clearance_status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $shipmentId,
                $auth['user']['id'],
                $shipment['tracking_number'],
                $driverId,
                $vehicleId
            ]);
            
            // Notify admin for approval
            $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll();
            
            foreach ($admins as $admin) {
                sendNotification(
                    $conn,
                    $admin['id'],
                    'New Clearance for Approval',
                    "Staff has created clearance for shipment {$shipment['tracking_number']}",
                    'info'
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Clearance created! Awaiting admin approval.',
                'data' => ['clearance_id' => $conn->lastInsertId()]
            ]);
            break;
        
        // ========== GET PENDING CLEARANCES (ADMIN) ==========
        case 'pending-clearances':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $stmt = $conn->prepare("
                SELECT c.*, 
                       s.shipment_type, s.transport_mode, s.goods_type,
                       u.name as staff_name,
                       d.name as driver_name, d.phone as driver_phone,
                       v.registration_number as vehicle_reg
                FROM clearances c
                JOIN shipments s ON c.shipment_id = s.id
                JOIN users u ON c.staff_id = u.id
                LEFT JOIN drivers d ON c.driver_id = d.id
                LEFT JOIN vehicles v ON c.vehicle_id = v.id
                WHERE c.clearance_status = 'pending'
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
            $clearances = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $clearances]);
            break;
        
        // ========== APPROVE/REJECT CLEARANCE (ADMIN) ==========
        case 'update-clearance-status':
            $auth = checkAuth($conn);
            if (!$auth['success'] || $auth['user']['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $clearanceId = $input['clearance_id'] ?? null;
            $status = $input['status'] ?? '';
            
            if (!$clearanceId || !in_array($status, ['approved', 'rejected'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                break;
            }
            
            $conn->beginTransaction();
            
            try {
                // Update clearance status
                $stmt = $conn->prepare("
                    UPDATE clearances 
                    SET clearance_status = ?, admin_id = ?, approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $auth['user']['id'], $clearanceId]);
                
                // If approved, update shipment status to in_transit
                if ($status === 'approved') {
                    $clearanceStmt = $conn->prepare("SELECT shipment_id FROM clearances WHERE id = ?");
                    $clearanceStmt->execute([$clearanceId]);
                    $clearance = $clearanceStmt->fetch();
                    
                    if ($clearance) {
                        $updateShipment = $conn->prepare("UPDATE shipments SET status = 'in_transit' WHERE id = ?");
                        $updateShipment->execute([$clearance['shipment_id']]);
                        
                        // Get customer ID for notification
                        $shipmentStmt = $conn->prepare("SELECT customer_id, tracking_number FROM shipments WHERE id = ?");
                        $shipmentStmt->execute([$clearance['shipment_id']]);
                        $shipment = $shipmentStmt->fetch();
                        
                        if ($shipment) {
                            // Notify customer
                            sendNotification(
                                $conn,
                                $shipment['customer_id'],
                                'Shipment In Transit!',
                                "Great news! Your shipment ({$shipment['tracking_number']}) is now in transit. You can track it in real-time.",
                                'success'
                            );
                            
                            // Notify staff
                            sendNotification(
                                $conn,
                                $auth['user']['id'],
                                'Clearance Approved',
                                "Your clearance for shipment {$shipment['tracking_number']} has been approved.",
                                'success'
                            );
                        }
                    }
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => "Clearance {$status} successfully"]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
        
        // ========== GET ALL CLEARANCES (STAFF/ADMIN) ==========
        case 'clearances':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            $stmt = $conn->prepare("
                SELECT c.*, 
                       s.shipment_type, s.transport_mode, s.goods_type, s.status as shipment_status,
                       u.name as staff_name,
                       d.name as driver_name, d.phone as driver_phone,
                       v.registration_number as vehicle_reg, v.type as vehicle_type
                FROM clearances c
                JOIN shipments s ON c.shipment_id = s.id
                JOIN users u ON c.staff_id = u.id
                LEFT JOIN drivers d ON c.driver_id = d.id
                LEFT JOIN vehicles v ON c.vehicle_id = v.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
            $clearances = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $clearances]);
            break;
        
        // ========== GET DRIVERS ==========
        case 'drivers':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            $stmt = $conn->query("SELECT * FROM drivers ORDER BY name");
            $drivers = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $drivers]);
            break;
        
        // ========== GET VEHICLES ==========
        case 'vehicles':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            $stmt = $conn->query("SELECT * FROM vehicles WHERE status = 'available' ORDER BY registration_number");
            $vehicles = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $vehicles]);
            break;
        
        // ========== GET NOTIFICATIONS ==========
        case 'notifications':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            $userId = $auth['user']['id'];
            
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $notifications]);
            break;
            // Add these functions to cargo.php

// ==================== TRIGGER NOTIFICATIONS ====================

function sendClearanceNotification($conn, $clearance_id, $status) {
    try {
        // Get clearance details
        $stmt = $conn->prepare("
            SELECT c.*, u.phone as customer_phone, u.name as customer_name
            FROM clearances c
            LEFT JOIN customers u ON c.customer_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$clearance_id]);
        $clearance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$clearance || !$clearance['customer_phone']) {
            return false;
        }
        
        $notification_type = $status === 'approved' ? 'clearance_approved' : 'clearance_rejected';
        
        // Call messaging API
        $notification_data = [
            'type' => $notification_type,
            'customer_id' => $clearance['customer_id'],
            'tracking_number' => $clearance['tracking_number'],
            'phone' => $clearance['customer_phone'],
            'customer_name' => $clearance['customer_name'] ?? 'Customer'
        ];
        
        // Send notification (you can call the messaging API endpoint)
        $api_url = 'http://localhost/xampp/api/messaging.php?endpoint=send-notification';
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Update your approve-clearance endpoint to trigger notification
if ($endpoint === "approve-clearance") {
    // ... existing approval code ...
    
    // After approval, send notification
    sendClearanceNotification($conn, $clearance_id, 'approved');
    
    // ... rest of code ...
}

// Update your reject-clearance endpoint to trigger notification
if ($endpoint === "reject-clearance") {
    // ... existing rejection code ...
    
    // After rejection, send notification
    sendClearanceNotification($conn, $clearance_id, 'rejected');
    
    // ... rest of code ...
}
        
        // ========== GET DASHBOARD STATS ==========
        case 'stats':
            $auth = checkAuth($conn);
            if (!$auth['success']) {
                http_response_code(401);
                echo json_encode($auth);
                break;
            }
            
            $role = $auth['user']['role'];
            $userId = $auth['user']['id'];
            
            if ($role === 'admin') {
                $stats = [];
                
                // Total shipments
                $stmt = $conn->query("SELECT COUNT(*) as total FROM shipments");
                $stats['total_shipments'] = $stmt->fetch()['total'];
                
                // Pending shipments
                $stmt = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status = 'pending'");
                $stats['pending_shipments'] = $stmt->fetch()['total'];
                
                // In transit
                $stmt = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status = 'in_transit'");
                $stats['in_transit'] = $stmt->fetch()['total'];
                
                // Pending clearances
                $stmt = $conn->query("SELECT COUNT(*) as total FROM clearances WHERE clearance_status = 'pending'");
                $stats['pending_clearances'] = $stmt->fetch()['total'];
                
                echo json_encode(['success' => true, 'data' => $stats]);
                
            } elseif ($role === 'staff') {
                $stats = [];
                
                // Total clearances created by staff
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clearances WHERE staff_id = ?");
                $stmt->execute([$userId]);
                $stats['total_clearances'] = $stmt->fetch()['total'];
                
                // Pending clearances
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clearances WHERE staff_id = ? AND clearance_status = 'pending'");
                $stmt->execute([$userId]);
                $stats['pending_clearances'] = $stmt->fetch()['total'];
                
                // Approved clearances
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clearances WHERE staff_id = ? AND clearance_status = 'approved'");
                $stmt->execute([$userId]);
                $stats['approved_clearances'] = $stmt->fetch()['total'];
                
                echo json_encode(['success' => true, 'data' => $stats]);
                
            } else {
                $stats = [];
                
                // Customer shipments
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM shipments WHERE customer_id = ?");
                $stmt->execute([$userId]);
                $stats['total_shipments'] = $stmt->fetch()['total'];
                
                // In transit
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM shipments WHERE customer_id = ? AND status = 'in_transit'");
                $stmt->execute([$userId]);
                $stats['in_transit'] = $stmt->fetch()['total'];
                
                // Delivered
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM shipments WHERE customer_id = ? AND status = 'delivered'");
                $stmt->execute([$userId]);
                $stats['delivered'] = $stmt->fetch()['total'];
                
                echo json_encode(['success' => true, 'data' => $stats]);
            }
            break;
        
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid endpoint: ' . $endpoint,
                'available_endpoints' => [
                    'register', 'login', 'create-shipment', 'customer-shipments',
                    'pending-shipments', 'update-shipment-status', 'create-clearance',
                    'pending-clearances', 'update-clearance-status', 'clearances',
                    'drivers', 'vehicles', 'notifications', 'stats'
                ]
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
