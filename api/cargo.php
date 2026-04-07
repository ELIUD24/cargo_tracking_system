<?php
declare(strict_types=1);

/**
 * CargoTrack API - cargo.php
 * Backend API for Cargo Tracking Management System
 * 
 * Endpoints:
 * - GET  /cargo.php?endpoint=status     - API health check
 * - POST /cargo.php?endpoint=login      - User login
 * - POST /cargo.php?endpoint=register   - User registration
 * - GET  /cargo.php?endpoint=customers  - List customers (auth required)
 * - POST /cargo.php?endpoint=customers  - Create customer (auth required)
 * - GET  /cargo.php?endpoint=shipments  - List shipments (auth required)
 * - POST /cargo.php?endpoint=shipments  - Create shipment (auth required)
 * - PUT  /cargo.php?endpoint=shipments  - Update shipment status (auth required)
 * - DELETE /cargo.php?endpoint=shipments - Delete shipment (auth required)
 * - GET  /cargo.php?endpoint=track&id=X - Track shipment by number
 * - GET  /cargo.php?endpoint=clearances - List clearances (auth required)
 * - POST /cargo.php?endpoint=clearances - Create clearance (auth required)
 * - PUT  /cargo.php?endpoint=clearances - Approve/reject/begin journey (auth required)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // Set to '1' for development only
date_default_timezone_set('Africa/Nairobi');

// ===== CORS HEADERS =====
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== CONFIGURATION =====
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'cargo_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('SITE_NAME', 'CargoTrack');
define('SECRET_KEY', getenv('SECRET_KEY') ?: 'change-this-secret-key-production-2026-min-32-chars!');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/cargo_tracking_system');

// ===== HELPER FUNCTIONS =====
function json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function request_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return $_POST ?: [];
}

function request_endpoint(): string {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($qs) {
        parse_str($qs, $params);
        return trim((string)($params['endpoint'] ?? ''));
    }
    return trim((string)($_GET['endpoint'] ?? ''));
}

function starts_with(string $haystack, string $needle): bool {
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

// ===== DATABASE CLASS =====
final class Database {
    private ?PDO $conn = null;
    
    public function connection(): PDO {
        if ($this->conn instanceof PDO) return $this->conn;
        
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        
        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->conn->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            json_response(['success' => false, 'message' => 'Database connection failed'], 500);
        }
        return $this->conn;
    }
}

// ===== SCHEMA MANAGER =====
final class SchemaManager {
    public function __construct(private PDO $pdo) {}
    
    public function ensure(): void {
        $this->ensureUsers();
        $this->ensureCustomers();
        $this->ensureShipments();
        $this->ensureShipmentTracking();
        $this->ensureClearances();
        $this->ensureMessageLogs();
        $this->seedDefaults();
    }
    
    private function tableExists(string $table): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = :db AND table_name = :tbl");
        $stmt->execute(['db' => DB_NAME, 'tbl' => $table]);
        return ((int)$stmt->fetch()['c']) > 0;
    }
    
    private function execSafe(string $sql): void {
        try { $this->pdo->exec($sql); } catch (Throwable $e) { error_log("Schema: " . $e->getMessage()); }
    }
    
    private function ensureUsers(): void {
        $this->execSafe("CREATE TABLE IF NOT EXISTS users (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NULL,
            role ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
            status ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
            company VARCHAR(150) NULL,
            department VARCHAR(150) NULL,
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email), INDEX idx_role (role), INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    private function ensureCustomers(): void {
        $this->execSafe("CREATE TABLE IF NOT EXISTS customers (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            country VARCHAR(100) DEFAULT 'Kenya',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_email (email), INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    private function ensureShipments(): void {
        $this->execSafe("CREATE TABLE IF NOT EXISTS shipments (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tracking_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INT(11) NULL,
            customer_ref_id INT(11) NULL,
            customer_name VARCHAR(150) NULL,
            customer_email VARCHAR(100) NULL,
            customer_phone VARCHAR(20) NULL,
            sender_name VARCHAR(150) NULL,
            sender_phone VARCHAR(20) NULL,
            sender_address TEXT NULL,
            receiver_name VARCHAR(150) NOT NULL,
            receiver_phone VARCHAR(20) NOT NULL,
            receiver_address TEXT NULL,
            receiver_city VARCHAR(100) NULL,
            receiver_country VARCHAR(100) DEFAULT 'Kenya',
            weight DECIMAL(10,2) NULL,
            service_type VARCHAR(100) DEFAULT 'Standard',
            estimated_delivery DATE NULL,
            price DECIMAL(12,2) DEFAULT 0,
            payment_status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
            notes TEXT NULL,
            status ENUM('pending','processing','confirmed','in_transit','out_for_delivery','delivered','cleared','failed') DEFAULT 'pending',
            created_by INT(11) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_tracking (tracking_number), INDEX idx_status (status), INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    private function ensureShipmentTracking(): void {
        $this->execSafe("CREATE TABLE IF NOT EXISTS shipment_tracking (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            shipment_id INT(11) NOT NULL,
            status VARCHAR(100) NOT NULL,
            location VARCHAR(255) NULL,
            description TEXT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            updated_by INT(11) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
            INDEX idx_shipment (shipment_id), INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // ✅ FIXED: Updated to match your actual database structure
    private function ensureClearances(): void {
        $this->execSafe("CREATE TABLE IF NOT EXISTS clearances (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tracking_number VARCHAR(50) NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            customer_id VARCHAR(50) NULL,
            customer_phone VARCHAR(20) NULL,
            customer_email VARCHAR(100) NULL,
            destination VARCHAR(150) NOT NULL,
            departure_time DATETIME NULL,
            driver_name VARCHAR(150) NULL,
            vehicle_reg VARCHAR(50) NULL,
            goods_description TEXT NULL,
            total_weight DECIMAL(10,2) NULL,
            status ENUM('pending','approved','rejected','in_transit','delivered') DEFAULT 'pending',
            journey_started BOOLEAN DEFAULT FALSE,
            journey_started_at DATETIME NULL,
            journey_started_by VARCHAR(100) NULL,
            approved_at DATETIME NULL,
            approved_by VARCHAR(100) NULL,
            rejected_at DATETIME NULL,
            rejected_by VARCHAR(100) NULL,
            rejection_reason TEXT NULL,
            staff_id INT(11) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tracking (tracking_number), 
            INDEX idx_status (status), 
            INDEX idx_customer (customer_name),
            FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    private function ensureMessageLogs(): void {
        $this->execSafe("CREATE TABLE IF NOT EXISTS message_logs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(50) NOT NULL,
            message_type ENUM('sms','email') NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent','pending','failed') DEFAULT 'pending',
            tracking_number VARCHAR(50) NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tracking (tracking_number), INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    private function seedDefaults(): void {
        $defaults = [
            ['admin', 'admin@cargotrack.co.ke', 'admin123', 'System Admin', '+254700000001', 'admin', 'active'],
            ['staff', 'staff@cargotrack.co.ke', 'staff123', 'Staff Member', '+254700000002', 'staff', 'active'],
        ];
        foreach ($defaults as [$username, $email, $password, $name, $phone, $role, $status]) {
            $check = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) continue;
            $insert = $this->pdo->prepare("INSERT INTO users (username,email,password,name,phone,role,status) VALUES (?,?,?,?,?,?,?)");
            $insert->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $name, $phone, $role, $status]);
        }
    }
}

// ===== AUTH SERVICE =====
final class AuthService {
    public function __construct(private PDO $pdo) {}
    
    public function login(string $identifier, string $password): array {
        $stmt = $this->pdo->prepare("SELECT id,username,email,password,name,phone,role,status,company,department FROM users WHERE username=? OR email=? OR name=? LIMIT 1");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, (string)$user['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        if (!in_array($user['status'], ['active'], true)) {
            return ['success' => false, 'message' => 'Account not active'];
        }
        
        $this->pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
        
        $token = $this->createToken([
            'user_id' => (int)$user['id'], 'role' => $user['role'], 'email' => $user['email'],
            'name' => $user['name'], 'exp' => time() + 86400, 'iat' => time()
        ]);
        
        return [
            'success' => true, 'message' => 'Login successful',
            'data' => [
                'token' => $token, 'expires_in' => 86400, 'token_type' => 'Bearer',
                'user' => [
                    'id' => (int)$user['id'], 'username' => $user['username'], 'name' => $user['name'],
                    'full_name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone'],
                    'role' => $user['role'], 'company' => $user['company'], 'department' => $user['department']
                ]
            ]
        ];
    }
    
    public function register(array $data): array {
        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $password = (string)($data['password'] ?? '');
        $role = strtolower(trim($data['role'] ?? 'customer'));
        
        if (!$name || !$email || !$password) return ['success' => false, 'message' => 'Name, email and password required'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Invalid email'];
        if (strlen($password) < 6) return ['success' => false, 'message' => 'Password must be 6+ characters'];
        
        $check = $this->pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) return ['success' => false, 'message' => 'Email already registered'];
        
        $role = in_array($role, ['admin','staff','customer'], true) ? $role : 'customer';
        $status = ($role === 'customer') ? 'active' : 'pending';
        $username = $this->genUsername($email, $name);
        
        $this->pdo->prepare("INSERT INTO users (username,email,password,name,phone,role,status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $name, $phone ?: null, $role, $status]);
        
        $userId = (int)$this->pdo->lastInsertId();
        
        if ($role === 'customer') {
            $cc = $this->pdo->prepare("SELECT id FROM customers WHERE email=? LIMIT 1");
            $cc->execute([$email]);
            if (!$cc->fetch()) {
                $this->pdo->prepare("INSERT INTO customers (user_id,name,email,phone,country) VALUES (?,?,?,?,'Kenya')")
                    ->execute([$userId, $name, $email, $phone ?: null]);
            }
        }
        
        return ['success' => true, 'message' => ($status==='active'?'Registration successful':'Account pending approval'),
                'data' => ['user_id'=>$userId, 'role'=>$role, 'status'=>$status, 'username'=>$username]];
    }
    
    public function requireAuth(): array {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!$auth || !starts_with($auth, 'Bearer ')) {
            json_response(['success'=>false,'message'=>'Unauthorized'], 401);
        }
        
        $token = substr($auth, 7);
        $payload = $this->verifyToken($token);
        if (!$payload) json_response(['success'=>false,'message'=>'Invalid or expired token'], 401);
        
        return $payload;
    }
    
    private function createToken(array $payload): string {
        $header = rtrim(strtr(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])),'+/','-_='), '=');
        $body = rtrim(strtr(base64_encode(json_encode($payload)),'+/','-_='), '=');
        $sig = hash_hmac('sha256', "$header.$body", SECRET_KEY, true);
        $encSig = rtrim(strtr(base64_encode($sig),'+/','-_='), '=');
        return "$header.$body.$encSig";
    }
    
    private function verifyToken(string $token): array|false {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        [$header, $body, $sig] = $parts;
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", SECRET_KEY, true)),'+/','-_='), '=');
        if (!hash_equals($expected, $sig)) return false;
        $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        if (!is_array($payload) || !isset($payload['exp']) || (int)$payload['exp'] < time()) return false;
        return $payload;
    }
    
    private function genUsername(string $email, string $name): string {
        $base = preg_replace('/[^a-z0-9_]/i', '', strtolower(strtok($email, '@'))) ?: preg_replace('/[^a-z0-9_]/i', '', strtolower(str_replace(' ','',$name))) ?: 'user';
        $cand = $base; $i = 1;
        while (true) {
            $chk = $this->pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
            $chk->execute([$cand]);
            if (!$chk->fetch()) return $cand;
            $cand = $base . ($i++);
        }
    }
}

// ===== CUSTOMER SERVICE =====
final class CustomerService {
    public function __construct(private PDO $pdo) {}
    
    public function all(): array {
        $stmt = $this->pdo->query("SELECT id,user_id,name,email,phone,address,city,country,created_at FROM customers ORDER BY created_at DESC");
        return ['success'=>true, 'data'=>$stmt->fetchAll()];
    }
    
    public function create(array $data): array {
        $name = trim($data['name']??''); $email = strtolower(trim($data['email']??'')); $phone = trim($data['phone']??'');
        $address = trim($data['address']??''); $city = trim($data['city']??''); $country = trim($data['country']??'Kenya');
        
        if (!$name || !$email || !$phone) return ['success'=>false, 'message'=>'Name, email and phone required'];
        
        $chk = $this->pdo->prepare("SELECT id FROM customers WHERE email=? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) return ['success'=>false, 'message'=>'Customer email exists'];
        
        $userId = null; $tempPass = null;
        $uc = $this->pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $uc->execute([$email]);
        $u = $uc->fetch();
        
        if ($u) {
            $userId = (int)$u['id'];
        } else {
            $tempPass = 'temp'.substr(bin2hex(random_bytes(6)),0,6);
            $uname = preg_replace('/[^a-z0-9_]/i','',strtolower(strtok($email,'@'))) ?: 'customer'.time();
            $c=1; $cand=$uname;
            while(true){
                $x=$this->pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
                $x->execute([$cand]);
                if(!$x->fetch()){ $uname=$cand; break; }
                $cand=$uname.($c++);
            }
            $this->pdo->prepare("INSERT INTO users (username,email,password,name,phone,role,status) VALUES (?,?,?,?,?,'customer','active')")
                ->execute([$uname,$email,password_hash($tempPass,PASSWORD_DEFAULT),$name,$phone]);
            $userId = (int)$this->pdo->lastInsertId();
        }
        
        $this->pdo->prepare("INSERT INTO customers (user_id,name,email,phone,address,city,country) VALUES (?,?,?,?,?,?,?)")
            ->execute([$userId,$name,$email,$phone,$address?:null,$city?:null,$country?:'Kenya']);
        
        return ['success'=>true, 'message'=>'Customer created', 'data'=>['customer_id'=>(int)$this->pdo->lastInsertId(),'user_id'=>$userId,'temp_password'=>$tempPass]];
    }
}

// ===== SHIPMENT SERVICE =====
final class ShipmentService {
    public function __construct(private PDO $pdo) {}
    
    public function create(array $data, array $auth): array {
        $tn = $this->genTracking();
        
        $sn = trim($data['sender_name']??''); $sp = trim($data['sender_phone']??''); $sa = trim($data['sender_address']??'');
        $rn = trim($data['receiver_name']??''); $rp = trim($data['receiver_phone']??''); $ra = trim($data['receiver_address']??'');
        $rc = trim($data['receiver_city']??''); $rco = trim($data['receiver_country']??'Kenya');
        
        if (!$sn || !$rn || !$rp || !$ra) return ['success'=>false, 'message'=>'Sender and receiver details required'];
        
        // ✅ FIX: Properly handle customer_id lookup to avoid FK constraint errors
        $customerId = null;
        $custStmt = $this->pdo->prepare("SELECT id FROM customers WHERE user_id = ? OR email = ? LIMIT 1");
        $custStmt->execute([(int)$auth['user_id'], $auth['email'] ?? '']);
        $cust = $custStmt->fetch();
        if ($cust) {
            $customerId = (int)$cust['id'];
        }
        
        $this->pdo->prepare("INSERT INTO shipments (
            tracking_number,customer_id,customer_ref_id,customer_name,customer_email,customer_phone,
            sender_name,sender_phone,sender_address,receiver_name,receiver_phone,receiver_address,receiver_city,receiver_country,
            weight,service_type,estimated_delivery,price,payment_status,notes,status,created_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $tn, $customerId, null, $rn, $auth['email']??null, $auth['phone']??$rp,
            $sn, $sp?:null, $sa?:null, $rn, $rp?:null, $ra?:null, $rc?:null, $rco?:'Kenya',
            $data['weight']!==''?(float)$data['weight']:null, $data['service_type']??'Standard',
            $data['estimated_delivery']?:null, $data['price']!==''?(float)$data['price']:0,
            $data['payment_status']??'unpaid', $data['notes']?:null, $data['status']??'pending', (int)$auth['user_id']
        ]);
        
        $sid = (int)$this->pdo->lastInsertId();
        $this->addTrack($sid, $data['status']??'pending', 'Shipment created', $rc?:$rco?:'Kenya', (int)$auth['user_id']);
        
        return ['success'=>true, 'message'=>'Shipment created', 'data'=>['shipment_id'=>$sid,'tracking_number'=>$tn]];
    }
    
    public function all(array $auth, ?string $status=null, ?string $search=null): array {
        if (($auth['role']??'')==='customer') return $this->forCustomer($auth, $status, $search);
        
        $sql = "SELECT s.* FROM shipments s WHERE 1=1"; $p=[];
        if ($status && $status!=='' && $status!=='all') { $sql.=" AND s.status=?"; $p[]=$status; }
        if ($search && $search!=='') { $sql.=" AND (s.tracking_number LIKE ? OR s.sender_name LIKE ? OR s.receiver_name LIKE ? OR s.customer_name LIKE ?)"; $s="%$search%"; $p=array_merge($p,[$s,$s,$s,$s]); }
        $sql.=" ORDER BY s.created_at DESC";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($p);
        return ['success'=>true, 'data'=>$stmt->fetchAll()];
    }
    
    public function forCustomer(array $auth, ?string $status=null, ?string $search=null): array {
        $sql = "SELECT s.* FROM shipments s WHERE (s.customer_id=? OR s.customer_email=? OR s.customer_phone=?)";
        $p = [(int)$auth['user_id'], $auth['email']??'', $auth['phone']??''];
        if ($status && $status!=='' && $status!=='all') { $sql.=" AND s.status=?"; $p[]=$status; }
        if ($search && $search!=='') { $sql.=" AND (s.tracking_number LIKE ? OR s.sender_name LIKE ? OR s.receiver_name LIKE ?)"; $s="%$search%"; $p=array_merge($p,[$s,$s,$s]); }
        $sql.=" ORDER BY s.created_at DESC";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($p);
        return ['success'=>true, 'data'=>$stmt->fetchAll()];
    }
    
    public function trackByNumber(string $tn): array {
        $stmt = $this->pdo->prepare("SELECT s.* FROM shipments s WHERE s.tracking_number=? LIMIT 1");
        $stmt->execute([$tn]); $s = $stmt->fetch();
        if (!$s) return ['success'=>false, 'message'=>'Shipment not found', 'data'=>null];
        $s['tracking_history'] = $this->history((int)$s['id']);
        return ['success'=>true, 'data'=>$s];
    }
    
    public function updateStatus(int $id, string $status, array $auth, ?string $desc=null, ?string $loc=null): array {
        $stmt = $this->pdo->prepare("UPDATE shipments SET status=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount()<1) return ['success'=>false, 'message'=>'Shipment not found'];
        $this->addTrack($id, $status, $desc?:'Status updated', $loc?:'Kenya', (int)$auth['user_id']);
        return ['success'=>true, 'message'=>'Status updated'];
    }
    
    public function delete(int $id): array {
        $this->pdo->prepare("DELETE FROM shipment_tracking WHERE shipment_id=?")->execute([$id]);
        $stmt = $this->pdo->prepare("DELETE FROM shipments WHERE id=?");
        $stmt->execute([$id]);
        if ($stmt->rowCount()<1) return ['success'=>false, 'message'=>'Shipment not found'];
        return ['success'=>true, 'message'=>'Shipment deleted'];
    }
    
    private function history(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT * FROM shipment_tracking WHERE shipment_id=? ORDER BY created_at DESC, id DESC");
        $stmt->execute([$sid]); return $stmt->fetchAll();
    }
    
    private function addTrack(int $sid, string $status, string $desc, string $loc, int $by): void {
        $this->pdo->prepare("INSERT INTO shipment_tracking (shipment_id,status,location,description,updated_by) VALUES (?,?,?,?,?)")
            ->execute([$sid, $status, $loc, $desc, $by]);
    }
    
    private function genTracking(): string {
        do {
            $cand = 'CG'.strtoupper(substr(bin2hex(random_bytes(5)),0,9)).'KE';
            $chk = $this->pdo->prepare("SELECT id FROM shipments WHERE tracking_number=? LIMIT 1");
            $chk->execute([$cand]);
        } while ($chk->fetch());
        return $cand;
    }
}

// ===== CLEARANCE SERVICE =====
final class ClearanceService {
    public function __construct(private PDO $pdo) {}
    
    // ✅ FIXED: Updated to match your actual database columns (staff_id instead of staff_name)
    public function create(array $data, array $auth): array {
        // ✅ Add validation
        if (empty($data['tracking_number']) || empty($data['customer_name']) || empty($data['destination'])) {
            return ['success'=>false, 'message'=>'Required fields: tracking_number, customer_name, destination'];
        }
        
        $this->pdo->prepare("INSERT INTO clearances (
            tracking_number,
            customer_name,
            customer_id,
            customer_phone,
            customer_email,
            destination,
            departure_time,
            driver_name,
            vehicle_reg,
            goods_description,
            total_weight,
            status,
            staff_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)")
        ->execute([
            trim($data['tracking_number'] ?? ''),
            trim($data['customer_name'] ?? ''),
            trim($data['customer_id'] ?? ''),
            trim($data['customer_phone'] ?? ''),
            trim($data['customer_email'] ?? ''),
            trim($data['destination'] ?? ''),
            trim($data['departure_time'] ?? '') ?: null,
            trim($data['driver_name'] ?? ''),
            trim($data['vehicle_reg'] ?? ''),
            trim($data['goods_description'] ?? ''),
            $data['total_weight'] !== '' ? (float)$data['total_weight'] : null,
            (int)($auth['user_id'] ?? 0)
        ]);
        
        return ['success'=>true, 'message'=>'Clearance submitted', 'data'=>['clearance_id'=>(int)$this->pdo->lastInsertId()]];
    }
    
    public function all(?string $status=null): array {
        $sql = "SELECT * FROM clearances WHERE 1=1"; $p=[];
        if ($status && $status!=='' && $status!=='all') { $sql.=" AND status=?"; $p[]=$status; }
        $sql.=" ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($p);
        return ['success'=>true, 'data'=>$stmt->fetchAll()];
    }
    
    public function approve(int $id, array $auth): array {
        $stmt = $this->pdo->prepare("UPDATE clearances SET status='approved',approved_at=NOW(),approved_by=? WHERE id=?");
        $stmt->execute([$auth['name']??'Admin', $id]);
        if ($stmt->rowCount()<1) return ['success'=>false, 'message'=>'Clearance not found'];
        return ['success'=>true, 'message'=>'Clearance approved'];
    }
    
    public function reject(int $id, string $reason, array $auth): array {
        $stmt = $this->pdo->prepare("UPDATE clearances SET status='rejected',rejected_at=NOW(),rejected_by=?,rejection_reason=? WHERE id=?");
        $stmt->execute([$auth['name']??'Admin', $reason, $id]);
        if ($stmt->rowCount()<1) return ['success'=>false, 'message'=>'Clearance not found'];
        return ['success'=>true, 'message'=>'Clearance rejected'];
    }
    
    public function beginJourney(int $id, array $auth): array {
        $stmt = $this->pdo->prepare("UPDATE clearances SET status='in_transit',journey_started=TRUE,journey_started_at=NOW(),journey_started_by=? WHERE id=?");
        $stmt->execute([$auth['name']??'Staff', $id]);
        if ($stmt->rowCount()<1) return ['success'=>false, 'message'=>'Clearance not found'];
        return ['success'=>true, 'message'=>'Journey started'];
    }
}

// ===== MAIN ROUTER =====
$db = new Database();
$pdo = $db->connection();

$schema = new SchemaManager($pdo);
$schema->ensure();

$auth = new AuthService($pdo);
$customers = new CustomerService($pdo);
$shipments = new ShipmentService($pdo);
$clearances = new ClearanceService($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$endpoint = request_endpoint();

try {
    switch ($endpoint) {
        case '': case 'index': case 'health': case 'status':
            json_response(['success'=>true, 'message'=>SITE_NAME.' API running', 'version'=>'3.0', 'timestamp'=>date('Y-m-d H:i:s')]);
            break;
            
        case 'login':
            if ($method!=='POST') json_response(['success'=>false,'message'=>'Method not allowed'],405);
            $in = request_input();
            json_response($auth->login((string)($in['username']??''), (string)($in['password']??'')));
            break;
            
        case 'register': case 'signup':
            if ($method!=='POST') json_response(['success'=>false,'message'=>'Method not allowed'],405);
            json_response($auth->register(request_input()));
            break;
            
        case 'customers':
            $au = $auth->requireAuth();
            if (!in_array((string)($au['role']??''), ['admin','staff'], true)) json_response(['success'=>false,'message'=>'Forbidden'],403);
            if ($method==='GET') json_response($customers->all());
            if ($method==='POST') json_response($customers->create(request_input()));
            json_response(['success'=>false,'message'=>'Method not allowed'],405);
            break;
            
        case 'shipments':
            $au = $auth->requireAuth();
            if ($method==='GET') {
                $st = $_GET['status']??null; $sr = $_GET['search']??null;
                json_response($shipments->all($au, $st, $sr));
            }
            if ($method==='POST') json_response($shipments->create(request_input(), $au));
            if ($method==='PUT') {
                if (!in_array((string)($au['role']??''), ['admin','staff'], true)) json_response(['success'=>false,'message'=>'Forbidden'],403);
                $in = request_input(); $sid=(int)($in['shipment_id']??0); $st=trim($in['status']??'');
                if ($sid<1 || $st==='') json_response(['success'=>false,'message'=>'Shipment ID and status required'],400);
                json_response($shipments->updateStatus($sid, $st, $au, $in['description']??null, $in['location']??null));
            }
            if ($method==='DELETE') {
                if (!in_array((string)($au['role']??''), ['admin','staff'], true)) json_response(['success'=>false,'message'=>'Forbidden'],403);
                $in = request_input(); $sid=(int)($in['shipment_id']??($_GET['id']??0));
                if ($sid<1) json_response(['success'=>false,'message'=>'Shipment ID required'],400);
                json_response($shipments->delete($sid));
            }
            json_response(['success'=>false,'message'=>'Method not allowed'],405);
            break;
            
        case 'track':
            if ($method!=='GET') json_response(['success'=>false,'message'=>'Method not allowed'],405);
            $tn = trim($_GET['id']??$_GET['tracking_number']??'');
            if ($tn==='') json_response(['success'=>false,'message'=>'Tracking number required'],400);
            $res = $shipments->trackByNumber($tn);
            json_response($res, $res['success']?200:404);
            break;
            
        case 'clearances':
            $au = $auth->requireAuth();
            if ($method==='GET') {
                $st = $_GET['status']??null;
                json_response($clearances->all($st));
            }
            if ($method==='POST') json_response($clearances->create(request_input(), $au));
            if ($method==='PUT') {
                $in = request_input(); $cid=(int)($in['clearance_id']??0); $act=trim($in['action']??'');
                if ($act==='approve') {
                    if (!in_array((string)($au['role']??''), ['admin'], true)) json_response(['success'=>false,'message'=>'Forbidden'],403);
                    json_response($clearances->approve($cid, $au));
                } elseif ($act==='reject') {
                    if (!in_array((string)($au['role']??''), ['admin'], true)) json_response(['success'=>false,'message'=>'Forbidden'],403);
                    json_response($clearances->reject($cid, trim($in['reason']??'No reason'), $au));
                } elseif ($act==='begin_journey') {
                    if (!in_array((string)($au['role']??''), ['staff'], true)) json_response(['success'=>false,'message'=>'Forbidden'],403);
                    json_response($clearances->beginJourney($cid, $au));
                } else {
                    json_response(['success'=>false,'message'=>'Invalid action'],400);
                }
            }
            json_response(['success'=>false,'message'=>'Method not allowed'],405);
            break;
            
        default:
            json_response(['success'=>false, 'message'=>'Endpoint not found',
                'available'=>['status','login','register','customers','shipments','track','clearances']], 404);
            break;
    }
} catch (Throwable $e) {
    json_response([
        'success' => false, 
        'message' => 'Server Error', 
        'debug' => $e->getMessage()
    ], 500);
}
?>