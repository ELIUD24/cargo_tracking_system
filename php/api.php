<?php
/**
 * CargoTrack - Complete Backend System
 * Single File Backend Solution v2.0
 * 
 * Features:
 * - Role-based authentication (customer/staff/admin)
 * - Clearance request workflow
 * - Shipment management with tracking history
 * - SMS/Email notifications via Africa's Talking
 * - JWT token authentication
 * - Activity logging for audit trail
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging only
date_default_timezone_set('Africa/Nairobi');

// ==================== SECURITY HEADERS ====================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Handle Preflight Requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==================== DATABASE CREDENTIALS ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'cargo_db');  // ✅ Match your actual database
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_NAME', 'CargoTrack');
define('SITE_URL', 'http://localhost/cargo_tracking_system/api');
define('SECRET_KEY', 'change-this-secret-key-to-a-strong-random-string-now-123456');

// Africa's Talking API (Optional - for SMS)
define('AT_USERNAME', 'sandbox');  
define('AT_API_KEY', 'atsk_28b843c00b4234728ecbd38107361f3cae17920c4a8027ab701a3e478fdf5c3d0dbd2549');  // Paste your API key here

// ==================== DATABASE CONNECTION ====================
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database Connection Error: ' . $exception->getMessage()
            ]);
            exit;
        }
        return $this->conn;
    }
}

// ==================== AUTHENTICATION CLASS ====================
class Auth {
    private $conn;
    private $table = 'users';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function login($username, $password) {
        try {
            // Match your users table: name OR email for login
            $query = "SELECT id, name, email, password, role, status, phone, company, department 
                      FROM {$this->table}
                      WHERE (name = :username OR email = :username)
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] === 'active') {
                        // Update last login timestamp
                        $updateQuery = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
                        $updateStmt = $this->conn->prepare($updateQuery);
                        $updateStmt->bindParam(':id', $user['id']);
                        $updateStmt->execute();
                        
                        $token = $this->generateToken($user);
                        return [
                            'success' => true,
                            'message' => 'Login successful',
                            'data' => [
                                'user' => [
                                    'id' => $user['id'],
                                    'username' => $user['name'],
                                    'name' => $user['name'],
                                    'email' => $user['email'],
                                    'full_name' => $user['name'],
                                    'role' => $user['role'],
                                    'phone' => $user['phone'],
                                    'company' => $user['company'],
                                    'department' => $user['department']
                                ],
                                'token' => $token,
                                'expires_in' => 3600,
                                'token_type' => 'Bearer'
                            ]
                        ];
                    } elseif ($user['status'] === 'pending') {
                        return ['success' => false, 'message' => 'Account pending approval. Contact administrator.'];
                    } else {
                        return ['success' => false, 'message' => 'Account is inactive. Contact administrator.'];
                    }
                } else {
                    return ['success' => false, 'message' => 'Invalid password'];
                }
            } else {
                return ['success' => false, 'message' => 'User not found'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
        }
    }

    // ✅ Enhanced Registration with Role Support
    public function register($data) {
        try {
            // Check if email already exists
            $checkQuery = "SELECT id, role, status FROM {$this->table} WHERE email = :email LIMIT 1";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $data['email']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $existing = $checkStmt->fetch();
                if ($existing['status'] === 'pending' && ($data['role'] ?? '') === 'staff') {
                    return ['success' => false, 'message' => 'Your staff request is already pending approval.'];
                }
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Validate and sanitize role
            $allowed_roles = ['customer', 'staff', 'admin'];
            $role = in_array($data['role'] ?? 'customer', $allowed_roles) ? $data['role'] : 'customer';
            
            // Staff/Admin require approval; customers are auto-active
            $status = ($role === 'customer') ? 'active' : 'pending';
            
            // Hash password securely
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert new user (matches your table structure)
            $query = "INSERT INTO {$this->table}
                      (name, email, phone, role, password, status, company, department)
                      VALUES
                      (:name, :email, :phone, :role, :password, :status, :company, :department)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':phone', $data['phone']);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':status', $status);
            $company = isset($data['company']) ? $data['company'] : NULL;
            $department = isset($data['department']) ? $data['department'] : NULL;
            $stmt->bindParam(':company', $company);
            $stmt->bindParam(':department', $department);
            
            if ($stmt->execute()) {
                $userId = $this->conn->lastInsertId();
                
                // Log activity for staff/admin requests
                if (in_array($role, ['staff', 'admin'])) {
                    $this->logActivity($userId, 'account_requested', 'users', $userId, "Role: {$role}, Department: {$department}");
                }
                
                return [
                    'success' => true,
                    'message' => $status === 'active' ? 'Registration successful' : 'Account pending approval',
                    'data' => [
                        'user_id' => $userId,
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => $role,
                        'status' => $status,
                        'requires_approval' => ($status === 'pending')
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to register user'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
        }
    }

    private function generateToken($user) {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => $user['id'],
            'username' => $user['name'],
            'role' => $user['role'],
            'exp' => time() + 3600,
            'iat' => time()
        ]));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", SECRET_KEY, true));
        return "$header.$payload.$signature";
    }

    public function verifyToken($token) {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) return false;
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!$payload) return false;
            if (!isset($payload['exp']) || $payload['exp'] < time()) return false;
            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }

    public function requireAuth() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized - Missing or invalid authorization header']);
            exit;
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $payload = $this->verifyToken($token);

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid or expired token']);
            exit;
        }

        return $payload;
    }

    // Helper: Log staff/admin activity for audit trail
    private function logActivity($user_id, $action, $entity_type, $entity_id, $details = null) {
        try {
            $query = "INSERT INTO staff_activity_log (user_id, action, entity_type, entity_id, details, ip_address)
                      VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':entity_type', $entity_type);
            $stmt->bindParam(':entity_id', $entity_id);
            $stmt->bindParam(':details', $details);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
        } catch (Exception $e) {
            // Fail silently for logging to avoid breaking main flow
        }
    }

    // Admin: Approve/reject pending staff accounts
    public function approveUser($user_id, $approve, $admin_id) {
        try {
            $status = $approve ? 'active' : 'rejected';
            $query = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id AND role IN ('staff', 'admin')";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                $this->logActivity($admin_id, 'user_' . ($approve ? 'approved' : 'rejected'), 'users', $user_id);
                return ['success' => true, 'message' => $approve ? 'User approved' : 'User rejected'];
            }
            return ['success' => false, 'message' => 'Failed to update user status'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // Admin: Get pending approval requests
    public function getPendingUsers() {
        try {
            $query = "SELECT id, name, email, phone, role, department, company, created_at 
                      FROM {$this->table} 
                      WHERE status = 'pending' 
                      ORDER BY created_at DESC";
            $stmt = $this->conn->query($query);
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// ==================== SHIPMENT CLASS ====================
class Shipment {
    private $conn;
    private $table = 'shipments';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function generateTrackingNumber() {
        $prefix = 'CG';
        $random = strtoupper(substr(uniqid(), -7));
        $suffix = 'KE';
        return $prefix . $random . $suffix;
    }

    public function create($data) {
        try {
            $tracking_number = $this->generateTrackingNumber();
            $query = "INSERT INTO {$this->table}
                      (tracking_number, customer_id, sender_name, sender_phone, sender_address,
                       receiver_name, receiver_phone, receiver_address, receiver_city, receiver_country,
                       weight, service_type, estimated_delivery, price, created_by, notes, status)
                      VALUES
                      (:tracking_number, :customer_id, :sender_name, :sender_phone, :sender_address,
                       :receiver_name, :receiver_phone, :receiver_address, :receiver_city, :receiver_country,
                       :weight, :service_type, :estimated_delivery, :price, :created_by, :notes, :status)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tracking_number', $tracking_number);
            $stmt->bindParam(':customer_id', $data['customer_id']);
            $stmt->bindParam(':sender_name', $data['sender_name']);
            $stmt->bindParam(':sender_phone', $data['sender_phone']);
            $stmt->bindParam(':sender_address', $data['sender_address']);
            $stmt->bindParam(':receiver_name', $data['receiver_name']);
            $stmt->bindParam(':receiver_phone', $data['receiver_phone']);
            $stmt->bindParam(':receiver_address', $data['receiver_address']);
            $stmt->bindParam(':receiver_city', $data['receiver_city']);
            $stmt->bindParam(':receiver_country', $data['receiver_country']);
            $stmt->bindParam(':weight', $data['weight']);
            $stmt->bindParam(':service_type', $data['service_type']);
            $stmt->bindParam(':estimated_delivery', $data['estimated_delivery']);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':created_by', $data['created_by']);
            $stmt->bindParam(':notes', $data['notes']);
            $status = $data['status'] ?? 'pending';
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $shipment_id = $this->conn->lastInsertId();
                
                // Add initial tracking entry
                $this->addTrackingEntry($shipment_id, 'pending', 'Shipment created successfully', 'Nairobi, Kenya', null, null, $data['created_by']);
                
                return [
                    'success' => true,
                    'message' => 'Shipment created successfully',
                    'data' => [
                        'shipment_id' => $shipment_id,
                        'tracking_number' => $tracking_number
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create shipment'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function getByTrackingNumber($tracking_number) {
        try {
            $query = "SELECT s.*, u.name as created_by_name
                      FROM {$this->table} s
                      LEFT JOIN users u ON s.created_by = u.id
                      WHERE s.tracking_number = :tracking_number
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tracking_number', $tracking_number);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $shipment = $stmt->fetch();
                $shipment['tracking_history'] = $this->getTrackingHistory($shipment['id']);
                return ['success' => true, 'data' => $shipment];
            } else {
                return ['success' => false, 'message' => 'Shipment not found', 'data' => null];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function getTrackingHistory($shipment_id) {
        try {
            $query = "SELECT st.*, u.name as updated_by_name
                      FROM shipment_tracking st
                      LEFT JOIN users u ON st.updated_by = u.id
                      WHERE st.shipment_id = :shipment_id
                      ORDER BY st.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':shipment_id', $shipment_id);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function addTrackingEntry($shipment_id, $status, $description, $location = null, $lat = null, $lng = null, $user_id = null) {
        try {
            $query = "INSERT INTO shipment_tracking
                      (shipment_id, status, location, description, latitude, longitude, updated_by)
                      VALUES
                      (:shipment_id, :status, :location, :description, :latitude, :longitude, :updated_by)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':shipment_id', $shipment_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':latitude', $lat);
            $stmt->bindParam(':longitude', $lng);
            $stmt->bindParam(':updated_by', $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateStatus($shipment_id, $status, $user_id = null) {
        try {
            $query = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $shipment_id);

            if ($stmt->execute()) {
                $status_messages = [
                    'pending' => 'Shipment pending processing',
                    'processing' => 'Shipment being processed at facility',
                    'in_transit' => 'Shipment in transit to destination',
                    'out_for_delivery' => 'Out for delivery - Expected today',
                    'delivered' => 'Shipment delivered successfully',
                    'cleared' => 'Customs/port clearance completed',
                    'failed' => 'Delivery failed - Contact support'
                ];
                $this->addTrackingEntry($shipment_id, $status, $status_messages[$status] ?? 'Status updated', 'Nairobi, Kenya', null, null, $user_id);
                return [
                    'success' => true,
                    'message' => 'Status updated successfully',
                    'data' => ['shipment_id' => $shipment_id, 'new_status' => $status]
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to update status'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function getAll($page = 1, $limit = 10, $status = null, $search = null) {
        try {
            $offset = ($page - 1) * $limit;
            $query = "SELECT s.*, u.name as created_by_name
                      FROM {$this->table} s
                      LEFT JOIN users u ON s.created_by = u.id";
            $conditions = [];
            if ($status) {
                $conditions[] = "s.status = :status";
            }
            if ($search) {
                $conditions[] = "(s.tracking_number LIKE :search OR s.receiver_name LIKE :search OR s.sender_name LIKE :search)";
            }
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            $query .= " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            if ($status) {
                $stmt->bindParam(':status', $status);
            }
            if ($search) {
                $searchParam = "%{$search}%";
                $stmt->bindParam(':search', $searchParam);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $shipments = $stmt->fetchAll();

            return ['success' => true, 'data' => $shipments];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []];
        }
    }

    public function getStats() {
        try {
            $stats = [];
            $query = "SELECT COUNT(*) as total FROM {$this->table}";
            $stmt = $this->conn->query($query);
            $stats['total_shipments'] = (int)$stmt->fetch()['total'];

            $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE status = 'in_transit'";
            $stmt = $this->conn->query($query);
            $stats['in_transit'] = (int)$stmt->fetch()['total'];

            $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE status = 'delivered'";
            $stmt = $this->conn->query($query);
            $stats['delivered'] = (int)$stmt->fetch()['total'];

            $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE status IN ('pending', 'processing')";
            $stmt = $this->conn->query($query);
            $stats['pending'] = (int)$stmt->fetch()['total'];

            $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE status = 'cleared'";
            $stmt = $this->conn->query($query);
            $stats['cleared'] = (int)$stmt->fetch()['total'];

            // Revenue this month
            $query = "SELECT SUM(price) as total FROM {$this->table} WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(NOW())";
            $stmt = $this->conn->query($query);
            $stats['monthly_revenue'] = (float)($stmt->fetch()['total'] ?? 0);

            return ['success' => true, 'data' => $stats, 'message' => 'Stats retrieved successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function delete($shipment_id) {
        try {
            // First delete tracking history (cascade should handle this, but be safe)
            $query = "DELETE FROM shipment_tracking WHERE shipment_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $shipment_id);
            $stmt->execute();
            
            // Then delete shipment
            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $shipment_id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Shipment deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete shipment'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// ==================== CLEARANCE CLASS (NEW) ====================
class Clearance {
    private $conn;
    private $table = 'clearance_requests';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create($data, $user_id) {
        try {
            $query = "INSERT INTO {$this->table}
                      (shipment_id, requested_by, clearance_type, documents_required, 
                       fees_amount, fees_paid, notes, status)
                      VALUES
                      (:shipment_id, :requested_by, :clearance_type, :documents_required, 
                       :fees_amount, :fees_paid, :notes, :status)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':shipment_id', $data['shipment_id']);
            $stmt->bindParam(':requested_by', $user_id);
            $stmt->bindParam(':clearance_type', $data['clearance_type']);
            
            // Handle documents array
            $docs = is_array($data['documents_required']) ? json_encode($data['documents_required']) : ($data['documents_required'] ?? null);
            $stmt->bindParam(':documents_required', $docs);
            
            $stmt->bindParam(':fees_amount', $data['fees_amount']);
            $fees_paid = isset($data['fees_paid']) ? (int)$data['fees_paid'] : 0;
            $stmt->bindParam(':fees_paid', $fees_paid);
            $stmt->bindParam(':notes', $data['notes']);
            $status = $data['status'] ?? 'pending';
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $clearance_id = $this->conn->lastInsertId();
                $this->logActivity($user_id, 'clearance_created', 'clearance_requests', $clearance_id);
                return ['success' => true, 'message' => 'Clearance request submitted', 'data' => ['clearance_id' => $clearance_id]];
            }
            return ['success' => false, 'message' => 'Failed to create clearance request'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function getByShipment($shipment_id) {
        try {
            $query = "SELECT cr.*, u.name as requested_by_name, u2.name as approved_by_name
                      FROM {$this->table} cr
                      LEFT JOIN users u ON cr.requested_by = u.id
                      LEFT JOIN users u2 ON cr.approved_by = u2.id
                      WHERE cr.shipment_id = :shipment_id
                      ORDER BY cr.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':shipment_id', $shipment_id);
            $stmt->execute();
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function updateStatus($clearance_id, $status, $user_id, $notes = null) {
        try {
            $query = "UPDATE {$this->table} 
                      SET status = :status, approved_by = :approved_by, approved_at = NOW(), notes = COALESCE(:notes, notes)
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':approved_by', $user_id);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':id', $clearance_id);
            
            if ($stmt->execute()) {
                $this->logActivity($user_id, 'clearance_' . $status, 'clearance_requests', $clearance_id, $notes);
                
                // If completed, update shipment status to 'cleared'
                if ($status === 'completed') {
                    $shipQuery = "UPDATE shipments SET status = 'cleared', updated_at = NOW() WHERE id = (SELECT shipment_id FROM {$this->table} WHERE id = :cid)";
                    $shipStmt = $this->conn->prepare($shipQuery);
                    $shipStmt->bindParam(':cid', $clearance_id);
                    $shipStmt->execute();
                }
                
                return ['success' => true, 'message' => 'Clearance status updated'];
            }
            return ['success' => false, 'message' => 'Failed to update clearance'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function getAll($page = 1, $limit = 10, $status = null, $user_id = null) {
        try {
            $offset = ($page - 1) * $limit;
            $query = "SELECT cr.*, s.tracking_number, u.name as requested_by_name
                      FROM {$this->table} cr
                      JOIN shipments s ON cr.shipment_id = s.id
                      LEFT JOIN users u ON cr.requested_by = u.id";
            $conditions = [];
            if ($status) $conditions[] = "cr.status = :status";
            if ($user_id) $conditions[] = "(cr.requested_by = :user_id OR cr.approved_by = :user_id)";
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            $query .= " ORDER BY cr.created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            if ($status) $stmt->bindParam(':status', $status);
            if ($user_id) $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    private function logActivity($user_id, $action, $entity_type, $entity_id, $details = null) {
        try {
            $query = "INSERT INTO staff_activity_log (user_id, action, entity_type, entity_id, details, ip_address)
                      VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':entity_type', $entity_type);
            $stmt->bindParam(':entity_id', $entity_id);
            $stmt->bindParam(':details', $details);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
        } catch (Exception $e) {}
    }
}

// ==================== NOTIFICATION CLASS ====================
class Notification {
    private $conn;
    private $table = 'notifications';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function sendSMS($phone, $message, $shipment_id = null, $customer_id = null) {
        try {
            $phone = $this->formatPhoneNumber($phone);
            
            // Africa's Talking API
            $url = 'https://api.africastalking.com/version1/messaging';
            $data = [
                'username' => AT_USERNAME,
                'to' => $phone,
                'message' => $message,
                'apiKey' => AT_API_KEY
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $status = ($httpCode == 200) ? 'sent' : 'failed';
            $this->logNotification($shipment_id, $customer_id, 'sms', $message, $status);

            return [
                'success' => ($status == 'sent'),
                'message' => $status == 'sent' ? 'SMS sent successfully' : 'Failed to send SMS',
                'response' => $response,
                'http_code' => $httpCode
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'SMS Error: ' . $e->getMessage()];
        }
    }

    public function sendEmail($email, $subject, $message, $shipment_id = null, $customer_id = null) {
        try {
            $headers = "From: noreply@cargotrack.co.ke\r\n";
            $headers .= "Reply-To: support@cargotrack.co.ke\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $status = mail($email, $subject, $message, $headers) ? 'sent' : 'failed';
            $this->logNotification($shipment_id, $customer_id, 'email', $subject . ': ' . $message, $status);

            return [
                'success' => ($status == 'sent'),
                'message' => $status == 'sent' ? 'Email sent successfully' : 'Failed to send email'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Email Error: ' . $e->getMessage()];
        }
    }

    public function sendShipmentUpdate($shipment_id, $status, $customer_phone, $customer_email, $tracking_number = null) {
        $messages = [
            'pending' => 'Your shipment has been received and is pending processing.',
            'processing' => 'Your shipment is being processed at our facility.',
            'in_transit' => 'Your shipment is now in transit to the destination.',
            'out_for_delivery' => 'Your shipment is out for delivery. Expected today!',
            'delivered' => 'Your shipment has been delivered successfully. Thank you for choosing CargoTrack!',
            'cleared' => 'Your shipment has cleared customs/port. Proceeding to delivery.',
            'failed' => 'Delivery attempt failed. We will contact you shortly.'
        ];
        $message = $messages[$status] ?? 'Shipment status updated: ' . $status;
        $message .= " Track at: " . SITE_URL;
        if ($tracking_number) {
            $message .= " | Tracking: " . $tracking_number;
        }

        // Send SMS
        $smsResult = $this->sendSMS($customer_phone, $message, $shipment_id);
        
        // Send Email
        $emailSubject = "Shipment Update - " . SITE_NAME . " - Status: " . ucfirst(str_replace('_', ' ', $status));
        $emailBody = $this->generateEmailTemplate($status, $message, $tracking_number);
        $emailResult = $this->sendEmail($customer_email, $emailSubject, $emailBody, $shipment_id);

        return [
            'success' => true,
            'message' => 'Notifications sent successfully',
            'sms' => $smsResult,
            'email' => $emailResult
        ];
    }

    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) == 9) {
            $phone = '254' . $phone;
        } elseif (substr($phone, 0, 1) != '+') {
            $phone = '+' . $phone;
        }
        return $phone;
    }

    private function logNotification($shipment_id, $customer_id, $type, $message, $status) {
        try {
            $query = "INSERT INTO {$this->table}
                      (shipment_id, customer_id, type, message, status, sent_at)
                      VALUES
                      (:shipment_id, :customer_id, :type, :message, :status, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':shipment_id', $shipment_id);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
        } catch (Exception $e) {
            // Log error silently
        }
    }

    private function generateEmailTemplate($status, $message, $tracking_number = null) {
        $statusColors = [
            'pending' => '#f59e0b',
            'processing' => '#3b82f6',
            'in_transit' => '#8b5cf6',
            'out_for_delivery' => '#06b6d4',
            'delivered' => '#10b981',
            'cleared' => '#059669',
            'failed' => '#ef4444'
        ];
        $color = $statusColors[$status] ?? '#2563eb';
        
        return "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><style>
body{font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0}
.container{max-width:600px;margin:0 auto}
.header{background:{$color};color:white;padding:30px;text-align:center}
.header h1{margin:0;font-size:28px}
.content{padding:30px;background:#f9f9f9}
.status-badge{display:inline-block;padding:8px 16px;background:{$color};color:white;border-radius:20px;font-weight:bold;margin:10px 0}
.button{display:inline-block;padding:12px 30px;background:{$color};color:white;text-decoration:none;border-radius:5px;font-weight:bold;margin:20px 0}
.footer{text-align:center;padding:20px;font-size:12px;color:#666;background:#f1f1f1}
.info-box{background:white;padding:20px;border-radius:8px;margin:20px 0}
</style></head>
<body>
<div class='container'>
<div class='header'><h1>📦 " . SITE_NAME . "</h1><p>Shipment Update Notification</p></div>
<div class='content'>
<div class='status-badge'>" . ucfirst(str_replace('_', ' ', $status)) . "</div>
<h2>Your Shipment Update</h2>
<p>{$message}</p>
" . ($tracking_number ? "<div class='info-box'><strong>Tracking Number:</strong> {$tracking_number}<br><strong>Track Online:</strong> <a href='" . SITE_URL . "'>" . SITE_URL . "</a></div>" : "") . "
<a href='" . SITE_URL . "' class='button'>Track Your Shipment</a>
<p>Need help? Contact us at support@cargotrack.co.ke or +254 700 123456</p>
</div>
<div class='footer'>
<p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
<p>Nairobi, Kenya | Mombasa Road</p>
<p>This is an automated message. Please do not reply.</p>
</div>
</div>
</body>
</html>";
    }

    public function getNotifications($shipment_id = null, $limit = 50) {
        try {
            $query = "SELECT * FROM {$this->table}";
            if ($shipment_id) {
                $query .= " WHERE shipment_id = :shipment_id";
            }
            $query .= " ORDER BY created_at DESC LIMIT :limit";
            $stmt = $this->conn->prepare($query);
            if ($shipment_id) {
                $stmt->bindParam(':shipment_id', $shipment_id);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// ==================== CUSTOMER CLASS ====================
class Customer {
    private $conn;
    private $table = 'customers';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create($data) {
        try {
            $query = "INSERT INTO {$this->table}
                      (name, email, phone, address, city, country)
                      VALUES
                      (:name, :email, :phone, :address, :city, :country)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':phone', $data['phone']);
            $stmt->bindParam(':address', $data['address']);
            $stmt->bindParam(':city', $data['city']);
            $stmt->bindParam(':country', $data['country']);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Customer created successfully',
                    'data' => ['customer_id' => $this->conn->lastInsertId()]
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create customer'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function getAll($page = 1, $limit = 10, $search = null) {
        try {
            $offset = ($page - 1) * $limit;
            $query = "SELECT * FROM {$this->table}";
            if ($search) {
                $query .= " WHERE name LIKE :search OR email LIKE :search OR phone LIKE :search";
            }
            $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            if ($search) {
                $searchParam = "%{$search}%";
                $stmt->bindParam(':search', $searchParam);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// ==================== API ROUTER ====================
$method = $_SERVER['REQUEST_METHOD'];
$queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

// Parse endpoint from query string
$endpoint = '';
if (!empty($queryString)) {
    parse_str($queryString, $queryParams);
    if (isset($queryParams['endpoint'])) {
        $endpoint = $queryParams['endpoint'];
    }
}

// Initialize classes
$auth = new Auth();
$shipment = new Shipment();
$clearance = new Clearance();
$notification = new Notification();
$customer = new Customer();

try {
    switch ($endpoint) {
        // ==================== PUBLIC ENDPOINTS ====================
        case '':
        case 'index':
        case 'health':
        case 'status':
            echo json_encode([
                'success' => true,
                'message' => SITE_NAME . ' Backend API is running',
                'version' => '2.0',
                'timestamp' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ]);
            break;

        case 'track':
            if ($method == 'GET') {
                $tracking_number = $_GET['id'] ?? $_GET['tracking_number'] ?? '';
                if (empty($tracking_number)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Tracking number required']);
                } else {
                    echo json_encode($shipment->getByTrackingNumber($tracking_number));
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        case 'login':
            if ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                $username = $input['username'] ?? '';
                $password = $input['password'] ?? '';

                if (empty($username) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Username and password required']);
                } else {
                    echo json_encode($auth->login($username, $password));
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        // ✅ Registration endpoint with role support
        case 'register':
        case 'signup':
            if ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                
                // Validate required fields
                if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Name, email and password required']);
                    break;
                }
                
                // Validate password length
                if (strlen($input['password']) < 6) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                    break;
                }
                
                // Validate email format
                if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    break;
                }
                
                // Set defaults
                $input['role'] = $input['role'] ?? 'customer';
                $input['phone'] = $input['phone'] ?? '';
                $input['company'] = $input['company'] ?? NULL;
                $input['department'] = $input['department'] ?? NULL;
                
                echo json_encode($auth->register($input));
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        // ==================== PROTECTED ENDPOINTS ====================
        case 'shipments':
            $user = $auth->requireAuth();
            if ($method == 'GET') {
                if (isset($_GET['stats'])) {
                    echo json_encode($shipment->getStats());
                } else {
                    $page = $_GET['page'] ?? 1;
                    $limit = $_GET['limit'] ?? 10;
                    $status = $_GET['status'] ?? null;
                    $search = $_GET['search'] ?? null;
                    echo json_encode($shipment->getAll($page, $limit, $status, $search));
                }
            } elseif ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                $input['created_by'] = $user['user_id'];
                $result = $shipment->create($input);
                
                // Send notification if customer details provided
                if ($result['success'] && isset($input['customer_phone']) && isset($input['customer_email'])) {
                    $notification->sendShipmentUpdate(
                        $result['data']['shipment_id'],
                        'pending',
                        $input['customer_phone'],
                        $input['customer_email'],
                        $result['data']['tracking_number']
                    );
                }
                echo json_encode($result);
            } elseif ($method == 'PUT') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                $shipment_id = $input['shipment_id'] ?? null;
                $status = $input['status'] ?? null;
                
                if (!$shipment_id || !$status) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Shipment ID and status required']);
                } else {
                    $result = $shipment->updateStatus($shipment_id, $status, $user['user_id']);
                    if ($result['success'] && isset($input['notify']) && $input['notify']) {
                        $shipmentData = $shipment->getByTrackingNumber($input['tracking_number'] ?? '');
                        if (isset($shipmentData['data']['customer_phone'])) {
                            $notification->sendShipmentUpdate(
                                $shipment_id,
                                $status,
                                $shipmentData['data']['customer_phone'],
                                $shipmentData['data']['customer_email']
                            );
                        }
                    }
                    echo json_encode($result);
                }
            } elseif ($method == 'DELETE') {
                $input = json_decode(file_get_contents('php://input'), true);
                $shipment_id = $input['shipment_id'] ?? $_GET['id'] ?? null;
                if (!$shipment_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
                } else {
                    echo json_encode($shipment->delete($shipment_id));
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        // ✅ Clearance endpoints
        case 'clearance':
            $user = $auth->requireAuth();
            if ($method == 'GET') {
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 10;
                $status = $_GET['status'] ?? null;
                echo json_encode($clearance->getAll($page, $limit, $status, $user['user_id']));
            } elseif ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                echo json_encode($clearance->create($input, $user['user_id']));
            } elseif ($method == 'PUT') {
                $input = json_decode(file_get_contents('php://input'), true);
                $clearance_id = $input['clearance_id'] ?? null;
                $status = $input['status'] ?? null;
                $notes = $input['notes'] ?? null;
                
                if (!$clearance_id || !$status) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Clearance ID and status required']);
                } else {
                    echo json_encode($clearance->updateStatus($clearance_id, $status, $user['user_id'], $notes));
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        case 'clearance-by-shipment':
            $auth->requireAuth();
            if ($method == 'GET') {
                $shipment_id = $_GET['shipment_id'] ?? null;
                if (!$shipment_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
                } else {
                    echo json_encode($clearance->getByShipment($shipment_id));
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        // Admin: Approve pending users
        case 'approve-user':
            $user = $auth->requireAuth();
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            if ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $user_id = $input['user_id'] ?? null;
                $approve = $input['approve'] ?? false;
                
                if (!$user_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'User ID required']);
                } else {
                    echo json_encode($auth->approveUser($user_id, $approve, $user['user_id']));
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        case 'pending-users':
            $user = $auth->requireAuth();
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            if ($method == 'GET') {
                echo json_encode($auth->getPendingUsers());
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        case 'stats':
        case 'dashboard':
            $auth->requireAuth();
            if ($method == 'GET') {
                echo json_encode($shipment->getStats());
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        case 'notifications':
        case 'notify':
            $auth->requireAuth();
            if ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                
                if ($input['type'] == 'sms') {
                    echo json_encode($notification->sendSMS(
                        $input['phone'],
                        $input['message'],
                        $input['shipment_id'] ?? null
                    ));
                } elseif ($input['type'] == 'email') {
                    echo json_encode($notification->sendEmail(
                        $input['email'],
                        $input['subject'] ?? 'Notification',
                        $input['message'],
                        $input['shipment_id'] ?? null
                    ));
                } elseif ($input['type'] == 'shipment_update') {
                    echo json_encode($notification->sendShipmentUpdate(
                        $input['shipment_id'],
                        $input['status'],
                        $input['phone'],
                        $input['email'],
                        $input['tracking_number'] ?? null
                    ));
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid notification type']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        case 'customers':
            $auth->requireAuth();
            if ($method == 'GET') {
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 10;
                $search = $_GET['search'] ?? null;
                echo json_encode($customer->getAll($page, $limit, $search));
            } elseif ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;
                echo json_encode($customer->create($input));
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;

        // Test endpoints (remove in production)
        case 'test-sms':
        case 'test-email':
            if ($method == 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($endpoint == 'test-sms') {
                    echo json_encode($notification->sendSMS(
                        $input['phone'] ?? '+254700000000',
                        $input['message'] ?? 'Test SMS from CargoTrack'
                    ));
                } elseif ($endpoint == 'test-email') {
                    echo json_encode($notification->sendEmail(
                        $input['email'] ?? 'test@example.com',
                        $input['subject'] ?? 'Test Email',
                        $input['message'] ?? 'This is a test email from CargoTrack'
                    ));
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'Test endpoint available']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found',
                'available_endpoints' => [
                    'track', 'login', 'register', 'shipments', 
                    'clearance', 'clearance-by-shipment', 'approve-user',
                    'pending-users', 'stats', 'notifications', 'customers'
                ]
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>